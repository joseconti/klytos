<?php

/**
 * Klytos — JSON-RPC 2.0 Parser/Builder
 * Handles parsing and building JSON-RPC 2.0 messages for MCP.
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

namespace Klytos\Core\MCP;

class JsonRpc
{
    /**
     * Parse a JSON-RPC 2.0 request from raw body.
     *
     * @param  string $rawBody
     * @return array  Parsed request ['jsonrpc', 'method', 'params', 'id']
     * @throws \RuntimeException On invalid JSON or missing fields.
     */
    public static function parseRequest(string $rawBody): array
    {
        if (empty($rawBody)) {
            throw new \RuntimeException('Empty request body.');
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON.');
        }

        // Validate JSON-RPC 2.0
        if (($data['jsonrpc'] ?? '') !== '2.0') {
            throw new \RuntimeException('Invalid JSON-RPC version. Expected "2.0".');
        }

        if (empty($data['method']) || !is_string($data['method'])) {
            throw new \RuntimeException('Missing or invalid "method" field.');
        }

        return [
            'jsonrpc' => '2.0',
            'method'  => $data['method'],
            'params'  => $data['params'] ?? [],
            'id'      => $data['id'] ?? null,
        ];
    }

    /**
     * Build a successful JSON-RPC 2.0 response.
     *
     * @param  mixed      $result
     * @param  string|int|null $id Request ID.
     * @return array
     */
    public static function success(mixed $result, string|int|null $id = null): array
    {
        return [
            'jsonrpc' => '2.0',
            'result'  => $result,
            'id'      => $id,
        ];
    }

    /**
     * Build a JSON-RPC 2.0 error response.
     *
     * @param  int             $code    Error code.
     * @param  string          $message Error message.
     * @param  mixed           $data    Additional error data.
     * @param  string|int|null $id      Request ID.
     * @return array
     */
    public static function error(int $code, string $message, mixed $data = null, string|int|null $id = null): array
    {
        $error = [
            'code'    => $code,
            'message' => $message,
        ];

        if ($data !== null) {
            $error['data'] = $data;
        }

        return [
            'jsonrpc' => '2.0',
            'error'   => $error,
            'id'      => $id,
        ];
    }

    // ─── Standard JSON-RPC 2.0 Error Codes ────────────────────

    public static function parseError(string|int|null $id = null): array
    {
        return self::error(-32700, 'Parse error', null, $id);
    }

    public static function invalidRequest(string|int|null $id = null): array
    {
        return self::error(-32600, 'Invalid Request', null, $id);
    }

    public static function methodNotFound(string $method, string|int|null $id = null): array
    {
        return self::error(-32601, "Method not found: {$method}", null, $id);
    }

    public static function invalidParams(string $message, string|int|null $id = null): array
    {
        return self::error(-32602, "Invalid params: {$message}", null, $id);
    }

    public static function internalError(string $message, string|int|null $id = null): array
    {
        return self::error(-32603, "Internal error: {$message}", null, $id);
    }
}
