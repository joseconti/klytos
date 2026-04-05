<?php

/**
 * Klytos — Encryption Level Trait
 * Shared logic for determining which data should be encrypted
 * based on the configured encryption level (basic, medium, professional).
 *
 * Used by FileStorage and DatabaseStorage to avoid duplicating
 * encryption-level logic across storage backends.
 *
 * @package Klytos
 * @since   1.1.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare( strict_types=1 );

namespace Klytos\Core;

trait EncryptionLevelTrait
{
    /**
     * Numeric mapping for encryption levels.
     * Higher number = more data is encrypted.
     */
    private const ENCRYPTION_LEVELS = [
        'basic'        => 0,
        'medium'       => 1,
        'professional' => 2,
    ];

    /**
     * Collections and config IDs that require encryption at each level.
     *
     * - 'basic': critical system configuration only.
     * - 'medium': adds personal/sensitive user data (GDPR-relevant).
     * - 'professional': encrypts absolutely everything.
     *
     * Each entry can be:
     * - A string: collection name (all records in that collection are encrypted).
     * - An array with 'collection' + 'ids': only specific IDs within the collection.
     */
    private const ENCRYPTED_PATHS = [
        'basic' => [
            // Config files handled via readFrom/writeTo are always encrypted
            // (they use .json.enc extension directly). This covers collections:
            ['collection' => 'config', 'ids' => ['tokens', 'app_passwords', 'oauth_clients']],
        ],
        'medium' => [
            'users',
            'audit-log',
            'sessions',
            'chats',
            '2fa',
        ],
        'professional' => [
            'pages',
            'blocks',
            'page-templates',
            'page-versions',
            'forms',
            'form-submissions',
            'logs',
            'webhooks',
            'analytics',
            'analytics-salt',
            'assets',
            'asset-categories',
            'asset-usage',
            'plugins',
            'options',
            'tasks',
            // Config IDs that become encrypted at professional level
            ['collection' => 'config', 'ids' => ['site', 'theme', 'menus', 'templates', 'post_types']],
        ],
    ];

    /** @var string|null Cached encryption level to avoid repeated config reads. */
    private ?string $cachedEncryptionLevel = null;

    /**
     * Determine if a collection+id pair requires encryption
     * based on the current encryption level.
     *
     * @param string $collection Collection name (e.g. 'pages', 'users').
     * @param string $id         Record identifier (e.g. 'user-001', 'site').
     * @return bool True if the data should be encrypted.
     */
    public function shouldEncrypt( string $collection, string $id = '' ): bool
    {
        $level    = $this->getEncryptionLevel();
        $levelNum = self::ENCRYPTION_LEVELS[$level] ?? 0;

        // Check each level up to and including the current one.
        foreach ( self::ENCRYPTION_LEVELS as $levelName => $num ) {
            if ( $num > $levelNum ) {
                break;
            }

            foreach ( self::ENCRYPTED_PATHS[$levelName] as $entry ) {
                if ( is_string( $entry ) ) {
                    // Entire collection is encrypted at this level.
                    if ( $collection === $entry ) {
                        return true;
                    }
                } elseif ( is_array( $entry ) ) {
                    // Specific IDs within a collection.
                    if ( $collection === $entry['collection'] && in_array( $id, $entry['ids'], true ) ) {
                        return true;
                    }
                }
            }
        }

        // Consult the option sensitivity registry for the 'options' collection.
        // Developers declare sensitivity via klytos_register_option() and the
        // OptionsManager decides per-key whether encryption is needed.
        if ( $collection === 'options' && $id !== '' && class_exists( '\Klytos\Core\OptionsManager' ) ) {
            if ( \Klytos\Core\OptionsManager::shouldEncryptOption( $id, $levelNum ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the current encryption level.
     * Defaults to 'basic' if not configured (backward compatibility).
     *
     * @return string One of: 'basic', 'medium', 'professional'.
     */
    public function getEncryptionLevel(): string
    {
        if ( $this->cachedEncryptionLevel !== null ) {
            return $this->cachedEncryptionLevel;
        }

        $this->cachedEncryptionLevel = $this->loadEncryptionLevelFromConfig();

        return $this->cachedEncryptionLevel;
    }

    /**
     * Change the encryption level, encrypting or decrypting
     * affected collections as needed.
     *
     * @param string $newLevel The target level ('basic', 'medium', 'professional').
     * @throws \InvalidArgumentException If the level is invalid.
     */
    public function changeEncryptionLevel( string $newLevel ): void
    {
        if ( !isset( self::ENCRYPTION_LEVELS[$newLevel] ) ) {
            throw new \InvalidArgumentException(
                "Invalid encryption level: '{$newLevel}'. Valid levels: basic, medium, professional."
            );
        }

        $currentLevel = $this->getEncryptionLevel();
        $currentNum   = self::ENCRYPTION_LEVELS[$currentLevel];
        $newNum       = self::ENCRYPTION_LEVELS[$newLevel];

        if ( $newNum === $currentNum ) {
            return;
        }

        if ( $newNum > $currentNum ) {
            // Upgrading: encrypt collections that weren't encrypted before.
            foreach ( self::ENCRYPTION_LEVELS as $levelName => $levelNum ) {
                if ( $levelNum <= $currentNum ) {
                    continue;
                }
                if ( $levelNum > $newNum ) {
                    break;
                }
                foreach ( self::ENCRYPTED_PATHS[$levelName] as $entry ) {
                    $this->encryptCollectionData( $entry );
                }
            }
        } else {
            // Downgrading: decrypt collections that no longer need encryption.
            foreach ( self::ENCRYPTION_LEVELS as $levelName => $levelNum ) {
                if ( $levelNum <= $newNum ) {
                    continue;
                }
                if ( $levelNum > $currentNum ) {
                    break;
                }
                foreach ( self::ENCRYPTED_PATHS[$levelName] as $entry ) {
                    $this->decryptCollectionData( $entry );
                }
            }
        }

        // Update cached level and persist.
        $this->cachedEncryptionLevel = $newLevel;
        $this->saveEncryptionLevelToConfig( $newLevel );
    }

    /**
     * Load the encryption level from the main config file.
     * Must be implemented by the using class.
     *
     * @return string The encryption level string.
     */
    abstract private function loadEncryptionLevelFromConfig(): string;

    /**
     * Persist the encryption level to the main config file.
     * Must be implemented by the using class.
     *
     * @param string $level The new encryption level.
     */
    abstract private function saveEncryptionLevelToConfig( string $level ): void;

    /**
     * Encrypt all records in a collection (or specific IDs).
     * Must be implemented by the using class (FileStorage vs DatabaseStorage).
     *
     * @param string|array $entry Collection name or ['collection' => ..., 'ids' => [...]].
     */
    abstract private function encryptCollectionData( string|array $entry ): void;

    /**
     * Decrypt all records in a collection (or specific IDs).
     * Must be implemented by the using class (FileStorage vs DatabaseStorage).
     *
     * @param string|array $entry Collection name or ['collection' => ..., 'ids' => [...]].
     */
    abstract private function decryptCollectionData( string|array $entry ): void;
}
