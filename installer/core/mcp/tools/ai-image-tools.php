<?php

/**
 * Klytos — MCP AI Image Tools
 * AI-powered image generation via MCP.
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

namespace Klytos\Core\MCP\Tools;

use Klytos\Core\App;
use Klytos\Core\AiImageGenerator;
use Klytos\Core\MCP\ToolRegistry;

function registerAiImageTools(ToolRegistry $registry): void
{
    $registry->register(
        'klytos_generate_image',
        'Generate an image using AI (Gemini/Imagen). The image is saved to the site assets automatically.',
        [
            'prompt'    => ['type' => 'string', 'description' => 'Text description of the image to generate. Be specific and detailed for best results.'],
            'filename'  => ['type' => 'string', 'description' => 'Optional filename (e.g. "hero-banner.png"). Auto-generated if empty.'],
            'directory' => ['type' => 'string', 'description' => 'Subdirectory within assets/ (default: "images/ai-generated")'],
            'model'     => ['type' => 'string', 'description' => 'AI model to use', 'enum' => ['gemini-2.0-flash-exp', 'imagen-3.0-generate-002']],
        ],
        function (array $params, App $app): array {
            $generator = new AiImageGenerator(
                $app->getStorage(),
                $app->getAssets()
            );

            if (!$generator->isConfigured()) {
                throw new \RuntimeException('Gemini API key not configured. Set it via the admin panel in Settings.');
            }

            $prompt = $params['prompt'] ?? '';
            if (empty($prompt)) {
                throw new \RuntimeException('A prompt is required to generate an image.');
            }

            $options = [];
            if (!empty($params['filename'])) {
                $options['filename'] = $params['filename'];
            }
            if (!empty($params['directory'])) {
                $options['directory'] = $params['directory'];
            }
            if (!empty($params['model'])) {
                $options['model'] = $params['model'];
            }

            return $generator->generate($prompt, $options);
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false],
        ['prompt']
    );

    $registry->register(
        'klytos_list_ai_images',
        'List all AI-generated images with their prompts and metadata.',
        [
            'limit' => ['type' => 'integer', 'description' => 'Max results (default 50)'],
        ],
        function (array $params, App $app): array {
            $generator = new AiImageGenerator(
                $app->getStorage(),
                $app->getAssets()
            );

            $history = $generator->getHistory((int) ($params['limit'] ?? 50));
            return ['images' => $history, 'total' => count($history)];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );
}
