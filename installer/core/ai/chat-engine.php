<?php

/**
 * Klytos — Chat Engine
 * Orchestrates the AI chat using the soukicz/php-llm SDK.
 * The SDK handles provider abstraction, agentic loop, and tool execution.
 *
 * @package Klytos
 * @since   0.9.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core\Ai;

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;
use Soukicz\Llm\Client\Anthropic\AnthropicClient;
use Soukicz\Llm\Client\OpenAI\OpenAIClient;
use Soukicz\Llm\Client\OpenAI\OpenAICompatibleClient;
use Soukicz\Llm\Client\Gemini\GeminiClient;
use Soukicz\Llm\Client\LLMAgentClient;
use Soukicz\Llm\Client\LLMClient;
use Soukicz\Llm\Client\Universal\LocalModel;
use Soukicz\Llm\Tool\CallbackToolDefinition;
use Soukicz\Llm\LLMConversation;
use Soukicz\Llm\LLMRequest;
use Soukicz\Llm\LLMResponse;
use Soukicz\Llm\Message\LLMMessage;
use Soukicz\Llm\Message\LLMMessageContents;
use Soukicz\Llm\Message\LLMMessageText;

/**
 * Result of a complete chat interaction (may include multiple AI/tool iterations).
 */
class ChatResult
{
    public string $status = 'success';
    public string $assistantMessage = '';
    public array $toolExecutions = [];
    public int $inputTokens = 0;
    public int $outputTokens = 0;
    public string $provider = '';
    public string $model = '';
    public ?string $error = null;

    public static function error(string $errorCode, string $message): self
    {
        $result         = new self();
        $result->status = 'error';
        $result->error  = $message;
        return $result;
    }

    public function toArray(): array
    {
        return [
            'status'            => $this->status,
            'assistant_message' => $this->assistantMessage,
            'tool_executions'   => $this->toolExecutions,
            'usage'             => [
                'input_tokens'  => $this->inputTokens,
                'output_tokens' => $this->outputTokens,
                'total_tokens'  => $this->inputTokens + $this->outputTokens,
            ],
            'provider'          => $this->provider,
            'model'             => $this->model,
            'error'             => $this->error,
        ];
    }
}

/**
 * The main chat engine. Uses soukicz/php-llm SDK for AI provider communication
 * and the MCP ToolRegistry for tool execution.
 */
class ChatEngine
{
    private AiKeyManager $keys;
    private ToolRegistry $toolRegistry;
    private App $app;

    public function __construct(AiKeyManager $keys, ToolRegistry $toolRegistry, App $app)
    {
        $this->keys         = $keys;
        $this->toolRegistry = $toolRegistry;
        $this->app          = $app;
    }

