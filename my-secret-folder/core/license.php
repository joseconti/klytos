<?php
/**
 * Klytos — License Manager
 * Activation and verification against plugins.joseconti.com.
 * Adapted from WC_Gateway_Redsys_License logic to pure PHP.
 *
 * @package Klytos
 * @since   1.0.0
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

class License
{
    /** @var StorageInterface Storage backend (FileStorage or DatabaseStorage). */
    private StorageInterface $storage;

    private string $configPath;

    private string $apiUrl   = 'https://plugins.joseconti.com/';
    private string $itemName = 'Klytos';
    private string $slug     = 'klytos';

    private const LICENSE_FILE       = 'license.json.enc';
    private const VERIFY_INTERVAL    = 7 * 24 * 3600;   // 7 days
    private const GRACE_PERIOD_DAYS  = 14;

    public function __construct(StorageInterface $storage, string $configPath)
    {
        $this->storage    = $storage;
        $this->configPath = rtrim($configPath, '/');
    }

    /**
     * Activate a license key against the remote server.
     *
     * @param  string $licenseKey The license key hash.
     * @param  string $siteUrl    Full site URL (https://domain.tld).
     * @return array  ['success' => bool, 'license' => string, 'salt' => string, 'error' => string]
     */
    public function activate(string $licenseKey, string $siteUrl): array
    {
        $domain = parse_url($siteUrl, PHP_URL_HOST) ?: $siteUrl;

        $response = $this->apiPost('lm-license-api', [
            'action'    => 'activate_license',
            'license'   => $licenseKey,
            'item_name' => $this->itemName,
            'url'       => $siteUrl,
            'site_url'  => $siteUrl,
            'domain'    => $domain,
        ]);

        if ($response === null) {
            return [
                'success' => false,
                'license' => 'error',
                'salt'    => '',
                'error'   => 'Could not connect to license server.',
            ];
        }

        if (!empty($response->activated) && $response->activated === true) {
            // Save license data
            $licenseData = [
                'license_key'      => $licenseKey,
                'license_status'   => 'valid',
                'license_salt'     => $response->salt ?? '',
                'domain'           => $domain,
                'site_url'         => $siteUrl,
                'activated_at'     => Helpers::now(),
                'last_verified'    => Helpers::now(),
                'plan'             => $response->plan ?? 'pro',
                'grace_period_until' => null,
            ];

            $this->storage->writeTo($this->configPath, self::LICENSE_FILE, $licenseData);

            return [
                'success' => true,
                'license' => 'valid',
                'salt'    => $response->salt ?? '',
                'error'   => '',
            ];
        }

        return [
            'success' => false,
            'license' => $response->license ?? 'invalid',
            'salt'    => '',
            'error'   => $response->error ?? 'License activation failed.',
        ];
    }

    /**
     * Get the current license status (local read).
     *
     * @return array
     */
    public function getStatus(): array
    {
        try {
            return $this->storage->readFrom($this->configPath, self::LICENSE_FILE);
        } catch (\RuntimeException $e) {
            return [
                'license_key'    => '',
                'license_status' => 'missing',
                'domain'         => '',
                'activated_at'   => null,
                'last_verified'  => null,
                'plan'           => '',
            ];
        }
    }

    /**
     * Re-verify the license against the remote server.
     * Called periodically (every 7 days).
     *
     * @return array Verification result.
     */
    public function verify(): array
    {
        $status = $this->getStatus();

        if (empty($status['license_key'])) {
            return ['success' => false, 'error' => 'No license key found.'];
        }

        $response = $this->apiPost('lm-license-api', [
            'action'    => 'check_license',
            'license'   => $status['license_key'],
            'item_name' => $this->itemName,
            'url'       => $status['site_url'] ?? '',
            'site_url'  => $status['site_url'] ?? '',
            'domain'    => $status['domain'] ?? '',
        ]);

        if ($response === null) {
            // Server unreachable — keep current status gracefully
            return ['success' => true, 'error' => '', 'note' => 'Server unreachable, keeping current status.'];
        }

        $status['last_verified'] = Helpers::now();

        if (isset($response->license) && $response->license === 'valid') {
            $status['license_status']      = 'valid';
            $status['grace_period_until']  = null;
        } else {
            // License revoked or invalid
            $status['license_status'] = 'revoked';

            // Set grace period if not already set
            if (empty($status['grace_period_until'])) {
                $graceDate = new \DateTimeImmutable('+' . self::GRACE_PERIOD_DAYS . ' days');
                $status['grace_period_until'] = $graceDate->format('c');
            }
        }

        $this->storage->writeTo($this->configPath, self::LICENSE_FILE, $status);

        return [
            'success' => $status['license_status'] === 'valid',
            'status'  => $status['license_status'],
            'error'   => '',
        ];
    }

    /**
     * Check if the license is currently active.
     * Considers grace period for revoked licenses.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        $status = $this->getStatus();

        if (($status['license_status'] ?? '') === 'valid') {
            return true;
        }

        // Check grace period
        if (($status['license_status'] ?? '') === 'revoked' && !empty($status['grace_period_until'])) {
            $graceEnd = new \DateTimeImmutable($status['grace_period_until']);
            $now      = new \DateTimeImmutable();

            if ($now < $graceEnd) {
                return true; // Still within grace period
            }
        }

        return false;
    }

    /**
     * Check if periodic verification is due.
     *
     * @return bool
     */
    public function needsVerification(): bool
    {
        $status = $this->getStatus();

        if (empty($status['last_verified'])) {
            return true;
        }

        $lastVerified = new \DateTimeImmutable($status['last_verified']);
        $now          = new \DateTimeImmutable();
        $diff         = $now->getTimestamp() - $lastVerified->getTimestamp();

        return $diff >= self::VERIFY_INTERVAL;
    }

    /**
     * Perform a background verification check if due.
     * Safe to call on every admin load.
     */
    public function checkIfDue(): void
    {
        if ($this->needsVerification()) {
            $this->verify();
        }
    }

    /**
     * Get the license salt (needed for update API calls).
     *
     * @return string
     */
    public function getSalt(): string
    {
        $status = $this->getStatus();
        return $status['license_salt'] ?? '';
    }

    /**
     * Get the license key.
     *
     * @return string
     */
    public function getKey(): string
    {
        $status = $this->getStatus();
        return $status['license_key'] ?? '';
    }

    /**
     * Make a POST request to the license API.
     *
     * @param  string $endpoint API endpoint suffix (e.g. 'lm-license-api').
     * @param  array  $params   POST parameters.
     * @return object|null Decoded JSON response, or null on failure.
     */
    private function apiPost(string $endpoint, array $params): ?object
    {
        $url = $this->apiUrl . '?wc-api=' . $endpoint;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            // Retry without SSL verification (same behavior as WC_Gateway_Redsys_License)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }

        curl_close($ch);

        if ($httpCode !== 200 || empty($response)) {
            return null;
        }

        return json_decode($response);
    }
}
