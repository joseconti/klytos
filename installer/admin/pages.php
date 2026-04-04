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

        case 'bulk_action':
            $bulkAction = $_POST['bulk_action'] ?? '';
            $bulkSlugs  = $_POST['bulk_slugs'] ?? [];
            if ( !empty( $bulkAction ) && !empty( $bulkSlugs ) && is_array( $bulkSlugs ) ) {
                $bulkCount = 0;
                klytos_do_action( 'admin.bulk_action.before', $bulkAction, $bulkSlugs );
                foreach ( $bulkSlugs as $bSlug ) {
                    try {
                        switch ( $bulkAction ) {
                            case 'publish':
                                $pages->update( $bSlug, ['status' => 'published'] );
                                break;
                            case 'draft':
                                $pages->update( $bSlug, ['status' => 'draft'] );
                                break;
                            case 'trash':
                                $pages->delete( $bSlug );
                                break;
                            case 'restore':
                                $pages->restore( $bSlug );
                                break;
                            case 'permanent_delete':
                                $pages->permanentDelete( $bSlug );
                                break;
                            default:
                                // Custom status: set status directly.
                                if ( $bulkAction !== '' ) {
                                    $pages->update( $bSlug, ['status' => $bulkAction] );
                                }
                                break;
                        }
                        $bulkCount++;
                    } catch ( \Throwable $e ) {
                        // Skip individual errors.
                    }
                }
                klytos_do_action( 'admin.bulk_action.after', $bulkAction, $bulkCount, [] );
                $success = __( 'bulk.success', ['count' => $bulkCount] );
            }
            break;
    }
}

// Load custom statuses for the current post type.
$customStatuses = [];
$statusDefs     = [];
if ( $postTypeFilter !== '' ) {
    try {
        $statusDefs     = $app->getPostTypeManager()->getStatusesForPostType( $postTypeFilter );
        $customStatuses = array_filter( $statusDefs, fn( $s ) => empty( $s['system'] ) );
    } catch ( \Throwable $e ) {
        // Ignore — fall back to system statuses only.
    }
} else {
    $statusDefs = \Klytos\Core\PostTypeManager::SYSTEM_STATUS_DEFS;
}

