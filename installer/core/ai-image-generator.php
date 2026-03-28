<?php
/**
 * Klytos — AI Image Generator
 * Integration with Google Gemini (Nano Banana) for image generation.
 * Supports Imagen 3 via the Gemini API.
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

class AiImageGenerator
{
    /** @var StorageInterface Storage backend (FileStorage or DatabaseStorage). */
    private StorageInterface $storage;

    private AssetManager $assets;
    private string $configPath;

    private const AI_CONFIG_FILE = 'ai-config.json.enc';
    private const AI_HISTORY_FILE = 'ai-history.json.enc';
    private const DEFAULT_MODEL  = 'gemini-2.0-flash-exp';

    // Gemini API endpoints
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(StorageInterface $storage, AssetManager $assets, string $configPath)
    {
        $this->storage    = $storage;
        $this->assets     = $assets;
        $this->configPath = rtrim($configPath, '/');
    }

    /**
     * Generate an image from a text prompt.
     *
     * @param  string $prompt      Text description of the desired image.
     * @param  array  $options     Optional: model, size, filename, directory.
     * @return array  Result with generated image info.
     * @throws \RuntimeException On API errors or missing config.
     */
    public function generate(string $prompt, array $options = []): array
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            throw new \RuntimeException('Gemini API key not configured. Set it in Settings > AI Images.');
        }

        $model     = $options['model'] ?? self::DEFAULT_MODEL;
        $filename  = $options['filename'] ?? $this->generateFilename($prompt);
        $directory = $options['directory'] ?? 'images/ai-generated';

        // Build the API request
        $url = self::API_BASE . '/models/' . $model . ':generateContent?key=' . $apiKey;

        $requestBody = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
            ],
        ];

        $response = $this->apiRequest($url, $requestBody);

        if ($response === null) {
            throw new \RuntimeException('Failed to connect to Gemini API.');
        }

        // Extract image from response
        $imageData = $this->extractImage($response);

        if ($imageData === null) {
            // Check for text-only response (model might not support image generation)
            $textResponse = $this->extractText($response);
            throw new \RuntimeException(
                'No image was generated. The model may not support image generation. ' .
                ($textResponse ? 'API response: ' . mb_substr($textResponse, 0, 200) : '')
            );
        }

        // Save to assets
        $assetResult = $this->assets->upload(
            $filename,
            $imageData['base64'],
            $directory
        );

        // Log to history
        $this->addToHistory([
            'prompt'     => $prompt,
            'model'      => $model,
            'filename'   => $assetResult['filename'],
            'path'       => $assetResult['path'],
            'size'       => $assetResult['size'],
            'mime_type'  => $imageData['mime_type'],
            'created_at' => Helpers::now(),
        ]);

        return [
            'success'   => true,
            'prompt'    => $prompt,
            'model'     => $model,
            'asset'     => $assetResult,
            'mime_type' => $imageData['mime_type'],
        ];
    }

    /**
     * Get the configured API key.
     *
     * @return string
     */
    public function getApiKey(): string
    {
        try {
            $config = $this->storage->readFrom($this->configPath, self::AI_CONFIG_FILE);
            return $config['gemini_api_key'] ?? '';
        } catch (\RuntimeException $e) {
            return '';
        }
    }

    /**
     * Save the API key.
     *
     * @param string $apiKey
     */
    public function setApiKey(string $apiKey): void
    {
        $config = [];
        try {
            $config = $this->storage->readFrom($this->configPath, self::AI_CONFIG_FILE);
        } catch (\RuntimeException $e) {
            // New config
        }

        $config['gemini_api_key'] = $apiKey;
        $config['updated_at']     = Helpers::now();

        $this->storage->writeTo($this->configPath, self::AI_CONFIG_FILE, $config);
    }

    /**
     * Check if the API key is configured.
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->getApiKey());
    }

    /**
     * Get the generation history.
     *
     * @param  int $limit
     * @return array
     */
    public function getHistory(int $limit = 50): array
    {
        try {
            $data = $this->storage->read(self::AI_HISTORY_FILE);
            $history = $data['history'] ?? [];
            // Sort newest first
            usort($history, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
            return array_slice($history, 0, $limit);
        } catch (\RuntimeException $e) {
            return [];
        }
    }

    /**
     * Get available models for image generation.
     *
     * @return array
     */
    public function getAvailableModels(): array
    {
        return [
            [
                'id'          => 'gemini-2.0-flash-exp',
                'name'        => 'Gemini 2.0 Flash (Experimental)',
                'description' => 'Fast multimodal model with native image generation',
            ],
            [
                'id'          => 'imagen-3.0-generate-002',
                'name'        => 'Imagen 3',
                'description' => 'Dedicated high-quality image generation model',
            ],
        ];
    }

    /**
     * Make a request to the Gemini API.
     */
    private function apiRequest(string $url, array $body): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120, // Image generation can be slow
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if (!empty($error)) {
            throw new \RuntimeException('cURL error: ' . $error);
        }

        if ($httpCode !== 200) {
            $decoded = json_decode($response, true);
            $message = $decoded['error']['message'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException('Gemini API error: ' . $message);
        }

        return json_decode($response, true);
    }

    /**
     * Extract image data from a Gemini API response.
     */
    private function extractImage(array $response): ?array
    {
        $candidates = $response['candidates'] ?? [];

        foreach ($candidates as $candidate) {
            $parts = $candidate['content']['parts'] ?? [];

            foreach ($parts as $part) {
                if (isset($part['inlineData'])) {
                    return [
                        'base64'    => $part['inlineData']['data'] ?? '',
                        'mime_type' => $part['inlineData']['mimeType'] ?? 'image/png',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Extract text from a Gemini API response.
     */
    private function extractText(array $response): ?string
    {
        $candidates = $response['candidates'] ?? [];

        foreach ($candidates as $candidate) {
            $parts = $candidate['content']['parts'] ?? [];
            foreach ($parts as $part) {
                if (isset($part['text'])) {
                    return $part['text'];
                }
            }
        }

        return null;
    }

    /**
     * Add an entry to the generation history.
     */
    private function addToHistory(array $entry): void
    {
        try {
            $data = $this->storage->read(self::AI_HISTORY_FILE);
        } catch (\RuntimeException $e) {
            $data = ['history' => []];
        }

        $data['history'][] = $entry;

        // Keep last 200 entries
        if (count($data['history']) > 200) {
            $data['history'] = array_slice($data['history'], -200);
        }

        $this->storage->write(self::AI_HISTORY_FILE, $data);
    }

    /**
     * Generate a filename from the prompt.
     */
    private function generateFilename(string $prompt): string
    {
        // Take first 40 chars, slugify, add timestamp
        $slug = Helpers::sanitizeSlug(mb_substr($prompt, 0, 40));
        $slug = str_replace('/', '-', $slug);
        if (empty($slug)) {
            $slug = 'ai-image';
        }
        return $slug . '-' . date('YmdHis') . '.png';
    }
}
