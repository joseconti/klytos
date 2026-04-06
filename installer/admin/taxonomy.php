<?php

/**
 * Klytos Admin — Taxonomy Terms Management
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
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
$currentPage  = 'tax-' . $postTypeId . '-' . $taxonomyId;

// Handle POST actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (klytos_verify_csrf()) {
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
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3><?php echo klytos_esc_html( $taxonomyName ); ?> — <?php echo __( 'common.add' ); ?></h3>
    </div>
    <form method="post">
        <input type="hidden" name="action" value="add_term">
        <?php echo klytos_csrf_field(); ?>
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
        <h3><?php echo klytos_esc_html( $taxonomyName ); ?> (<?php echo count( $terms ); ?>)</h3>
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
                        <td class="mono"><?php echo klytos_esc_html( $term['slug'] ?? '' ); ?></td>
                        <td><?php echo klytos_esc_html( $term['name'] ?? '' ); ?></td>
                        <td><?php echo klytos_esc_html( $term['description'] ?? '' ); ?></td>
                        <td class="flex flex-gap-sm flex-center">
                            <form method="post" class="inline-form form-confirm-delete">
                                <input type="hidden" name="action" value="delete_term">
                                <input type="hidden" name="slug" value="<?php echo klytos_esc_attr( $term['slug'] ?? '' ); ?>">
                                <?php echo klytos_csrf_field(); ?>
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
