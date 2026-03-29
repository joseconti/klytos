<?php
/**
 * Klytos Admin API — AI Chat Endpoint
 * AJAX endpoint for the integrated AI chat in the admin panel.
 *
 * Actions (POST with JSON body):
 * - send_message:    Send a message and get the AI response (with tool executions).
 * - new_chat:        Create a new conversation.
 * - list_chats:      List conversations for the current user.
 * - get_chat:        Get a full conversation with messages.
 * - delete_chat:     Delete a conversation.
 * - rename_chat:     Rename a conversation.
 * - switch_provider: Change AI provider mid-conversation.
 * - set_key:         Save an API key (requires site.configure).
 * - remove_key:      Remove an API key (requires site.configure).
 * - validate_key:    Test an API key against the provider.
 * - set_active:      Set the active provider and model.
 * - get_providers:   List providers with configuration status.
 *
 * Authentication: Requires active admin session + CSRF token for POST.
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

require_once dirname(__DIR__) . '/bootstrap.php';

use Klytos\Core\Helpers;
use Klytos\Core\Ai\AiKeyManager;
use Klytos\Core\Ai\ChatManager;

header('Content-Type: application/json; charset=utf-8');

// Require authentication.
if (!$app->getAuth()->isAuthenticated()) {
    Helpers::jsonResponse(['error' => 'Unauthorized'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Initialize managers.
$keys        = new AiKeyManager($app->getStorage(), $app->getConfigPath());
$chatManager = new ChatManager($app->getStorage());

// Get current user info.
$currentUser = klytos_current_user();
$userId      = (int) ($currentUser['id'] ?? 0);

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';

        if ($action === 'list_chats') {
            $limit  = (int) ($_GET['limit'] ?? 20);
            $offset = (int) ($_GET['offset'] ?? 0);
            $chats  = $chatManager->listChats($userId, $limit, $offset);

            Helpers::jsonResponse(['success' => true, 'chats' => $chats]);

        } elseif ($action === 'get_chat') {
            $chatId = $_GET['chat_id'] ?? '';
            if (empty($chatId)) {
                Helpers::jsonResponse(['error' => 'chat_id is required'], 400);
            }

            $chat = $chatManager->getChat($chatId);
            if ($chat === null || (int) ($chat['user_id'] ?? 0) !== $userId) {
                Helpers::jsonResponse(['error' => 'Conversation not found'], 404);
            }

            Helpers::jsonResponse(['success' => true, 'chat' => $chat]);

        } elseif ($action === 'get_providers') {
            Helpers::jsonResponse([
                'success'   => true,
                'providers' => $keys->listProviders(),
                'active'    => $keys->getActive(),
            ]);

        } else {
            Helpers::jsonResponse(['error' => 'Unknown action'], 400);
        }

    } elseif ($method === 'POST') {
        // Parse JSON body.
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        // Validate CSRF.
        if (!klytos_verify_csrf()) {
            Helpers::jsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $action = $input['action'] ?? '';

        // ─── Chat actions ────────────────────────────────────────

        if ($action === 'send_message') {
            $chatId  = $input['chat_id'] ?? '';
            $message = trim($input['message'] ?? '');

            if (empty($message)) {
                Helpers::jsonResponse(['error' => 'Message is required'], 400);
            }

            // Create a new conversation if no chat_id provided.
            if (empty($chatId)) {
                $active = $keys->getActive();
                $chatId = $chatManager->create(
                    $userId,
                    $active['provider'] ?? '',
                    $active['model'] ?? ''
                );
            }

            // Verify ownership.
            $chat = $chatManager->getChat($chatId);
            if ($chat === null || (int) ($chat['user_id'] ?? 0) !== $userId) {
                Helpers::jsonResponse(['error' => 'Conversation not found'], 404);
            }

            // Add user message to history.
            $chatManager->addMessage($chatId, [
                'role'    => 'user',
                'content' => $message,
            ]);

            // Rebuild the messages array from the conversation.
            $chat     = $chatManager->getChat($chatId);
            $messages = buildMessagesForProvider($chat['messages'] ?? []);

            // Determine provider/model.
            $providerId = $input['provider'] ?? null;
            $modelId    = $input['model'] ?? null;

            // Process through the chat engine (SDK-based).
            $chatEngine = $app->getChatEngine();
            $result     = $chatEngine->processMessage($userId, $messages, [
                'provider' => $providerId,
                'model'    => $modelId,
            ]);

            // Save assistant response to the conversation.
            $chatManager->addMessage($chatId, [
                'role'             => 'assistant',
                'content'          => $result->assistantMessage,
                'tool_executions'  => $result->toolExecutions,
                'provider'         => $result->provider,
                'model'            => $result->model,
                'usage'            => $result->totalUsage->toArray(),
            ]);

            Helpers::jsonResponse([
                'success' => true,
                'chat_id' => $chatId,
                'result'  => $result->toArray(),
            ]);

        } elseif ($action === 'new_chat') {
            $active = $keys->getActive();
            $chatId = $chatManager->create(
                $userId,
                $input['provider'] ?? $active['provider'] ?? '',
                $input['model'] ?? $active['model'] ?? ''
            );

            Helpers::jsonResponse(['success' => true, 'chat_id' => $chatId]);

        } elseif ($action === 'delete_chat') {
            $chatId = $input['chat_id'] ?? '';
            $chat   = $chatManager->getChat($chatId);

            if ($chat === null || (int) ($chat['user_id'] ?? 0) !== $userId) {
                Helpers::jsonResponse(['error' => 'Conversation not found'], 404);
            }

            $chatManager->deleteChat($chatId);
            Helpers::jsonResponse(['success' => true]);

        } elseif ($action === 'rename_chat') {
            $chatId = $input['chat_id'] ?? '';
            $title  = trim($input['title'] ?? '');

            if (empty($title)) {
                Helpers::jsonResponse(['error' => 'Title is required'], 400);
            }

            $chat = $chatManager->getChat($chatId);
            if ($chat === null || (int) ($chat['user_id'] ?? 0) !== $userId) {
                Helpers::jsonResponse(['error' => 'Conversation not found'], 404);
            }

            $chatManager->renameChat($chatId, $title);
            Helpers::jsonResponse(['success' => true]);

        } elseif ($action === 'switch_provider') {
            $chatId     = $input['chat_id'] ?? '';
            $providerId = $input['provider'] ?? '';
            $modelId    = $input['model'] ?? '';

            if (empty($providerId)) {
                Helpers::jsonResponse(['error' => 'Provider is required'], 400);
            }

            $chat = $chatManager->getChat($chatId);
            if ($chat === null || (int) ($chat['user_id'] ?? 0) !== $userId) {
                Helpers::jsonResponse(['error' => 'Conversation not found'], 404);
            }

            if (!isset(\Klytos\Core\Ai\AiKeyManager::PROVIDERS[$providerId])) {
                Helpers::jsonResponse(['error' => 'Unknown provider'], 400);
            }

            $providerName = \Klytos\Core\Ai\AiKeyManager::PROVIDERS[$providerId]['name'] ?? $providerId;

            // Add a system message recording the switch.
            $chatManager->addMessage($chatId, [
                'role'         => 'system',
                'content'      => 'Switched to ' . $providerName . ' / ' . ($modelId ?: $keys->getDefaultModelForProvider($providerId)),
                'message_type' => 'provider_change',
            ]);

            Helpers::jsonResponse(['success' => true]);

        // ─── Key management actions (require site.configure) ─────

        } elseif ($action === 'set_key') {
            if (!klytos_has_permission('site.configure')) {
                Helpers::jsonResponse(['error' => 'Permission denied'], 403);
            }

            $providerId   = $input['provider'] ?? '';
            $apiKey       = $input['api_key'] ?? '';
            $defaultModel = $input['default_model'] ?? '';

            if (empty($providerId) || empty($apiKey)) {
                Helpers::jsonResponse(['error' => 'Provider and API key are required'], 400);
            }

            if (!isset(\Klytos\Core\Ai\AiKeyManager::PROVIDERS[$providerId])) {
                Helpers::jsonResponse(['error' => 'Unknown provider'], 400);
            }

            $keys->setKey($providerId, $apiKey, $defaultModel ?: $keys->getDefaultModelForProvider($providerId));

            Helpers::jsonResponse([
                'success'    => true,
                'masked_key' => $keys->getMasked($providerId),
            ]);

        } elseif ($action === 'remove_key') {
            if (!klytos_has_permission('site.configure')) {
                Helpers::jsonResponse(['error' => 'Permission denied'], 403);
            }

            $providerId = $input['provider'] ?? '';
            $keys->removeKey($providerId);

            Helpers::jsonResponse(['success' => true]);

        } elseif ($action === 'validate_key') {
            if (!klytos_has_permission('site.configure')) {
                Helpers::jsonResponse(['error' => 'Permission denied'], 403);
            }

            $providerId = $input['provider'] ?? '';
            $apiKey     = $input['api_key'] ?? '';

            if (!isset(\Klytos\Core\Ai\AiKeyManager::PROVIDERS[$providerId])) {
                Helpers::jsonResponse(['error' => 'Unknown provider'], 400);
            }

            // Basic validation: key is non-empty and looks plausible.
            $valid = !empty($apiKey) && strlen($apiKey) > 10;
            Helpers::jsonResponse(['success' => true, 'valid' => $valid]);

        } elseif ($action === 'set_active') {
            if (!klytos_has_permission('site.configure')) {
                Helpers::jsonResponse(['error' => 'Permission denied'], 403);
            }

            $providerId = $input['provider'] ?? '';
            $modelId    = $input['model'] ?? '';

            if (empty($providerId)) {
                Helpers::jsonResponse(['error' => 'Provider is required'], 400);
            }

            $keys->setActive($providerId, $modelId);
            Helpers::jsonResponse(['success' => true]);

        } else {
            Helpers::jsonResponse(['error' => 'Unknown action'], 400);
        }

    } else {
        Helpers::jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (\Throwable $e) {
    Helpers::jsonResponse(['error' => $e->getMessage()], 500);
}

// ─── Helper ────────────────────────────────────────────────────

/**
 * Build the normalized messages array from stored conversation messages.
 * Strips metadata not needed by the AI provider (timestamps, UI data).
 */