    /**
     * Process a user message through the SDK's agentic loop.
     *
     * The SDK handles: sending to provider, receiving tool_calls,
     * executing tool callbacks, returning results, repeating until done.
     *
     * @param  int   $userId   Authenticated user ID (for permissions).
     * @param  array $messages Conversation history in normalized format.
     * @param  array $options  Optional: provider, model, temperature, max_tokens.
     * @return ChatResult
     */
    public function processMessage(int $userId, array $messages, array $options = []): ChatResult
    {
        $result = new ChatResult();

        // Carry the acting user's identity onto the tool registry so the
        // authorization gate (D-046) applies to every tool the AI executes,
        // exactly as it does to a direct MCP call — call() denies by default
        // when no actor is set. The role is resolved from the session, the same
        // source getAvailableTools() filters the advertised list by; a missing
        // one resolves to null, which the gate treats as deny (fail-closed).
        $actorRole = null;
        if (function_exists('klytos_current_user')) {
            $current   = klytos_current_user();
            $actorRole = $current['role'] ?? null;
        }
        $this->toolRegistry->setActor($userId, $actorRole);

        // Determine provider and model.
        $providerId = $options['provider'] ?? null;
        $modelId    = $options['model'] ?? null;

        if ($providerId === null || $modelId === null) {
            $active     = $this->keys->getActive();
            $providerId = $providerId ?? $active['provider'] ?? null;
            $modelId    = $modelId ?? $active['model'] ?? null;
        }

        if ($providerId === null) {
            return ChatResult::error('no_provider', 'No AI provider configured. Please add an API key in MCP settings.');
        }

        $apiKey = $this->keys->getKey($providerId);
        if ($apiKey === null) {
            return ChatResult::error('no_key', "No API key configured for provider: {$providerId}");
        }

        $result->provider = $providerId;
        $result->model    = $modelId ?: 'default';

        // Record key usage.
        $this->keys->touchLastUsed($providerId);

        // Fire pre-send hook.
        klytos_do_action('ai.chat.before_send', $userId, $messages, $providerId);

        try {
            // Create SDK client for the active provider.
            $client = $this->createClient($providerId, $apiKey);

            // Build the conversation from stored messages.
            $conversation = $this->buildConversation($messages);

            // Convert MCP tools to SDK CallbackToolDefinitions.
            $mcpTools = $this->getAvailableTools($userId);
            $sdkTools = $this->convertTools($mcpTools, $userId, $result);

            // Build the system prompt.
            $systemPrompt = $this->buildSystemPrompt($userId);

            // Add system prompt as first message if not already present.
            $conversation = new LLMConversation(
                array_merge(
                    [LLMMessage::createFromSystem(new LLMMessageContents([new LLMMessageText($systemPrompt)]))],
                    $conversation->getMessages()
                )
            );

            // Build the LLMRequest.
            $request = new LLMRequest(
                model: new LocalModel($modelId ?: $this->keys->getDefaultModelForProvider($providerId)),
                conversation: $conversation,
                temperature: $options['temperature'] ?? 0.0,
                maxTokens: $options['max_tokens'] ?? 4096,
                tools: $sdkTools,
            );

            // Run the agentic loop — the SDK handles everything.
            $agent    = new LLMAgentClient();
            $response = $agent->run($client, $request);

            // Extract results.
            try {
                $result->assistantMessage = $response->getLastText();
            } catch (\RuntimeException $e) {
                $result->assistantMessage = '';
            }
            $result->inputTokens  = $response->getInputTokens();
            $result->outputTokens = $response->getOutputTokens();

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $httpCode = $e->getResponse()->getStatusCode();
            $body     = (string) $e->getResponse()->getBody();

            $result = match ($httpCode) {
                401, 403 => ChatResult::error('invalid_key', 'API key is invalid or expired. Check your key in MCP > API IA settings.'),
                429      => ChatResult::error('rate_limited', 'Rate limited by provider. Please wait a moment and try again.'),
                402      => ChatResult::error('quota_exceeded', 'API quota exceeded. Check your billing at the provider.'),
                default  => ChatResult::error('provider_error', "Provider returned error {$httpCode}: " . mb_substr($body, 0, 200)),
            };
            $result->provider = $providerId;
            $result->model    = $modelId ?: '';

            klytos_do_action('ai.chat.error', $providerId, $e);

            if (function_exists('klytos_log')) {
                klytos_log("AI chat error [{$httpCode}]: " . mb_substr($body, 0, 300), 'error');
            }

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            $result = ChatResult::error('network', 'Could not connect to the AI provider. Check your server internet connection.');
            $result->provider = $providerId;

            if (function_exists('klytos_log')) {
                klytos_log('AI chat network error: ' . $e->getMessage(), 'error');
            }

        } catch (\Throwable $e) {
            $result = ChatResult::error('unknown', 'Unexpected error: ' . $e->getMessage());
            $result->provider = $providerId;

            if (function_exists('klytos_log')) {
                klytos_log('AI chat error: ' . $e->getMessage(), 'error');
            }
        }

        // Fire post-send hook.
        klytos_do_action('ai.chat.after_send', $userId, $result, $providerId);

        return $result;
    }

    /**
     * Get the key manager (for UI access to provider info).
     */
    public function getKeys(): AiKeyManager
    {
        return $this->keys;
    }

