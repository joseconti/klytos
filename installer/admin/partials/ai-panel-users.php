<?php
/**
 * Klytos — AI Panel: Users
 * User management content rendered inside the AI chat interface.
 *
 * @package Klytos
 * @since   0.9.0
 */

if (!isset($app)) { return; }

use Klytos\Core\UserManager;

$userManager    = new UserManager($app->getStorage());
$usersSuccess   = '';
$usersError     = '';
$csrf           = $app->getAuth()->getCsrfToken();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_panel_user_action']) && klytos_verify_csrf()) {
    $action = $_POST['ai_panel_user_action'];

    try {
        if ($action === 'create') {
            $userManager->create([
                'username'     => $_POST['username'] ?? '',
                'password'     => $_POST['password'] ?? '',
                'email'        => $_POST['email'] ?? '',
                'role'         => $_POST['role'] ?? 'editor',
                'display_name' => $_POST['display_name'] ?? '',
            ]);
            $usersSuccess = 'User created successfully.';
        } elseif ($action === 'suspend') {
            $userManager->update($_POST['user_id'] ?? '', ['status' => 'suspended']);
            $usersSuccess = 'User suspended.';
        } elseif ($action === 'activate') {
            $userManager->update($_POST['user_id'] ?? '', ['status' => 'active']);
            $usersSuccess = 'User activated.';
        } elseif ($action === 'update_user') {
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
            $usersSuccess = 'User updated successfully.';
        } elseif ($action === 'send_password_reset') {
            $targetUserId = $_POST['user_id'] ?? '';
            $targetUser   = $userManager->getById($targetUserId);
            $token        = $userManager->generatePasswordResetToken($targetUserId);
            $basePath2    = \Klytos\Core\Helpers::getBasePath();
            $resetUrl     = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                . $basePath2 . 'admin/reset-password.php?user_id=' . urlencode($targetUserId) . '&token=' . urlencode($token);
            $app->getMailer()->sendWithButton(
                $targetUser['email'],
                'Password Reset',
                'Click the button below to reset your password. This link expires in 1 hour.',
                'Reset Password',
                $resetUrl
            );
            $usersSuccess = 'Password reset link sent.';
        } elseif ($action === 'force_logout') {
            $userManager->forceLogoutAllSessions($_POST['user_id'] ?? '');
            $usersSuccess = 'All sessions closed for this user.';
        }
    } catch (\Throwable $e) {
        $usersError = $e->getMessage();
    }

    $csrf = $app->getAuth()->getCsrfToken();
}

$users = $userManager->list();
$panelUrl = klytos_esc_url($basePath . 'admin/ai-chat.php?panel=users');
?>