function buildMessagesForProvider(array $storedMessages): array
{
    $messages = [];

    foreach ($storedMessages as $msg) {
        $role = $msg['role'] ?? 'user';

        // Skip system provider_change messages.
        if ($role === 'system' && ($msg['message_type'] ?? '') === 'provider_change') {
            continue;
        }

        $entry = ['role' => $role, 'content' => $msg['content'] ?? ''];

        // Include tool call data for assistant messages.
        if ($role === 'assistant' && !empty($msg['tool_executions'])) {
            $entry['tool_calls'] = [];

            foreach ($msg['tool_executions'] as $exec) {
                $entry['tool_calls'][] = [
                    'tool_call_id' => $exec['tool_call_id'] ?? ('tc_' . bin2hex(random_bytes(8))),
                    'tool_name'    => $exec['tool'] ?? '',
                    'tool_input'   => $exec['input'] ?? [],
                ];
            }

            // Add corresponding tool result messages after the assistant message.
            $messages[] = $entry;

            foreach ($msg['tool_executions'] as $exec) {
                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $exec['tool_call_id'] ?? ('tc_' . bin2hex(random_bytes(8))),
                    'tool_name'    => $exec['tool'] ?? '',
                    'content'      => json_encode($exec['output'] ?? []),
                ];
            }

            continue;
        }

        $messages[] = $entry;
    }

    return $messages;
}
