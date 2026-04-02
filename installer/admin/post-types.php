<?php

/**
 * Klytos Admin — Post Types Management
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

$pageTitle   = 'Post Types';
$currentPage = 'post-types';
$auth        = $app->getAuth();
$ptManager   = $app->getPostTypeManager();
$error       = '';
$success     = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf()) {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $editorValue = $_POST['editor'] ?? 'gutenberg';
            if (!in_array($editorValue, ['gutenberg', 'tinymce'], true)) {
                $editorValue = 'gutenberg';
            }
            $ptManager->create([
                'id'     => $_POST['id'] ?? '',
                'name'   => $_POST['name'] ?? '',
                'slug'   => $_POST['slug'] ?? '',
                'editor' => $editorValue,
            ]);
            $success = __( 'common.success' );
        } elseif ($action === 'delete') {
            $deleteId = $_POST['id'] ?? '';
            if ($deleteId === 'page') {
                $error = 'Cannot delete the built-in page post type.';
            } else {
                $ptManager->delete($deleteId);
                $success = __( 'common.success' );
            }
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

$postTypes = $ptManager->list();
$csrf      = $auth->getCsrfToken();

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.post_types.before' ); ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Post Types (<?php echo count( $postTypes ); ?>)</h3>
        <button type="button" class="btn btn-primary btn-sm" id="btn-new-pt">+ New Post Type</button>
    </div>

    <?php if (empty($postTypes)): ?>
        <div class="empty-state">
            <h3>No post types found.</h3>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Taxonomies</th>
                        <th>Editor</th>
                        <th>Built-in</th>
                        <th><?php echo __( 'common.actions' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($postTypes as $pt): ?>
                    <tr>
                        <td class="mono"><?php echo klytos_esc_html( $pt['id'] ?? '' ); ?></td>
                        <td><?php echo klytos_esc_html( $pt['name'] ?? '' ); ?></td>
                        <td class="mono"><?php echo klytos_esc_html( $pt['slug'] ?? '' ); ?></td>
                        <td>
                            <?php
                            $taxList = $pt['taxonomies'] ?? [];
                            $taxNames = array_map(fn($t) => $t['name'] ?? $t['id'] ?? '', $taxList);
                            ?>
                            <span title="<?php echo klytos_esc_attr(implode(', ', $taxNames)); ?>" style="cursor:default;"><?php echo count($taxList); ?></span>
                        </td>
                        <td>
                            <span class="badge-status badge-<?php echo ($pt['editor'] ?? 'gutenberg') === 'gutenberg' ? 'published' : 'draft'; ?>">
                                <?php echo ($pt['editor'] ?? 'gutenberg') === 'gutenberg' ? 'Gutenberg' : 'TinyMCE'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-status badge-<?php echo ($pt['builtin'] ?? false) ? 'published' : 'draft'; ?>">
                                <?php echo ($pt['builtin'] ?? false) ? 'Yes' : 'No'; ?>
                            </span>
                        </td>
                        <td class="flex-center flex-gap-sm">
                            <a href="post-type-edit.php?id=<?php echo urlencode( $pt['id'] ?? '' ); ?>" class="btn btn-outline btn-sm"><?php echo __( 'common.edit' ); ?></a>
                            <?php if (!($pt['builtin'] ?? false)): ?>
                            <form method="post" class="inline-form form-confirm-delete">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo klytos_esc_attr( $pt['id'] ?? '' ); ?>">
                                <?php echo klytos_csrf_field(); ?>
                                <button type="submit" class="btn btn-danger btn-sm"><?php echo __( 'common.delete' ); ?></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Create Post Type -->
<div id="modal-create-pt" class="modal-overlay">
    <div class="modal max-w-md">
        <div class="flex-between mb-3">
            <h3 class="mb-0">New Post Type</h3>
            <button type="button" id="btn-close-modal-x" class="btn btn-ghost text-xl">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="create">
            <?php echo klytos_csrf_field(); ?>
            <div class="form-group">
                <label>ID</label>
                <input type="text" name="id" class="form-control" required pattern="[a-z0-9_-]+" placeholder="e.g. product">
                <p class="form-help">Lowercase, no spaces. This cannot be changed later.</p>
            </div>
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Products">
            </div>
            <div class="form-group">
                <label>Slug</label>
                <input type="text" name="slug" class="form-control" required placeholder="e.g. products">
                <p class="form-help">URL prefix for this post type.</p>
            </div>
            <div class="form-group">
                <label><?php echo __('editor.title'); ?></label>
                <div class="selection-cards cols-2 mt-1">
                    <label class="selection-card">
                        <input type="radio" name="editor" value="gutenberg" checked>
                        <div class="selection-card-body">
                            <span class="selection-card-title">Gutenberg</span>
                        </div>
                    </label>
                    <label class="selection-card">
                        <input type="radio" name="editor" value="tinymce">
                        <div class="selection-card-body">
                            <span class="selection-card-title">TinyMCE</span>
                        </div>
                    </label>
                </div>
            </div>
            <div class="flex-end flex-gap-sm mt-3">
                <button type="button" class="btn btn-outline" id="btn-cancel-modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<script nonce="<?php echo $cspNonce; ?>">
(function() {
    var modal = document.getElementById('modal-create-pt');

    // Open modal
    document.getElementById('btn-new-pt').addEventListener('click', function() {
        modal.style.display = 'flex';
    });

    // Close modal (X button, Cancel button, backdrop click, Escape key)
    document.getElementById('btn-close-modal-x').addEventListener('click', function() {
        modal.style.display = 'none';
    });
    document.getElementById('btn-cancel-modal').addEventListener('click', function() {
        modal.style.display = 'none';
    });
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            modal.style.display = 'none';
        }
    });

    // Confirm delete
    document.querySelectorAll('.form-confirm-delete').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to delete this post type?')) {
                e.preventDefault();
            }
        });
    });
})();
</script>

<?php klytos_do_action( 'admin.post_types.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