    /**
     * Test an API key against the provider it belongs to.
     *
     * This exists because the endpoint that claimed to do it did not: it read
     * `strlen($apiKey) > 10` and reported valid, so a revoked or mistyped key
     * was confirmed as working. The test is a real, minimal round trip through
     * the SAME client factory the chat itself uses — a key that works here
     * works there by construction, which a separately-written HTTP probe could
     * not promise.
     *
     * THREE OUTCOMES, NOT TWO. "I could not reach the provider" is not "your
     * key is wrong", and answering a network failure with `invalid` sends a
     * person to regenerate a key that was fine. The status is what carries
     * that; `valid` stays a boolean so the shipped response shape still holds
     * for any client reading it.
     *
     * The key is NEVER stored, logged or echoed by this path: it is passed to
     * the client and discarded with it.
     *
     * @param  string $providerId One of AiKeyManager::PROVIDERS.
     * @param  string $apiKey     The key to test. Never persisted here.
     * @return array{valid:bool,status:string,message:string}
     *         status is `valid`, `invalid` or `unreachable`.
     *
     * @throws \InvalidArgumentException When the provider is unknown.
     *
     * Example:
     *   $verdict = $app->getChatEngine()->validateKey( 'anthropic', $key );
     *   if ( $verdict['status'] === 'unreachable' ) { // ask them to retry }
     */
    public function validateKey(string $providerId, string $apiKey): array
    {
        if ($apiKey === '') {
            return ['valid' => false, 'status' => 'invalid', 'message' => 'Empty key.'];
        }

        try {
            $client = $this->createClient($providerId, $apiKey);

            // The smallest request the provider will price: one token out, no
            // tools, no system prompt, temperature 0.
            $request = new LLMRequest(
                model: new LocalModel($this->keys->getDefaultModelForProvider($providerId)),
                conversation: new LLMConversation([
                    LLMMessage::createFromUser(new LLMMessageContents([new LLMMessageText('ping')])),
                ]),
                temperature: 0.0,
                maxTokens: 1,
            );

            (new LLMAgentClient())->run($client, $request);

            return ['valid' => true, 'status' => 'valid', 'message' => ''];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // A 4xx is the provider answering — it reached us, so the verdict
            // is about the key. 401/403 is the key; anything else 4xx still
            // means this key cannot be used as-is.
            return [
                'valid'   => false,
                'status'  => 'invalid',
                'message' => 'Provider rejected the key (HTTP ' . $e->getResponse()->getStatusCode() . ').',
            ];
        } catch (\Throwable $e) {
            // Connection refused, DNS failure, TLS, timeout, a 5xx, or an SDK
            // error. None of them says anything about the key.
            return [
                'valid'   => false,
                'status'  => 'unreachable',
                'message' => 'Could not reach the provider to test the key.',
            ];
        }
    }

    // ─── Private ─────────────────────────────────────────────────

    /**
     * Create the appropriate SDK client for a provider.
     */
    private function createClient(string $providerId, string $apiKey): LLMClient
    {
        return match ($providerId) {
            'anthropic'  => new AnthropicClient($apiKey),
            'openai'     => new OpenAIClient($apiKey, ''),
            'gemini'     => new GeminiClient($apiKey),
            'openrouter' => new OpenAICompatibleClient($apiKey, 'https://openrouter.ai/api/v1'),
            'ollama'     => new OpenAICompatibleClient($apiKey, 'https://ollama.com/v1'),
            default      => throw new \InvalidArgumentException("Unknown AI provider: {$providerId}"),
        };
    }

    /**
     * Build an LLMConversation from stored message arrays.
     */
    private function buildConversation(array $messages): LLMConversation
    {
        $sdkMessages = [];

        foreach ($messages as $msg) {
            $role    = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';

            if ($role === 'system') {
                continue; // System prompt handled separately.
            }

            if ($role === 'user') {
                $sdkMessages[] = LLMMessage::createFromUserString($content);
            } elseif ($role === 'assistant') {
                $sdkMessages[] = LLMMessage::createFromAssistantString($content);
            }
            // Tool results are handled by the SDK's agentic loop internally.
        }

        return new LLMConversation($sdkMessages);
    }

    /**
     * Convert MCP tools from ToolRegistry to SDK CallbackToolDefinitions.
     *
     * Each tool's callback calls ToolRegistry::call() and tracks the execution
     * in the ChatResult for UI display.
     *
     * @param  array      $mcpTools MCP tools from ToolRegistry::listTools().
     * @param  int        $userId   User ID for permission checking.
     * @param  ChatResult $result   Reference to accumulate tool executions.
     * @return CallbackToolDefinition[]
     */
    private function convertTools(array $mcpTools, int $userId, ChatResult &$result): array
    {
        $sdkTools    = [];
        $registry    = $this->toolRegistry;

        foreach ($mcpTools as $tool) {
            $toolName    = $tool['name'];
            $schema      = $tool['inputSchema'] ?? ['type' => 'object', 'properties' => new \stdClass()];

            // Convert stdClass to array recursively for the SDK.
            $schemaArray = json_decode(json_encode($schema), true);

            $sdkTools[] = new CallbackToolDefinition(
                name: $toolName,
                description: $tool['description'] ?? '',
                inputSchema: $schemaArray,
                handler: function (array $input) use ($registry, $toolName, $userId, &$result): LLMMessageContents {
                    // Fire hook before tool execution.
                    klytos_do_action('ai.chat.tool_executed', $toolName, $input, $userId);

                    try {
                        $toolResult = $registry->call($toolName, $input);
                        $success    = !($toolResult['isError'] ?? false);
                        $text       = $toolResult['content'][0]['text'] ?? json_encode($toolResult);
                    } catch (\Throwable $e) {
                        $toolResult = ['error' => $e->getMessage()];
                        $success    = false;
                        $text       = 'Error: ' . $e->getMessage();

                        if (function_exists('klytos_log')) {
                            klytos_log("AI chat tool error [{$toolName}]: " . $e->getMessage(), 'error');
                        }
                    }

                    // Track execution for UI display.
                    $result->toolExecutions[] = [
                        'tool'    => $toolName,
                        'input'   => $input,
                        'output'  => $toolResult,
                        'success' => $success,
                    ];

                    return $success
                        ? LLMMessageContents::fromString($text)
                        : LLMMessageContents::fromErrorString($text);
                }
            );
        }

        return $sdkTools;
    }

