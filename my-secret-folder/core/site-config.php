<?php
/**
 * Klytos — Site Configuration Manager
 * Reads and writes global site metadata and settings.
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

class SiteConfig
{
    /** @var StorageInterface Storage backend (FileStorage or DatabaseStorage). */
    private StorageInterface $storage;
    private const COLLECTION = 'config';
    private const ID         = 'site';

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Get the full site configuration.
     *
     * @return array
     */
    public function get(): array
    {
        if (!$this->storage->exists(self::COLLECTION, self::ID)) {
            return $this->getDefaults();
        }

        return array_merge($this->getDefaults(), $this->storage->read(self::COLLECTION, self::ID));
    }

    /**
     * Update site configuration (partial update).
     *
     * @param  array $data Fields to update.
     * @return array The updated configuration.
     */
    public function set(array $data): array
    {
        $current = $this->get();

        // Top-level fields
        $topLevel = [
            'site_name', 'tagline', 'default_language',
            'description', 'favicon_url', 'logo_url',
            'indexing_enabled',
        ];

        foreach ($topLevel as $field) {
            if (array_key_exists($field, $data)) {
                $current[$field] = $data[$field];
            }
        }

        // Nested: social
        if (isset($data['social']) && is_array($data['social'])) {
            $current['social'] = array_merge($current['social'], $data['social']);
        }

        // Nested: analytics
        if (isset($data['analytics']) && is_array($data['analytics'])) {
            $current['analytics'] = array_merge($current['analytics'], $data['analytics']);
        }

        // Nested: seo
        if (isset($data['seo']) && is_array($data['seo'])) {
            $current['seo'] = array_merge($current['seo'], $data['seo']);
        }

        // Nested: email
        if (isset($data['email']) && is_array($data['email'])) {
            $current['email'] = array_merge($current['email'], $data['email']);
        }

        $current['updated_at'] = Helpers::now();
        $this->storage->write(self::COLLECTION, self::ID, $current);

        return $current;
    }

    /**
     * Get a single config value by dot-notation key.
     *
     * @param  string $key     e.g. 'site_name' or 'social.twitter'
     * @param  mixed  $default
     * @return mixed
     */
    public function getValue(string $key, mixed $default = null): mixed
    {
        $config = $this->get();
        $parts  = explode('.', $key);
        $value  = $config;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }

    /**
     * Update the last build timestamp.
     */
    public function updateBuildTimestamp(): void
    {
        $config = $this->get();
        $config['last_build'] = Helpers::now();
        $this->storage->write(self::COLLECTION, self::ID, $config);
    }

    /**
     * Default site configuration.
     */
    private function getDefaults(): array
    {
        return [
            'site_name'        => 'My Klytos Site',
            'tagline'          => '',
            'default_language' => 'es',
            'description'      => '',
            'favicon_url'      => '',
            'logo_url'         => '',
            'indexing_enabled' => false,
            'social'           => [
                'twitter'   => '',
                'github'    => '',
                'linkedin'  => '',
                'instagram' => '',
                'youtube'   => '',
                'mastodon'  => '',
            ],
            'analytics'        => [
                'google_analytics_id'  => '',
                'custom_head_scripts'  => '',
                'custom_body_scripts'  => '',
            ],
            'seo'              => [
                'default_og_image'  => '',
                'robots_txt_extra'  => '',
            ],
            'email'            => [
                'transport'     => 'mail',     // 'mail' (PHP) or 'smtp'
                'from_name'     => '',         // Default From name (falls back to site_name)
                'from_email'    => '',         // Default From email (falls back to noreply@domain)
                'reply_to'      => '',         // Default Reply-To address
                'smtp_host'     => '',         // SMTP server hostname
                'smtp_port'     => 587,        // SMTP port (587=STARTTLS, 465=SSL, 25=plain)
                'smtp_user'     => '',         // SMTP username
                'smtp_pass'     => '',         // SMTP password
                'smtp_security' => 'tls',      // 'tls', 'ssl', or ''
            ],
            'last_build'       => null,
            'created_at'       => null,
            'updated_at'       => null,
        ];
    }
}
