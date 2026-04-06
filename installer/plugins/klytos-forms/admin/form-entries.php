<?php

/**
 * Klytos Admin — Form Entries
 * View, filter, search, and manage form submissions.
 *
 * @package Klytos
 * @since   0.20.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare( strict_types=1 );

$pageTitle = 'Entradas de formularios';
$fm        = klytos_forms();
$error     = '';
$success   = '';

// ─── Get available forms ────────────────────────────────────
$allForms   = $fm->listForms();
$formId     = $_GET['form_id'] ?? ( !empty( $allForms ) ? $allForms[0]['id'] : '' );
$currentForm = $formId ? $fm->getForm( $formId ) : null;

// ─── Handle POST actions ────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $postAction = $_POST['action'] ?? '';
    $entryId    = $_POST['entry_id'] ?? '';

    if ( $postAction === 'delete_entry' && !empty( $entryId ) ) {
        if ( $fm->deleteEntry( $entryId ) ) {
            $success = 'Entrada eliminada.';
        }
    }

    if ( $postAction === 'mark_read' && !empty( $entryId ) ) {
        $fm->updateEntryStatus( $entryId, 'read' );
        $success = 'Marcada como leida.';
    }

    if ( $postAction === 'mark_starred' && !empty( $entryId ) ) {
        $fm->updateEntryStatus( $entryId, 'starred' );
        $success = 'Marcada como importante.';
    }

    if ( $postAction === 'delete_all' && !empty( $formId ) ) {
        $count   = $fm->deleteEntriesByForm( $formId );
        $success = "{$count} entradas eliminadas.";
    }
}

// ─── Filters ────────────────────────────────────────────────
$filters = [
    'status'   => $_GET['status'] ?? null,
    'search'   => $_GET['search'] ?? null,
    'date_from' => $_GET['date_from'] ?? null,
    'date_to'  => $_GET['date_to'] ?? null,
    'page'     => $_GET['page'] ?? 1,
    'per_page' => 20,
];
$filters = array_filter( $filters, fn( $v ) => $v !== null && $v !== '' );

$result  = $formId ? $fm->listEntries( $formId, $filters ) : ['entries' => [], 'total' => 0, 'page' => 1, 'pages' => 0];
$entries = $result['entries'];
$stats   = $formId ? $fm->getFormStats( $formId ) : [];

?>
<?php klytos_do_action( 'admin.form_entries.before' ); ?>

<?php if ( !empty( $error ) ): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<?php if ( !empty( $success ) ): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>

<!-- Form selector + stats -->
<div style="display:flex;gap:1rem;align-items:flex-start;margin-bottom:1.5rem;flex-wrap:wrap;">
    <div>
        <label style="font-weight:500;display:block;margin-bottom:0.25rem;">Formulario</label>
        <select id="formSelector" class="form-control" style="min-width:250px;">
            <?php foreach ( $allForms as $f ): ?>
                <option value="<?php echo klytos_esc_html( $f['id'] ); ?>" <?php echo $f['id'] === $formId ? 'selected' : ''; ?>>
                    <?php echo klytos_esc_html( $f['title'] ?? $f['id'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ( !empty( $stats ) ): ?>
    <div style="display:flex;gap:1rem;align-items:center;padding-top:1.25rem;">
        <span class="badge badge-primary"><?php echo $stats['total']; ?> total</span>
        <?php if ( $stats['unread'] > 0 ): ?>
            <span class="badge badge-warning"><?php echo $stats['unread']; ?> nuevas</span>
        <?php endif; ?>
        <span class="badge badge-muted"><?php echo $stats['today']; ?> hoy</span>
        <span class="badge badge-muted"><?php echo $stats['week']; ?> esta semana</span>
    </div>
    <?php endif; ?>
</div>

<!-- Filters -->
<form method="get" style="display:flex;gap:0.5rem;margin-bottom:1rem;flex-wrap:wrap;align-items:flex-end;">
    <input type="hidden" name="plugin" value="klytos-forms">
    <input type="hidden" name="page" value="form-entries">
    <input type="hidden" name="form_id" value="<?php echo klytos_esc_html( $formId ); ?>">

    <div>
        <label style="font-size:0.85rem;">Estado</label>
        <select name="status" class="form-control form-control-sm">
            <option value="">Todos</option>
            <option value="unread" <?php echo ( $filters['status'] ?? '' ) === 'unread' ? 'selected' : ''; ?>>No leidas</option>
            <option value="read" <?php echo ( $filters['status'] ?? '' ) === 'read' ? 'selected' : ''; ?>>Leidas</option>
            <option value="starred" <?php echo ( $filters['status'] ?? '' ) === 'starred' ? 'selected' : ''; ?>>Importantes</option>
            <option value="trash" <?php echo ( $filters['status'] ?? '' ) === 'trash' ? 'selected' : ''; ?>>Papelera</option>
        </select>
    </div>

    <div>
        <label style="font-size:0.85rem;">Buscar</label>
        <input type="text" name="search" class="form-control form-control-sm" value="<?php echo klytos_esc_html( $filters['search'] ?? '' ); ?>" placeholder="Buscar en datos...">
    </div>

    <div>
        <label style="font-size:0.85rem;">Desde</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo klytos_esc_html( $filters['date_from'] ?? '' ); ?>">
    </div>

    <div>
        <label style="font-size:0.85rem;">Hasta</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo klytos_esc_html( $filters['date_to'] ?? '' ); ?>">
    </div>

    <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
    <a href="plugin-page.php?plugin=klytos-forms&page=form-entries&form_id=<?php echo urlencode( $formId ); ?>" class="btn btn-sm btn-outline">Limpiar</a>
</form>

<!-- Actions bar -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
    <span style="color:var(--admin-text-muted);font-size:0.9rem;">
        <?php echo $result['total']; ?> entradas (pagina <?php echo $result['page']; ?>/<?php echo max( 1, $result['pages'] ); ?>)
    </span>
    <div style="display:flex;gap:0.5rem;">
        <a href="api/forms.php?action=export&form_id=<?php echo urlencode( $formId ); ?>&format=csv&_csrf_token=<?php echo urlencode( $_SESSION['csrf_token'] ?? '' ); ?>" class="btn btn-sm btn-outline">Exportar CSV</a>
        <form method="post" style="display:inline;">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="action" value="delete_all">
            <button type="submit" class="btn btn-sm btn-danger" data-confirm="Eliminar TODAS las entradas de este formulario?">Eliminar todas</button>
        </form>
    </div>
</div>

<!-- Entries table -->
<?php if ( empty( $entries ) ): ?>
    <div class="card" style="padding:3rem;text-align:center;color:var(--admin-text-muted);">
        No hay entradas <?php echo !empty( $filters['status'] ) ? 'con este filtro' : 'todavia'; ?>.
    </div>
<?php else: ?>
    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <?php
                    // Show first 3 data fields as columns
                    $displayFields = [];
                    if ( $currentForm ) {
                        foreach ( $currentForm['fields'] as $field ) {
                            if ( in_array( $field['type'], ['html', 'section', 'hidden'] ) ) continue;
                            $displayFields[] = $field;
                            if ( count( $displayFields ) >= 3 ) break;
                        }
                    }
                    foreach ( $displayFields as $df ):
                    ?>
                        <th><?php echo klytos_esc_html( $df['label'] ?: $df['id'] ); ?></th>
                    <?php endforeach; ?>
                    <th>Estado</th>
                    <th style="text-align:right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $entries as $entry ): ?>
                <tr>
                    <td><code style="font-size:0.8rem;"><?php echo klytos_esc_html( $entry['id'] ); ?></code></td>
                    <td style="white-space:nowrap;font-size:0.9rem;">
                        <?php echo klytos_esc_html( substr( $entry['metadata']['submitted_at'] ?? $entry['created_at'] ?? '', 0, 16 ) ); ?>
                    </td>
                    <?php foreach ( $displayFields as $df ):
                        $val = $entry['data'][$df['id']] ?? '';
                        if ( is_array( $val ) ) $val = implode( ', ', $val );
                        $val = mb_substr( (string) $val, 0, 50 );
                    ?>
                        <td style="font-size:0.9rem;"><?php echo klytos_esc_html( $val ); ?></td>
                    <?php endforeach; ?>
                    <td>
                        <?php
                        $statusLabels = ['unread' => 'Nueva', 'read' => 'Leida', 'starred' => 'Importante', 'trash' => 'Papelera'];
                        $statusClass  = ['unread' => 'badge-warning', 'read' => 'badge-muted', 'starred' => 'badge-primary', 'trash' => 'badge-danger'];
                        $st = $entry['status'] ?? 'unread';
                        ?>
                        <span class="badge <?php echo $statusClass[$st] ?? 'badge-muted'; ?>">
                            <?php echo $statusLabels[$st] ?? $st; ?>
                        </span>
                    </td>
                    <td style="text-align:right">
                        <div style="display:flex;gap:0.25rem;justify-content:flex-end;">
                            <?php if ( $st === 'unread' ): ?>
                            <form method="post" style="display:inline;">
                                <?php echo klytos_csrf_field(); ?>
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="entry_id" value="<?php echo klytos_esc_html( $entry['id'] ); ?>">
                                <button type="submit" class="btn btn-sm btn-outline" title="Marcar leida">Leida</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" style="display:inline;">
                                <?php echo klytos_csrf_field(); ?>
                                <input type="hidden" name="action" value="mark_starred">
                                <input type="hidden" name="entry_id" value="<?php echo klytos_esc_html( $entry['id'] ); ?>">
                                <button type="submit" class="btn btn-sm btn-outline" title="Importante">&#9733;</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <?php echo klytos_csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_entry">
                                <input type="hidden" name="entry_id" value="<?php echo klytos_esc_html( $entry['id'] ); ?>">
                                <button type="submit" class="btn btn-sm btn-danger" data-confirm="Eliminar esta entrada?">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ( $result['pages'] > 1 ): ?>
    <div style="display:flex;justify-content:center;gap:0.25rem;margin-top:1rem;">
        <?php for ( $p = 1; $p <= $result['pages']; $p++ ):
            $isCurrentPage = $p === (int) $result['page'];
            $params = array_merge( $_GET, ['page' => $p] );
        ?>
            <a href="plugin-page.php?<?php echo http_build_query( $params ); ?>" class="btn btn-sm <?php echo $isCurrentPage ? 'btn-primary' : 'btn-outline'; ?>">
                <?php echo $p; ?>
            </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
document.getElementById('formSelector').addEventListener('change', function() {
    window.location.href = 'plugin-page.php?plugin=klytos-forms&page=form-entries&form_id=' + encodeURIComponent(this.value);
});
document.querySelectorAll('[data-confirm]').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
});
</script>

<?php klytos_do_action( 'admin.form_entries.after' ); ?>
