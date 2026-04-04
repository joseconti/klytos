<?php

/**
 * Klytos Admin — Pages Management
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

$auth      = $app->getAuth();
$pages     = $app->getPages();
$error     = '';
$success   = '';

// Post type filter: when accessed via ?post_type=casas, only show that type.
$postTypeFilter = trim($_GET['post_type'] ?? '');
$postTypeName   = '';

if ($postTypeFilter !== '') {
    // Resolve the human-readable name from the PostTypeManager.
    try {
        $ptDef        = $app->getPostTypeManager()->get($postTypeFilter);
        $postTypeName = $ptDef['name'] ?? ucfirst($postTypeFilter);
    } catch (\Throwable $e) {
        $postTypeName = ucfirst($postTypeFilter);
    }
    $pageTitle   = $postTypeName;
    $currentPage = 'pt-' . $postTypeFilter;
} else {
    $pageTitle   = __( 'pages.title' );
    $currentPage = 'pages';
}

// Handle actions (trash, restore, permanent delete, empty trash).
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $action = $_POST['action'] ?? '';
    $slug   = $_POST['slug'] ?? '';

    switch ( $action ) {
        case 'delete':
            // Soft-delete: move to trash.
            if ( $pages->delete( $slug ) ) {
                $success = __( 'pages.moved_to_trash' );
            } else {
                $error = __( 'common.error' );
            }
            break;

        case 'restore':
            try {
                $pages->restore( $slug );
                $success = __( 'pages.restored' );
            } catch ( \Throwable $e ) {
                $error = $e->getMessage();
            }
            break;

        case 'permanent_delete':
            if ( $pages->permanentDelete( $slug ) ) {
                $success = __( 'pages.permanently_deleted' );
            } else {
                $error = __( 'common.error' );
            }
            break;

        case 'empty_trash':
            $count   = $pages->emptyTrash();
            $success = __( 'pages.trash_emptied', ['count' => $count] );
            break;
    }
}

// Determine which status view to show.
$statusView = trim( $_GET['status'] ?? '' );
if ( $statusView === 'trashed' ) {
    $allPages = $pages->list( 'trashed', '', 50, 0, $postTypeFilter );
} elseif ( $statusView === 'scheduled' ) {
    $allPages = $pages->list( 'scheduled', '', 50, 0, $postTypeFilter );
} else {
    $allPages = $pages->list( 'all', '', 50, 0, $postTypeFilter );
}

// Count for tab badges.
$countAll       = $pages->count( 'all', $postTypeFilter );
$countTrashed   = $pages->count( 'trashed', $postTypeFilter );
$countScheduled = $pages->count( 'scheduled', $postTypeFilter );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.pages.before' ); ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<?php
// Build base URL for tab links.
$baseUrl = 'pages.php';
if ( $postTypeFilter !== '' ) {
    $baseUrl .= '?post_type=' . urlencode( $postTypeFilter );
}
$tabSep = str_contains( $baseUrl, '?' ) ? '&' : '?';
?>

<div class="card">
    <div class="card-header">
        <h3><?php echo klytos_esc_html( $postTypeFilter !== '' ? $postTypeName : __( 'pages.title' ) ); ?> (<?php echo count( $allPages ); ?>)</h3>
        <?php if ( $statusView !== 'trashed' ): ?>
            <a href="page-editor.php<?php echo $postTypeFilter !== '' ? '?post_type=' . urlencode( $postTypeFilter ) : ''; ?>" class="btn btn-primary btn-sm">
                <?php echo $postTypeFilter !== '' ? '+ New ' . klytos_esc_html( $postTypeName ) : __( 'pages.create_page' ); ?>
            </a>
        <?php endif; ?>
    </div>

    <!-- Status tabs -->
    <div class="tabs" style="padding: 0 var(--klytos-space-4); border-bottom: 1px solid var(--klytos-border);">
        <a href="<?php echo klytos_esc_url( $baseUrl ); ?>" class="tab-item<?php echo $statusView === '' ? ' active' : ''; ?>">
            <?php echo __( 'pages.tab_all' ); ?> (<?php echo $countAll; ?>)
        </a>
        <?php if ( $countScheduled > 0 ): ?>
        <a href="<?php echo klytos_esc_url( $baseUrl . $tabSep . 'status=scheduled' ); ?>" class="tab-item<?php echo $statusView === 'scheduled' ? ' active' : ''; ?>">
            <?php echo __( 'pages.tab_scheduled' ); ?> (<?php echo $countScheduled; ?>)
        </a>
        <?php endif; ?>
        <a href="<?php echo klytos_esc_url( $baseUrl . $tabSep . 'status=trashed' ); ?>" class="tab-item<?php echo $statusView === 'trashed' ? ' active' : ''; ?>">
            <?php echo __( 'pages.tab_trash' ); ?> (<?php echo $countTrashed; ?>)
        </a>
    </div>

    <?php if ( $statusView === 'trashed' && $countTrashed > 0 ): ?>
        <div style="padding: var(--klytos-space-3) var(--klytos-space-4); display: flex; justify-content: flex-end;">
            <form method="post" class="inline-form form-confirm-empty-trash">
                <input type="hidden" name="action" value="empty_trash">
                <?php echo klytos_csrf_field(); ?>
                <button type="submit" class="btn btn-danger btn-sm"><?php echo __( 'pages.empty_trash' ); ?></button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ( empty( $allPages ) ): ?>
        <div class="empty-state">
            <h3><?php echo $statusView === 'trashed' ? __( 'pages.trash_empty' ) : __( 'pages.no_pages' ); ?></h3>
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
                    <?php foreach ( $allPages as $page ): ?>
                    <?php
                        $pageStatus = $page['status'] ?? 'draft';
                        $isSticky   = !empty( $page['is_sticky'] );

                        // Status badge class and label.
                        $badgeClass = 'draft';
                        $badgeLabel = __( 'pages.draft' );
                        if ( $pageStatus === 'published' ) {
                            $badgeClass = 'published';
                            $badgeLabel = __( 'pages.published' );
                        } elseif ( $pageStatus === 'scheduled' ) {
                            $badgeClass = 'scheduled';
                            $badgeLabel = __( 'pages.scheduled' );
                        } elseif ( $pageStatus === 'trashed' ) {
                            $badgeClass = 'trashed';
                            $badgeLabel = __( 'pages.trashed' );
                        }
                    ?>
                    <tr>
                        <td class="mono"><?php echo klytos_esc_html( $page['slug'] ?? '' ); ?></td>
                        <td>
                            <?php if ( $isSticky ): ?><span title="<?php echo __( 'pages.sticky' ); ?>">&#128204; </span><?php endif; ?>
                            <?php echo klytos_esc_html( $page['title'] ?? '' ); ?>
                            <?php if ( $pageStatus === 'scheduled' && !empty( $page['publish_at'] ) ): ?>
                                <br><small class="text-muted"><?php echo klytos_esc_html( $page['publish_at'] ); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo klytos_esc_html( $page['template'] ?? 'default' ); ?></td>
                        <td><?php echo klytos_esc_html( $page['lang'] ?? '—' ); ?></td>
                        <td>
                            <span class="badge-status badge-<?php echo $badgeClass; ?>">
                                <?php echo $badgeLabel; ?>
                            </span>
                        </td>
                        <td class="flex flex-gap-sm flex-center">
                            <?php if ( $statusView === 'trashed' ): ?>
                                <!-- Trash view: restore + permanent delete -->
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="slug" value="<?php echo klytos_esc_attr( $page['slug'] ?? '' ); ?>">
                                    <?php echo klytos_csrf_field(); ?>
                                    <button type="submit" class="btn btn-outline btn-sm"><?php echo __( 'pages.restore' ); ?></button>
                                </form>
                                <form method="post" class="inline-form form-confirm-delete">
                                    <input type="hidden" name="action" value="permanent_delete">
                                    <input type="hidden" name="slug" value="<?php echo klytos_esc_attr( $page['slug'] ?? '' ); ?>">
                                    <?php echo klytos_csrf_field(); ?>
                                    <button type="submit" class="btn btn-danger btn-sm"><?php echo __( 'pages.permanent_delete' ); ?></button>
                                </form>
                            <?php else: ?>
                                <!-- Normal view: edit + trash -->
                                <a href="page-editor.php?slug=<?php echo urlencode( $page['slug'] ?? '' ); ?>" class="btn btn-outline btn-sm"><?php echo __( 'common.edit' ); ?></a>
                                <form method="post" class="inline-form form-confirm-delete">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="slug" value="<?php echo klytos_esc_attr( $page['slug'] ?? '' ); ?>">
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

<script nonce="<?php echo $cspNonce; ?>">
(function() {
    document.querySelectorAll( '.form-confirm-delete' ).forEach( function( form ) {
        form.addEventListener( 'submit', function( e ) {
            var msg = form.querySelector('[name="action"]').value === 'permanent_delete'
                ? '<?php echo __( 'pages.confirm_permanent_delete' ); ?>'
                : '<?php echo __( 'pages.confirm_delete_page' ); ?>';
            if ( !confirm( msg ) ) {
                e.preventDefault();
            }
        });
    });
    document.querySelectorAll( '.form-confirm-empty-trash' ).forEach( function( form ) {
        form.addEventListener( 'submit', function( e ) {
            if ( !confirm( '<?php echo __( 'pages.confirm_empty_trash' ); ?>' ) ) {
                e.preventDefault();
            }
        });
    });
})();
</script>

<?php klytos_do_action( 'admin.pages.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