    /**
     * Build the system prompt with site context.
     */
    private function buildSystemPrompt(int $userId): string
    {
        $storage = $this->app->getStorage();

        $site = [];
        try {
            $site = $storage->readFrom($this->app->getConfigPath(), 'config.json.enc');
        } catch (\Throwable $e) {
            // Fallback to empty.
        }

        $user = null;
        try {
            $userManager = $this->app->getUserManager();
            $user = $userManager->getById($userId);
        } catch (\Throwable $e) {
            // Fallback.
        }

        $toolCount = count($this->toolRegistry->listTools());

        $siteName    = $site['site_name'] ?? 'Klytos Site';
        $siteUrl     = $site['site_url'] ?? '';
        $defaultLang = $site['default_language'] ?? 'en';
        $description = $site['description'] ?? '';
        $username    = $user['username'] ?? 'admin';
        $userRole    = $user['role'] ?? 'owner';

        $prompt = <<<PROMPT
You are the Klytos assistant, an AI integrated into the admin panel of a CMS controlled 100% by AI.

SITE CONTEXT:
- Name: {$siteName}
- URL: {$siteUrl}
- Default language: {$defaultLang}
- Description: {$description}

CURRENT USER:
- Username: {$username}
- Role: {$userRole}

CAPABILITIES:
You have access to {$toolCount} tools to manage the site. Use them when the user asks you to make changes.

RULES:
1. Execute actions directly when the user asks. Do not ask for confirmation on each individual step unless the action is destructive (deleting pages, restoring versions).
2. After creating or modifying content, offer to run a build so the changes go live.
3. Respond in the user's language.
4. If an action fails, explain what happened and suggest alternatives.
5. When creating content (pages, blocks), generate professional and realistic content, not placeholders.
PROMPT;

        return klytos_apply_filters('ai.system_prompt', $prompt, $userId, $site);
    }

    /**
     * Get tools available for the current user, filtered by permissions.
     */
    private function getAvailableTools(int $userId): array
    {
        // listTools() is ALREADY filtered by the actor's capabilities (D-048):
        // processMessage() set the actor on this registry (:123), so this list is
        // the authoritative, capability-gated surface, and call() gates every
        // dispatch regardless. The role switch below is an ADVISORY refinement on
        // top of that — using the model-facing readOnlyHint / destructiveHint
        // annotations, never as the access control. Slice 2 neutralized the teeth
        // of the old fail-opens here; slice 3 makes this advisory list HONEST so
        // it is never wider than what the gate will actually allow.
        $allTools = $this->toolRegistry->listTools();

        // Resolve the acting role, fail-closed. If the identity helper is somehow
        // absent, or the user carries no role, treat the caller as the least
        // privileged real role (viewer) rather than skipping the filter and
        // returning the full list — the fail-OPEN this used to have when
        // klytos_current_user() was undefined.
        $role = 'viewer';
        if (function_exists('klytos_current_user')) {
            $user = klytos_current_user();
            $role = $user['role'] ?? 'viewer';
        }

        switch ($role) {
            case 'owner':
            case 'admin':
                // The full capability-gated list; the gate authorizes each call.
                break;

            case 'editor':
                $allTools = array_values(array_filter($allTools, function (array $tool): bool {
                    $annotations = (array) ($tool['annotations'] ?? []);
                    return ($annotations['destructiveHint'] ?? false) !== true;
                }));
                break;

            case 'viewer':
                $allTools = array_values(array_filter($allTools, function (array $tool): bool {
                    $annotations = (array) ($tool['annotations'] ?? []);
                    return ($annotations['readOnlyHint'] ?? false) === true;
                }));
                break;

            default:
                // An unrecognized role holds nothing in the matrix, so the gate
                // (denialReason) refuses it every capability-gated tool. The
                // advisory list must match that: default-deny to an empty set
                // rather than fall through to the full list, which is the
                // fail-open NEW-02 required be closed.
                $allTools = [];
                break;
        }

        return klytos_apply_filters('ai.tools_for_chat', $allTools, $userId);
    }
}
