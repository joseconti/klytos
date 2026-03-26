<?php
/**
 * Klytos — MCP Webhook Management Tools
 * Tools: klytos_create_webhook, klytos_list_webhooks, klytos_delete_webhook,
 *        klytos_list_webhook_events, klytos_test_webhook.
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

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;

function registerWebhookTools(ToolRegistry $registry, App $app): void
{
    $registry->register(
        'klytos_create_webhook',
        'Create a webhook to receive notifications when events occur. Returns the HMAC secret for signature verification.',
        [
            'url'         => ['type' => 'string', 'description' => 'Target URL (HTTPS recommended).'],
            'events'      => ['type' => 'array', 'description' => 'Events to subscribe to (e.g. ["page.created", "build.completed"]).', 'items' => ['type' => 'string']],
            'description' => ['type' => 'string', 'description' => 'Description of this webhook (optional).'],
        ],
        function (array $params, App $app): array {
            $webhookManager = new \Klytos\Core\WebhookManager($app->getStorage());
            return $webhookManager->create($params);
        },
        ['title' => 'Create Webhook', 'readOnlyHint' => false],
        ['url', 'events']
    );

    $registry->register(
        'klytos_list_webhooks',
        'List all configured webhooks with their status and subscribed events.',
        [],
        function (array $params, App $app): array {
            $webhookManager = new \Klytos\Core\WebhookManager($app->getStorage());
            $webhooks = $webhookManager->list();
            // Hide secrets from the listing for security.
            return array_map(function (array $wh): array {
                unset($wh['secret']);
                return $wh;
            }, $webhooks);
        },
        ['title' => 'List Webhooks', 'readOnlyHint' => true]
    );

    $registry->register(
        'klytos_delete_webhook',
        'Delete a webhook subscription permanently.',
        [
            'webhook_id' => ['type' => 'string', 'description' => 'Webhook ID to delete.'],
        ],
        function (array $params, App $app): array {
            if (empty($params['webhook_id'])) {
                throw new \InvalidArgumentException('webhook_id is required.');
            }
            $webhookManager = new \Klytos\Core\WebhookManager($app->getStorage());
            return ['deleted' => $webhookManager->delete($params['webhook_id'])];
        },
        ['title' => 'Delete Webhook', 'readOnlyHint' => false, 'destructiveHint' => true],
        ['webhook_id']
    );

    $registry->register(
        'klytos_list_webhook_events',
        'List all available webhook events (core + plugin-registered).',
        [],
        function (array $params, App $app): array {
            $webhookManager = new \Klytos\Core\WebhookManager($app->getStorage());
            return $webhookManager->getAvailableEvents();
        },
        ['title' => 'List Webhook Events', 'readOnlyHint' => true]
    );

    $registry->register(
        'klytos_test_webhook',
        'Send a test event to a webhook to verify it is working correctly.',
        [
            'webhook_id' => ['type' => 'string', 'description' => 'Webhook ID to test.'],
        ],
        function (array $params, App $app): array {
            if (empty($params['webhook_id'])) {
                throw new \InvalidArgumentException('webhook_id is required.');
            }
            $webhookManager = new \Klytos\Core\WebhookManager($app->getStorage());
            $webhook = $webhookManager->get($params['webhook_id']);

            // Dispatch a test event to this specific webhook.
            $webhookManager->dispatch('test.ping', [
                'message' => 'This is a test event from Klytos.',
                'webhook_id' => $params['webhook_id'],
                'timestamp' => \Klytos\Core\Helpers::now(),
            ]);

            return [
                'success' => true,
                'message' => 'Test event dispatched to ' . ($webhook['url'] ?? 'unknown'),
            ];
        },
        ['title' => 'Test Webhook', 'readOnlyHint' => false],
        ['webhook_id']
    );
}
