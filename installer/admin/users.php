<?php
/**
 * Klytos Admin — User Management
 * List, create, edit, and manage users with role-based access control.
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

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\Helpers;
use Klytos\Core\UserManager;

$pageTitle   = __( 'common.name' ) . ' — Users';
$auth        = $app->getAuth();
$userManager = new UserManager($app->getStorage());
$success     = '';
$error       = '';
$csrf        = $auth->getCsrfToken();

// ─── Handle POST actions ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        try {
            $userManager->create([
                'username'     => $_POST['username'] ?? '',
                'password'     => $_POST['password'] ?? '',
                'email'        => $_POST['email'] ?? '',
                'role'         => $_POST['role'] ?? 'editor',
                'display_name' => $_POST['display_name'] ?? '',
            ]);
            $success = 'User created successfully.';
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'update_role') {
        try {
            $userManager->update($_POST['user_id'] ?? '', ['role' => $_POST['role'] ?? '']);
            $success = 'User role updated.';
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'suspend') {
        try {
            $userManager->update($_POST['user_id'] ?? '', ['status' => 'suspended']);
            $success = 'User suspended.';
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'activate') {
        try {
            $userManager->update($_POST['user_id'] ?? '', ['status' => 'active']);
            $success = 'User activated.';
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'update_user') {
        try {
            $updateData = [
                'first_name' => $_POST['first_name'] ?? '',
                'last_name'  => $_POST['last_name'] ?? '',
                'email'      => $_POST['email'] ?? '',
                'role'       => $_POST['role'] ?? '',
            ];
            $userManager->update($_POST['user_id'] ?? '', $updateData);
            $newPassword = $_POST['password'] ?? '';
            if (!empty($newPassword)) {
                $userManager->changePassword($_POST['user_id'] ?? '', $newPassword);
            }
            $success = 'User updated successfully.';
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'send_password_reset') {
        try {
            $targetUserId = $_POST['user_id'] ?? '';
            $targetUser   = $userManager->getById($targetUserId);
            $token        = $userManager->generatePasswordResetToken($targetUserId);
            $basePath     = \Klytos\Core\Helpers::getBasePath();
            $resetUrl     = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                . $basePath . 'admin/reset-password.php?user_id=' . urlencode($targetUserId) . '&token=' . urlencode($token);
            $app->getMailer()->sendWithButton(
                $targetUser['email'],
                'Password Reset',
                'Click the button below to reset your password. This link expires in 1 hour.',
                'Reset Password',
                $resetUrl
            );
            $success = 'Password reset link sent to ' . ($targetUser['email'] ?? '') . '.';
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'force_logout') {
        try {
            $userManager->forceLogoutAllSessions($_POST['user_id'] ?? '');
            $success = 'All sessions closed for this user.';
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    }

    // Refresh CSRF after action.
    $csrf = $auth->getCsrfToken();
}

// ─── Load data ───────────────────────────────────────────────
$users     = $userManager->list();
$roleFilter = $_GET['role'] ?? 'all';

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Users</div>
        <div class="stat-value"><?php echo count( $users); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Admins</div>
        <div class="stat-value"><?php echo count( array_filter($users, fn($u) => ($u['role'] ?? '') === 'admin')); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Editors</div>
        <div class="stat-value"><?php echo count( array_filter($users, fn($u) => ($u['role'] ?? '') === 'editor')); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Viewers</div>
        <div class="stat-value"><?php echo count( array_filter($users, fn($u) => ($u['role'] ?? '') === 'viewer')); ?></div>
    </div>
</div>

<!-- Action bar -->
<div class="action-bar">
    <div class="filters">
        <a href="?role=all" class="tab <?php echo $roleFilter === 'all' ? 'active' : ''; ?>">All</a>
        <a href="?role=owner" class="tab <?php echo $roleFilter === 'owner' ? 'active' : ''; ?>">Owner</a>
        <a href="?role=admin" class="tab <?php echo $roleFilter === 'admin' ? 'active' : ''; ?>">Admin</a>
        <a href="?role=editor" class="tab <?php echo $roleFilter === 'editor' ? 'active' : ''; ?>">Editor</a>
        <a href="?role=viewer" class="tab <?php echo $roleFilter === 'viewer' ? 'active' : ''; ?>">Viewer</a>
    </div>
    <button class="btn btn-primary" id="btnNewUser">
        + New User
    </button>
</div>

<!-- Users table -->
<div class="card">
    <?php if (empty($users)): ?>
        <div class="empty-state">
            <h3>No users yet</h3>
            <p>The owner account will be created from the v1.0 admin credentials.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):
                        if ($roleFilter !== 'all' && ($user['role'] ?? '') !== $roleFilter) continue;
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo klytos_esc_html( $user['display_name'] ?? $user['username'] ?? ''); ?></strong>
                            <br><small style="color:var(--admin-text-muted)">@<?php echo klytos_esc_html( $user['username'] ?? ''); ?></small>
                        </td>
                        <td><?php echo klytos_esc_html( $user['email'] ?? ''); ?></td>
                        <td>
                            <span class="badge-status badge-<?php echo klytos_esc_attr( $user['role'] ?? 'viewer'); ?>">
                                <?php echo ucfirst( klytos_esc_html( $user['role'] ?? 'viewer')); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-status badge-<?php echo ($user['status'] ?? 'active') === 'active' ? 'active' : 'inactive'; ?>">
                                <?php echo ucfirst( klytos_esc_html( $user['status'] ?? 'active')); ?>
                            </span>
                        </td>
                        <td style="font-size:0.85rem;color:var(--admin-text-muted)">
                            <?php echo $user['last_login'] ? date( 'M j, Y H:i', strtotime($user['last_login'])) : '—'; ?>
                        </td>
                        <td style="display:flex;gap:0.3rem;align-items:center;flex-wrap:wrap;">
                            <button type="button" class="btn btn-outline btn-sm btnEditUser"
                                data-user-id="<?php echo klytos_esc_attr( $user['id'] ?? ''); ?>"
                                data-user-username="<?php echo klytos_esc_attr( $user['username'] ?? ''); ?>"
                                data-user-first-name="<?php echo klytos_esc_attr( $user['first_name'] ?? ''); ?>"
                                data-user-last-name="<?php echo klytos_esc_attr( $user['last_name'] ?? ''); ?>"
                                data-user-email="<?php echo klytos_esc_attr( $user['email'] ?? ''); ?>"
                                data-user-role="<?php echo klytos_esc_attr( $user['role'] ?? 'viewer'); ?>"
                            >Edit</button>
                            <?php if (($user['role'] ?? '') !== 'owner'): ?>
                                <?php if (($user['status'] ?? 'active') === 'active'): ?>
                                    <form method="post" style="display:inline">
                                        <?php echo klytos_csrf_field(); ?>
                                        <input type="hidden" name="action" value="suspend">
                                        <input type="hidden" name="user_id" value="<?php echo klytos_esc_attr( $user['id'] ?? ''); ?>">
                                        <button type="submit" class="btn btn-outline btn-sm">Suspend</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display:inline">
                                        <?php echo klytos_csrf_field(); ?>
                                        <input type="hidden" name="action" value="activate">
                                        <input type="hidden" name="user_id" value="<?php echo klytos_esc_attr( $user['id'] ?? ''); ?>">
                                        <button type="submit" class="btn btn-primary btn-sm">Activate</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Create User Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal">
        <h3>Create New User</h3>
        <form method="post">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="action" value="create">

            <div class="grid-2">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required pattern="[a-zA-Z0-9_\-]{3,50}" placeholder="john_doe">
                </div>
                <div class="form-group">
                    <label>Display Name</label>
                    <input type="text" name="display_name" class="form-control" placeholder="John Doe">
                </div>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required placeholder="john@example.com">
            </div>

            <div class="form-group">
                <label>Password (min 12 characters)</label>
                <input type="password" name="password" class="form-control" required minlength="12" data-klytos-pwgen>
            </div>

            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control">
                    <option value="editor">Editor — Can create and edit pages</option>
                    <option value="admin">Admin — Full content and settings control</option>
                    <option value="viewer">Viewer — Read-only access</option>
                </select>
            </div>

            <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem">
                <button type="button" class="btn btn-outline" id="btnCancelUser">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <h3>Edit User</h3>
        <form method="post">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" id="editUserId">

            <div class="form-group">
                <label>Username</label>
                <input type="text" class="form-control" id="editUsername" disabled>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" class="form-control" id="editFirstName" placeholder="John">
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" class="form-control" id="editLastName" placeholder="Doe">
                </div>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" id="editEmail" required>
            </div>

            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control" id="editRole">
                    <option value="editor">Editor</option>
                    <option value="admin">Admin</option>
                    <option value="viewer">Viewer</option>
                </select>
            </div>

            <div class="form-group">
                <label>New Password (leave blank to keep current)</label>
                <input type="password" name="password" class="form-control" minlength="12" data-klytos-pwgen>
            </div>

            <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem">
                <button type="button" class="btn btn-outline" id="btnCancelEdit">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>

        <hr style="margin:1rem 0;border-color:var(--admin-border)">

        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <form method="post" style="display:inline">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="action" value="send_password_reset">
                <input type="hidden" name="user_id" class="editModalUserId">
                <button type="submit" class="btn btn-outline btn-sm" onclick="return confirm('Send password reset link?')">Send Reset Link</button>
            </form>
            <form method="post" style="display:inline">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="action" value="force_logout">
                <input type="hidden" name="user_id" class="editModalUserId">
                <button type="submit" class="btn btn-outline btn-sm" onclick="return confirm('Close all active sessions for this user?')">Close All Sessions</button>
            </form>
        </div>
    </div>
</div>

<script nonce="<?php echo $cspNonce; ?>">
(function() {
    var modal     = document.getElementById( 'createModal' );
    var btnOpen   = document.getElementById( 'btnNewUser' );
    var btnCancel = document.getElementById( 'btnCancelUser' );

    if ( btnOpen ) {
        btnOpen.addEventListener( 'click', function() {
            modal.classList.add( 'active' );
        });
    }
    if ( btnCancel ) {
        btnCancel.addEventListener( 'click', function() {
            modal.classList.remove( 'active' );
        });
    }
    modal.addEventListener( 'click', function( e ) {
        if ( e.target === modal ) modal.classList.remove( 'active' );
    });

    // Edit modal
    var editModal     = document.getElementById( 'editModal' );
    var btnCancelEdit = document.getElementById( 'btnCancelEdit' );

    document.querySelectorAll( '.btnEditUser' ).forEach( function( btn ) {
        btn.addEventListener( 'click', function() {
            document.getElementById( 'editUserId' ).value     = btn.dataset.userId;
            document.getElementById( 'editUsername' ).value    = btn.dataset.userUsername;
            document.getElementById( 'editFirstName' ).value  = btn.dataset.userFirstName;
            document.getElementById( 'editLastName' ).value   = btn.dataset.userLastName;
            document.getElementById( 'editEmail' ).value      = btn.dataset.userEmail;
            document.getElementById( 'editRole' ).value       = btn.dataset.userRole;

            // Disable role for owner
            var roleSelect = document.getElementById( 'editRole' );
            if ( btn.dataset.userRole === 'owner' ) {
                roleSelect.disabled = true;
                roleSelect.innerHTML = '<option value="owner" selected>Owner</option>';
            } else {
                roleSelect.disabled = false;
                roleSelect.innerHTML = '<option value="editor">Editor</option><option value="admin">Admin</option><option value="viewer">Viewer</option>';
                roleSelect.value = btn.dataset.userRole;
            }

            // Set user_id on extra action forms
            document.querySelectorAll( '.editModalUserId' ).forEach( function( input ) {
                input.value = btn.dataset.userId;
            });

            editModal.classList.add( 'active' );
        });
    });

    if ( btnCancelEdit ) {
        btnCancelEdit.addEventListener( 'click', function() {
            editModal.classList.remove( 'active' );
        });
    }
    editModal.addEventListener( 'click', function( e ) {
        if ( e.target === editModal ) editModal.classList.remove( 'active' );
    });

    // Confirm dialogs for delete/suspend buttons.
    document.querySelectorAll( 'button[type="submit"]' ).forEach( function( btn ) {
        var form = btn.closest( 'form' );
        if ( !form ) return;
        var action = form.querySelector( 'input[name="action"]' );
        if ( action && ( action.value === 'suspend' || action.value === 'delete' ) ) {
            btn.addEventListener( 'click', function( e ) {
                if ( !confirm( 'Are you sure?' ) ) e.preventDefault();
            });
        }
    });
})();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
