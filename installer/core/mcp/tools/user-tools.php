<?php
/**
 * Klytos — MCP User Management Tools
 * Tools: klytos_list_users, klytos_create_user, klytos_update_user.
 *
 * @package Klytos
 * @since   2.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;

function registerUserTools(ToolRegistry $registry, App $app): void
{
    $registry->register(
        'klytos_list_users',
        'List all users with their roles and status. Does not expose password hashes.',
        [
            'role' => [
                'type'        => 'string',
                'description' => 'Filter by role: owner, admin, editor, viewer, or all.',
                'enum'        => ['all', 'owner', 'admin', 'editor', 'viewer'],
            ],
        ],
        function (array $params, App $app): array {
            $userManager = new \Klytos\Core\UserManager($app->getStorage());
            $role        = $params['role'] ?? 'all';
            return $userManager->list($role);
        },
        ['title' => 'List Users', 'readOnlyHint' => true, 'destructiveHint' => false]
    );

    $registry->register(
        'klytos_create_user',
        'Create a new user account with the specified role.',
        [
            'username'     => ['type' => 'string', 'description' => 'Username (3-50 chars, alphanumeric).'],
            'password'     => ['type' => 'string', 'description' => 'Password (min 12 characters).'],
            'email'        => ['type' => 'string', 'description' => 'Email address.'],
            'role'         => ['type' => 'string', 'description' => 'Role: admin, editor, viewer.', 'enum' => ['admin', 'editor', 'viewer']],
            'display_name' => ['type' => 'string', 'description' => 'Display name (optional).'],
        ],
        function (array $params, App $app): array {
            $userManager = new \Klytos\Core\UserManager($app->getStorage());
            return $userManager->create($params);
        },
        ['title' => 'Create User', 'readOnlyHint' => false, 'destructiveHint' => false],
        ['username', 'password', 'email', 'role']
    );

    $registry->register(
        'klytos_update_user',
        'Update an existing user. Only provide the fields you want to change. To change the password, provide the password field.',
        [
            'user_id'      => ['type' => 'string', 'description' => 'User ID to update.'],
            'first_name'   => ['type' => 'string', 'description' => 'First name.'],
            'last_name'    => ['type' => 'string', 'description' => 'Last name.'],
            'email'        => ['type' => 'string', 'description' => 'New email address.'],
            'display_name' => ['type' => 'string', 'description' => 'New display name.'],
            'role'         => ['type' => 'string', 'description' => 'New role.', 'enum' => ['admin', 'editor', 'viewer']],
            'status'       => ['type' => 'string', 'description' => 'Account status.', 'enum' => ['active', 'suspended']],
            'password'     => ['type' => 'string', 'description' => 'New password (min 12 chars). Omit to keep current.'],
        ],
        function (array $params, App $app): array {
            $userId = $params['user_id'] ?? '';
            if (empty($userId)) {
                throw new \InvalidArgumentException('user_id is required.');
            }
            $password = $params['password'] ?? '';
            unset($params['user_id'], $params['password']);
            $userManager = new \Klytos\Core\UserManager($app->getStorage());
            $result = $userManager->update($userId, $params);
            if (!empty($password)) {
                $userManager->changePassword($userId, $password);
            }
            return $result;
        },
        ['title' => 'Update User', 'readOnlyHint' => false, 'destructiveHint' => false],
        ['user_id']
    );

    $registry->register(
        'klytos_reset_user_password',
        'Generate a password reset token and send a reset link via email to the user.',
        [
            'user_id' => ['type' => 'string', 'description' => 'User ID to send the reset link to.'],
        ],
        function (array $params, App $app): array {
            $userId = $params['user_id'] ?? '';
            if (empty($userId)) {
                throw new \InvalidArgumentException('user_id is required.');
            }
            $userManager = new \Klytos\Core\UserManager($app->getStorage());
            $user  = $userManager->getById($userId);
            $token = $userManager->generatePasswordResetToken($userId);

            $basePath = \Klytos\Core\Helpers::getBasePath();
            $resetUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                . $basePath . 'admin/reset-password.php?user_id=' . urlencode($userId) . '&token=' . urlencode($token);

            $app->getMailer()->sendWithButton(
                $user['email'],
                'Password Reset',
                'Click the button below to reset your password. This link expires in 1 hour.',
                'Reset Password',
                $resetUrl
            );

            return ['success' => true, 'message' => 'Password reset link sent to ' . $user['email']];
        },
        ['title' => 'Reset User Password', 'readOnlyHint' => false, 'destructiveHint' => false],
        ['user_id']
    );

    $registry->register(
        'klytos_force_logout_user',
        'Force-close all active sessions for a user.',
        [
            'user_id' => ['type' => 'string', 'description' => 'User ID to force logout.'],
        ],
        function (array $params, App $app): array {
            $userId = $params['user_id'] ?? '';
            if (empty($userId)) {
                throw new \InvalidArgumentException('user_id is required.');
            }
            $userManager = new \Klytos\Core\UserManager($app->getStorage());
            $userManager->forceLogoutAllSessions($userId);
            return ['success' => true, 'message' => 'All sessions closed for user ' . $userId];
        },
        ['title' => 'Force Logout User', 'readOnlyHint' => false, 'destructiveHint' => true],
        ['user_id']
    );
}
