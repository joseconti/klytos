<?php
/**
 * Klytos — AI Panel: Users
 * User management content rendered inside the AI chat interface.
 *
 * @package Klytos
 * @since   0.9.0
 */

defined('KLYTOS_LOADED') || die();

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
                        <td>
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
                            <?php else: ?>
                                <span style="font-size:0.8rem;color:var(--chat-text-dim)">Owner</span>
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
                        <input type="password" name="password" class="ai-panel-form-control" required minlength="12">
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

<script nonce="<?php echo $cspNonce; ?>">
(function() {
    var modal = document.getElementById('aiCreateUserModal');
    var btnOpen = document.getElementById('btnAiNewUser');
    var btnCancel = document.getElementById('btnAiCancelUser');
    if (btnOpen) btnOpen.addEventListener('click', function() { modal.classList.add('active'); });
    if (btnCancel) btnCancel.addEventListener('click', function() { modal.classList.remove('active'); });
    if (modal) modal.addEventListener('click', function(e) { if (e.target === modal) modal.classList.remove('active'); });
})();
</script>
