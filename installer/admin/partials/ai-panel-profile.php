<?php

/**
 * Klytos — AI Panel: Profile
 * Self-edit profile content rendered inside the AI chat interface.
 *
 * @package Klytos
 * @since   1.0.0
 */

if (!isset($app)) {
    return;
}

use Klytos\Core\UserManager;

$userManager    = new UserManager($app->getStorage());
$profileSuccess = '';
$profileError   = '';
$csrf           = $app->getAuth()->getCsrfToken();

$userId = $app->getAuth()->getUserId();
if (!$userId) {
    return;
}

$user = $userManager->getById($userId);
$profileUrl = klytos_esc_url($basePath . 'admin/ai-chat.php?panel=profile');

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_panel_profile_action']) && klytos_verify_csrf()) {
    $currentPassword = $_POST['current_password'] ?? '';
    $authResult = $userManager->authenticate($user['username'], $currentPassword);

    if ($authResult === null) {
        $profileError = 'Current password is incorrect.';
    } else {
        try {
            $updateData = [
                'first_name' => $_POST['first_name'] ?? '',
                'last_name'  => $_POST['last_name'] ?? '',
                'email'      => $_POST['email'] ?? '',
            ];
            $userManager->update($userId, $updateData);

            $newPassword = $_POST['new_password'] ?? '';
            if (!empty($newPassword)) {
                $userManager->changePassword($userId, $newPassword);
            }

            $profileSuccess = 'Profile updated successfully.';
            $user = $userManager->getById($userId);
        } catch (\Throwable $e) {
            $profileError = $e->getMessage();
        }
    }

    $csrf = $app->getAuth()->getCsrfToken();
}
?>

<div class="ai-chat-panel">
    <div class="ai-chat-panel-sidebar">
        <a href="<?php echo klytos_esc_url($basePath . 'admin/ai-chat.php'); ?>" class="ai-chat-panel-back">
            <i class="fa-solid fa-chevron-left"></i> <?php echo klytos_esc_html(__('ai_chat.chats')); ?>
        </a>
        <div class="ai-chat-panel-title">My Profile</div>
    </div>
    <div class="ai-chat-panel-content">

        <?php if ($profileSuccess): ?>
            <div class="ai-panel-alert ai-panel-alert-success"><?php echo klytos_esc_html($profileSuccess); ?></div>
        <?php endif; ?>
        <?php if ($profileError): ?>
            <div class="ai-panel-alert ai-panel-alert-error"><?php echo klytos_esc_html($profileError); ?></div>
        <?php endif; ?>

        <form method="post" action="<?php echo $profileUrl; ?>">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="ai_panel_profile_action" value="update">

            <div class="ai-panel-form-group">
                <label>Username</label>
                <input type="text" class="ai-panel-form-control" value="<?php echo klytos_esc_attr($user['username'] ?? ''); ?>" disabled>
            </div>

            <div class="ai-panel-grid-2">
                <div class="ai-panel-form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" class="ai-panel-form-control" value="<?php echo klytos_esc_attr($user['first_name'] ?? ''); ?>">
                </div>
                <div class="ai-panel-form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" class="ai-panel-form-control" value="<?php echo klytos_esc_attr($user['last_name'] ?? ''); ?>">
                </div>
            </div>

            <div class="ai-panel-form-group">
                <label>Email</label>
                <input type="email" name="email" class="ai-panel-form-control" value="<?php echo klytos_esc_attr($user['email'] ?? ''); ?>" required>
            </div>

            <hr class="divider">

            <div class="ai-panel-form-group">
                <label>Current Password (required to save changes)</label>
                <input type="password" name="current_password" class="ai-panel-form-control" required>
            </div>

            <div class="ai-panel-form-group">
                <label>New Password (leave blank to keep current)</label>
                <input type="password" name="new_password" class="ai-panel-form-control" minlength="12" data-klytos-pwgen data-klytos-pwgen-style="ai-panel">
            </div>

            <div class="flex flex-gap-sm flex-end mt-2">
                <button type="submit" class="ai-panel-btn ai-panel-btn-primary">Save Profile</button>
            </div>
        </form>

    </div>
</div>
