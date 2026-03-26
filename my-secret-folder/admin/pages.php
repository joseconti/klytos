<?php
/**
 * Klytos Admin — Pages Management
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

$pageTitle = __( 'pages.title' );
$auth      = $app->getAuth();
$pages     = $app->getPages();
$error     = '';
$success   = '';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if ($auth->validateCsrf($_POST['csrf'] ?? '')) {
        $slug = $_POST['slug'] ?? '';
        if ($pages->delete($slug)) {
            $success = __( 'common.success' );
        } else {
            $error = __( 'common.error' );
        }
    }
}

$allPages = $pages->list('all');
$csrf     = $auth->getCsrfToken();
$currentPage = 'pages';

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<div class="admin-content">
<div class="admin-topbar">
    <h1 style="font-size:1.1rem;font-weight:600;"><?php echo __( 'pages.title' ); ?></h1>
    <div style="display:flex;align-items:center;gap:0.75rem;">
        <a href="page-editor.php" class="btn btn-primary"><?php echo __( 'pages.create_page' ); ?></a>
        <?php echo htmlspecialchars( $auth->getUsername() ); ?>
        <a href="logout.php" class="btn btn-outline btn-sm"><?php echo __( 'auth.logout' ); ?></a>
    </div>
</div>
<div class="admin-main">

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars( $success ); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars( $error ); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3><?php echo __( 'pages.title' ); ?> (<?php echo count( $allPages); ?>)</h3>
        <a href="page-editor.php" class="btn btn-primary btn-sm"><?php echo __( 'pages.create_page' ); ?></a>
    </div>

    <?php if (empty($allPages)): ?>
        <div class="empty-state">
            <h3><?php echo __( 'pages.no_pages' ); ?></h3>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><?php echo __( 'pages.slug' ); ?></th>
                        <th><?php echo __( 'pages.page_title' ); ?></th>
                        <th><?php echo __( 'pages.template' ); ?></th>
                        <th><?php echo __( 'pages.language' ); ?></th>
                        <th><?php echo __( 'common.status' ); ?></th>
                        <th><?php echo __( 'common.actions' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allPages as $page): ?>
                    <tr>
                        <td class="mono"><?php echo htmlspecialchars( $page['slug'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars( $page['title'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars( $page['template'] ?? 'default'); ?></td>
                        <td><?php echo htmlspecialchars( $page['lang'] ?? '—'); ?></td>
                        <td>
                            <span class="badge-status badge-<?php echo ($page['status'] ?? '') === 'published' ? 'published' : 'draft'; ?>">
                                <?php echo ($page['status'] ?? '') === 'published' ? __( 'pages.published' ) : __( 'pages.draft' ); ?>
                            </span>
                        </td>
                        <td style="display:flex;gap:0.5rem;align-items:center;">
                            <a href="page-editor.php?slug=<?php echo urlencode( $page['slug'] ?? '' ); ?>" class="btn btn-outline btn-sm"><?php echo __( 'common.edit' ); ?></a>
                            <form method="post" style="display:inline;" class="form-confirm-delete">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="slug" value="<?php echo htmlspecialchars( $page['slug'] ?? '' ); ?>">
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

</div>
</div>

<script nonce="<?php echo $cspNonce; ?>">
(function() {
    document.querySelectorAll( '.form-confirm-delete' ).forEach( function( form ) {
        form.addEventListener( 'submit', function( e ) {
            if ( !confirm( '<?php echo __( 'pages.confirm_delete_page' ); ?>' ) ) {
                e.preventDefault();
            }
        });
    });
})();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
