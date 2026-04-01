<?php

/**
 * Klytos — MCP Translation Tools
 * Tools for managing translations via MCP assistant.
 *
 * @package Klytos
 * @since   0.19.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core\MCP\Tools;

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;
use Klytos\Core\TranslationManager;

function registerTranslationTools( ToolRegistry $registry ): void
{
    // ─── klytos_list_translation_sources ─────────────────────
    $registry->register(
        'klytos_list_translation_sources',
        'List all translation sources (core, active plugins, templates) with translation statistics per language.',
        [],
        function ( array $params, App $app ): array {
            $tm        = new TranslationManager( $app );
            $sources   = $tm->getSources();
            $stats     = $tm->getStats();
            $languages = $tm->getConfiguredLanguages();

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode( [
                            'sources'   => $sources,
                            'languages' => $languages,
                            'stats'     => $stats,
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
                    ],
                ],
                'isError' => false,
            ];
        },
        ['readOnlyHint' => true, 'idempotentHint' => true],
        []
    );

    // ─── klytos_get_translations ─────────────────────────────
    $registry->register(
        'klytos_get_translations',
        'Get all translation keys for a source, comparing English reference with a target locale. Shows which keys are missing translations.',
        [
            'source'       => ['type' => 'string', 'description' => "Source ID ('core', plugin-id, template-id)"],
            'locale'       => ['type' => 'string', 'description' => "Target locale code (e.g. 'es', 'fr')"],
            'only_missing' => ['type' => 'boolean', 'description' => 'If true, only return keys without translation'],
        ],
        function ( array $params, App $app ): array {
            $source      = $params['source'] ?? '';
            $locale      = $params['locale'] ?? '';
            $onlyMissing = $params['only_missing'] ?? false;

            if ( $source === '' || $locale === '' ) {
                return [
                    'content' => [['type' => 'text', 'text' => json_encode( ['error' => 'Missing required parameters: source, locale'] )]],
                    'isError' => true,
                ];
            }

            $tm         = new TranslationManager( $app );
            $reference  = $tm->getReferenceKeys( $source );
            $translated = $tm->getTranslations( $source, $locale );

            $keys = [];
            foreach ( $reference as $key => $enValue ) {
                $translation = $translated[$key] ?? null;
                $isMissing   = $translation === null || $translation === '';

                if ( $onlyMissing && !$isMissing ) {
                    continue;
                }

                $keys[$key] = [
                    'en'          => $enValue,
                    'translation' => $isMissing ? null : $translation,
                ];
            }

            $total          = count( $reference );
            $translatedCount = count( $translated );
            $missingCount    = $total - $translatedCount;

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode( [
                            'source'     => $source,
                            'locale'     => $locale,
                            'total'      => $total,
                            'translated' => $translatedCount,
                            'missing'    => max( 0, $missingCount ),
                            'keys'       => $keys,
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
                    ],
                ],
                'isError' => false,
            ];
        },
        ['readOnlyHint' => true, 'idempotentHint' => true],
        ['source', 'locale']
    );

    // ─── klytos_translate ────────────────────────────────────
    $registry->register(
        'klytos_translate',
        'Save one or more translations for a source and locale. The AI assistant should translate from the English reference text. IMPORTANT: Always maintain HTML tags intact. Do not translate placeholder variables like {variable}.',
        [
            'source'       => ['type' => 'string', 'description' => 'Source ID'],
            'locale'       => ['type' => 'string', 'description' => 'Target locale code'],
            'translations' => [
                'type'                 => 'object',
                'description'          => 'Map of dot-notation key to translated string. Example: {"common.save": "Guardar", "common.cancel": "Cancelar"}',
                'additionalProperties' => true,
            ],
        ],
        function ( array $params, App $app ): array {
            $source       = $params['source'] ?? '';
            $locale       = $params['locale'] ?? '';
            $translations = $params['translations'] ?? [];

            if ( $source === '' || $locale === '' || empty( $translations ) ) {
                return [
                    'content' => [['type' => 'text', 'text' => json_encode( ['error' => 'Missing required parameters: source, locale, translations'] )]],
                    'isError' => true,
                ];
            }

            try {
                $tm = new TranslationManager( $app );
                $tm->saveBulkTranslations( $source, $locale, $translations );

                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => json_encode( [
                                'success' => true,
                                'saved'   => count( $translations ),
                            ] ),
                        ],
                    ],
                    'isError' => false,
                ];
            } catch ( \Throwable $e ) {
                return [
                    'content' => [['type' => 'text', 'text' => json_encode( ['error' => $e->getMessage()] )]],
                    'isError' => true,
                ];
            }
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true],
        ['source', 'locale', 'translations']
    );

    // ─── klytos_translate_with_ai ────────────────────────────
    $registry->register(
        'klytos_translate_with_ai',
        'Use a configured AI provider to automatically translate missing keys for a source and locale. Requires an AI provider with API key configured in Settings > AI Keys.',
        [
            'source'   => ['type' => 'string', 'description' => 'Source ID'],
            'locale'   => ['type' => 'string', 'description' => 'Target locale code'],
            'provider' => ['type' => 'string', 'description' => 'AI provider ID. If omitted, uses the active provider.'],
            'keys'     => [
                'type'        => 'array',
                'description' => 'Specific keys to translate. If omitted, translates all missing keys.',
                'items'       => ['type' => 'string'],
            ],
        ],
        function ( array $params, App $app ): array {
            $source     = $params['source'] ?? '';
            $locale     = $params['locale'] ?? '';
            $providerId = $params['provider'] ?? '';
            $keyFilter  = $params['keys'] ?? [];

            if ( $source === '' || $locale === '' ) {
                return [
                    'content' => [['type' => 'text', 'text' => json_encode( ['error' => 'Missing required parameters: source, locale'] )]],
                    'isError' => true,
                ];
            }

            try {
                $tm      = new TranslationManager( $app );
                $missing = $tm->getMissingKeys( $source, $locale );

                // Filter to specific keys if provided.
                if ( !empty( $keyFilter ) ) {
                    $missing = array_intersect_key( $missing, array_flip( $keyFilter ) );
                }

                if ( empty( $missing ) ) {
                    return [
                        'content' => [['type' => 'text', 'text' => json_encode( ['success' => true, 'translated' => 0, 'message' => 'No missing keys to translate.'] )]],
                        'isError' => false,
                    ];
                }

                // Get AI provider.
                $aiKeys = new \Klytos\Core\Ai\AiKeyManager( $app->getStorage(), $app->getConfigPath() );

                if ( $providerId === '' ) {
                    $active     = $aiKeys->getActive();
                    $providerId = $active['provider'] ?? '';
                }

                if ( $providerId === '' || !$aiKeys->hasKey( $providerId ) ) {
                    return [
                        'content' => [['type' => 'text', 'text' => json_encode( ['error' => 'No AI provider configured or invalid provider.'] )]],
                        'isError' => true,
                    ];
                }

                $chatEngine   = $app->getChatEngine();
                $userId       = 0;
                $translations = [];

                foreach ( $missing as $key => $englishText ) {
                    $prompt = "Translate the following text from en to {$locale}.\n"
                        . "Context: This is a UI string for a CMS admin panel. Key: {$key}\n"
                        . "Keep HTML tags intact if present. Do not translate placeholder variables like {variable}.\n"
                        . "Only return the translated text, nothing else.\n\n"
                        . "Text: {$englishText}";

                    $result = $chatEngine->processMessage( $userId, [
                        ['role' => 'user', 'content' => $prompt],
                    ], [
                        'provider'   => $providerId,
                        'max_tokens' => 512,
                    ] );

                    if ( $result->status === 'success' && $result->assistantMessage !== '' ) {
                        $translation = trim( $result->assistantMessage );
                        // Remove potential quote wrapping.
                        if (
                            ( str_starts_with( $translation, '"' ) && str_ends_with( $translation, '"' ) ) ||
                            ( str_starts_with( $translation, "'" ) && str_ends_with( $translation, "'" ) )
                        ) {
                            $translation = substr( $translation, 1, -1 );
                        }
                        $translations[$key] = $translation;
                    }
                }

                // Save all translations at once.
                if ( !empty( $translations ) ) {
                    $tm->saveBulkTranslations( $source, $locale, $translations );
                }

                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => json_encode( [
                                'success'      => true,
                                'translated'   => count( $translations ),
                                'provider'     => $providerId,
                                'translations' => $translations,
                            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
                        ],
                    ],
                    'isError' => false,
                ];
            } catch ( \Throwable $e ) {
                return [
                    'content' => [['type' => 'text', 'text' => json_encode( ['error' => $e->getMessage()] )]],
                    'isError' => true,
                ];
            }
        },
        ['readOnlyHint' => false, 'destructiveHint' => false],
        ['source', 'locale']
    );
}
