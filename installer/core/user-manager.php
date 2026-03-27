<?php
/**
 * Klytos — User Manager
 * Multi-user CRUD with role-based access control.
 *
 * Roles (hierarchical, highest to lowest):
 * - owner:  Full control. Only one owner per site. Can transfer ownership.
 * - admin:  Full content + settings control. Cannot manage other admins or the owner.
 * - editor: Can create, edit, and view pages. Cannot delete or manage system settings.
 * - viewer: Read-only access to the admin panel. Useful for clients reviewing content.
 *
 * Security:
 * - Passwords hashed with bcrypt (cost 12) — same as the installer.
 * - User IDs are UUIDs (16 hex chars) to prevent enumeration.
 * - Owner role is unique: only one user can be owner at a time.
 * - Ownership transfer requires the current owner to initiate it.
 * - All user mutations are logged via the AuditLog.
 *
 * Storage:
 * - Collection 'users' in StorageInterface (flat-file or database).
 * - Each user is stored as: users/{user_id}.json.enc
 * - Passwords are NEVER stored in cleartext — only bcrypt hashes.
 *
 * @package Klytos
 * @since   2.0.0
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

class UserManager
{
    /** @var StorageInterface Storage backend. */
    private StorageInterface $storage;

    /** @var string Collection name in storage. */
    private const COLLECTION = 'users';

    /** @var array Valid user roles (ordered highest to lowest privilege). */
    private const VALID_ROLES = ['owner', 'admin', 'editor', 'viewer'];

    /** @var int Minimum password length. */
    private const MIN_PASSWORD_LENGTH = 12;

    /** @var int Bcrypt cost factor. Higher = slower but more secure. */
    private const BCRYPT_COST = 12;

    /**
     * @param StorageInterface $storage Storage backend instance.
     */
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    // ─── CRUD Operations ─────────────────────────────────────────

    /**
     * Create a new user.
     *
     * @param  array $data User data: username (required), password (required),
     *                     email (required), role, display_name.
     * @return array The created user (without password hash).
     * @throws \InvalidArgumentException On validation failure.
     * @throws \RuntimeException If username already exists.
     */
    public function create(array $data): array
    {
        // Validate required fields.
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $email    = trim($data['email'] ?? '');
        $role     = $data['role'] ?? 'editor';

        if (empty($username)) {
            throw new \InvalidArgumentException('Username is required.');
        }

        if (!preg_match('/^[a-zA-Z0-9_\-]{3,50}$/', $username)) {
            throw new \InvalidArgumentException(
                'Username must be 3-50 characters: letters, numbers, hyphens, underscores.'
            );
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new \InvalidArgumentException(
                'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.'
            );
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('A valid email address is required.');
        }

        if (!in_array($role, self::VALID_ROLES, true)) {
            throw new \InvalidArgumentException(
                'Invalid role. Must be one of: ' . implode(', ', self::VALID_ROLES)
            );
        }

        // Prevent creating a second owner.
        if ($role === 'owner' && $this->findOwner() !== null) {
            throw new \RuntimeException('An owner already exists. Use transferOwnership() instead.');
        }

        // Check for duplicate username.
        if ($this->getByUsername($username) !== null) {
            throw new \RuntimeException("Username already exists: {$username}");
        }

        // Check for duplicate email.
        if ($this->getByEmail($email) !== null) {
            throw new \RuntimeException("Email already in use: {$email}");
        }

        // Generate a unique user ID (16 hex chars = 8 bytes of randomness).
        $userId = Helpers::randomHex(8);

        $user = [
            'id'           => $userId,
            'username'     => $username,
            'email'        => $email,
            'display_name' => trim($data['display_name'] ?? $username),
            'role'         => $role,
            'pass_hash'    => password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]),
            'status'       => 'active',
            'created_at'   => Helpers::now(),
            'updated_at'   => Helpers::now(),
            'last_login'   => null,
        ];

        $this->storage->write(self::COLLECTION, $userId, $user);

        // Fire hook for plugins (e.g., send welcome email, log creation).
        Hooks::doAction('user.created', $this->sanitizeForOutput($user));

        return $this->sanitizeForOutput($user);
    }

    /**
     * Update an existing user.
     *
     * Supports partial updates: only provided fields are changed.
     * Password and role changes have additional security checks.
     *
     * @param  string $userId User ID to update.
     * @param  array  $data   Fields to update: email, display_name, role, status.
     * @return array  The updated user (without password hash).
     * @throws \RuntimeException If user not found.
     */
    public function update(string $userId, array $data): array
    {
        $user = $this->storage->read(self::COLLECTION, $userId);

        // Updatable fields (password is handled separately via changePassword).
        $updatable = ['email', 'display_name', 'role', 'status'];

        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                // Validate specific fields.
                if ($field === 'email') {
                    if (empty(trim($data['email'])) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                        throw new \InvalidArgumentException('A valid email address is required.');
                    }
                }
                if ($field === 'role' && !in_array($data['role'], self::VALID_ROLES, true)) {
                    throw new \InvalidArgumentException('Invalid role.');
                }
                if ($field === 'role' && $data['role'] === 'owner' && $user['role'] !== 'owner') {
                    throw new \RuntimeException('Cannot set owner role directly. Use transferOwnership().');
                }
                if ($field === 'status' && !in_array($data['status'], ['active', 'suspended'], true)) {
                    throw new \InvalidArgumentException('Invalid status. Must be: active, suspended.');
                }

                $user[$field] = $data[$field];
            }
        }

        $user['updated_at'] = Helpers::now();
        $this->storage->write(self::COLLECTION, $userId, $user);

        Hooks::doAction('user.updated', $this->sanitizeForOutput($user));

        return $this->sanitizeForOutput($user);
    }

    /**
     * Delete a user permanently.
     *
     * The owner cannot be deleted (must transfer ownership first).
     *
     * @param  string $userId User ID to delete.
     * @return bool   True if deleted.
     * @throws \RuntimeException If trying to delete the owner.
     */
    public function delete(string $userId): bool
    {
        $user = $this->storage->read(self::COLLECTION, $userId);

        if ($user['role'] === 'owner') {
            throw new \RuntimeException('Cannot delete the owner. Transfer ownership first.');
        }

        $result = $this->storage->delete(self::COLLECTION, $userId);

        if ($result) {
            Hooks::doAction('user.deleted', $userId, $user['username']);
        }

        return $result;
    }

    // ─── Lookup Methods ──────────────────────────────────────────

    /**
     * Get a user by their ID.
     *
     * @param  string $userId User ID.
     * @return array  User data (without password hash).
     * @throws \RuntimeException If not found.
     */
    public function getById(string $userId): array
    {
        $user = $this->storage->read(self::COLLECTION, $userId);
        return $this->sanitizeForOutput($user);
    }

    /**
     * Find a user by username.
     *
     * @param  string $username Username to search for.
     * @return array|null User data (without password hash), or null if not found.
     */
    public function getByUsername(string $username): ?array
    {
        $users = $this->storage->list(self::COLLECTION);

        foreach ($users as $user) {
            if (($user['username'] ?? '') === $username) {
                return $this->sanitizeForOutput($user);
            }
        }

        return null;
    }

    /**
     * Find a user by email address.
     *
     * @param  string $email Email to search for.
     * @return array|null User data (without password hash), or null if not found.
     */
    public function getByEmail(string $email): ?array
    {
        $users = $this->storage->list(self::COLLECTION);

        foreach ($users as $user) {
            if (($user['email'] ?? '') === $email) {
                return $this->sanitizeForOutput($user);
            }
        }

        return null;
    }

    /**
     * List all users with optional role filter.
     *
     * @param  string $role   Filter by role ('all' for no filter).
     * @param  int    $limit  Maximum results.
     * @param  int    $offset Skip N results.
     * @return array  Array of user data (without password hashes).
     */
    public function list(string $role = 'all', int $limit = 50, int $offset = 0): array
    {
        $filters = [];
        if ($role !== 'all' && in_array($role, self::VALID_ROLES, true)) {
            $filters['role'] = $role;
        }

        $users = $this->storage->list(self::COLLECTION, $filters, $limit, $offset);

        return array_map([$this, 'sanitizeForOutput'], $users);
    }

    /**
     * Count total users with optional role filter.
     *
     * @param  string $role Filter by role ('all' for no filter).
     * @return int
     */
    public function count(string $role = 'all'): int
    {
        $filters = [];
        if ($role !== 'all') {
            $filters['role'] = $role;
        }

        return $this->storage->count(self::COLLECTION, $filters);
    }

    // ─── Authentication ──────────────────────────────────────────

    /**
     * Authenticate a user by username and password.
     *
     * Returns the full user data (with password hash) on success,
     * or null on failure. The caller should NOT expose the hash.
     *
     * @param  string $username Username.
     * @param  string $password Plain-text password to verify.
     * @return array|null Full user data (including hash) on success, null on failure.
     */
    public function authenticate(string $username, string $password): ?array
    {
        // We need to search raw (with hash) to verify the password.
        $users = $this->storage->list(self::COLLECTION);

        foreach ($users as $user) {
            if (($user['username'] ?? '') !== $username) {
                continue;
            }

            // Check account status.
            if (($user['status'] ?? 'active') !== 'active') {
                return null; // Suspended accounts cannot log in.
            }

            // Verify password against bcrypt hash.
            if (password_verify($password, $user['pass_hash'] ?? '')) {
                // Update last login timestamp.
                $user['last_login'] = Helpers::now();
                $this->storage->write(self::COLLECTION, $user['id'], $user);

                Hooks::doAction('user.login', $this->sanitizeForOutput($user));

                return $user; // Return full data (caller handles sanitization).
            }

            return null; // Password mismatch.
        }

        return null; // User not found.
    }

    /**
     * Change a user's password.
     *
     * @param  string $userId      User ID.
     * @param  string $newPassword New plain-text password (min 12 chars).
     * @return bool   True on success.
     * @throws \InvalidArgumentException If password is too short.
     */
    public function changePassword(string $userId, string $newPassword): bool
    {
        if (strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            throw new \InvalidArgumentException(
                'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.'
            );
        }

        $user = $this->storage->read(self::COLLECTION, $userId);
        $user['pass_hash']  = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
        $user['updated_at'] = Helpers::now();

        $this->storage->write(self::COLLECTION, $userId, $user);

        return true;
    }

    // ─── Ownership ───────────────────────────────────────────────

    /**
     * Transfer site ownership from the current owner to another user.
     *
     * The current owner becomes an admin. Only the current owner can do this.
     *
     * @param  string $currentOwnerId Current owner's user ID.
     * @param  string $newOwnerId     New owner's user ID.
     * @return bool   True on success.
     * @throws \RuntimeException On validation failures.
     */
    public function transferOwnership(string $currentOwnerId, string $newOwnerId): bool
    {
        $currentOwner = $this->storage->read(self::COLLECTION, $currentOwnerId);
        $newOwner     = $this->storage->read(self::COLLECTION, $newOwnerId);

        if ($currentOwner['role'] !== 'owner') {
            throw new \RuntimeException('Only the current owner can transfer ownership.');
        }

        if ($newOwner['status'] !== 'active') {
            throw new \RuntimeException('Cannot transfer ownership to a suspended user.');
        }

        // Demote current owner to admin.
        $currentOwner['role']       = 'admin';
        $currentOwner['updated_at'] = Helpers::now();
        $this->storage->write(self::COLLECTION, $currentOwnerId, $currentOwner);

        // Promote new owner.
        $newOwner['role']       = 'owner';
        $newOwner['updated_at'] = Helpers::now();
        $this->storage->write(self::COLLECTION, $newOwnerId, $newOwner);

        Hooks::doAction('user.ownership_transferred', $currentOwnerId, $newOwnerId);

        return true;
    }

    /**
     * Find the current site owner.
     *
     * @return array|null Owner user data, or null if no owner exists.
     */
    public function findOwner(): ?array
    {
        $users = $this->storage->list(self::COLLECTION, ['role' => 'owner']);
        return !empty($users) ? $this->sanitizeForOutput($users[0]) : null;
    }

    // ─── Permission Checks ───────────────────────────────────────

    /**
     * Check if a user has a specific permission.
     *
     * Uses the same capability matrix as klytos_has_permission() in helpers-global.php.
     * Applies the 'auth.capabilities' filter so plugins can extend permissions.
     *
     * @param  array  $user       User data (must include 'role').
     * @param  string $permission Permission key (e.g. 'pages.create').
     * @return bool
     */
    public function hasPermission(array $user, string $permission): bool
    {
        $role = $user['role'] ?? 'viewer';

        // Owner has all permissions.
        if ($role === 'owner') {
            return true;
        }

        // Default capability matrix.
        $capabilities = [
            'pages.view'       => ['owner', 'admin', 'editor', 'viewer'],
            'pages.create'     => ['owner', 'admin', 'editor'],
            'pages.edit'       => ['owner', 'admin', 'editor'],
            'pages.delete'     => ['owner', 'admin'],
            'theme.manage'     => ['owner', 'admin'],
            'menu.manage'      => ['owner', 'admin'],
            'blocks.manage'    => ['owner', 'admin'],
            'templates.manage' => ['owner', 'admin'],
            'templates.approve' => ['owner'],
            'build.run'        => ['owner', 'admin'],
            'assets.manage'    => ['owner', 'admin', 'editor'],
            'tasks.create'     => ['owner', 'admin', 'editor'],
            'tasks.manage'     => ['owner', 'admin'],
            'users.manage'     => ['owner'],
            'mcp.manage'       => ['owner', 'admin'],
            'site.configure'   => ['owner', 'admin'],
            'plugins.manage'   => ['owner'],
            'analytics.view'   => ['owner', 'admin', 'editor'],
            'forms.manage'     => ['owner', 'admin'],
            'webhooks.manage'  => ['owner', 'admin'],
            'updates.manage'   => ['owner'],
        ];

        // Allow plugins to extend capabilities.
        $capabilities = Hooks::applyFilters('auth.capabilities', $capabilities);

        $allowedRoles = $capabilities[$permission] ?? [];

        return in_array($role, $allowedRoles, true);
    }

    // ─── Migration Helper ────────────────────────────────────────

    /**
     * Migrate the v1.0 single admin user to the v2.0 multi-user system.
     *
     * Called once during the upgrade from v1.x to v2.0. Reads the admin
     * credentials from config and creates the owner user in the users collection.
     *
     * @param  array $config The v1.0 config array (admin_user, admin_pass_hash, admin_email).
     * @return array The created owner user.
     */
    public function migrateFromV1Config(array $config): array
    {
        // Check if migration already happened.
        $existingOwner = $this->findOwner();
        if ($existingOwner !== null) {
            return $existingOwner;
        }

        // Email is mandatory for all users. If the v1 config is missing it,
        // we cannot create a valid owner — the installer always collects it.
        $email = trim($config['admin_email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException(
                'Cannot migrate: admin_email is missing or invalid in config. '
                . 'Please reinstall or add admin_email to the configuration.'
            );
        }

        $userId = Helpers::randomHex(8);

        $user = [
            'id'           => $userId,
            'username'     => $config['admin_user'] ?? 'admin',
            'email'        => $email,
            'display_name' => $config['admin_user'] ?? 'Admin',
            'role'         => 'owner',
            'pass_hash'    => $config['admin_pass_hash'] ?? '',
            'status'       => 'active',
            'created_at'   => $config['installed_at'] ?? Helpers::now(),
            'updated_at'   => Helpers::now(),
            'last_login'   => null,
        ];

        $this->storage->write(self::COLLECTION, $userId, $user);

        return $this->sanitizeForOutput($user);
    }

    // ─── Internal ────────────────────────────────────────────────

    /**
     * Remove sensitive fields (password hash) before returning user data.
     *
     * NEVER expose the password hash to the outside world.
     *
     * @param  array $user Raw user data from storage.
     * @return array Sanitized user data (safe for API responses and templates).
     */
    private function sanitizeForOutput(array $user): array
    {
        unset($user['pass_hash']);
        return $user;
    }
}
