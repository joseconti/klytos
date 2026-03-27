<?php
/**
 * Klytos — Webhook Manager
 * Core infrastructure for sending event notifications to external URLs.
 *
 * Webhooks allow Klytos (and its plugins) to notify external services when
 * events occur: page published, build completed, form submitted (via plugin), etc.
 *
 * Each webhook has:
 * - A target URL (HTTPS required in production).
 * - One or more event subscriptions (e.g. 'page.created', 'build.completed').
 * - A HMAC-SHA256 secret for payload signature verification.
 * - Retry logic: 5 attempts with exponential backoff (1s, 2s, 4s, 8s, 16s).
 *
 * Payload format (JSON):
 * {
 *   "event": "page.created",
 *   "timestamp": "2025-01-15T10:30:00+00:00",
 *   "data": { ... event-specific data ... }
 * }
 *
 * Signature header:
 *   X-Klytos-Signature: sha256=<HMAC-SHA256 of raw JSON body using the webhook secret>
 *
 * Plugins can register additional event types via the 'webhooks.events' filter.
 *
 * Storage: Collection 'webhooks' in StorageInterface.
 *
 * @package Klytos
 * @since   2.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

class WebhookManager
{
    /** @var StorageInterface Storage backend. */
    private StorageInterface $storage;

    /** @var string Collection for webhook configurations. */
    private const COLLECTION = 'webhooks';

    /** @var string Collection for delivery logs. */
    private const LOG_COLLECTION = 'webhook-logs';

    /** @var int Maximum retry attempts. */
    private const MAX_RETRIES = 5;

    /** @var int Base delay in seconds for exponential backoff. */
    private const BASE_DELAY_SECONDS = 1;

    /** @var int HTTP timeout for webhook delivery (seconds). */
    private const HTTP_TIMEOUT = 10;

    /**
     * Core events that Klytos fires. Plugins can add more via the 'webhooks.events' filter.
     */
    private const CORE_EVENTS = [
        'page.created'     => 'A new page was created',
        'page.updated'     => 'A page was updated',
        'page.deleted'     => 'A page was deleted',
        'build.completed'  => 'A site build completed',
        'build.failed'     => 'A site build failed',
        'task.created'     => 'A new review task was created',
        'task.completed'   => 'A task was marked as completed',
        'user.created'     => 'A new user was created',
        'user.login'       => 'A user logged in',
        'plugin.activated' => 'A plugin was activated',
        'plugin.deactivated' => 'A plugin was deactivated',
    ];

    /**
     * @param StorageInterface $storage Storage backend instance.
     */
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    // ─── Webhook CRUD ────────────────────────────────────────────

    /**
     * Create a new webhook subscription.
     *
     * @param  array $data Webhook data: url (required), events (required, array of event names),
     *                     description (optional).
     * @return array The created webhook (including the generated secret).
     * @throws \InvalidArgumentException On validation failure.
     */
    public function create(array $data): array
    {
        $url    = trim($data['url'] ?? '');
        $events = $data['events'] ?? [];

        if (empty($url)) {
            throw new \InvalidArgumentException('Webhook URL is required.');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid webhook URL.');
        }

        if (empty($events) || !is_array($events)) {
            throw new \InvalidArgumentException('At least one event is required.');
        }

        // Validate that all subscribed events are known.
        $validEvents = $this->getAvailableEvents();
        foreach ($events as $event) {
            if (!array_key_exists($event, $validEvents)) {
                throw new \InvalidArgumentException("Unknown event: {$event}");
            }
        }

        // Generate a unique webhook ID and HMAC secret.
        $webhookId = Helpers::randomHex(8);
        $secret    = Helpers::randomHex(32); // 64 hex chars for HMAC-SHA256 signing.

        $webhook = [
            'id'           => $webhookId,
            'url'          => $url,
            'events'       => $events,
            'secret'       => $secret,
            'description'  => trim($data['description'] ?? ''),
            'status'       => 'active',
            'created_at'   => Helpers::now(),
            'updated_at'   => Helpers::now(),
            'last_triggered' => null,
            'failure_count'  => 0,
        ];

        $this->storage->write(self::COLLECTION, $webhookId, $webhook);

        return $webhook;
    }

    /**
     * Update a webhook.
     *
     * @param  string $webhookId Webhook ID.
     * @param  array  $data      Fields to update: url, events, description, status.
     * @return array  Updated webhook.
     */
    public function update(string $webhookId, array $data): array
    {
        $webhook = $this->storage->read(self::COLLECTION, $webhookId);

        $updatable = ['url', 'events', 'description', 'status'];
        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $webhook[$field] = $data[$field];
            }
        }

        $webhook['updated_at'] = Helpers::now();
        $this->storage->write(self::COLLECTION, $webhookId, $webhook);

        return $webhook;
    }

    /**
     * Delete a webhook.
     *
     * @param  string $webhookId Webhook ID.
     * @return bool   True if deleted.
     */
    public function delete(string $webhookId): bool
    {
        return $this->storage->delete(self::COLLECTION, $webhookId);
    }

    /**
     * Get a webhook by ID.
     *
     * @param  string $webhookId Webhook ID.
     * @return array  Webhook data.
     */
    public function get(string $webhookId): array
    {
        return $this->storage->read(self::COLLECTION, $webhookId);
    }

    /**
     * List all webhooks.
     *
     * @return array Array of webhook configurations.
     */
    public function list(): array
    {
        return $this->storage->list(self::COLLECTION);
    }

    // ─── Event Dispatching ───────────────────────────────────────

    /**
     * Dispatch an event to all subscribed webhooks.
     *
     * This is the main method called by the core and plugins when an event occurs.
     * It finds all active webhooks subscribed to the event, builds the payload,
     * signs it with HMAC-SHA256, and sends it via HTTP POST.
     *
     * @param string $event Event name (e.g. 'page.created').
     * @param array  $data  Event-specific data payload.
     */
    public function dispatch(string $event, array $data = []): void
    {
        $webhooks = $this->getWebhooksForEvent($event);

        if (empty($webhooks)) {
            return; // No subscriptions for this event.
        }

        // Build the payload.
        $payload = json_encode([
            'event'     => $event,
            'timestamp' => Helpers::now(),
            'data'      => $data,
        ], JSON_UNESCAPED_UNICODE);

        // Send to each subscribed webhook.
        foreach ($webhooks as $webhook) {
            $this->deliver($webhook, $payload);
        }
    }

    /**
     * Get all available events (core + plugin-registered).
     *
     * @return array Event name => description.
     */
    public function getAvailableEvents(): array
    {
        $events = self::CORE_EVENTS;

        // Allow plugins to register additional events.
        $events = Hooks::applyFilters('webhooks.events', $events);

        return $events;
    }

    // ─── HTTP Delivery ───────────────────────────────────────────

    /**
     * Deliver a payload to a single webhook URL.
     *
     * Signs the payload with HMAC-SHA256 using the webhook's secret,
     * sends via HTTP POST, and handles failures with retry logic.
     *
     * @param array  $webhook Webhook configuration (url, secret, id).
     * @param string $payload JSON payload string.
     */
    private function deliver(array $webhook, string $payload): void
    {
        $url    = $webhook['url'] ?? '';
        $secret = $webhook['secret'] ?? '';

        // Sign the payload with HMAC-SHA256.
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        // Attempt delivery with retries.
        $attempt = 0;
        $success = false;
        $lastError = '';

        while ($attempt < self::MAX_RETRIES && !$success) {
            if ($attempt > 0) {
                // Exponential backoff: 1s, 2s, 4s, 8s, 16s.
                $delay = self::BASE_DELAY_SECONDS * (2 ** ($attempt - 1));
                sleep($delay);
            }

            $attempt++;

            try {
                $responseCode = $this->sendHttpPost($url, $payload, $signature);

                // Consider 2xx and 3xx as success.
                if ($responseCode >= 200 && $responseCode < 400) {
                    $success = true;
                } else {
                    $lastError = "HTTP {$responseCode}";
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        // Update webhook status based on delivery result.
        try {
            $webhook['last_triggered'] = Helpers::now();
            if (!$success) {
                $webhook['failure_count'] = ($webhook['failure_count'] ?? 0) + 1;

                // Auto-disable after 10 consecutive failures.
                if ($webhook['failure_count'] >= 10) {
                    $webhook['status'] = 'disabled';
                }
            } else {
                $webhook['failure_count'] = 0; // Reset on success.
            }

            $this->storage->write(self::COLLECTION, $webhook['id'], $webhook);
        } catch (\Throwable $e) {
            error_log('Klytos Webhook: failed to update webhook status: ' . $e->getMessage());
        }

        // Log the delivery attempt.
        $this->logDelivery($webhook['id'], $success, $attempt, $lastError);
    }

    /**
     * Send an HTTP POST request with the signed webhook payload.
     *
     * @param  string $url       Target URL.
     * @param  string $payload   JSON payload body.
     * @param  string $signature HMAC-SHA256 signature for X-Klytos-Signature header.
     * @return int    HTTP response code.
     * @throws \RuntimeException On cURL errors.
     */
    private function sendHttpPost(string $url, string $payload, string $signature): int
    {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Klytos-Signature: ' . $signature,
                'X-Klytos-Event: webhook',
                'User-Agent: Klytos-Webhook/2.0',
            ],
            // Security: follow redirects cautiously.
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);

        curl_close($ch);

        if (!empty($error)) {
            throw new \RuntimeException("cURL error: {$error}");
        }

        return $httpCode;
    }

    /**
     * Log a webhook delivery attempt.
     *
     * @param string $webhookId Webhook ID.
     * @param bool   $success   Whether delivery succeeded.
     * @param int    $attempts  Number of attempts made.
     * @param string $error     Error message (if failed).
     */
    private function logDelivery(string $webhookId, bool $success, int $attempts, string $error = ''): void
    {
        $logId = date('Ymd-His') . '-' . Helpers::randomHex(4);

        try {
            $this->storage->write(self::LOG_COLLECTION, $logId, [
                'webhook_id' => $webhookId,
                'success'    => $success,
                'attempts'   => $attempts,
                'error'      => $error,
                'timestamp'  => Helpers::now(),
            ]);
        } catch (\Throwable $e) {
            // Delivery log failures should not affect the main flow.
            error_log('Klytos Webhook: log write failed: ' . $e->getMessage());
        }
    }

    /**
     * Get all active webhooks subscribed to a specific event.
     *
     * @param  string $event Event name.
     * @return array  Matching webhook configurations.
     */
    private function getWebhooksForEvent(string $event): array
    {
        $webhooks = $this->storage->list(self::COLLECTION, ['status' => 'active']);

        return array_filter($webhooks, function (array $webhook) use ($event): bool {
            $events = $webhook['events'] ?? [];
            return in_array($event, $events, true);
        });
    }
}