<div class="ai-chat-panel">
    <div class="ai-chat-panel-sidebar">
        <a href="<?php echo klytos_esc_url($basePath . 'admin/ai-chat.php'); ?>" class="ai-chat-panel-back">
            <i class="fa-solid fa-chevron-left"></i> <?php echo klytos_esc_html(__('ai_chat.chats')); ?>
        </a>
        <div class="ai-chat-panel-title"><?php echo klytos_esc_html(__('ai_chat.users')); ?></div>
    </div>
    <div class="ai-chat-panel-content">

        <?php if ($usersSuccess): ?>
            <div class="ai-panel-alert ai-panel-alert-success"><?php echo klytos_esc_html($usersSuccess); ?></div>
        <?php endif; ?>
        <?php if ($usersError): ?>
            <div class="ai-panel-alert ai-panel-alert-error"><?php echo klytos_esc_html($usersError); ?></div>
        <?php endif; ?>

        <div class="ai-panel-stats">
            <div class="ai-panel-stat">
                <div class="ai-panel-stat-label">Total</div>
                <div class="ai-panel-stat-value"><?php echo count($users); ?></div>
            </div>
            <div class="ai-panel-stat">
                <div class="ai-panel-stat-label">Admins</div>
                <div class="ai-panel-stat-value"><?php echo count(array_filter($users, fn($u) => ($u['role'] ?? '') === 'admin')); ?></div>
            </div>
            <div class="ai-panel-stat">
                <div class="ai-panel-stat-label">Editors</div>
                <div class="ai-panel-stat-value"><?php echo count(array_filter($users, fn($u) => ($u['role'] ?? '') === 'editor')); ?></div>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
            <button class="ai-panel-btn ai-panel-btn-primary" id="btnAiNewUser">+ New User</button>
        </div>

        <?php if (empty($users)): ?>
            <p style="color:var(--chat-text-muted);text-align:center;padding:2rem 0;">No users yet.</p>
        <?php else: ?>
            <table class="ai-panel-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <strong><?php echo klytos_esc_html($user['display_name'] ?? $user['username'] ?? ''); ?></strong>
                            <br><small style="color:var(--chat-text-dim)">@<?php echo klytos_esc_html($user['username'] ?? ''); ?></small>
                        </td>
                        <td><?php echo klytos_esc_html($user['email'] ?? ''); ?></td>
                        <td>
                            <span class="ai-panel-badge ai-panel-badge-<?php echo klytos_esc_attr($user['role'] ?? 'viewer'); ?>">
                                <?php echo ucfirst(klytos_esc_html($user['role'] ?? 'viewer')); ?>
                            </span>
                        </td>
                        <td>
                            <span class="ai-panel-badge ai-panel-badge-<?php echo ($user['status'] ?? 'active') === 'active' ? 'active' : 'inactive'; ?>">
                                <?php echo ucfirst(klytos_esc_html($user['status'] ?? 'active')); ?>
                            </span>
                        </td>
                        <td style="display:flex;gap:0.3rem;align-items:center;flex-wrap:wrap;">
                            <button type="button" class="ai-panel-btn ai-panel-btn-outline ai-panel-btn-sm btnAiEditUser"
                                data-user-id="<?php echo klytos_esc_attr($user['id'] ?? ''); ?>"
                                data-user-username="<?php echo klytos_esc_attr($user['username'] ?? ''); ?>"
                                data-user-first-name="<?php echo klytos_esc_attr($user['first_name'] ?? ''); ?>"
                                data-user-last-name="<?php echo klytos_esc_attr($user['last_name'] ?? ''); ?>"
                                data-user-email="<?php echo klytos_esc_attr($user['email'] ?? ''); ?>"
                                data-user-role="<?php echo klytos_esc_attr($user['role'] ?? 'viewer'); ?>"
                            >Edit</button>
                            <?php if (($user['role'] ?? '') !== 'owner'): ?>
                                <?php if (($user['status'] ?? 'active') === 'active'): ?>
                                    <form method="post" action="<?php echo $panelUrl; ?>" style="display:inline">
                                        <?php echo klytos_csrf_field(); ?>
                                        <input type="hidden" name="ai_panel_user_action" value="suspend">
                                        <input type="hidden" name="user_id" value="<?php echo klytos_esc_attr($user['id'] ?? ''); ?>">
                                        <button type="submit" class="ai-panel-btn ai-panel-btn-outline ai-panel-btn-sm" onclick="return confirm('Are you sure?')">Suspend</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?php echo $panelUrl; ?>" style="display:inline">
                                        <?php echo klytos_csrf_field(); ?>
                                        <input type="hidden" name="ai_panel_user_action" value="activate">
                                        <input type="hidden" name="user_id" value="<?php echo klytos_esc_attr($user['id'] ?? ''); ?>">
                                        <button type="submit" class="ai-panel-btn ai-panel-btn-primary ai-panel-btn-sm">Activate</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Create User Modal -->
        <div class="ai-panel-modal-overlay" id="aiCreateUserModal">
            <div class="ai-panel-modal">
                <h3>Create New User</h3>
                <form method="post" action="<?php echo $panelUrl; ?>">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="ai_panel_user_action" value="create">
                    <div class="ai-panel-grid-2">
                        <div class="ai-panel-form-group">
                            <label>Username</label>
                            <input type="text" name="username" class="ai-panel-form-control" required pattern="[a-zA-Z0-9_\-]{3,50}" placeholder="john_doe">
                        </div>
                        <div class="ai-panel-form-group">
                            <label>Display Name</label>
                            <input type="text" name="display_name" class="ai-panel-form-control" placeholder="John Doe">
                        </div>
                    </div>
                    <div class="ai-panel-form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="ai-panel-form-control" required placeholder="john@example.com">
                    </div>
                    <div class="ai-panel-form-group">
                        <label>Password (min 12 characters)</label>
                        <input type="password" name="password" class="ai-panel-form-control" required minlength="12" data-klytos-pwgen data-klytos-pwgen-style="ai-panel">
                    </div>
                    <div class="ai-panel-form-group">
                        <label>Role</label>
                        <select name="role" class="ai-panel-form-control">
                            <option value="editor">Editor</option>
                            <option value="admin">Admin</option>
                            <option value="viewer">Viewer</option>
                        </select>
                    </div>
                    <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem">
                        <button type="button" class="ai-panel-btn ai-panel-btn-outline" id="btnAiCancelUser">Cancel</button>
                        <button type="submit" class="ai-panel-btn ai-panel-btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<!-- Edit User Modal -->
        <div class="ai-panel-modal-overlay" id="aiEditUserModal">
            <div class="ai-panel-modal">
                <h3>Edit User</h3>
                <form method="post" action="<?php echo $panelUrl; ?>">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="ai_panel_user_action" value="update_user">
                    <input type="hidden" name="user_id" id="aiEditUserId">

                    <div class="ai-panel-form-group">
                        <label>Username</label>
                        <input type="text" class="ai-panel-form-control" id="aiEditUsername" disabled>
                    </div>
                    <div class="ai-panel-grid-2">
                        <div class="ai-panel-form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" class="ai-panel-form-control" id="aiEditFirstName">
                        </div>
                        <div class="ai-panel-form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" class="ai-panel-form-control" id="aiEditLastName">
                        </div>
                    </div>
                    <div class="ai-panel-form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="ai-panel-form-control" id="aiEditEmail" required>
                    </div>
                    <div class="ai-panel-form-group">
                        <label>Role</label>
                        <select name="role" class="ai-panel-form-control" id="aiEditRole">
                            <option value="editor">Editor</option>
                            <option value="admin">Admin</option>
                            <option value="viewer">Viewer</option>
                        </select>
                    </div>
                    <div class="ai-panel-form-group">
                        <label>New Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="ai-panel-form-control" minlength="12" data-klytos-pwgen data-klytos-pwgen-style="ai-panel">
                    </div>
                    <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem">
                        <button type="button" class="ai-panel-btn ai-panel-btn-outline" id="btnAiCancelEdit">Cancel</button>
                        <button type="submit" class="ai-panel-btn ai-panel-btn-primary">Save Changes</button>
                    </div>
                </form>
                <hr style="margin:1rem 0;border-color:var(--chat-border)">
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                    <form method="post" action="<?php echo $panelUrl; ?>" style="display:inline">
                        <?php echo klytos_csrf_field(); ?>
                        <input type="hidden" name="ai_panel_user_action" value="send_password_reset">
                        <input type="hidden" name="user_id" class="aiEditModalUserId">
                        <button type="submit" class="ai-panel-btn ai-panel-btn-outline ai-panel-btn-sm" onclick="return confirm('Send password reset link?')">Send Reset Link</button>
                    </form>
                    <form method="post" action="<?php echo $panelUrl; ?>" style="display:inline">
                        <?php echo klytos_csrf_field(); ?>
                        <input type="hidden" name="ai_panel_user_action" value="force_logout">
                        <input type="hidden" name="user_id" class="aiEditModalUserId">
                        <button type="submit" class="ai-panel-btn ai-panel-btn-outline ai-panel-btn-sm" onclick="return confirm('Close all active sessions?')">Close All Sessions</button>
                    </form>
                </div>
            </div>
        </div>

