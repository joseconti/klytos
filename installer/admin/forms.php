<?php

/**
 * Klytos Admin — Forms
 * List, create, duplicate, and delete forms.
 *
 * @package Klytos
 * @since   0.20.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';

$pageTitle = 'Formularios';
$fm        = $app->getFormManager();
$error     = '';
$success   = '';

// ─── Handle POST actions ────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $postAction = $_POST['action'] ?? '';

    if ( $postAction === 'delete' ) {
        $deleteId      = $_POST['id'] ?? '';
        $deleteEntries = !empty( $_POST['delete_entries'] );
        if ( $fm->deleteForm( $deleteId, $deleteEntries ) ) {
            $success = 'Formulario eliminado correctamente.';
        } else {
            $error = 'No se pudo eliminar el formulario.';
        }
    }

    if ( $postAction === 'duplicate' ) {
        try {
            $fm->duplicateForm( $_POST['id'] ?? '' );
            $success = 'Formulario duplicado correctamente.';
        } catch ( \Throwable $e ) {
            $error = $e->getMessage();
        }
    }
}

// ─── Filters ────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? null;
$allForms     = $fm->listForms( $filterStatus );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.forms.before' ); ?>

<?php if ( !empty( $error ) ): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<?php if ( !empty( $success ) ): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <div style="display:flex;gap:0.5rem;">
        <a href="?status=" class="btn btn-sm <?php echo $filterStatus === null ? 'btn-primary' : 'btn-outline'; ?>">Todos</a>
        <a href="?status=active" class="btn btn-sm <?php echo $filterStatus === 'active' ? 'btn-primary' : 'btn-outline'; ?>">Activos</a>
        <a href="?status=inactive" class="btn btn-sm <?php echo $filterStatus === 'inactive' ? 'btn-primary' : 'btn-outline'; ?>">Inactivos</a>
    </div>
    <a href="form-editor.php" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nuevo formulario
    </a>
</div>

<?php if ( empty( $allForms ) ): ?>
    <div class="card" style="padding:3rem;text-align:center;color:var(--admin-text-muted);">
        <p>No hay formularios todavia.</p>
        <p>Crea uno desde aqui o via MCP con <code>forms_create</code>.</p>
    </div>
<?php else: ?>
    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Titulo</th>
                    <th>Estado</th>
                    <th>Campos</th>
                    <th>Entradas</th>
                    <th>Shortcode</th>
                    <th style="text-align:right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $allForms as $form ):
                    $stats     = $fm->getFormStats( $form['id'] );
                    $isActive  = ( $form['status'] ?? '' ) === 'active';
                ?>
                <tr>
                    <td>
                        <a href="form-editor.php?id=<?php echo urlencode( $form['id'] ); ?>" style="font-weight:500;">
                            <?php echo klytos_esc_html( $form['title'] ?? $form['id'] ); ?>
                        </a>
                    </td>
                    <td>
                        <span class="badge <?php echo $isActive ? 'badge-success' : 'badge-muted'; ?>">
                            <?php echo $isActive ? 'Activo' : 'Inactivo'; ?>
                        </span>
                    </td>
                    <td><?php echo count( $form['fields'] ?? [] ); ?></td>
                    <td>
                        <a href="form-entries.php?form_id=<?php echo urlencode( $form['id'] ); ?>">
                            <?php echo $stats['total']; ?>
                            <?php if ( $stats['unread'] > 0 ): ?>
                                <span class="badge badge-primary"><?php echo $stats['unread']; ?> nuevas</span>
                            <?php endif; ?>
                        </a>
                    </td>
                    <td>
                        <code style="font-size:0.8rem;cursor:pointer;" title="Haz clic para copiar" data-copy="{{form:<?php echo klytos_esc_html( $form['id'] ); ?>}}">
                            {{form:<?php echo klytos_esc_html( $form['id'] ); ?>}}
                        </code>
                    </td>
                    <td style="text-align:right">
                        <div style="display:flex;gap:0.25rem;justify-content:flex-end;">
                            <a href="form-editor.php?id=<?php echo urlencode( $form['id'] ); ?>" class="btn btn-sm btn-outline" title="Editar">Editar</a>
                            <form method="post" style="display:inline;">
                                <?php echo klytos_csrf_field(); ?>
                                <input type="hidden" name="action" value="duplicate">
                                <input type="hidden" name="id" value="<?php echo klytos_esc_html( $form['id'] ); ?>">
                                <button type="submit" class="btn btn-sm btn-outline" title="Duplicar">Duplicar</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <?php echo klytos_csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo klytos_esc_html( $form['id'] ); ?>">
                                <button type="submit" class="btn btn-sm btn-danger" title="Eliminar" data-confirm="Eliminar este formulario?">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('[data-copy]').forEach(function(el) {
    el.addEventListener('click', function() {
        navigator.clipboard.writeText(el.dataset.copy);
        el.style.background = '#d1fae5';
        setTimeout(function() { el.style.background = ''; }, 1000);
    });
});
document.querySelectorAll('[data-confirm]').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
});
</script>

<?php klytos_do_action( 'admin.forms.after' ); ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
