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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf()) {
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
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<!-- Upload -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'assets.upload' ); ?></h3></div>
    <form method="post" enctype="multipart/form-data" id="upload-form">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="action" value="upload">

        <div id="drop-zone" style="border:2px dashed var(--admin-border, #555);border-radius:8px;padding:2rem;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;margin-bottom:1rem;">
            <p style="margin:0 0 0.5rem;font-size:1.1rem;" id="drop-zone-text">
                Drag &amp; drop files here or click to select
            </p>
            <p style="margin:0;font-size:0.85rem;color:var(--admin-text-muted,#888);" id="drop-zone-file"></p>
            <input type="file" name="file" id="file-input" style="display:none;" required>
        </div>

        <div style="display:flex;gap:1rem;align-items:end;">
            <div class="form-group" style="flex:1;">
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
                        <td class="mono" style="font-size:0.8rem;"><?php echo klytos_esc_html( $asset['path'] ?? ''); ?></td>
                        <td><?php echo klytos_esc_html( $asset['mime_type'] ?? ''); ?></td>
                        <td><?php echo klytos_esc_html( $asset['size_human'] ?? ''); ?></td>
                        <td><?php echo $asset['modified'] ? date( 'Y-m-d', strtotime($asset['modified'])) : ''; ?></td>
                        <td>
                            <form method="post" style="display:inline;" class="form-confirm-delete">
                                <?php echo klytos_csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="path" value="<?php echo klytos_esc_attr( $asset['path'] ?? '' ); ?>">
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
    // Confirm delete.
    document.querySelectorAll( '.form-confirm-delete' ).forEach( function( form ) {
        form.addEventListener( 'submit', function( e ) {
            if ( !confirm( '<?php echo __( 'assets.confirm_delete_asset' ); ?>' ) ) {
                e.preventDefault();
            }
        });
    });

    // Drag & drop upload.
    var dropZone   = document.getElementById('drop-zone');
    var fileInput  = document.getElementById('file-input');
    var fileLabel  = document.getElementById('drop-zone-file');

    if (!dropZone || !fileInput) return;

    // Click to open file picker.
    dropZone.addEventListener('click', function() {
        fileInput.click();
    });

    // Show selected file name.
    fileInput.addEventListener('change', function() {
        if (fileInput.files.length) {
            fileLabel.textContent = fileInput.files[0].name;
        }
    });

    // Prevent default browser behavior for drag events.
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(evt) {
        dropZone.addEventListener(evt, function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
    });

    // Visual feedback on drag.
    ['dragenter', 'dragover'].forEach(function(evt) {
        dropZone.addEventListener(evt, function() {
            dropZone.style.borderColor = 'var(--admin-primary, #4f8cff)';
            dropZone.style.background  = 'rgba(79,140,255,0.08)';
        });
    });
    ['dragleave', 'drop'].forEach(function(evt) {
        dropZone.addEventListener(evt, function() {
            dropZone.style.borderColor = '';
            dropZone.style.background  = '';
        });
    });

    // Handle dropped files.
    dropZone.addEventListener('drop', function(e) {
        var files = e.dataTransfer.files;
        if (files.length) {
            fileInput.files = files;
            fileLabel.textContent = files[0].name;
        }
    });
})();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
