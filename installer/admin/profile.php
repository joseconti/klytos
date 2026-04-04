<?php

/**
 * Klytos Admin — User Profile
 * Self-edit profile page for the currently logged-in user.
 *
 * @package Klytos
 * @since   1.0.0
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
                'first_name'   => $_POST['first_name'] ?? '',
                'last_name'    => $_POST['last_name'] ?? '',
                'email'        => $_POST['email'] ?? '',
                'bio'          => substr( trim( $_POST['bio'] ?? '' ), 0, 500 ),
                'avatar'       => trim( $_POST['avatar'] ?? '' ),
                'website'      => trim( $_POST['website'] ?? '' ),
                'locale'       => trim( $_POST['locale'] ?? '' ),
                'social_links' => [
                    'twitter'  => trim( $_POST['social_twitter'] ?? '' ),
                    'linkedin' => trim( $_POST['social_linkedin'] ?? '' ),
                    'github'   => trim( $_POST['social_github'] ?? '' ),
                    'mastodon' => trim( $_POST['social_mastodon'] ?? '' ),
                ],
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
<?php klytos_do_action( 'admin.profile.before' ); ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html($success); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html($error); ?></div>
<?php endif; ?>

<div class="card" style="max-width:600px;">
    <form method="post">
        <?php echo klytos_csrf_field(); ?>
        <?php klytos_do_action( 'admin.profile.before_fields', $user ); ?>

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

        <div class="form-group">
            <label><?php echo __( 'profile.bio' ); ?></label>
            <textarea name="bio" class="form-control" rows="3" maxlength="500" placeholder="<?php echo klytos_esc_attr( __( 'profile.bio_placeholder' ) ); ?>"><?php echo klytos_esc_html( $user['bio'] ?? '' ); ?></textarea>
        </div>

        <div class="form-group">
            <label><?php echo __( 'profile.avatar' ); ?></label>
            <div class="flex flex-gap-sm flex-center">
                <?php $avatarUrl = klytos_get_avatar_url( $user, 64 ); ?>
                <img src="<?php echo klytos_esc_url( $avatarUrl ); ?>" alt="Avatar" width="64" height="64"
                    style="border-radius:50%;border:2px solid var(--klytos-border);object-fit:cover"
                    id="avatar-preview">
                <div class="flex-col flex-gap-xs" style="flex:1">
                    <input type="url" name="avatar" class="form-control" id="avatar-input"
                        value="<?php echo klytos_esc_attr( $user['avatar'] ?? '' ); ?>"
                        placeholder="<?php echo klytos_esc_attr( __( 'profile.avatar_placeholder' ) ); ?>">
                    <small class="text-muted">
                        <?php echo __( 'profile.avatar_gravatar_hint' ); ?>
                    </small>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label><?php echo __( 'profile.website' ); ?></label>
            <input type="url" name="website" class="form-control" value="<?php echo klytos_esc_attr( $user['website'] ?? '' ); ?>" placeholder="https://...">
        </div>

        <div class="form-group">
            <label><?php echo __( 'profile.locale' ); ?></label>
            <select name="locale" class="form-control">
                <option value=""><?php echo __( 'common.default' ); ?></option>
                <option value="en" <?php echo ($user['locale'] ?? '') === 'en' ? 'selected' : ''; ?>>English</option>
                <option value="es" <?php echo ($user['locale'] ?? '') === 'es' ? 'selected' : ''; ?>>Español</option>
                <?php $languages = $app->getSiteConfig()->getValue( 'languages', [] );
                foreach ( $languages as $lang ) :
                    $code = $lang['code'] ?? '';
                    $name = $lang['name'] ?? $code;
                    if ( in_array( $code, ['en', 'es'], true ) ) continue; ?>
                    <option value="<?php echo klytos_esc_attr( $code ); ?>" <?php echo ($user['locale'] ?? '') === $code ? 'selected' : ''; ?>><?php echo klytos_esc_html( $name ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <details class="mb-md">
            <summary class="font-bold" style="cursor:pointer"><?php echo __( 'profile.social_links' ); ?></summary>
            <div class="grid-2 mt-sm">
                <div class="form-group">
                    <label>Twitter / X</label>
                    <input type="url" name="social_twitter" class="form-control" value="<?php echo klytos_esc_attr( $user['social_links']['twitter'] ?? '' ); ?>" placeholder="https://twitter.com/...">
                </div>
                <div class="form-group">
                    <label>LinkedIn</label>
                    <input type="url" name="social_linkedin" class="form-control" value="<?php echo klytos_esc_attr( $user['social_links']['linkedin'] ?? '' ); ?>" placeholder="https://linkedin.com/in/...">
                </div>
                <div class="form-group">
                    <label>GitHub</label>
                    <input type="url" name="social_github" class="form-control" value="<?php echo klytos_esc_attr( $user['social_links']['github'] ?? '' ); ?>" placeholder="https://github.com/...">
                </div>
                <div class="form-group">
                    <label>Mastodon</label>
                    <input type="url" name="social_mastodon" class="form-control" value="<?php echo klytos_esc_attr( $user['social_links']['mastodon'] ?? '' ); ?>" placeholder="https://mastodon.social/@...">
                </div>
            </div>
        </details>

        <?php klytos_do_action( 'admin.profile.custom_fields', $user ); ?>

        <hr class="divider">

        <div class="form-group">
            <label>Current Password (required to save changes)</label>
            <input type="password" name="current_password" class="form-control" required>
        </div>

        <div class="form-group">
            <label>New Password (leave blank to keep current)</label>
            <input type="password" name="new_password" class="form-control" minlength="12" data-klytos-pwgen>
        </div>

        <?php klytos_do_action( 'admin.profile.after_fields', $user ); ?>
        <div class="flex flex-gap-sm flex-end mt-2">
            <button type="submit" class="btn btn-primary">Save Profile</button>
        </div>
    </form>
</div>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
(function() {
    var input   = document.getElementById('avatar-input');
    var preview = document.getElementById('avatar-preview');
    var gravUrl = <?php echo json_encode( klytos_gravatar_url( $user['email'] ?? '', 64 ) ); ?>;

    if (!input || !preview) return;

    // Live-update preview when the URL changes.
    input.addEventListener('input', function() {
        var val = input.value.trim();
        preview.src = val !== '' ? val : gravUrl;
    });

    // Handle image load errors — fall back to Gravatar.
    preview.addEventListener('error', function() {
        preview.src = gravUrl;
    });
})();
</script>

<?php klytos_do_action( 'admin.profile.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