<script nonce="<?php echo $cspNonce; ?>">
(function() {
    var modal = document.getElementById('aiCreateUserModal');
    var btnOpen = document.getElementById('btnAiNewUser');
    var btnCancel = document.getElementById('btnAiCancelUser');
    if (btnOpen) btnOpen.addEventListener('click', function() { modal.classList.add('active'); });
    if (btnCancel) btnCancel.addEventListener('click', function() { modal.classList.remove('active'); });
    if (modal) modal.addEventListener('click', function(e) { if (e.target === modal) modal.classList.remove('active'); });

    // Edit modal
    var editModal = document.getElementById('aiEditUserModal');
    var btnCancelEdit = document.getElementById('btnAiCancelEdit');

    document.querySelectorAll('.btnAiEditUser').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('aiEditUserId').value    = btn.dataset.userId;
            document.getElementById('aiEditUsername').value   = btn.dataset.userUsername;
            document.getElementById('aiEditFirstName').value = btn.dataset.userFirstName;
            document.getElementById('aiEditLastName').value  = btn.dataset.userLastName;
            document.getElementById('aiEditEmail').value     = btn.dataset.userEmail;

            var roleSelect = document.getElementById('aiEditRole');
            if (btn.dataset.userRole === 'owner') {
                roleSelect.disabled = true;
                roleSelect.innerHTML = '<option value="owner" selected>Owner</option>';
            } else {
                roleSelect.disabled = false;
                roleSelect.innerHTML = '<option value="editor">Editor</option><option value="admin">Admin</option><option value="viewer">Viewer</option>';
                roleSelect.value = btn.dataset.userRole;
            }

            document.querySelectorAll('.aiEditModalUserId').forEach(function(input) {
                input.value = btn.dataset.userId;
            });

            editModal.classList.add('active');
        });
    });

    if (btnCancelEdit) btnCancelEdit.addEventListener('click', function() { editModal.classList.remove('active'); });
    if (editModal) editModal.addEventListener('click', function(e) { if (e.target === editModal) editModal.classList.remove('active'); });
})();
</script>
