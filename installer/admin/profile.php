<?php
/**
 * Klytos Admin — User Profile
 * Self-edit profile page for the currently logged-in user.
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

$pageTitle   = 'My Profile';
$auth        = $app->getAuth();
$userManager = new UserManager($app->getStorage());
$success     = '';
$error       = '';
$csrf        = $auth->getCsrfToken();

// Get current user data.
$userId = $auth->getUserId();
if (!$userId) {
    Helpers::redirect(Helpers::url('admin/'));
}

$user = $userManager->getById($userId);

// ─── Handle POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf()) {
    $currentPassword = $_POST['current_password'] ?? '';

    // Verify current password before allowing changes.
    $authResult = $userManager->authenticate($user['username'], $currentPassword);
    if ($authResult === null) {
        $error = 'Current password is incorrect.';
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

            $success = 'Profile updated successfully.';
            $user    = $userManager->getById($userId);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    }

    $csrf = $auth->getCsrfToken();
}

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html($success); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html($error); ?></div>
<?php endif; ?>

<div class="card" style="max-width:600px;">
    <form method="post">
        <?php echo klytos_csrf_field(); ?>

        <div class="form-group">
            <label>Username</label>
            <input type="text" class="form-control" value="<?php echo klytos_esc_attr($user['username'] ?? ''); ?>" disabled>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name" class="form-control" value="<?php echo klytos_esc_attr($user['first_name'] ?? ''); ?>" placeholder="John">
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name" class="form-control" value="<?php echo klytos_esc_attr($user['last_name'] ?? ''); ?>" placeholder="Doe">
            </div>
        </div>

        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?php echo klytos_esc_attr($user['email'] ?? ''); ?>" required>
        </div>

        <hr style="margin:1.5rem 0;border-color:var(--admin-border)">

        <div class="form-group">
            <label>Current Password (required to save changes)</label>
            <input type="password" name="current_password" class="form-control" required>
        </div>

        <div class="form-group">
            <label>New Password (leave blank to keep current)</label>
            <input type="password" name="new_password" class="form-control" minlength="12" data-klytos-pwgen>
        </div>

        <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem">
            <button type="submit" class="btn btn-primary">Save Profile</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
