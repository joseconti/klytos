<?php
/**
 * Klytos Admin — Asset Management
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

$pageTitle = __( 'assets.title' );
$auth      = $app->getAuth();
$assets    = $app->getAssets();
$success   = '';
$error     = '';

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->validateCsrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload' && isset($_FILES['file'])) {
        $file = $_FILES['file'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $data = file_get_contents($file['tmp_name']);
            try {
                $result = $assets->upload(
                    $file['name'],
                    base64_encode($data),
                    $_POST['directory'] ?? 'images'
                );
                $success = __( 'assets.upload_success' );
            } catch (\RuntimeException $e) {
                $error = $e->getMessage();
            }
        } else {
            $error = __( 'assets.upload_error' );
        }
    } elseif ($action === 'delete') {
        $path = $_POST['path'] ?? '';
        if ($assets->delete($path)) {
            $success = __( 'common.success' );
        } else {
            $error = __( 'common.error' );
        }
    }
}

$allAssets = $assets->list();
$csrf      = $auth->getCsrfToken();

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars( $success ); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars( $error ); ?></div>
<?php endif; ?>

<!-- Upload -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'assets.upload' ); ?></h3></div>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="action" value="upload">
        <div style="display:flex;gap:1rem;align-items:end;">
            <div class="form-group" style="flex:1;">
                <label><?php echo __( 'assets.upload' ); ?></label>
                <input type="file" name="file" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Directory</label>
                <select name="directory" class="form-control">
                    <option value="images">images</option>
                    <option value="images/ai-generated">images/ai-generated</option>
                    <option value="fonts">fonts</option>
                    <option value="docs">docs</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo __( 'common.upload' ); ?></button>
        </div>
        <p class="form-help"><?php echo __( 'assets.max_size', ['size' => '10']); ?></p>
    </form>
</div>

<!-- File List -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'assets.title' ); ?> (<?php echo count( $allAssets); ?>)</h3></div>
    <?php if (empty($allAssets)): ?>
        <div class="empty-state"><h3><?php echo __( 'assets.no_assets' ); ?></h3></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><?php echo __( 'common.name' ); ?></th>
                        <th><?php echo __( 'common.type' ); ?></th>
                        <th><?php echo __( 'common.size' ); ?></th>
                        <th><?php echo __( 'common.date' ); ?></th>
                        <th><?php echo __( 'common.actions' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allAssets as $asset): ?>
                    <tr>
                        <td class="mono" style="font-size:0.8rem;"><?php echo htmlspecialchars( $asset['path'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars( $asset['mime_type'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars( $asset['size_human'] ?? ''); ?></td>
                        <td><?php echo $asset['modified'] ? date( 'Y-m-d', strtotime($asset['modified'])) : ''; ?></td>
                        <td>
                            <form method="post" style="display:inline;" class="form-confirm-delete">
                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="path" value="<?php echo htmlspecialchars( $asset['path'] ?? '' ); ?>">
                                <button type="submit" class="btn btn-danger btn-sm"><?php echo __( 'common.delete' ); ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script nonce="<?php echo $cspNonce; ?>">
(function() {
    document.querySelectorAll( '.form-confirm-delete' ).forEach( function( form ) {
        form.addEventListener( 'submit', function( e ) {
            if ( !confirm( '<?php echo __( 'assets.confirm_delete_asset' ); ?>' ) ) {
                e.preventDefault();
            }
        });
    });
})();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
