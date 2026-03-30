<?php
/**
 * Klytos — AI Key Manager
 * Manages encrypted API keys for AI providers.
 * Keys stored in config/ai-keys.json.enc via StorageInterface.
 *
 * @package Klytos
 * @since   0.9.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core\Ai;

use Klytos\Core\StorageInterface;
use Klytos\Core\Helpers;

class AiKeyManager
{
    private StorageInterface $storage;
    private string $configPath;
    private const KEY_FILE = 'ai-keys.json.enc';

    /** @var array|null In-memory cache of decrypted key data. */
    private ?array $cache = null;

    /**
     * Static provider metadata for the UI (name, models, default_model).
     * The SDK handles the actual API communication; this is just for display.
     */
    public const PROVIDERS = [
        'anthropic' => [
            'name'          => 'Anthropic Claude',
            'models'        => [
                ['id' => 'claude-opus-4-6',           'name' => 'Claude Opus 4.6',   'tier' => 'premium'],
                ['id' => 'claude-sonnet-4-6',          'name' => 'Claude Sonnet 4.6', 'tier' => 'standard'],
                ['id' => 'claude-haiku-4-5-20251001',  'name' => 'Claude Haiku 4.5',  'tier' => 'economy'],
            ],
            'default_model' => 'claude-sonnet-4-6',
        ],
        'openai' => [
            'name'          => 'OpenAI GPT',
            'models'        => [
                ['id' => 'gpt-4o',      'name' => 'GPT-4o',        'tier' => 'standard'],
                ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini',   'tier' => 'economy'],
                ['id' => 'o3',          'name' => 'o3 (Reasoning)', 'tier' => 'premium'],
                ['id' => 'o4-mini',     'name' => 'o4 Mini',       'tier' => 'standard'],
            ],
            'default_model' => 'gpt-4o',
        ],
        'gemini' => [
            'name'          => 'Google Gemini',
            'models'        => [
                ['id' => 'gemini-2.5-pro',   'name' => 'Gemini 2.5 Pro',   'tier' => 'premium'],
                ['id' => 'gemini-2.5-flash',  'name' => 'Gemini 2.5 Flash', 'tier' => 'standard'],
            ],
            'default_model' => 'gemini-2.5-flash',
        ],
        'openrouter' => [
            'name'          => 'OpenRouter (Multi-modelo)',
            'models'        => [
                ['id' => 'anthropic/claude-sonnet-4',   'name' => 'Claude Sonnet 4 (OpenRouter)',   'tier' => 'standard'],
                ['id' => 'openai/gpt-4o',               'name' => 'GPT-4o (OpenRouter)',            'tier' => 'standard'],
                ['id' => 'google/gemini-2.5-flash',      'name' => 'Gemini 2.5 Flash (OpenRouter)',  'tier' => 'economy'],
                ['id' => 'meta-llama/llama-4-maverick',  'name' => 'Llama 4 Maverick (OpenRouter)',  'tier' => 'economy'],
            ],
            'default_model' => 'anthropic/claude-sonnet-4',
        ],
    ];

    public function __construct(StorageInterface $storage, string $configPath)
    {
        $this->storage    = $storage;
        $this->configPath = $configPath;
    }

    /**
     * Save an API key for a provider.
     *
     * @param string $providerId   Provider identifier (e.g. 'anthropic').
     * @param string $apiKey       The raw API key.
     * @param string $defaultModel Default model to use.
     */
    public function setKey(string $providerId, string $apiKey, string $defaultModel): void
    {
        $data = $this->loadData();

        $data['providers'][$providerId] = [
            'api_key'       => $apiKey,
            'default_model' => $defaultModel,
            'configured_at' => Helpers::now(),
            'last_used'     => null,
        ];

        // If this is the first key, set it as active.
        if (!isset($data['active_provider'])) {
            $data['active_provider'] = $providerId;
            $data['active_model']    = $defaultModel;
        }

        $this->saveData($data);

        if (function_exists('klytos_do_action')) {
            klytos_do_action('ai.key.configured', $providerId);
        }
    }

    /**
     * Get the decrypted API key for a provider (for internal use only).
     *
     * @param  string $providerId Provider identifier.
     * @return string|null The API key, or null if not configured.
     */
    public function getKey(string $providerId): ?string
    {
        $data     = $this->loadData();
        $provider = $data['providers'][$providerId] ?? null;

        return $provider['api_key'] ?? null;
    }

    /**
     * Remove an API key for a provider.
     */
    public function removeKey(string $providerId): bool
    {
        $data = $this->loadData();

        if (!isset($data['providers'][$providerId])) {
            return false;
        }

        unset($data['providers'][$providerId]);

        // If the removed provider was active, clear or switch active.
        if (($data['active_provider'] ?? '') === $providerId) {
            $remaining = array_keys($data['providers'] ?? []);
            if (!empty($remaining)) {
                $data['active_provider'] = $remaining[0];
                $data['active_model']    = $data['providers'][$remaining[0]]['default_model'] ?? '';
            } else {
                $data['active_provider'] = null;
                $data['active_model']    = null;
            }
        }

        $this->saveData($data);

        if (function_exists('klytos_do_action')) {
            klytos_do_action('ai.key.removed', $providerId);
        }

        return true;
    }

    /**
     * Check if a provider has a configured API key.
     */
    public function hasKey(string $providerId): bool
    {
        $data = $this->loadData();

        return isset($data['providers'][$providerId]['api_key']);
    }

    /**
     * List configured provider IDs (without exposing keys).
     *
     * @return array<array{provider_id: string, default_model: string, configured_at: string, last_used: string|null}>
     */
    public function listConfigured(): array
    {
        $data   = $this->loadData();
        $result = [];

        foreach ($data['providers'] ?? [] as $id => $info) {
            $result[] = [
                'provider_id'   => $id,
                'default_model' => $info['default_model'] ?? '',
                'configured_at' => $info['configured_at'] ?? '',
                'last_used'     => $info['last_used'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Get the active provider and model.
     *
     * @return array{provider: string|null, model: string|null}
     */
    public function getActive(): array
    {
        $data = $this->loadData();

        return [
            'provider' => $data['active_provider'] ?? null,
            'model'    => $data['active_model'] ?? null,
        ];
    }

    /**
     * Set the active provider and model.
     */
    public function setActive(string $providerId, string $model): void
    {
        $data = $this->loadData();

        $data['active_provider'] = $providerId;
        $data['active_model']    = $model;

        $this->saveData($data);
    }

    /**
     * Get the masked version of an API key (last 4 characters).
     */
    public function getMasked(string $providerId): ?string
    {
        $key = $this->getKey($providerId);

        if ($key === null) {
            return null;
        }

        $prefix = substr($key, 0, 6);
        $suffix = substr($key, -4);

        return $prefix . '...' . $suffix;
    }

    /**
     * Record that a provider's key was used (for "last used" tracking).
     */
    public function touchLastUsed(string $providerId): void
    {
        $data = $this->loadData();

        if (isset($data['providers'][$providerId])) {
            $data['providers'][$providerId]['last_used'] = Helpers::now();
            $this->saveData($data);
        }
    }

    /**
     * List all providers with their metadata and configuration status.
     * Used by the admin UI to display provider cards and selectors.
     *
     * @return array List of providers with id, name, models, configured, masked_key.
     */
    public function listProviders(): array
    {
        $list = [];

        foreach (self::PROVIDERS as $id => $info) {
            $list[] = [
                'id'            => $id,
                'name'          => $info['name'],
                'models'        => $info['models'],
                'default_model' => $info['default_model'],
                'configured'    => $this->hasKey($id),
                'masked_key'    => $this->getMasked($id),
            ];
        }

        return $list;
    }

    /**
     * Get the default model for a provider from the static PROVIDERS list.
     */
    public function getDefaultModelForProvider(string $providerId): string
    {
        return self::PROVIDERS[$providerId]['default_model'] ?? '';
    }

    // ─── Private ─────────────────────────────────────────────────

    /**
     * Load the encrypted key data from disk.
     */
    private function loadData(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            $this->cache = $this->storage->readFrom($this->configPath, self::KEY_FILE);
        } catch (\RuntimeException $e) {
            // File does not exist yet — start with empty data.
            $this->cache = [
                'providers'       => [],
                'active_provider' => null,
                'active_model'    => null,
            ];
        }

        return $this->cache;
    }

    /**
     * Save data to disk and update the in-memory cache.
     */
    private function saveData(array $data): void
    {
        $this->storage->writeTo($this->configPath, self::KEY_FILE, $data);
        $this->cache = $data;
    }
}
