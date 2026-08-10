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
    /**
     * The minimum password length this manager accepts, anywhere.
     *
     * PUBLIC since entry 27 (Profile), and deliberately so: the floor is
     * enforced here, in `create()` and `changePassword()`, and every screen that
     * asks a person for a password has to state the same number in its hint and
     * in its `minlength` attribute. While it was private, each of those was a
     * hand-copied `12` that nothing tied to this one — the shipped profile
     * screen carried exactly that, and a change here would have left three
     * screens promising the old floor.
     *
     * @var int
     */
    public const MIN_PASSWORD_LENGTH = 12;

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

        if (empty($email) || !Helpers::isEmail( $email )) {
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

        $firstName = trim($data['first_name'] ?? '');
        $lastName  = trim($data['last_name'] ?? '');

        // Compute display_name from first/last name, or fall back to explicit value / username.
        $displayName = trim($firstName . ' ' . $lastName);
        if ($displayName === '') {
            $displayName = trim($data['display_name'] ?? $username);
        }

        $user = [
            'id'                     => $userId,
            'username'               => $username,
            'email'                  => $email,
            'first_name'             => $firstName,
            'last_name'              => $lastName,
            'display_name'           => $displayName,
            'role'                   => $role,
            'pass_hash'              => password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]),
            'status'                 => 'active',
            'created_at'             => Helpers::now(),
            'updated_at'             => Helpers::now(),
            'last_login'             => null,
            'password_reset_token'   => null,
            'password_reset_expires' => null,
            'force_logout_at'        => null,
            'bio'                    => trim( $data['bio'] ?? '' ),
            'avatar'                 => trim( $data['avatar'] ?? '' ),
            'website'                => trim( $data['website'] ?? '' ),
            'social_links'           => $data['social_links'] ?? [],
            'locale'                 => trim( $data['locale'] ?? '' ),
        ];

        // Pre-hook: allow plugins to act before user creation.
        klytos_do_action('user.before_create', $user);

        $this->storage->write(self::COLLECTION, $userId, $user);

        // Fire hook for plugins (e.g., send welcome email, log creation).
        klytos_do_action('user.created', $this->sanitizeForOutput($user));

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
        $user    = $this->storage->read(self::COLLECTION, $userId);
        $oldRole = $user['role'] ?? '';

        // Updatable fields (password is handled separately via changePassword).
        $updatable = ['email', 'display_name', 'first_name', 'last_name', 'role', 'status',
                       'bio', 'avatar', 'website', 'locale'];

        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                // Validate specific fields.
                if ($field === 'email') {
                    $newEmail = trim($data['email']);
                    if (empty($newEmail) || !Helpers::isEmail( $newEmail )) {
                        throw new \InvalidArgumentException('A valid email address is required.');
                    }
                    // Check email uniqueness (exclude the current user).
                    $existing = $this->getByEmail($newEmail);
                    if ($existing !== null && ($existing['id'] ?? '') !== $userId) {
                        throw new \RuntimeException('Email already in use by another user.');
                    }
                    $data['email'] = $newEmail;
                }
                if ($field === 'role' && !in_array($data['role'], self::VALID_ROLES, true)) {
                    throw new \InvalidArgumentException('Invalid role.');
                }
                if ($field === 'role' && $data['role'] === 'owner' && $oldRole !== 'owner') {
                    throw new \RuntimeException('Cannot set owner role directly. Use transferOwnership().');
                }
                // ...and the mirror: the owner's role cannot be taken away here
                // either. Without it an owner could demote themselves, leaving an
                // install with NO owner — the state D-031 contains and D-055 exists
                // to repair. transferOwnership() is the one supported path, and it
                // demotes the outgoing owner as part of promoting the incoming one,
                // so the "exactly one owner" invariant this class documents at the
                // top of the file holds at every point.
                if ($field === 'role' && $data['role'] !== 'owner' && $oldRole === 'owner') {
                    throw new \RuntimeException('Cannot remove the owner role directly. Use transferOwnership().');
                }
                if ($field === 'status' && !in_array($data['status'], ['active', 'suspended'], true)) {
                    throw new \InvalidArgumentException('Invalid status. Must be: active, suspended.');
                }
                // The owner cannot be suspended, mirroring delete()'s protection.
                // Without this the owner could suspend themselves out of an install
                // that owner:repair (D-055) ALSO refuses to help, because that
                // command refuses whenever an owner record exists — leaving the
                // install permanently unrecoverable through the product. Harmless
                // while only config['admin_user'] could log in; a live hazard from
                // the moment the record became the login authority (D-056).
                //
                // Compared against $oldRole, NOT $user['role']: 'role' is processed
                // BEFORE 'status' in $updatable and the loop mutates $user in place,
                // so update( $ownerId, [ 'role' => 'admin', 'status' => 'suspended' ] )
                // would have read 'admin' here and sailed past this guard — demoting
                // AND suspending the owner in one call. Found by this slice's own
                // code-reviewer; pinned by testTheOwnerCannotBeSuspendedByDemotingInTheSameCall.
                if ($field === 'status' && $data['status'] !== 'active' && $oldRole === 'owner') {
                    throw new \RuntimeException('Cannot suspend the owner. Transfer ownership first.');
                }

                $user[$field] = $data[$field];
            }
        }

        // Handle social_links as array merge.
        if ( array_key_exists( 'social_links', $data ) && is_array( $data['social_links'] ) ) {
            $user['social_links'] = array_merge( $user['social_links'] ?? [], $data['social_links'] );
        }

        // Allow plugins to add custom profile fields.
        $user = klytos_apply_filters( 'user.profile_fields', $user, $data, 'update' );

        // Recompute display_name when first/last name changes.
        if (array_key_exists('first_name', $data) || array_key_exists('last_name', $data)) {
            $computed = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            if ($computed !== '') {
                $user['display_name'] = $computed;
            }
        }

        $user['updated_at'] = Helpers::now();

        // Pre-hook: allow plugins to act before user update.
        klytos_do_action('user.before_update', $userId, $data, $this->sanitizeForOutput($user));

        $this->storage->write(self::COLLECTION, $userId, $user);

        klytos_do_action('user.updated', $this->sanitizeForOutput($user));

        // Fire role change hook only when the role actually changed.
        $newRole = $user['role'] ?? '';
        if ($newRole !== $oldRole && $oldRole !== '') {
            klytos_do_action('user.role_changed', $userId, $newRole, $oldRole);
        }

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

        // Pre-hook: allow plugins to act before user deletion.
        klytos_do_action('user.before_delete', $userId, $user);

        $result = $this->storage->delete(self::COLLECTION, $userId);

        if ($result) {
            klytos_do_action('user.deleted', $userId, $user['username']);
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

        $found = null;
        foreach ($users as $user) {
            if (($user['username'] ?? '') === $username) {
                $found = $user;
                break;
            }
        }

        // EVERY outcome pays one bcrypt verify, and that is a security property
        // rather than tidiness. The first version returned early for an unknown
        // username and for a non-active account, so only "the account exists and
        // is active" reached password_verify(). Measured on the seeded playground:
        // 218.98 ms against 0.65 ms — a 340x difference, trivially readable over a
        // network, and it turns the login form into an account-status oracle even
        // though the message, the status code and the lockout bucket are all
        // deliberately identical. Harmless while this method was only reachable
        // from an already-authenticated re-auth (admin/profile.php); a live
        // enumeration channel from the moment D-056 put it behind the public login
        // form. Found by this slice's own security-auditor pass and MEASURED
        // before being fixed.
        //
        // The comparison hash comes from a real record rather than a literal, so
        // the cost matches exactly, no bcrypt string is committed, and there is no
        // first-call-in-the-process outlier (which would invert the oracle under
        // php -S, where every request is a fresh process).
        $comparisonHash = $found['pass_hash'] ?? $this->anyPasswordHash($users);
        $verified       = is_string($comparisonHash) && $comparisonHash !== ''
            ? password_verify($password, $comparisonHash)
            : false;

        if ($found === null) {
            return null; // User not found.
        }

        // Check account status.
        if (($found['status'] ?? 'active') !== 'active') {
            return null; // Suspended accounts cannot log in.
        }

        if (! $verified) {
            return null; // Password mismatch.
        }

        // Update last login timestamp.
        $found['last_login'] = Helpers::now();
        $this->storage->write(self::COLLECTION, $found['id'], $found);

        klytos_do_action('user.login', $this->sanitizeForOutput($found));

        return $found; // Return full data (caller handles sanitization).
    }

    /**
     * Any stored password hash, used only to equalize authenticate()'s cost.
     *
     * Verifying a submitted password against another account's hash always fails
     * and discloses nothing — the point is solely that the bcrypt work happens.
     * An install with no users at all yields null and the equalization is skipped,
     * which is stated rather than hidden: there is no account to enumerate then.
     *
     * @param  array<int, array<string, mixed>> $users Raw user records.
     * @return string|null
     */
    private function anyPasswordHash(array $users): ?string
    {
        foreach ($users as $user) {
            $hash = $user['pass_hash'] ?? null;
            if (is_string($hash) && $hash !== '') {
                return $hash;
            }
        }

        return null;
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

    // ─── Password Reset Tokens ───────────────────────────────────

    /**
     * Generate a password reset token for a user.
     *
     * The raw token is returned (to be sent via email). Only the SHA-256
     * hash is stored, so a storage breach cannot be used to reset passwords.
     *
     * @param  string $userId User ID.
     * @return string Raw token (64 hex chars).
     */
    public function generatePasswordResetToken(string $userId): string
    {
        $user = $this->storage->read(self::COLLECTION, $userId);

        $rawToken = Helpers::randomHex(32);
        $user['password_reset_token']   = hash('sha256', $rawToken);
        $user['password_reset_expires'] = klytos_timestamp_to_datetime( time() + 3600 ); // 1 hour.
        $user['updated_at']             = Helpers::now();

        $this->storage->write(self::COLLECTION, $userId, $user);

        return $rawToken;
    }

    /**
     * Validate a password reset token.
     *
     * @param  string $userId User ID.
     * @param  string $token  Raw token from the reset URL.
     * @return bool   True if the token is valid and not expired.
     */
    public function validatePasswordResetToken(string $userId, string $token): bool
    {
        try {
            $user = $this->storage->read(self::COLLECTION, $userId);
        } catch (\RuntimeException $e) {
            return false;
        }

        $storedHash = $user['password_reset_token'] ?? '';
        $expires    = $user['password_reset_expires'] ?? '';

        if (empty($storedHash) || empty($expires)) {
            return false;
        }

        if (strtotime($expires) < time()) {
            return false;
        }

        return hash_equals($storedHash, hash('sha256', $token));
    }

    /**
     * Consume (clear) a password reset token after successful use.
     *
     * @param string $userId User ID.
     */
    public function consumePasswordResetToken(string $userId): void
    {
        $user = $this->storage->read(self::COLLECTION, $userId);
        $user['password_reset_token']   = null;
        $user['password_reset_expires'] = null;
        $user['updated_at']             = Helpers::now();

        $this->storage->write(self::COLLECTION, $userId, $user);
    }

    // ─── Session Invalidation ────────────────────────────────────

    /**
     * Force-logout all active sessions for a user.
     *
     * Sets a timestamp; any session started before this time will be
     * rejected by Auth::isAuthenticated().
     *
     * @param string $userId User ID.
     */
    public function forceLogoutAllSessions(string $userId): void
    {
        $user = $this->storage->read(self::COLLECTION, $userId);
        $user['force_logout_at'] = Helpers::now();
        $user['updated_at']      = Helpers::now();

        $this->storage->write(self::COLLECTION, $userId, $user);
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

        klytos_do_action('user.ownership_transferred', $currentOwnerId, $newOwnerId);

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
        // Fail closed on a record that carries no usable role. This used to
        // default to 'viewer', which still granted the viewer row of the matrix
        // (pages.view, analytics.view) to a malformed or partial user record.
        // Every record UserManager itself writes has a VALID_ROLES-checked role
        // (create(), migrateFromV1Config()), so nothing legitimate relies on the
        // default — and this method is now the single authorization decision
        // point in the product (S-04), which is exactly where guessing an
        // identity's privileges is least acceptable.
        $role = $user['role'] ?? null;

        if (!is_string($role) || $role === '') {
            return false;
        }

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
            'terminal.access'   => ['owner'],

            // ── Self-service tier (Sprint 1, slice 4) ──────────────
            // Held by EVERY role on purpose. The central admin gate
            // (S-07) default-denies any surface with no matching
            // capability, and five surfaces are legitimately reachable
            // by any authenticated user: their own profile, their own
            // second factor, and per-user UI state. Nothing in the
            // pre-existing matrix expressed that — users.manage is
            // owner-only and would have locked every non-owner out of
            // their own password.
            //
            // These are capabilities rather than a bare "is logged in"
            // marker so that ONE mechanism decides authorization
            // (S-04): they are introspectable, they are filterable
            // through auth.capabilities like every other row, and a
            // deployment can revoke one without patching the gate.
            //
            // Note the failure direction: a role added later does NOT
            // inherit these, so it is denied its own profile until the
            // matrix says otherwise. That is the correct direction for
            // a default-deny system, but it is a real upgrade note.
            'profile.edit'     => ['owner', 'admin', 'editor', 'viewer'],
            'security.self'    => ['owner', 'admin', 'editor', 'viewer'],
            'ui.preferences'   => ['owner', 'admin', 'editor', 'viewer'],

            // ── First-run setup (Sprint 1, slice 4) ────────────────
            // Owner-only. The setup wizard mints MCP application
            // passwords and stores AI provider keys; before slice 4 it
            // required authentication but checked no role, so on a
            // fresh install any authenticated user could complete it
            // and issue themselves credentials (NEW-10). Owner-only is
            // safe on a fresh install because the owner is the only
            // account that exists at that point.
            'setup.run'        => ['owner'],

            // ── AI chat (Sprint 1 slice 4; widened Sprint 2 slice 4) ──
            // Editor was excluded by D-035 for ONE reason: the chat
            // executes MCP tools and the tool layer had zero permission
            // checks (NEW-02), so reaching this surface was owner-
            // equivalent power whatever the caller's role. Sprint 2
            // closed that — every tools/call now passes the default-deny
            // gate in ToolRegistry::call() carrying the caller's OWN
            // role — so an editor in the chat can do exactly what an
            // editor may do, and no more. Widened per D-051, which
            // supersedes D-035 on the strength of its own recorded
            // trigger ("Sprint 2 close"). Viewer stays out: a read-only
            // role has no authoring work for an agent to do.
            'ai.use'           => ['owner', 'admin', 'editor'],
        ];

        // Allow plugins to extend capabilities.
        $capabilities = klytos_apply_filters('auth.capabilities', $capabilities);

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
        if (empty($email) || !Helpers::isEmail( $email )) {
            throw new \RuntimeException(
                'Cannot migrate: admin_email is missing or invalid in config. '
                . 'Please reinstall or add admin_email to the configuration.'
            );
        }

        $userId = Helpers::randomHex(8);

        $user = [
            'id'                     => $userId,
            'username'               => $config['admin_user'] ?? 'admin',
            'email'                  => $email,
            'first_name'             => '',
            'last_name'              => '',
            'display_name'           => $config['admin_user'] ?? 'Admin',
            'role'                   => 'owner',
            'pass_hash'              => $config['admin_pass_hash'] ?? '',
            'status'                 => 'active',
            'created_at'             => $config['installed_at'] ?? Helpers::now(),
            'updated_at'             => Helpers::now(),
            'last_login'             => null,
            'password_reset_token'   => null,
            'password_reset_expires' => null,
            'force_logout_at'        => null,
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
        unset($user['pass_hash'], $user['password_reset_token'], $user['password_reset_expires']);
        return $user;
    }
}
