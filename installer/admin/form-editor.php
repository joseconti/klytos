<?php

/**
 * Klytos Admin — Form Editor
 * Create and edit forms with tabs: Fields, Settings, Notifications, Anti-spam, Insert.
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

use Klytos\Core\Helpers;

$fm      = $app->getFormManager();
$formId  = $_GET['id'] ?? '';
$isNew   = empty( $formId );
$error   = '';
$success = '';
$form    = null;

// ─── Load existing form or prepare new ──────────────────────
if ( !$isNew ) {
    $form = $fm->getForm( $formId );
    if ( !$form ) {
        $error = "Formulario '{$formId}' no encontrado.";
    }
}

$pageTitle = $isNew ? 'Nuevo formulario' : 'Editar: ' . ( $form['title'] ?? $formId );

// ─── Handle POST (save form) ───────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $postAction = $_POST['action'] ?? '';

    if ( $postAction === 'save' ) {
        $jsonData = $_POST['form_json'] ?? '{}';
        $formData = json_decode( $jsonData, true );

        if ( !$formData ) {
            $error = 'Error al decodificar los datos del formulario.';
        } else {
            try {
                if ( $isNew ) {
                    $form    = $fm->createForm( $formData );
                    $formId  = $form['id'];
                    $isNew   = false;
                    $success = 'Formulario creado correctamente.';
                } else {
                    $form    = $fm->updateForm( $formId, $formData );
                    $success = 'Formulario actualizado correctamente.';
                }
            } catch ( \Throwable $e ) {
                $error = $e->getMessage();
            }
        }
    }
}

// Prepare form JSON for the editor
$formJson = json_encode( $form ?? [
    'id'            => '',
    'title'         => '',
    'description'   => '',
    'status'        => 'active',
    'fields'        => [],
    'settings'      => [
        'submit_label'    => 'Enviar',
        'success_message' => 'Formulario enviado correctamente.',
        'success_action'  => 'message',
        'success_redirect' => '',
        'enable_ajax'     => true,
        'css_class'       => '',
        'layout'          => 'stacked',
        'steps'           => [ ['step' => 1, 'title' => ''] ],
    ],
    'notifications' => [],
    'anti_spam'     => [
        'honeypot'          => true,
        'rate_limit'        => 3,
        'rate_limit_window' => 60,
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.form_editor.before', $form ); ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
    <a href="forms.php" class="btn btn-outline btn-sm">&larr; Volver a formularios</a>
    <button type="button" id="formSaveBtn" class="btn btn-primary">Guardar formulario</button>
</div>

<?php if ( !empty( $error ) ): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<?php if ( !empty( $success ) ): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>

<!-- Tab Navigation -->
<div class="tabs" style="margin-bottom:1.5rem;">
    <button class="tab-btn active" data-tab="fields">Campos</button>
    <button class="tab-btn" data-tab="settings">Configuracion</button>
    <button class="tab-btn" data-tab="notifications">Notificaciones</button>
    <button class="tab-btn" data-tab="antispam">Anti-spam</button>
    <?php if ( !$isNew ): ?>
    <button class="tab-btn" data-tab="insert">Insertar</button>
    <?php endif; ?>
</div>

<!-- Tab: Fields -->
<div class="tab-content active" id="tab-fields">
    <div class="card">
        <div class="card-header">
            <h3>Campos del formulario</h3>
            <button type="button" id="addFieldBtn" class="btn btn-sm btn-primary">Anadir campo</button>
        </div>
        <div id="fieldsContainer" style="min-height:100px;padding:1rem;">
            <p style="color:var(--admin-text-muted);text-align:center;" id="noFieldsMsg">
                No hay campos. Haz clic en "Anadir campo" o crea campos via MCP.
            </p>
        </div>
    </div>
</div>

<!-- Tab: Settings -->
<div class="tab-content" id="tab-settings" style="display:none;">
    <div class="card" style="padding:1.5rem;">
        <div class="form-group">
            <label>Titulo del formulario</label>
            <input type="text" id="settFormTitle" class="form-control" placeholder="Ej: Formulario de contacto">
        </div>
        <div class="form-group">
            <label>Descripcion</label>
            <textarea id="settFormDesc" class="form-control" rows="2" placeholder="Descripcion opcional"></textarea>
        </div>
        <div class="form-group">
            <label>Estado</label>
            <select id="settFormStatus" class="form-control">
                <option value="active">Activo</option>
                <option value="inactive">Inactivo</option>
            </select>
        </div>
        <div class="form-group">
            <label>Texto del boton de envio</label>
            <input type="text" id="settSubmitLabel" class="form-control" placeholder="Enviar">
        </div>
        <div class="form-group">
            <label>Accion al enviar</label>
            <select id="settSuccessAction" class="form-control">
                <option value="message">Mostrar mensaje</option>
                <option value="redirect">Redirigir a URL</option>
            </select>
        </div>
        <div class="form-group">
            <label>Mensaje de exito</label>
            <input type="text" id="settSuccessMsg" class="form-control" placeholder="Formulario enviado correctamente.">
        </div>
        <div class="form-group" id="redirectGroup" style="display:none;">
            <label>URL de redireccion</label>
            <input type="url" id="settRedirectUrl" class="form-control" placeholder="https://...">
        </div>
        <div class="form-group">
            <label>Layout</label>
            <select id="settLayout" class="form-control">
                <option value="stacked">Apilado (stacked)</option>
                <option value="inline">En linea (inline)</option>
            </select>
        </div>
        <div class="form-group">
            <label>Clase CSS personalizada</label>
            <input type="text" id="settCssClass" class="form-control" placeholder="Opcional">
        </div>
    </div>
</div>

<!-- Tab: Notifications -->
<div class="tab-content" id="tab-notifications" style="display:none;">
    <div class="card">
        <div class="card-header">
            <h3>Notificaciones por email</h3>
            <button type="button" id="addNotifBtn" class="btn btn-sm btn-primary">Anadir notificacion</button>
        </div>
        <div id="notificationsContainer" style="padding:1rem;">
            <p style="color:var(--admin-text-muted);text-align:center;" id="noNotifsMsg">
                No hay notificaciones configuradas.
            </p>
        </div>
        <div style="padding:0 1rem 1rem;font-size:0.85rem;color:var(--admin-text-muted);">
            Variables disponibles: <code>{{field_id}}</code>, <code>{{site_name}}</code>, <code>{{site_email}}</code>, <code>{{entry_id}}</code>, <code>{{all_fields}}</code>
        </div>
    </div>
</div>

<!-- Tab: Anti-spam -->
<div class="tab-content" id="tab-antispam" style="display:none;">
    <div class="card" style="padding:1.5rem;">
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:0.5rem;">
                <input type="checkbox" id="settHoneypot" checked>
                Honeypot (campo invisible anti-bots)
            </label>
        </div>
        <div class="form-group">
            <label>Limite de envios por IP</label>
            <input type="number" id="settRateLimit" class="form-control" value="3" min="0" style="max-width:120px;">
            <small style="color:var(--admin-text-muted);">0 = sin limite</small>
        </div>
        <div class="form-group">
            <label>Ventana de tiempo (segundos)</label>
            <input type="number" id="settRateWindow" class="form-control" value="60" min="10" style="max-width:120px;">
        </div>
    </div>
</div>

<?php if ( !$isNew ): ?>
<!-- Tab: Insert -->
<div class="tab-content" id="tab-insert" style="display:none;">
    <div class="card" style="padding:1.5rem;">
        <h3 style="margin-bottom:1rem;">Insertar formulario en una pagina</h3>
        <p>Copia y pega este shortcode en el contenido de cualquier pagina:</p>
        <div style="background:var(--admin-bg-subtle,#f1f5f9);padding:1rem;border-radius:0.5rem;margin:1rem 0;display:flex;align-items:center;justify-content:space-between;">
            <code style="font-size:1.1rem;" id="shortcodeText">{{form:<?php echo klytos_esc_html( $formId ); ?>}}</code>
            <button type="button" class="btn btn-sm btn-outline" id="copyShortcodeBtn">Copiar</button>
        </div>
        <p style="color:var(--admin-text-muted);font-size:0.9rem;">
            El formulario se renderizara automaticamente al generar la pagina HTML.
        </p>
    </div>
</div>
<?php endif; ?>

<!-- Hidden form for saving -->
<form method="post" id="formSaveForm" style="display:none;">
    <?php echo klytos_csrf_field(); ?>
    <input type="hidden" name="action" value="save">
    <textarea name="form_json" id="formJsonInput"></textarea>
</form>

<script>
(function() {
    // ─── State ──────────────────────────────────────────────
    var formData = <?php echo $formJson; ?>;
    var fieldTypes = [
        {value: 'text', label: 'Texto corto'},
        {value: 'email', label: 'Email'},
        {value: 'url', label: 'URL'},
        {value: 'phone', label: 'Telefono'},
        {value: 'number', label: 'Numero'},
        {value: 'textarea', label: 'Texto largo'},
        {value: 'select', label: 'Desplegable'},
        {value: 'radio', label: 'Radio'},
        {value: 'checkbox', label: 'Checkbox'},
        {value: 'checkbox_group', label: 'Grupo checkboxes'},
        {value: 'date', label: 'Fecha'},
        {value: 'time', label: 'Hora'},
        {value: 'file', label: 'Archivo'},
        {value: 'hidden', label: 'Oculto'},
        {value: 'html', label: 'HTML libre'},
        {value: 'section', label: 'Separador'},
        {value: 'consent', label: 'Consentimiento'},
        {value: 'password', label: 'Contrasena'},
        {value: 'range', label: 'Slider'}
    ];

    // ─── Tabs ───────────────────────────────────────────────
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
            document.querySelectorAll('.tab-content').forEach(function(c) { c.style.display = 'none'; });
            btn.classList.add('active');
            document.getElementById('tab-' + btn.dataset.tab).style.display = '';
        });
    });

    // ─── Populate settings from formData ────────────────────
    function loadSettings() {
        var s = formData.settings || {};
        document.getElementById('settFormTitle').value = formData.title || '';
        document.getElementById('settFormDesc').value = formData.description || '';
        document.getElementById('settFormStatus').value = formData.status || 'active';
        document.getElementById('settSubmitLabel').value = s.submit_label || 'Enviar';
        document.getElementById('settSuccessAction').value = s.success_action || 'message';
        document.getElementById('settSuccessMsg').value = s.success_message || '';
        document.getElementById('settRedirectUrl').value = s.success_redirect || '';
        document.getElementById('settLayout').value = s.layout || 'stacked';
        document.getElementById('settCssClass').value = s.css_class || '';

        var as = formData.anti_spam || {};
        document.getElementById('settHoneypot').checked = as.honeypot !== false;
        document.getElementById('settRateLimit').value = as.rate_limit ?? 3;
        document.getElementById('settRateWindow').value = as.rate_limit_window ?? 60;

        toggleRedirect();
    }

    function toggleRedirect() {
        var g = document.getElementById('redirectGroup');
        g.style.display = document.getElementById('settSuccessAction').value === 'redirect' ? '' : 'none';
    }
    document.getElementById('settSuccessAction').addEventListener('change', toggleRedirect);

    // ─── Collect settings back into formData ────────────────
    function collectSettings() {
        formData.title       = document.getElementById('settFormTitle').value;
        formData.description = document.getElementById('settFormDesc').value;
        formData.status      = document.getElementById('settFormStatus').value;

        formData.settings = formData.settings || {};
        formData.settings.submit_label    = document.getElementById('settSubmitLabel').value;
        formData.settings.success_action  = document.getElementById('settSuccessAction').value;
        formData.settings.success_message = document.getElementById('settSuccessMsg').value;
        formData.settings.success_redirect = document.getElementById('settRedirectUrl').value;
        formData.settings.layout          = document.getElementById('settLayout').value;
        formData.settings.css_class       = document.getElementById('settCssClass').value;

        formData.anti_spam = {
            honeypot: document.getElementById('settHoneypot').checked,
            rate_limit: parseInt(document.getElementById('settRateLimit').value) || 3,
            rate_limit_window: parseInt(document.getElementById('settRateWindow').value) || 60
        };
    }

    // ─── Render fields list ─────────────────────────────────
    function renderFields() {
        var container = document.getElementById('fieldsContainer');
        var noMsg = document.getElementById('noFieldsMsg');
        var fields = formData.fields || [];

        if (fields.length === 0) {
            noMsg.style.display = '';
            container.innerHTML = '';
            container.appendChild(noMsg);
            return;
        }
        noMsg.style.display = 'none';

        var html = '';
        fields.forEach(function(field, idx) {
            var typeName = fieldTypes.find(function(t) { return t.value === field.type; });
            html += '<div class="card" style="margin-bottom:0.75rem;padding:0.75rem 1rem;" data-idx="' + idx + '">';
            html += '<div style="display:flex;justify-content:space-between;align-items:center;">';
            html += '<div>';
            html += '<strong>' + (field.label || field.id || 'Sin nombre') + '</strong>';
            html += ' <span class="badge badge-muted" style="font-size:0.75rem;">' + (typeName ? typeName.label : field.type) + '</span>';
            if (field.required) html += ' <span class="badge badge-primary" style="font-size:0.7rem;">Requerido</span>';
            if (field.conditional) html += ' <span class="badge badge-warning" style="font-size:0.7rem;">Condicional</span>';
            html += '</div>';
            html += '<div style="display:flex;gap:0.25rem;">';
            if (idx > 0) html += '<button type="button" class="btn btn-sm btn-outline" data-move-up="' + idx + '">&uarr;</button>';
            if (idx < fields.length - 1) html += '<button type="button" class="btn btn-sm btn-outline" data-move-down="' + idx + '">&darr;</button>';
            html += '<button type="button" class="btn btn-sm btn-danger" data-remove="' + idx + '">Eliminar</button>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
        });

        container.innerHTML = html;
        container.appendChild(noMsg);

        // Bind move/remove buttons
        container.querySelectorAll('[data-move-up]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var i = parseInt(btn.dataset.moveUp);
                var tmp = fields[i]; fields[i] = fields[i-1]; fields[i-1] = tmp;
                renderFields();
            });
        });
        container.querySelectorAll('[data-move-down]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var i = parseInt(btn.dataset.moveDown);
                var tmp = fields[i]; fields[i] = fields[i+1]; fields[i+1] = tmp;
                renderFields();
            });
        });
        container.querySelectorAll('[data-remove]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!confirm('Eliminar este campo?')) return;
                fields.splice(parseInt(btn.dataset.remove), 1);
                formData.fields = fields;
                renderFields();
            });
        });
    }

    // ─── Add field ──────────────────────────────────────────
    document.getElementById('addFieldBtn').addEventListener('click', function() {
        var label = prompt('Nombre del campo (label):');
        if (!label) return;
        var type = prompt('Tipo (' + fieldTypes.map(function(t) { return t.value; }).join(', ') + '):', 'text');
        if (!type) type = 'text';
        var req = confirm('Es obligatorio?');

        formData.fields = formData.fields || [];
        formData.fields.push({
            id: 'field_' + label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/_+$/, ''),
            type: type,
            label: label,
            placeholder: '',
            required: req,
            validation: {},
            css_class: '',
            default_value: '',
            step: 1,
            order: formData.fields.length + 1,
            conditional: null
        });
        renderFields();
    });

    // ─── Save ───────────────────────────────────────────────
    document.getElementById('formSaveBtn').addEventListener('click', function() {
        collectSettings();
        document.getElementById('formJsonInput').value = JSON.stringify(formData);
        document.getElementById('formSaveForm').submit();
    });

    // ─── Copy shortcode ─────────────────────────────────────
    var copyBtn = document.getElementById('copyShortcodeBtn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            var text = document.getElementById('shortcodeText').textContent;
            navigator.clipboard.writeText(text);
            copyBtn.textContent = 'Copiado!';
            setTimeout(function() { copyBtn.textContent = 'Copiar'; }, 1500);
        });
    }

    // ─── Add notification ───────────────────────────────────
    document.getElementById('addNotifBtn').addEventListener('click', function() {
        var name = prompt('Nombre de la notificacion:', 'Notificacion admin');
        if (!name) return;
        var to = prompt('Email destinatario (o {{field_email}}):', '{{site_email}}');
        if (!to) return;

        formData.notifications = formData.notifications || [];
        formData.notifications.push({
            id: 'notif_' + Date.now(),
            name: name,
            enabled: true,
            to: to,
            reply_to: '',
            subject: 'Nuevo envio: ' + (formData.title || 'formulario'),
            body: '{{all_fields}}',
            format: 'text',
            conditional: null
        });
        renderNotifications();
    });

    function renderNotifications() {
        var container = document.getElementById('notificationsContainer');
        var noMsg = document.getElementById('noNotifsMsg');
        var notifs = formData.notifications || [];

        if (notifs.length === 0) {
            noMsg.style.display = '';
            container.innerHTML = '';
            container.appendChild(noMsg);
            return;
        }
        noMsg.style.display = 'none';

        var html = '';
        notifs.forEach(function(n, idx) {
            html += '<div class="card" style="margin-bottom:0.75rem;padding:0.75rem 1rem;">';
            html += '<div style="display:flex;justify-content:space-between;align-items:center;">';
            html += '<div>';
            html += '<strong>' + (n.name || 'Sin nombre') + '</strong>';
            html += ' &rarr; <code>' + (n.to || '') + '</code>';
            if (!n.enabled) html += ' <span class="badge badge-muted">Desactivada</span>';
            html += '</div>';
            html += '<button type="button" class="btn btn-sm btn-danger" data-remove-notif="' + idx + '">Eliminar</button>';
            html += '</div>';
            html += '</div>';
        });
        container.innerHTML = html;
        container.appendChild(noMsg);

        container.querySelectorAll('[data-remove-notif]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                notifs.splice(parseInt(btn.dataset.removeNotif), 1);
                formData.notifications = notifs;
                renderNotifications();
            });
        });
    }

    // ─── Init ───────────────────────────────────────────────
    loadSettings();
    renderFields();
    renderNotifications();
})();
</script>

<?php klytos_do_action( 'admin.form_editor.after', $form ); ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
