<?php
/**
 * Klytos Admin — Taxonomy Terms Management
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

$postTypeId = $_GET['post_type'] ?? '';
$taxonomyId = $_GET['taxonomy'] ?? '';

if ($postTypeId === '' || $taxonomyId === '') {
    header('Location: post-types.php');
    exit;
}

$postType = $app->getPostTypeManager()->get($postTypeId);
$auth     = $app->getAuth();
$error    = '';
$success  = '';

// Find taxonomy config from post type taxonomies.
$taxonomyConfig = null;
foreach ($postType['taxonomies'] ?? [] as $tax) {
    if (($tax['id'] ?? '') === $taxonomyId) {
        $taxonomyConfig = $tax;
        break;
    }
}

if ($taxonomyConfig === null) {
    header('Location: post-types.php');
    exit;
}

$taxonomyName = $taxonomyConfig['name'] ?? $taxonomyId;
$postTypeName = $postType['name'] ?? $postTypeId;
$pageTitle    = $taxonomyName . ' — ' . $postTypeName;
$currentPage  = 'taxonomy';

// Handle POST actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($auth->validateCsrf($_POST['csrf'] ?? '')) {
        switch ($action) {
            case 'add_term':
                $termData = [
                    'name'        => $_POST['name'] ?? '',
                    'slug'        => $_POST['slug'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'parent'      => $_POST['parent'] ?? '',
                ];
                try {
                    $app->getPostTypeManager()->addTerm($postTypeId, $taxonomyId, $termData);
                    $success = __('common.success');
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
                break;

            case 'delete_term':
                $termSlug = $_POST['slug'] ?? '';
                try {
                    if ($app->getPostTypeManager()->deleteTerm($postTypeId, $taxonomyId, $termSlug)) {
                        $success = __('common.success');
                    } else {
                        $error = __('common.error');
                    }
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
                break;

            case 'update_term':
                $termSlug = $_POST['term_slug'] ?? '';
                $updateData = [
                    'name'        => $_POST['name'] ?? '',
                    'description' => $_POST['description'] ?? '',
                ];
                try {
                    $app->getPostTypeManager()->updateTerm($postTypeId, $taxonomyId, $termSlug, $updateData);
                    $success = __('common.success');
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
                break;
        }
    }
}

$terms = $app->getPostTypeManager()->listTerms($postTypeId, $taxonomyId);
$csrf  = $auth->getCsrfToken();

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
        <h3><?php echo htmlspecialchars( $taxonomyName ); ?> — <?php echo __( 'common.add' ); ?></h3>
    </div>
    <form method="post">
        <input type="hidden" name="action" value="add_term">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <div class="form-group">
            <label><?php echo __( 'common.name' ); ?></label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="form-group">
            <label><?php echo __( 'common.slug' ); ?></label>
            <input type="text" name="slug" class="form-control" placeholder="<?php echo __( 'common.auto_generated' ); ?>">
        </div>
        <div class="form-group">
            <label><?php echo __( 'common.description' ); ?></label>
            <textarea name="description" class="form-control" rows="3"></textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><?php echo __( 'common.add' ); ?></button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3><?php echo htmlspecialchars( $taxonomyName ); ?> (<?php echo count( $terms ); ?>)</h3>
    </div>

    <?php if (empty($terms)): ?>
        <div class="empty-state">
            <h3><?php echo __( 'common.no_items' ); ?></h3>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><?php echo __( 'common.slug' ); ?></th>
                        <th><?php echo __( 'common.name' ); ?></th>
                        <th><?php echo __( 'common.description' ); ?></th>
                        <th><?php echo __( 'common.actions' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($terms as $term): ?>
                    <tr>
                        <td class="mono"><?php echo htmlspecialchars( $term['slug'] ?? '' ); ?></td>
                        <td><?php echo htmlspecialchars( $term['name'] ?? '' ); ?></td>
                        <td><?php echo htmlspecialchars( $term['description'] ?? '' ); ?></td>
                        <td style="display:flex;gap:0.5rem;align-items:center;">
                            <form method="post" style="display:inline;" class="form-confirm-delete">
                                <input type="hidden" name="action" value="delete_term">
                                <input type="hidden" name="slug" value="<?php echo htmlspecialchars( $term['slug'] ?? '' ); ?>">
                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
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
            if ( !confirm( '<?php echo __( 'common.confirm_delete' ); ?>' ) ) {
                e.preventDefault();
            }
        });
    });
})();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
