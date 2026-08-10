<?php

/**
 * Klytos — Site Configuration Manager
 * Reads and writes global site metadata and settings.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
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
            'indexing_enabled', 'editor', 'admin_theme',
            'maintenance_mode', 'maintenance_message',
            'admin_bar_enabled',
            /*
             * `encryption_key_backed_up` was written by two shipped surfaces
             * and read as the condition of an undismissable system error
             * notice, and it was missing from this list — so every write was
             * dropped, the method returned the caller's own value back to it,
             * and the notice could never be cleared on any install. Pinned by
             * SiteConfigSetTest, which was seen failing before this line
             * existed.
             */
            'encryption_key_backed_up',
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

        // Nested: developer
        if (isset($data['developer']) && is_array($data['developer'])) {
            $current['developer'] = array_merge($current['developer'] ?? [], $data['developer']);
        }

        // Nested: notices
        if (isset($data['notices']) && is_array($data['notices'])) {
            $current['notices'] = array_merge($current['notices'] ?? [], $data['notices']);
        }

        // Nested: cache
        if (isset($data['cache']) && is_array($data['cache'])) {
            $current['cache'] = array_merge($current['cache'] ?? [], $data['cache']);
            // Deep-merge Redis and Memcached sub-configs.
            if (isset($data['cache']['redis']) && is_array($data['cache']['redis'])) {
                $current['cache']['redis'] = array_merge(
                    $current['cache']['redis'] ?? [],
                    $data['cache']['redis']
                );
            }
            if (isset($data['cache']['memcached']) && is_array($data['cache']['memcached'])) {
                $current['cache']['memcached'] = array_merge(
                    $current['cache']['memcached'] ?? [],
                    $data['cache']['memcached']
                );
            }
        }

        // Languages list
        if (array_key_exists('languages', $data)) {
            $current['languages'] = $data['languages'];
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
     * Set a single config value by dot-notation key.
     *
     * The counterpart to {@see getValue()}, and it did not exist until Sprint 1
     * slice 7. Four call sites in core/mcp/tools/comment-tools.php:136-148 have
     * always called it, so `klytos_set_comment_settings` — the only supported
     * way to switch the comment system on — has been fataling with "Call to
     * undefined method" for its entire life. That is why audit S-09 could not
     * be demonstrated closed without this: the endpoint was unreachable AND
     * the feature was unswitchable, the second defect hidden behind the first
     * (the L-009 shape).
     *
     * Deliberately NOT routed through {@see set()}: that method carries a
     * hardcoded allow-list of top-level fields for the settings form, and
     * `comments_enabled` is not on it — so a value handed to set() is dropped
     * silently, which is the other half of the same bug. This writes the key
     * it was given.
     *
     * @param  string $key   e.g. 'comments_enabled' or 'social.twitter'
     * @param  mixed  $value Value to store.
     * @return void
     */
    public function setValue(string $key, mixed $value): void
    {
        $config = $this->get();
        $parts  = explode('.', $key);
        $target  = &$config;

        foreach ($parts as $part) {
            if (!isset($target[$part]) || !is_array($target[$part])) {
                $target[$part] = [];
            }
            $target = &$target[$part];
        }

        $target = $value;
        unset($target);

        $config['updated_at'] = Helpers::now();

        $this->storage->write(self::COLLECTION, self::ID, $config);
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
            'indexing_enabled'      => false,
            'editor'                => 'gutenberg',
            'admin_theme'           => 'dark',
            'maintenance_mode'      => false,
            'maintenance_message'   => '',
            'admin_bar_enabled'     => true,
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
            'cache'            => [
                'driver'      => 'auto',       // 'auto', 'apcu', 'redis', 'memcached', 'file', 'none'
                'prefix'      => 'klytos:',    // Global key prefix (change for multiple installs)
                'default_ttl' => 3600,         // Default TTL in seconds (1 hour)
                'redis'       => [
                    'host'       => '127.0.0.1',
                    'port'       => 6379,
                    'password'   => '',
                    'database'   => 0,
                    'timeout'    => 2.0,
                    'persistent' => true,
                ],
                'memcached'   => [
                    'servers'         => [['127.0.0.1', 11211, 1]],
                    'username'        => '',
                    'password'        => '',
                    'persistent_id'   => 'klytos',
                    'binary_protocol' => true,
                ],
            ],
            'developer'        => [
                'developer_mode' => false,
            ],
            'notices'          => [
                'show_ads' => true,
            ],
            'languages'        => [],
            'last_build'       => null,
            'created_at'       => null,
            'updated_at'       => null,
        ];
    }
}