// Determine which status view to show.
$statusView = trim( $_GET['status'] ?? '' );
if ( $statusView === 'trashed' ) {
    $allPages = $pages->list( 'trashed', '', 50, 0, $postTypeFilter );
} elseif ( $statusView === 'scheduled' ) {
    $allPages = $pages->list( 'scheduled', '', 50, 0, $postTypeFilter );
} elseif ( $statusView !== '' && $statusView !== 'all' ) {
    // Custom status or other valid status.
    $allPages = $pages->list( $statusView, '', 50, 0, $postTypeFilter );
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
    <div class="tabs">
        <a href="<?php echo klytos_esc_url( $baseUrl ); ?>" class="tab<?php echo $statusView === '' ? ' active' : ''; ?>">
            <?php echo __( 'pages.tab_all' ); ?> (<?php echo $countAll; ?>)
        </a>
        <?php if ( $countScheduled > 0 ): ?>
        <a href="<?php echo klytos_esc_url( $baseUrl . $tabSep . 'status=scheduled' ); ?>" class="tab<?php echo $statusView === 'scheduled' ? ' active' : ''; ?>">
            <?php echo __( 'pages.tab_scheduled' ); ?> (<?php echo $countScheduled; ?>)
        </a>
        <?php endif; ?>
        <?php foreach ( $customStatuses as $csSt ): ?>
            <?php $csCount = $pages->count( $csSt['id'], $postTypeFilter ); ?>
            <?php if ( $csCount > 0 ): ?>
            <a href="<?php echo klytos_esc_url( $baseUrl . $tabSep . 'status=' . urlencode( $csSt['id'] ) ); ?>"
               class="tab<?php echo $statusView === $csSt['id'] ? ' active' : ''; ?>">
                <?php echo klytos_esc_html( $csSt['label'] ); ?> (<?php echo $csCount; ?>)
            </a>
            <?php endif; ?>
        <?php endforeach; ?>
        <a href="<?php echo klytos_esc_url( $baseUrl . $tabSep . 'status=trashed' ); ?>" class="tab<?php echo $statusView === 'trashed' ? ' active' : ''; ?>">
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
        <!-- Bulk action bar -->
        <form method="post" id="bulk-action-form" data-confirm-delete="<?php echo klytos_esc_attr( __( 'bulk.confirm_delete' ) ); ?>">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="action" value="bulk_action">
            <div class="flex flex-gap-sm flex-center" style="padding: var(--klytos-space-2) var(--klytos-space-4);">
                <select name="bulk_action" id="bulk-action-select" class="form-control" style="width:auto;min-width:160px">
                    <option value=""><?php echo __( 'bulk.action_label' ); ?></option>
                    <?php if ( $statusView === 'trashed' ): ?>
                        <option value="restore"><?php echo __( 'pages.restore' ); ?></option>
                        <option value="permanent_delete"><?php echo __( 'pages.permanent_delete' ); ?></option>
                    <?php else: ?>
                        <?php
                        $bulkActions = [
                            'publish' => __( 'pages.published' ),
                            'draft'   => __( 'pages.draft' ),
                            'trash'   => __( 'pages.tab_trash' ),
                        ];
                        foreach ( $customStatuses as $csBulk ) {
                            $bulkActions[$csBulk['id']] = $csBulk['label'];
                        }
                        $bulkActions = klytos_apply_filters( 'pages.bulk_actions', $bulkActions );
                        ?>
                        <?php foreach ( $bulkActions as $bVal => $bLabel ): ?>
                            <option value="<?php echo klytos_esc_attr( $bVal ); ?>"><?php echo klytos_esc_html( $bLabel ); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <button type="submit" id="bulk-apply-btn" class="btn btn-outline btn-sm" disabled><?php echo __( 'bulk.apply' ); ?></button>
                <span id="bulk-count" class="badge-status badge-draft" style="display:none"></span>
            </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:30px"><input type="checkbox" id="bulk-select-all"></th>
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

                        // Status badge class, label, and custom color.
                        $badgeClass = 'draft';
                        $badgeLabel = __( 'pages.draft' );
                        $badgeCustomColor = '';
                        if ( $pageStatus === 'published' ) {
                            $badgeClass = 'published';
                            $badgeLabel = __( 'pages.published' );
                        } elseif ( $pageStatus === 'scheduled' ) {
                            $badgeClass = 'scheduled';
                            $badgeLabel = __( 'pages.scheduled' );
                        } elseif ( $pageStatus === 'trashed' ) {
                            $badgeClass = 'trashed';
                            $badgeLabel = __( 'pages.trashed' );
                        } else {
                            // Check custom statuses.
                            foreach ( $statusDefs as $stDef ) {
                                if ( ( $stDef['id'] ?? '' ) === $pageStatus && empty( $stDef['system'] ) ) {
                                    $badgeClass = 'custom';
                                    $badgeLabel = $stDef['label'] ?? ucfirst( $pageStatus );
                                    $badgeCustomColor = $stDef['color'] ?? '';
                                    break;
                                }
                            }
                        }
                    ?>
                    <tr>
                        <td><input type="checkbox" class="bulk-checkbox" name="bulk_slugs[]" value="<?php echo klytos_esc_attr( $page['slug'] ?? '' ); ?>"></td>
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
                            <span class="badge-status badge-<?php echo $badgeClass; ?>"<?php if ( $badgeCustomColor !== '' ): ?> style="--badge-color:<?php echo klytos_esc_attr( $badgeCustomColor ); ?>"<?php endif; ?>>
                                <?php echo klytos_esc_html( $badgeLabel ); ?>
                            </span>
                        </td>
                        <td class="flex flex-gap-sm flex-center">
                            <?php if ( $statusView === 'trashed' ): ?>
                                <!-- Trash view: restore + permanent delete -->
                                <button type="button" class="btn btn-outline btn-sm row-action"
                                    data-action="restore"
                                    data-slug="<?php echo klytos_esc_attr( $page['slug'] ?? '' ); ?>">
                                    <?php echo __( 'pages.restore' ); ?>
                                </button>
                                <button type="button" class="btn btn-danger btn-sm row-action"
                                    data-action="permanent_delete"
                                    data-slug="<?php echo klytos_esc_attr( $page['slug'] ?? '' ); ?>"
                                    data-confirm="<?php echo klytos_esc_attr( __( 'pages.confirm_permanent_delete' ) ); ?>">
                                    <?php echo __( 'pages.permanent_delete' ); ?>
                                </button>
                            <?php else: ?>
                                <!-- Normal view: edit + trash -->
                                <a href="page-editor.php?slug=<?php echo urlencode( $page['slug'] ?? '' ); ?>" class="btn btn-outline btn-sm"><?php echo __( 'common.edit' ); ?></a>
                                <button type="button" class="btn btn-danger btn-sm row-action"
                                    data-action="delete"
                                    data-slug="<?php echo klytos_esc_attr( $page['slug'] ?? '' ); ?>"
                                    data-confirm="<?php echo klytos_esc_attr( __( 'pages.confirm_delete_page' ) ); ?>">
                                    <?php echo __( 'common.delete' ); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </form><!-- /bulk-action-form -->
    <?php endif; ?>
</div>

<script nonce="<?php echo $cspNonce; ?>" src="js/bulk-actions.js"></script>
<script nonce="<?php echo $cspNonce; ?>">
(function() {
    var csrfValue = '<?php echo klytos_esc_attr( $app->getAuth()->getCsrfToken() ); ?>';

    document.addEventListener( 'click', function( e ) {
        var btn = e.target.closest( '.row-action' );
        if ( !btn ) return;

        var confirmMsg = btn.getAttribute( 'data-confirm' );
        if ( confirmMsg && !confirm( confirmMsg ) ) return;

        var form = document.createElement( 'form' );
        form.method = 'post';
        form.style.display = 'none';

        var fields = {
            action: btn.getAttribute( 'data-action' ),
            slug:   btn.getAttribute( 'data-slug' ),
            csrf:   csrfValue
        };

        for ( var key in fields ) {
            var input = document.createElement( 'input' );
            input.type  = 'hidden';
            input.name  = key;
            input.value = fields[key];
            form.appendChild( input );
        }

        document.body.appendChild( form );
        form.submit();
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
