<?php
/**
 * Klytos — Chat Manager
 * Persistence layer for AI chat conversations.
 * Supports both flat-file (FileStorage) and MySQL (DatabaseStorage).
 *
 * @package Klytos
 * @since   0.9.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core\Ai;

use Klytos\Core\StorageInterface;
use Klytos\Core\DatabaseStorage;
use Klytos\Core\Helpers;

class ChatManager
{
    private StorageInterface $storage;
    private const COLLECTION = 'chats';

    /** @var bool Whether SQL tables have been verified this request. */
    private bool $tablesVerified = false;

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Create a new conversation.
     *
     * @param  int    $userId   Owner user ID.
     * @param  string $provider Initial provider ID.
     * @param  string $model    Initial model ID.
     * @return string The new conversation ID.
     */
    public function create(int $userId, string $provider, string $model): string
    {
        $this->ensureTables();

        $chatId = 'chat_' . bin2hex(random_bytes(12));
        $now    = Helpers::now();

        $data = [
            'id'               => $chatId,
            'user_id'          => $userId,
            'title'            => '',
            'initial_provider' => $provider,
            'initial_model'    => $model,
            'status'           => 'active',
            'messages'         => [],
            'total_usage'      => ['input_tokens' => 0, 'output_tokens' => 0],
            'created_at'       => $now,
            'updated_at'       => $now,
        ];

        $this->storage->write(self::COLLECTION, $chatId, $data);

        return $chatId;
    }

    /**
     * Add a message to a conversation.
     */
    public function addMessage(string $chatId, array $message): void
    {
        $this->ensureTables();

        $chat = $this->storage->read(self::COLLECTION, $chatId);

        // Auto-generate title from first user message.
        if (empty($chat['title']) && ($message['role'] ?? '') === 'user') {
            $content = is_string($message['content']) ? $message['content'] : '';
            $chat['title'] = mb_substr($content, 0, 60);
            if (mb_strlen($content) > 60) {
                $chat['title'] .= '...';
            }
        }

        $message['timestamp'] = $message['timestamp'] ?? Helpers::now();
        $chat['messages'][]   = $message;

        // Accumulate token usage from assistant messages.
        if (isset($message['usage'])) {
            $chat['total_usage']['input_tokens']  += $message['usage']['input_tokens'] ?? 0;
            $chat['total_usage']['output_tokens'] += $message['usage']['output_tokens'] ?? 0;
        }

        $chat['updated_at'] = Helpers::now();

        $this->storage->write(self::COLLECTION, $chatId, $chat);
    }

    /**
     * Get a full conversation by ID.
     */
    public function getChat(string $chatId): ?array
    {
        $this->ensureTables();

        try {
            return $this->storage->read(self::COLLECTION, $chatId);
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    /**
     * List conversations for a user, sorted by updated_at descending.
     *
     * @param  int $userId User ID.
     * @param  int $limit  Maximum results.
     * @param  int $offset Skip count.
     * @return array List of conversations (without full message history).
     */
    public function listChats(int $userId, int $limit = 20, int $offset = 0): array
    {
        $this->ensureTables();

        $all = $this->storage->list(self::COLLECTION, ['user_id' => $userId], 0, 0);

        // Sort by updated_at descending.
        usort($all, fn(array $a, array $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));

        // Paginate.
        $slice = array_slice($all, $offset, $limit);

        // Strip full messages for the list view (return only metadata).
        return array_map(function (array $chat): array {
            $messageCount = count($chat['messages'] ?? []);
            unset($chat['messages']);
            $chat['message_count'] = $messageCount;
            return $chat;
        }, $slice);
    }

    /**
     * Delete a conversation.
     */
    public function deleteChat(string $chatId): bool
    {
        $this->ensureTables();

        return $this->storage->delete(self::COLLECTION, $chatId);
    }

    /**
     * Rename a conversation.
     */
    public function renameChat(string $chatId, string $title): void
    {
        $this->ensureTables();

        $chat = $this->storage->read(self::COLLECTION, $chatId);
        $chat['title']      = $title;
        $chat['updated_at'] = Helpers::now();

        $this->storage->write(self::COLLECTION, $chatId, $chat);
    }

    /**
     * Get token usage statistics for a user.
     *
     * @param  int         $userId User ID.
     * @param  string|null $period Period filter: 'month', 'week', 'all' (default: 'month').
     * @return array       Usage stats.
     */
    public function getChatUsage(int $userId, ?string $period = 'month'): array
    {
        $this->ensureTables();

        $all = $this->storage->list(self::COLLECTION, ['user_id' => $userId], 0, 0);

        $cutoff = match ($period) {
            'week'  => (new \DateTimeImmutable('-7 days', new \DateTimeZone('UTC')))->format('c'),
            'month' => (new \DateTimeImmutable('-30 days', new \DateTimeZone('UTC')))->format('c'),
            default => null,
        };

        $totalInput      = 0;
        $totalOutput     = 0;
        $conversations   = 0;
        $toolExecutions  = 0;

        foreach ($all as $chat) {
            if ($cutoff !== null && ($chat['created_at'] ?? '') < $cutoff) {
                continue;
            }

            $conversations++;
            $totalInput  += $chat['total_usage']['input_tokens'] ?? 0;
            $totalOutput += $chat['total_usage']['output_tokens'] ?? 0;

            // Count tool executions across messages.
            foreach ($chat['messages'] ?? [] as $msg) {
                $toolExecutions += count($msg['tool_executions'] ?? []);
            }
        }

        return [
            'input_tokens'    => $totalInput,
            'output_tokens'   => $totalOutput,
            'total_tokens'    => $totalInput + $totalOutput,
            'conversations'   => $conversations,
            'tool_executions' => $toolExecutions,
            'period'          => $period,
        ];
    }

    /**
     * Search conversations by title and message content.
     *
     * @param  int    $userId User ID.
     * @param  string $query  Search term.
     * @param  int    $limit  Maximum results.
     * @return array  Matching conversations (without full message history).
     */
    public function searchChats(int $userId, string $query, int $limit = 30): array
    {
        $this->ensureTables();

        if (empty(trim($query))) {
            return [];
        }

        $all     = $this->storage->list(self::COLLECTION, ['user_id' => $userId], 0, 0);
        $queryLc = mb_strtolower(trim($query));
        $results = [];

        foreach ($all as $chat) {
            if (count($results) >= $limit) {
                break;
            }

            $found = false;

            // Search in title.
            if (!empty($chat['title']) && mb_strpos(mb_strtolower($chat['title']), $queryLc) !== false) {
                $found = true;
            }

            // Search in message content.
            if (!$found) {
                foreach ($chat['messages'] ?? [] as $msg) {
                    $content = $msg['content'] ?? '';
                    if (is_string($content) && mb_strpos(mb_strtolower($content), $queryLc) !== false) {
                        $found = true;
                        break;
                    }
                }
            }

            if ($found) {
                $messageCount = count($chat['messages'] ?? []);
                unset($chat['messages']);
                $chat['message_count'] = $messageCount;
                $results[] = $chat;
            }
        }

        // Sort by updated_at descending.
        usort($results, fn(array $a, array $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));

        return $results;
    }

    // ─── Private ─────────────────────────────────────────────────

    /**
     * Ensure SQL tables exist if using DatabaseStorage.
     * Called once per request, result cached in memory.
     */
    private function ensureTables(): void
    {
        if ($this->tablesVerified) {
            return;
        }

        if (!($this->storage instanceof DatabaseStorage)) {
            $this->tablesVerified = true;
            return;
        }

        try {
            $pdo    = $this->storage->getPdo();
            $prefix = $this->storage->getPrefix();

            $stmt = $pdo->query("SHOW TABLES LIKE '{$prefix}chats'");
            if ($stmt->rowCount() === 0) {
                $this->createTables($pdo, $prefix);

                if (function_exists('klytos_log')) {
                    klytos_log('AI chat tables auto-created', 'info');
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal: if table creation fails, the StorageInterface
            // will handle it via its own collection auto-creation.
        }

        $this->tablesVerified = true;
    }

    /**
     * Create the chat SQL tables.
     */
    private function createTables(\PDO $pdo, string $prefix): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS {$prefix}chats (
                id          VARCHAR(32) PRIMARY KEY,
                user_id     INT UNSIGNED NOT NULL,
                title       VARCHAR(255) NOT NULL DEFAULT '',
                initial_provider VARCHAR(32) NOT NULL,
                initial_model    VARCHAR(64) NOT NULL,
                status      ENUM('active', 'archived') DEFAULT 'active',
                total_input_tokens  INT UNSIGNED DEFAULT 0,
                total_output_tokens INT UNSIGNED DEFAULT 0,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_date (user_id, updated_at DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS {$prefix}chat_messages (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chat_id     VARCHAR(32) NOT NULL,
                role        ENUM('user', 'assistant', 'system') NOT NULL,
                content     LONGTEXT NOT NULL,
                provider    VARCHAR(32),
                model       VARCHAR(64),
                message_type VARCHAR(32) DEFAULT 'message',
                input_tokens  INT UNSIGNED DEFAULT 0,
                output_tokens INT UNSIGNED DEFAULT 0,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (chat_id) REFERENCES {$prefix}chats(id) ON DELETE CASCADE,
                INDEX idx_chat_order (chat_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
