<?php
/**
 * Klytos Admin — Post Types Management
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

$pageTitle   = 'Post Types';
$currentPage = 'post-types';
$auth        = $app->getAuth();
$ptManager   = $app->getPostTypeManager();
$error       = '';
$success     = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($auth->validateCsrf($_POST['csrf'] ?? '')) {
        try {
            switch ($action) {
                case 'create':
                    $ptManager->create([
                        'id'   => $_POST['id'] ?? '',
                        'name' => $_POST['name'] ?? '',
                        'slug' => $_POST['slug'] ?? '',
                    ]);
                    $success = __( 'common.success' );
                    break;

                case 'delete':
                    $deleteId = $_POST['id'] ?? '';
                    if ($deleteId === 'page') {
                        $error = 'Cannot delete the built-in page post type.';
                    } else {
                        $ptManager->delete($deleteId);
                        $success = __( 'common.success' );
                    }
                    break;

                case 'add_taxonomy':
                    $ptManager->addTaxonomy($_POST['post_type_id'] ?? '', [
                        'id'           => $_POST['tax_id'] ?? '',
                        'name'         => $_POST['tax_name'] ?? '',
                        'slug'         => $_POST['tax_slug'] ?? '',
                        'hierarchical' => isset($_POST['hierarchical']),
                    ]);
                    $success = __( 'common.success' );
                    break;

                case 'remove_taxonomy':
                    $ptManager->removeTaxonomy(
                        $_POST['post_type_id'] ?? '',
                        $_POST['tax_id'] ?? ''
                    );
                    $success = __( 'common.success' );
                    break;
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = __( 'common.error' );
    }
}

$postTypes = $ptManager->list();
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

<div class="card">
    <div class="card-header">
        <h3>Create New Post Type</h3>
    </div>
    <form method="post" style="padding:1.5rem;">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <div class="form-group">
            <label>ID (slug format)</label>
            <input type="text" name="id" class="form-control" required pattern="[a-z0-9_-]+" placeholder="e.g. product">
        </div>
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" class="form-control" required placeholder="e.g. Products">
        </div>
        <div class="form-group">
            <label>Slug</label>
            <input type="text" name="slug" class="form-control" required placeholder="e.g. products">
        </div>
        <button type="submit" class="btn btn-primary">Create Post Type</button>
    </form>
</div>

<div class="card" style="margin-top:1.5rem;">
    <div class="card-header">
        <h3>Post Types (<?php echo count( $postTypes ); ?>)</h3>
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
                        <th>Built-in</th>
                        <th><?php echo __( 'common.actions' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($postTypes as $pt): ?>
                    <tr>
                        <td class="mono"><?php echo htmlspecialchars( $pt['id'] ?? '' ); ?></td>
                        <td><?php echo htmlspecialchars( $pt['name'] ?? '' ); ?></td>
                        <td class="mono"><?php echo htmlspecialchars( $pt['slug'] ?? '' ); ?></td>
                        <td><?php echo count( $pt['taxonomies'] ?? [] ); ?></td>
                        <td>
                            <span class="badge-status badge-<?php echo ($pt['builtin'] ?? false) ? 'published' : 'draft'; ?>">
                                <?php echo ($pt['builtin'] ?? false) ? 'Yes' : 'No'; ?>
                            </span>
                        </td>
                        <td style="display:flex;gap:0.5rem;align-items:center;">
                            <a href="post-type-edit.php?id=<?php echo urlencode( $pt['id'] ?? '' ); ?>" class="btn btn-outline btn-sm"><?php echo __( 'common.edit' ); ?></a>
                            <?php if (!($pt['builtin'] ?? false)): ?>
                            <form method="post" style="display:inline;" class="form-confirm-delete">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars( $pt['id'] ?? '' ); ?>">
                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                <button type="submit" class="btn btn-danger btn-sm"><?php echo __( 'common.delete' ); ?></button>
                            </form>
                            <?php else: ?>
                            <button type="button" class="btn btn-danger btn-sm" disabled><?php echo __( 'common.delete' ); ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php foreach ($postTypes as $pt):
    $ptId     = $pt['id'] ?? '';
    $isBuiltin = $pt['builtin'] ?? false;

    // Show taxonomy section for non-built-in post types AND for the built-in 'page'
    if ($isBuiltin && $ptId !== 'page') {
        continue;
    }
?>
<div class="card" style="margin-top:1.5rem;">
    <div class="card-header">
        <h3>Taxonomies for &ldquo;<?php echo htmlspecialchars( $pt['name'] ?? '' ); ?>&rdquo;</h3>
    </div>

    <?php $taxonomies = $pt['taxonomies'] ?? []; ?>

    <?php if (!empty($taxonomies)): ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Hierarchical</th>
                    <th><?php echo __( 'common.actions' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($taxonomies as $tax): ?>
                <tr>
                    <td class="mono"><?php echo htmlspecialchars( $tax['id'] ?? '' ); ?></td>
                    <td><?php echo htmlspecialchars( $tax['name'] ?? '' ); ?></td>
                    <td class="mono"><?php echo htmlspecialchars( $tax['slug'] ?? '' ); ?></td>
                    <td>
                        <span class="badge-status badge-<?php echo ($tax['hierarchical'] ?? false) ? 'published' : 'draft'; ?>">
                            <?php echo ($tax['hierarchical'] ?? false) ? 'Yes' : 'No'; ?>
                        </span>
                    </td>
                    <td>
                        <form method="post" style="display:inline;" class="form-confirm-delete">
                            <input type="hidden" name="action" value="remove_taxonomy">
                            <input type="hidden" name="post_type_id" value="<?php echo htmlspecialchars( $ptId ); ?>">
                            <input type="hidden" name="tax_id" value="<?php echo htmlspecialchars( $tax['id'] ?? '' ); ?>">
                            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <p>No taxonomies registered for this post type.</p>
    </div>
    <?php endif; ?>

    <form method="post" style="padding:1.5rem;border-top:1px solid var(--border, #e2e8f0);">
        <h4 style="margin-bottom:1rem;">Add Taxonomy</h4>
        <input type="hidden" name="action" value="add_taxonomy">
        <input type="hidden" name="post_type_id" value="<?php echo htmlspecialchars( $ptId ); ?>">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <div class="form-group">
            <label>ID (slug format)</label>
            <input type="text" name="tax_id" class="form-control" required pattern="[a-z0-9_-]+" placeholder="e.g. category">
        </div>
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="tax_name" class="form-control" required placeholder="e.g. Categories">
        </div>
        <div class="form-group">
            <label>Slug</label>
            <input type="text" name="tax_slug" class="form-control" required placeholder="e.g. categories">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="hierarchical" value="1"> Hierarchical</label>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Add Taxonomy</button>
    </form>
</div>
<?php endforeach; ?>

<script nonce="<?php echo $cspNonce; ?>">
(function() {
    document.querySelectorAll( '.form-confirm-delete' ).forEach( function( form ) {
        form.addEventListener( 'submit', function( e ) {
            if ( !confirm( 'Are you sure you want to delete this item?' ) ) {
                e.preventDefault();
            }
        });
    });
})();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
