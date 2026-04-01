<?php

/**
 * Klytos Admin — Asset Management (Media Library)
 *
 * Enhanced media library with grid/list views, filters, detail panel,
 * category management, usage tracking, and bulk cleanup.
 *
 * @package Klytos
 * @since   0.18.0
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

$pageTitle    = __( 'assets.title' );
$auth         = $app->getAuth();
$assetManager = $app->getAssetManager();
$csrf         = $auth->getCsrfToken();
$siteUrl      = rtrim( (string) $app->getSiteConfig()->getValue( 'site_url', '' ), '/' );
$apiBase      = Helpers::getBasePath() . 'admin/api/assets-management.php';

// Handle traditional upload (non-JS fallback).
$success = '';
$error   = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $action = $_POST['action'] ?? '';

    if ( $action === 'upload' && isset( $_FILES['file'] ) ) {
        $file = $_FILES['file'];
        if ( $file['error'] === UPLOAD_ERR_OK ) {
            $data = file_get_contents( $file['tmp_name'] );
            try {
                $result = $assetManager->upload(
                    $file['name'],
                    base64_encode( $data ),
                    $_POST['directory'] ?? 'images'
                );
                $success = __( 'assets.upload_success' );
            } catch ( \RuntimeException $e ) {
                $error = $e->getMessage();
            }
        } else {
            $error = __( 'assets.upload_error' );
        }
    }
}

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.assets.before' ); ?>

<?php if ( $success ): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>
<?php if ( $error ): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<!-- ─── Toolbar ─────────────────────────────────────────────── -->
<?php klytos_do_action( 'admin.assets.before_toolbar' ); ?>
<div class="card" id="assets-toolbar">
    <div style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;justify-content:space-between;">
        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;">
            <!-- Usage filter -->
            <select id="filter-usage" class="form-control" style="width:auto;">
                <option value="all"><?php echo __( 'common.all' ); ?></option>
                <option value="in_use"><?php echo __( 'assets.in_use' ); ?></option>
                <option value="unused"><?php echo __( 'assets.unused' ); ?></option>
            </select>

            <!-- Category filter -->
            <select id="filter-category" class="form-control" style="width:auto;">
                <option value=""><?php echo __( 'assets.all_categories' ); ?></option>
            </select>

            <!-- Type filter -->
            <select id="filter-type" class="form-control" style="width:auto;">
                <option value=""><?php echo __( 'assets.all_types' ); ?></option>
                <option value="image"><?php echo __( 'assets.type_image' ); ?></option>
                <option value="video"><?php echo __( 'assets.type_video' ); ?></option>
                <option value="application"><?php echo __( 'assets.type_document' ); ?></option>
                <option value="font"><?php echo __( 'assets.type_font' ); ?></option>
            </select>

            <!-- Search -->
            <input type="text" id="filter-search" class="form-control" placeholder="<?php echo klytos_esc_attr( __( 'common.search' ) ); ?>" style="width:180px;">
        </div>

        <div style="display:flex;gap:0.5rem;align-items:center;">
            <!-- View toggle -->
            <button type="button" class="btn btn-outline btn-sm" id="btn-view-grid" title="Grid view">&#9638;</button>
            <button type="button" class="btn btn-outline btn-sm" id="btn-view-list" title="List view">&#9776;</button>

            <!-- Actions -->
            <button type="button" class="btn btn-outline btn-sm" id="btn-manage-categories"><?php echo __( 'assets.manage_categories' ); ?></button>
            <button type="button" class="btn btn-outline btn-sm" id="btn-sync"><?php echo __( 'assets.sync' ); ?></button>
            <button type="button" class="btn btn-outline btn-sm" id="btn-rebuild-usage"><?php echo __( 'assets.rebuild_usage' ); ?></button>
            <button type="button" class="btn btn-danger btn-sm" id="btn-cleanup-unused"><?php echo __( 'assets.cleanup_unused' ); ?></button>
        </div>
    </div>
</div>
<?php klytos_do_action( 'admin.assets.after_toolbar' ); ?>

<!-- ─── Upload Zone ─────────────────────────────────────────── -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'assets.upload' ); ?></h3></div>
    <form method="post" enctype="multipart/form-data" id="upload-form">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="action" value="upload">

        <div id="drop-zone" style="border:2px dashed var(--admin-border,#555);border-radius:8px;padding:2rem;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;margin-bottom:1rem;">
            <p style="margin:0 0 0.5rem;font-size:1.1rem;" id="drop-zone-text">
                <?php echo __( 'assets.drop_zone_text' ); ?>
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
        <p class="form-help"><?php echo __( 'assets.max_size', ['size' => '10'] ); ?></p>
    </form>
</div>

<!-- ─── Asset Grid View ─────────────────────────────────────── -->
<div class="card" id="assets-grid-card" style="display:none;">
    <div class="card-header">
        <h3 id="assets-grid-title"><?php echo __( 'assets.title' ); ?></h3>
    </div>
    <div id="assets-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;padding:1rem;">
        <!-- Populated by JS -->
    </div>
    <div id="assets-grid-empty" class="empty-state" style="display:none;">
        <h3><?php echo __( 'assets.no_assets' ); ?></h3>
    </div>
    <div id="assets-pagination" style="display:flex;justify-content:center;gap:0.5rem;padding:1rem;"></div>
</div>

<!-- ─── Asset List View ─────────────────────────────────────── -->
<div class="card" id="assets-list-card" style="display:none;">
    <div class="card-header">
        <h3 id="assets-list-title"><?php echo __( 'assets.title' ); ?></h3>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:50px;"></th>
                    <th><?php echo __( 'common.name' ); ?></th>
                    <th><?php echo __( 'common.type' ); ?></th>
                    <th><?php echo __( 'common.size' ); ?></th>
                    <th><?php echo __( 'assets.categories' ); ?></th>
                    <th><?php echo __( 'assets.usages' ); ?></th>
                    <th><?php echo __( 'common.date' ); ?></th>
                    <th><?php echo __( 'common.actions' ); ?></th>
                </tr>
            </thead>
            <tbody id="assets-list-tbody">
                <!-- Populated by JS -->
            </tbody>
        </table>
    </div>
    <div id="assets-list-empty" class="empty-state" style="display:none;">
        <h3><?php echo __( 'assets.no_assets' ); ?></h3>
    </div>
    <div id="assets-list-pagination" style="display:flex;justify-content:center;gap:0.5rem;padding:1rem;"></div>
</div>

<!-- ─── Detail Panel (slide-in) ─────────────────────────────── -->
<div id="asset-detail-overlay" class="modal-overlay">
    <div class="modal" style="max-width:560px;max-height:90vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <h3 id="detail-title" style="margin:0;"><?php echo __( 'assets.detail' ); ?></h3>
            <button type="button" class="btn btn-outline btn-sm" id="detail-close">&times;</button>
        </div>

        <!-- Preview -->
        <div id="detail-preview" style="text-align:center;margin-bottom:1rem;background:var(--admin-bg);border-radius:8px;padding:1rem;min-height:120px;display:flex;align-items:center;justify-content:center;">
        </div>

        <!-- Editable fields -->
        <div class="form-group">
            <label><?php echo __( 'assets.field_title' ); ?></label>
            <input type="text" id="detail-field-title" class="form-control">
        </div>
        <div class="form-group">
            <label><?php echo __( 'assets.field_alt' ); ?></label>
            <input type="text" id="detail-field-alt" class="form-control">
        </div>
        <div class="form-group">
            <label><?php echo __( 'assets.field_description' ); ?></label>
            <textarea id="detail-field-description" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-group">
            <label><?php echo __( 'assets.categories' ); ?></label>
            <select id="detail-field-categories" class="form-control" multiple style="min-height:60px;"></select>
        </div>

        <!-- Usage table -->
        <div style="margin:1rem 0;">
            <h4 style="margin:0 0 0.5rem;"><?php echo __( 'assets.used_in' ); ?></h4>
            <div id="detail-usage" style="font-size:0.85rem;"></div>
        </div>

        <!-- Technical info -->
        <div style="margin:1rem 0;font-size:0.85rem;color:var(--admin-text-muted,#888);">
            <div id="detail-info"></div>
        </div>

        <?php klytos_do_action( 'admin.assets.detail_panel_extra' ); ?>

        <!-- Actions -->
        <div style="display:flex;gap:0.5rem;margin-top:1rem;">
            <button type="button" class="btn btn-primary" id="detail-save"><?php echo __( 'common.save' ); ?></button>
            <button type="button" class="btn btn-outline" id="detail-copy-url"><?php echo __( 'assets.copy_url' ); ?></button>
            <button type="button" class="btn btn-danger" id="detail-delete"><?php echo __( 'common.delete' ); ?></button>
        </div>
    </div>
</div>

<!-- ─── Categories Modal ────────────────────────────────────── -->
<div id="categories-modal" class="modal-overlay">
    <div class="modal" style="max-width:500px;max-height:80vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <h3 style="margin:0;"><?php echo __( 'assets.manage_categories' ); ?></h3>
            <button type="button" class="btn btn-outline btn-sm" id="categories-close">&times;</button>
        </div>

        <!-- Create category -->
        <div style="display:flex;gap:0.5rem;margin-bottom:1rem;">
            <input type="text" id="cat-new-name" class="form-control" placeholder="<?php echo klytos_esc_attr( __( 'assets.category_name' ) ); ?>" style="flex:1;">
            <input type="text" id="cat-new-desc" class="form-control" placeholder="<?php echo klytos_esc_attr( __( 'assets.category_description' ) ); ?>" style="flex:1;">
            <button type="button" class="btn btn-primary btn-sm" id="cat-create"><?php echo __( 'common.create' ); ?></button>
        </div>

        <!-- Category list -->
        <div id="categories-list"></div>
    </div>
</div>

<!-- ─── Cleanup Confirmation Modal ──────────────────────────── -->
<div id="cleanup-modal" class="modal-overlay">
    <div class="modal" style="max-width:600px;max-height:80vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <h3 style="margin:0;"><?php echo __( 'assets.cleanup_unused' ); ?></h3>
            <button type="button" class="btn btn-outline btn-sm" id="cleanup-close">&times;</button>
        </div>
        <p><?php echo __( 'assets.cleanup_confirm_text' ); ?></p>
        <div id="cleanup-preview" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:0.5rem;margin:1rem 0;max-height:300px;overflow-y:auto;"></div>
        <p id="cleanup-count" style="font-weight:600;"></p>
        <div style="display:flex;gap:0.5rem;">
            <button type="button" class="btn btn-danger" id="cleanup-confirm"><?php echo __( 'assets.cleanup_confirm' ); ?></button>
            <button type="button" class="btn btn-outline" id="cleanup-cancel"><?php echo __( 'common.cancel' ); ?></button>
        </div>
    </div>
</div>

<!-- ─── JavaScript ──────────────────────────────────────────── -->
<script nonce="<?php echo $cspNonce; ?>">
(function() {
    'use strict';

    var API_BASE   = <?php echo json_encode( $apiBase ); ?>;
    var SITE_URL   = <?php echo json_encode( $siteUrl ); ?>;
    var CSRF_TOKEN = <?php echo json_encode( $csrf ); ?>;

    var state = {
        view: 'grid',
        filter: 'all',
        category: '',
        type: '',
        search: '',
        page: 1,
        perPage: 24,
        assets: [],
        total: 0,
        pages: 0,
        categories: [],
        currentAsset: null
    };

    // ── Helpers ────────────────────────────────────────────────

    function apiGet( action, params ) {
        var url = API_BASE + '?action=' + encodeURIComponent( action );
        if ( params ) {
            Object.keys( params ).forEach( function( k ) {
                if ( params[k] !== '' && params[k] !== null && params[k] !== undefined ) {
                    url += '&' + encodeURIComponent( k ) + '=' + encodeURIComponent( params[k] );
                }
            });
        }
        return fetch( url, { credentials: 'same-origin' } ).then( function( r ) { return r.json(); } );
    }

    function apiPost( action, data ) {
        data = data || {};
        data.action = action;
        return fetch( API_BASE, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify( data )
        }).then( function( r ) { return r.json(); } );
    }

    function esc( str ) {
        var el = document.createElement( 'span' );
        el.textContent = str || '';
        return el.innerHTML;
    }

    function isImage( mime ) {
        return ( mime || '' ).indexOf( 'image/' ) === 0;
    }

    function thumbUrl( asset ) {
        if ( isImage( asset.mime_type ) ) {
            return SITE_URL + '/' + asset.path;
        }
        return '';
    }

    function shortDate( iso ) {
        if ( !iso ) return '';
        return iso.substring( 0, 10 );
    }

    // ── Load Assets ───────────────────────────────────────────

    function loadAssets() {
        apiGet( 'list', {
            filter: state.filter,
            category: state.category,
            type: state.type,
            search: state.search,
            page: state.page,
            per_page: state.perPage
        }).then( function( data ) {
            if ( !data.success ) return;
            state.assets = data.assets || [];
            state.total  = data.total || 0;
            state.pages  = data.pages || 0;
            render();
        });
    }

    function loadCategories( callback ) {
        apiGet( 'list_categories' ).then( function( data ) {
            if ( data.success ) {
                state.categories = data.categories || [];
                populateCategoryFilters();
            }
            if ( callback ) callback();
        });
    }

    // ── Render ─────────────────────────────────────────────────

    function render() {
        if ( state.view === 'grid' ) {
            renderGrid();
        } else {
            renderList();
        }
    }

    function renderGrid() {
        var gridCard = document.getElementById( 'assets-grid-card' );
        var listCard = document.getElementById( 'assets-list-card' );
        gridCard.style.display = '';
        listCard.style.display = 'none';

        var container = document.getElementById( 'assets-grid' );
        var empty     = document.getElementById( 'assets-grid-empty' );

        document.getElementById( 'assets-grid-title' ).textContent =
            <?php echo json_encode( __( 'assets.title' ) ); ?> + ' (' + state.total + ')';

        if ( state.assets.length === 0 ) {
            container.innerHTML = '';
            container.style.display = 'none';
            empty.style.display = '';
            renderPagination( 'assets-pagination' );
            return;
        }

        container.style.display = '';
        empty.style.display = 'none';

        var html = '';
        state.assets.forEach( function( asset ) {
            var thumb = thumbUrl( asset );
            var bgStyle = thumb
                ? 'background-image:url(\'' + esc( thumb ) + '\');background-size:cover;background-position:center;'
                : 'background:var(--admin-bg);display:flex;align-items:center;justify-content:center;font-size:0.75rem;color:var(--admin-text-muted,#888);';

            html += '<div class="asset-thumb" data-id="' + esc( asset.id ) + '" style="cursor:pointer;border-radius:8px;overflow:hidden;border:1px solid var(--admin-border,#333);transition:box-shadow .2s;">';
            html += '<div style="aspect-ratio:1;' + bgStyle + '">';
            if ( !thumb ) {
                html += '<span>' + esc( asset.mime_type || 'file' ) + '</span>';
            }
            html += '</div>';
            html += '<div style="padding:0.4rem 0.5rem;font-size:0.75rem;display:flex;justify-content:space-between;align-items:center;background:var(--admin-surface);">';
            html += '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;" title="' + esc( asset.filename ) + '">' + esc( asset.filename ) + '</span>';
            html += '</div>';
            html += '</div>';
        });

        container.innerHTML = html;

        container.querySelectorAll( '.asset-thumb' ).forEach( function( el ) {
            el.addEventListener( 'click', function() {
                openDetail( el.getAttribute( 'data-id' ) );
            });
        });

        renderPagination( 'assets-pagination' );
    }

    function renderList() {
        var gridCard = document.getElementById( 'assets-grid-card' );
        var listCard = document.getElementById( 'assets-list-card' );
        gridCard.style.display = 'none';
        listCard.style.display = '';

        var tbody = document.getElementById( 'assets-list-tbody' );
        var empty = document.getElementById( 'assets-list-empty' );

        document.getElementById( 'assets-list-title' ).textContent =
            <?php echo json_encode( __( 'assets.title' ) ); ?> + ' (' + state.total + ')';

        if ( state.assets.length === 0 ) {
            tbody.innerHTML = '';
            empty.style.display = '';
            renderPagination( 'assets-list-pagination' );
            return;
        }

        empty.style.display = 'none';

        var html = '';
        state.assets.forEach( function( asset ) {
            var thumb = thumbUrl( asset );
            var cats  = ( asset.categories || [] ).join( ', ' ) || '—';

            html += '<tr style="cursor:pointer;" data-id="' + esc( asset.id ) + '">';
            html += '<td>';
            if ( thumb ) {
                html += '<img src="' + esc( thumb ) + '" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">';
            } else {
                html += '<div style="width:40px;height:40px;background:var(--admin-bg);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:0.6rem;color:var(--admin-text-muted,#888);">FILE</div>';
            }
            html += '</td>';
            html += '<td class="mono" style="font-size:0.8rem;">' + esc( asset.filename ) + '</td>';
            html += '<td>' + esc( asset.mime_type ) + '</td>';
            html += '<td>' + esc( asset.size_human ) + '</td>';
            html += '<td>' + esc( cats ) + '</td>';
            html += '<td>—</td>';
            html += '<td>' + shortDate( asset.uploaded_at ) + '</td>';
            html += '<td><button type="button" class="btn btn-outline btn-sm btn-detail">' + <?php echo json_encode( __( 'common.view' ) ); ?> + '</button></td>';
            html += '</tr>';
        });

        tbody.innerHTML = html;

        tbody.querySelectorAll( 'tr[data-id]' ).forEach( function( row ) {
            row.addEventListener( 'click', function() {
                openDetail( row.getAttribute( 'data-id' ) );
            });
        });

        renderPagination( 'assets-list-pagination' );
    }

    function renderPagination( containerId ) {
        var container = document.getElementById( containerId );
        if ( !container ) return;
        if ( state.pages <= 1 ) {
            container.innerHTML = '';
            return;
        }

        var html = '';
        for ( var i = 1; i <= state.pages; i++ ) {
            var cls = i === state.page ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm';
            html += '<button type="button" class="' + cls + ' btn-page" data-page="' + i + '">' + i + '</button>';
        }
        container.innerHTML = html;

        container.querySelectorAll( '.btn-page' ).forEach( function( btn ) {
            btn.addEventListener( 'click', function() {
                state.page = parseInt( btn.getAttribute( 'data-page' ), 10 );
                loadAssets();
            });
        });
    }

    // ── Detail Panel ──────────────────────────────────────────

    function openDetail( assetId ) {
        apiGet( 'get', { id: assetId } ).then( function( data ) {
            if ( !data.success || !data.asset ) return;

            state.currentAsset = data.asset;
            var a = data.asset;

            // Preview.
            var preview = document.getElementById( 'detail-preview' );
            if ( isImage( a.mime_type ) ) {
                preview.innerHTML = '<img src="' + esc( SITE_URL + '/' + a.path ) + '" alt="" style="max-width:100%;max-height:300px;border-radius:4px;">';
            } else {
                preview.innerHTML = '<div style="padding:2rem;font-size:0.9rem;color:var(--admin-text-muted,#888);">' + esc( a.mime_type ) + '<br>' + esc( a.filename ) + '</div>';
            }

            // Fields.
            document.getElementById( 'detail-field-title' ).value       = a.title || '';
            document.getElementById( 'detail-field-alt' ).value         = a.alt_text || '';
            document.getElementById( 'detail-field-description' ).value = a.description || '';
            document.getElementById( 'detail-title' ).textContent       = a.filename || '';

            // Categories select.
            var catSelect = document.getElementById( 'detail-field-categories' );
            catSelect.innerHTML = '';
            state.categories.forEach( function( cat ) {
                var opt = document.createElement( 'option' );
                opt.value = cat.id;
                opt.textContent = cat.name;
                if ( ( a.categories || [] ).indexOf( cat.id ) !== -1 ) {
                    opt.selected = true;
                }
                catSelect.appendChild( opt );
            });

            // Usage table.
            var usageDiv = document.getElementById( 'detail-usage' );
            var usages   = a.usage || [];
            if ( usages.length === 0 ) {
                usageDiv.innerHTML = '<p style="color:var(--admin-text-muted,#888);">' + <?php echo json_encode( __( 'assets.not_in_use' ) ); ?> + '</p>';
            } else {
                var uHtml = '<table style="width:100%;font-size:0.8rem;"><thead><tr><th>' + <?php echo json_encode( __( 'common.type' ) ); ?> + '</th><th>' + <?php echo json_encode( __( 'assets.context' ) ); ?> + '</th><th>' + <?php echo json_encode( __( 'assets.field' ) ); ?> + '</th></tr></thead><tbody>';
                usages.forEach( function( u ) {
                    uHtml += '<tr><td>' + esc( u.context_type ) + '</td><td>' + esc( u.context_label || u.context_id ) + '</td><td>' + esc( u.field ) + '</td></tr>';
                });
                uHtml += '</tbody></table>';
                usageDiv.innerHTML = uHtml;
            }

            // Technical info.
            var infoHtml = '';
            infoHtml += '<div><strong>Path:</strong> ' + esc( a.path ) + '</div>';
            infoHtml += '<div><strong>Size:</strong> ' + esc( a.size_human ) + '</div>';
            infoHtml += '<div><strong>MIME:</strong> ' + esc( a.mime_type ) + '</div>';
            infoHtml += '<div><strong>Uploaded:</strong> ' + shortDate( a.uploaded_at ) + '</div>';
            infoHtml += '<div><strong>By:</strong> ' + esc( a.uploaded_by ) + '</div>';
            document.getElementById( 'detail-info' ).innerHTML = infoHtml;

            // Show overlay.
            document.getElementById( 'asset-detail-overlay' ).classList.add( 'active' );
        });
    }

    function closeDetail() {
        document.getElementById( 'asset-detail-overlay' ).classList.remove( 'active' );
        state.currentAsset = null;
    }

    function saveDetail() {
        if ( !state.currentAsset ) return;

        var catSelect = document.getElementById( 'detail-field-categories' );
        var cats = [];
        for ( var i = 0; i < catSelect.options.length; i++ ) {
            if ( catSelect.options[i].selected ) {
                cats.push( catSelect.options[i].value );
            }
        }

        apiPost( 'update', {
            id:          state.currentAsset.id,
            title:       document.getElementById( 'detail-field-title' ).value,
            alt_text:    document.getElementById( 'detail-field-alt' ).value,
            description: document.getElementById( 'detail-field-description' ).value,
            categories:  cats
        }).then( function( data ) {
            if ( data.success ) {
                closeDetail();
                loadAssets();
            }
        });
    }

    function deleteFromDetail() {
        if ( !state.currentAsset ) return;

        var usages = state.currentAsset.usage || [];
        var msg = usages.length > 0
            ? <?php echo json_encode( __( 'assets.confirm_delete_in_use' ) ); ?> + ' (' + usages.length + ' ' + <?php echo json_encode( __( 'assets.usages' ) ); ?> + ')'
            : <?php echo json_encode( __( 'assets.confirm_delete_asset' ) ); ?>;

        if ( !confirm( msg ) ) return;

        apiPost( 'delete', { id: state.currentAsset.id } ).then( function( data ) {
            if ( data.success ) {
                closeDetail();
                loadAssets();
            }
        });
    }

    function copyUrl() {
        if ( !state.currentAsset ) return;
        var url = SITE_URL + '/' + state.currentAsset.path;
        if ( navigator.clipboard ) {
            navigator.clipboard.writeText( url );
        }
    }

    // ── Categories Modal ──────────────────────────────────────

    function openCategories() {
        loadCategories( function() {
            renderCategoryList();
            document.getElementById( 'categories-modal' ).classList.add( 'active' );
        });
    }

    function closeCategories() {
        document.getElementById( 'categories-modal' ).classList.remove( 'active' );
    }

    function renderCategoryList() {
        var container = document.getElementById( 'categories-list' );
        if ( state.categories.length === 0 ) {
            container.innerHTML = '<p style="color:var(--admin-text-muted,#888);">' + <?php echo json_encode( __( 'assets.no_categories' ) ); ?> + '</p>';
            return;
        }

        var html = '<table style="width:100%;font-size:0.85rem;"><thead><tr><th>' + <?php echo json_encode( __( 'common.name' ) ); ?> + '</th><th>' + <?php echo json_encode( __( 'assets.asset_count' ) ); ?> + '</th><th>' + <?php echo json_encode( __( 'common.actions' ) ); ?> + '</th></tr></thead><tbody>';
        state.categories.forEach( function( cat ) {
            html += '<tr>';
            html += '<td><strong>' + esc( cat.name ) + '</strong>';
            if ( cat.description ) html += '<br><small style="color:var(--admin-text-muted,#888);">' + esc( cat.description ) + '</small>';
            html += '</td>';
            html += '<td>' + ( cat.asset_count || 0 ) + '</td>';
            html += '<td><button type="button" class="btn btn-danger btn-sm btn-cat-delete" data-id="' + esc( cat.id ) + '">' + <?php echo json_encode( __( 'common.delete' ) ); ?> + '</button></td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
        container.innerHTML = html;

        container.querySelectorAll( '.btn-cat-delete' ).forEach( function( btn ) {
            btn.addEventListener( 'click', function() {
                var catId = btn.getAttribute( 'data-id' );
                if ( confirm( <?php echo json_encode( __( 'assets.confirm_delete_category' ) ); ?> ) ) {
                    apiPost( 'delete_category', { id: catId } ).then( function() {
                        loadCategories( renderCategoryList );
                    });
                }
            });
        });
    }

    function createCategory() {
        var name = document.getElementById( 'cat-new-name' ).value.trim();
        var desc = document.getElementById( 'cat-new-desc' ).value.trim();
        if ( !name ) return;

        apiPost( 'create_category', { name: name, description: desc } ).then( function( data ) {
            if ( data.success ) {
                document.getElementById( 'cat-new-name' ).value = '';
                document.getElementById( 'cat-new-desc' ).value = '';
                loadCategories( renderCategoryList );
            } else if ( data.error ) {
                alert( data.error );
            }
        });
    }

    // ── Cleanup Modal ─────────────────────────────────────────

    function openCleanup() {
        apiGet( 'list', { filter: 'unused', per_page: 100 } ).then( function( data ) {
            if ( !data.success ) return;

            var assets = data.assets || [];
            var preview = document.getElementById( 'cleanup-preview' );
            var count   = document.getElementById( 'cleanup-count' );

            if ( assets.length === 0 ) {
                preview.innerHTML = '';
                count.textContent = <?php echo json_encode( __( 'assets.no_unused' ) ); ?>;
                document.getElementById( 'cleanup-confirm' ).style.display = 'none';
            } else {
                var html = '';
                assets.forEach( function( asset ) {
                    var thumb = thumbUrl( asset );
                    if ( thumb ) {
                        html += '<img src="' + esc( thumb ) + '" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:4px;">';
                    } else {
                        html += '<div style="width:80px;height:80px;background:var(--admin-bg);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:0.6rem;color:var(--admin-text-muted,#888);">' + esc( asset.filename ) + '</div>';
                    }
                });
                preview.innerHTML = html;
                count.textContent = assets.length + ' ' + <?php echo json_encode( __( 'assets.files_to_delete' ) ); ?>;
                document.getElementById( 'cleanup-confirm' ).style.display = '';
            }

            document.getElementById( 'cleanup-modal' ).classList.add( 'active' );
        });
    }

    function confirmCleanup() {
        apiGet( 'list', { filter: 'unused', per_page: 500 } ).then( function( data ) {
            if ( !data.success ) return;
            var ids = ( data.assets || [] ).map( function( a ) { return a.id; } );
            if ( ids.length === 0 ) return;

            apiPost( 'bulk_delete', { ids: ids } ).then( function( res ) {
                document.getElementById( 'cleanup-modal' ).classList.remove( 'active' );
                loadAssets();
            });
        });
    }

    // ── Sync & Rebuild ────────────────────────────────────────

    function syncAssets() {
        apiPost( 'sync' ).then( function( data ) {
            if ( data.success ) {
                alert( <?php echo json_encode( __( 'assets.sync_done' ) ); ?> + ': ' + ( data.synced || 0 ) );
                loadAssets();
            }
        });
    }

    function rebuildUsage() {
        apiPost( 'rebuild_usage' ).then( function( data ) {
            if ( data.success ) {
                var s = data.stats || {};
                alert( <?php echo json_encode( __( 'assets.rebuild_done' ) ); ?> + ': ' + ( s.scanned_pages || 0 ) + ' pages, ' + ( s.usages_found || 0 ) + ' usages' );
            }
        });
    }

    // ── Category filter population ────────────────────────────

    function populateCategoryFilters() {
        var select = document.getElementById( 'filter-category' );
        var current = select.value;
        // Keep first option.
        while ( select.options.length > 1 ) {
            select.remove( 1 );
        }
        state.categories.forEach( function( cat ) {
            var opt = document.createElement( 'option' );
            opt.value = cat.id;
            opt.textContent = cat.name + ' (' + ( cat.asset_count || 0 ) + ')';
            select.appendChild( opt );
        });
        select.value = current;
    }

    // ── View Toggle ───────────────────────────────────────────

    function setView( view ) {
        state.view = view;
        document.getElementById( 'btn-view-grid' ).classList.toggle( 'btn-primary', view === 'grid' );
        document.getElementById( 'btn-view-grid' ).classList.toggle( 'btn-outline', view !== 'grid' );
        document.getElementById( 'btn-view-list' ).classList.toggle( 'btn-primary', view === 'list' );
        document.getElementById( 'btn-view-list' ).classList.toggle( 'btn-outline', view !== 'list' );
        render();
    }

    // ── Event Listeners ───────────────────────────────────────

    // Filter changes.
    document.getElementById( 'filter-usage' ).addEventListener( 'change', function() {
        state.filter = this.value;
        state.page = 1;
        loadAssets();
    });

    document.getElementById( 'filter-category' ).addEventListener( 'change', function() {
        state.category = this.value;
        state.page = 1;
        loadAssets();
    });

    document.getElementById( 'filter-type' ).addEventListener( 'change', function() {
        state.type = this.value;
        state.page = 1;
        loadAssets();
    });

    var searchTimer = null;
    document.getElementById( 'filter-search' ).addEventListener( 'input', function() {
        var val = this.value;
        clearTimeout( searchTimer );
        searchTimer = setTimeout( function() {
            state.search = val;
            state.page = 1;
            loadAssets();
        }, 300 );
    });

    // View toggles.
    document.getElementById( 'btn-view-grid' ).addEventListener( 'click', function() { setView( 'grid' ); });
    document.getElementById( 'btn-view-list' ).addEventListener( 'click', function() { setView( 'list' ); });

    // Toolbar actions.
    document.getElementById( 'btn-manage-categories' ).addEventListener( 'click', openCategories );
    document.getElementById( 'btn-sync' ).addEventListener( 'click', syncAssets );
    document.getElementById( 'btn-rebuild-usage' ).addEventListener( 'click', rebuildUsage );
    document.getElementById( 'btn-cleanup-unused' ).addEventListener( 'click', openCleanup );

    // Detail panel.
    document.getElementById( 'detail-close' ).addEventListener( 'click', closeDetail );
    document.getElementById( 'detail-save' ).addEventListener( 'click', saveDetail );
    document.getElementById( 'detail-delete' ).addEventListener( 'click', deleteFromDetail );
    document.getElementById( 'detail-copy-url' ).addEventListener( 'click', copyUrl );

    // Categories modal.
    document.getElementById( 'categories-close' ).addEventListener( 'click', closeCategories );
    document.getElementById( 'cat-create' ).addEventListener( 'click', createCategory );

    // Cleanup modal.
    document.getElementById( 'cleanup-close' ).addEventListener( 'click', function() {
        document.getElementById( 'cleanup-modal' ).classList.remove( 'active' );
    });
    document.getElementById( 'cleanup-cancel' ).addEventListener( 'click', function() {
        document.getElementById( 'cleanup-modal' ).classList.remove( 'active' );
    });
    document.getElementById( 'cleanup-confirm' ).addEventListener( 'click', confirmCleanup );

    // Close overlays on backdrop click.
    ['asset-detail-overlay', 'categories-modal', 'cleanup-modal'].forEach( function( id ) {
        document.getElementById( id ).addEventListener( 'click', function( e ) {
            if ( e.target === this ) {
                this.classList.remove( 'active' );
            }
        });
    });

    // Drag & drop upload.
    var dropZone  = document.getElementById( 'drop-zone' );
    var fileInput = document.getElementById( 'file-input' );
    var fileLabel = document.getElementById( 'drop-zone-file' );

    if ( dropZone && fileInput ) {
        dropZone.addEventListener( 'click', function() { fileInput.click(); } );

        fileInput.addEventListener( 'change', function() {
            if ( fileInput.files.length ) {
                fileLabel.textContent = fileInput.files[0].name;
            }
        });

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach( function( evt ) {
            dropZone.addEventListener( evt, function( e ) {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        ['dragenter', 'dragover'].forEach( function( evt ) {
            dropZone.addEventListener( evt, function() {
                dropZone.style.borderColor = 'var(--admin-primary,#4f8cff)';
                dropZone.style.background  = 'rgba(79,140,255,0.08)';
            });
        });

        ['dragleave', 'drop'].forEach( function( evt ) {
            dropZone.addEventListener( evt, function() {
                dropZone.style.borderColor = '';
                dropZone.style.background  = '';
            });
        });

        dropZone.addEventListener( 'drop', function( e ) {
            var files = e.dataTransfer.files;
            if ( files.length ) {
                fileInput.files = files;
                fileLabel.textContent = files[0].name;
            }
        });
    }

    // ── Init ──────────────────────────────────────────────────

    setView( 'grid' );
    loadCategories( function() {
        loadAssets();
    });

})();
</script>

<?php klytos_do_action( 'admin.assets.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
