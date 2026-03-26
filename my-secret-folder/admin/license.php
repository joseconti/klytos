<?php
/**
 * Klytos Admin — License Management
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\Helpers;

$pageTitle = __( 'license.title' );
$auth      = $app->getAuth();
$license   = $app->getLicense();
$success   = '';
$error     = '';

// Handle activation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->validateCsrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'activate') {
        $licenseKey = trim($_POST['license_key'] ?? '');
        $siteUrl    = Helpers::siteUrl('');

        if (empty($licenseKey)) {
            $error = __( 'license.key' ) . ' is required.';
        } else {
            $result = $license->activate($licenseKey, $siteUrl);
            if ($result['success']) {
                $success = __( 'license.active' );
            } else {
                $error = $result['error'];
            }
        }
    } elseif ($action === 'verify') {
        $result = $license->verify();
        if ($result['success']) {
            $success = __( 'common.success' );
        } else {
            $error = $result['error'] ?? __( 'common.error' );
        }
    }
}

$status = $license->getStatus();
$csrf   = $auth->getCsrfToken();

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars( $success ); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars( $error ); ?></div>
<?php endif; ?>

<!-- License Status -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'license.status' ); ?></h3></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
        <div class="form-group">
            <label><?php echo __( 'license.status' ); ?></label>
            <?php
            $licenseStatus = $status['license_status'] ?? 'missing';
            $statusClass   = match($licenseStatus) {
                'valid'   => 'alert-success',
                'revoked' => 'alert-warning',
                default   => 'alert-error',
            };
            $statusLabel = match($licenseStatus) {
                'valid'   => __( 'license.active' ),
                'revoked' => __( 'license.revoked' ),
                'expired' => __( 'license.expired' ),
                default   => __( 'license.inactive' ),
            };
            ?>
            <div class="alert <?php echo $statusClass; ?>" style="margin:0;padding:0.5rem 1rem;display:inline-block;">
                <?php echo htmlspecialchars( $statusLabel ); ?>
            </div>
        </div>
        <div class="form-group">
            <label><?php echo __( 'license.plan' ); ?></label>
            <div style="font-size:1.1rem;font-weight:600;"><?php echo htmlspecialchars(ucfirst($status['plan'] ?? '---')); ?></div>
        </div>
        <div class="form-group">
            <label><?php echo __( 'license.domain' ); ?></label>
            <div class="mono" style="font-size:0.85rem;"><?php echo htmlspecialchars( $status['domain'] ?? '---'); ?></div>
        </div>
        <div class="form-group">
            <label><?php echo __( 'license.activated_on' ); ?></label>
            <div><?php echo !empty($status['activated_at']) ? date( 'Y-m-d H:i', strtotime($status['activated_at'])) : '---'; ?></div>
        </div>
        <div class="form-group">
            <label><?php echo __( 'license.last_check' ); ?></label>
            <div><?php echo !empty($status['last_verified']) ? date( 'Y-m-d H:i', strtotime($status['last_verified'])) : '---'; ?></div>
        </div>
        <div class="form-group">
            <label><?php echo __( 'license.key' ); ?></label>
            <div class="mono" style="font-size:0.8rem;"><?php echo !empty($status['license_key']) ? htmlspecialchars(substr($status['license_key'], 0, 8) . '...' . substr($status['license_key'], -8)) : '---'; ?></div>
        </div>
    </div>

    <?php if ($licenseStatus === 'revoked' && !empty($status['grace_period_until'])): ?>
        <div class="alert alert-warning" style="margin-top:1rem;">
            <?php echo __( 'license.grace_period', ['date' => date( 'Y-m-d', strtotime($status['grace_period_until']))]); ?>
        </div>
    <?php endif; ?>

    <?php if ($licenseStatus === 'valid'): ?>
        <form method="post" style="margin-top:1rem;">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="verify">
            <button type="submit" class="btn btn-outline"><?php echo __( 'license.last_check' ); ?> — <?php echo __( 'common.status' ); ?></button>
        </form>
    <?php endif; ?>
</div>

<!-- Activate License -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'license.activate' ); ?></h3></div>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="action" value="activate">
        <div class="form-group">
            <label><?php echo __( 'license.key' ); ?></label>
            <input type="text" name="license_key" class="form-control mono" placeholder="babf4d6048c134cbe77d5ac9c6b8dfa1995e1fda4a2b5f55ef0df234" required>
            <p class="form-help">plugins.joseconti.com</p>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo __( 'license.activate' ); ?></button>
    </form>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
