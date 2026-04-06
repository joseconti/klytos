<?php

/**
 * Klytos Admin — Form Editor (Gravity Forms-style)
 * Visual 2-column editor: field preview canvas + sidebar with field types grid & settings.
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

use Klytos\Core\Helpers;

$fm      = klytos_forms();
$formId  = $_GET['id'] ?? '';
$isNew   = empty( $formId );
$error   = '';
$success = '';
$form    = null;

if ( !$isNew ) {
    $form = $fm->getForm( $formId );
    if ( !$form ) {
        $error = "Formulario '{$formId}' no encontrado.";
    }
}

$pageTitle = $isNew ? 'Nuevo formulario' : 'Editar: ' . ( $form['title'] ?? $formId );

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

$formJson = json_encode( $form ?? [
    'id' => '', 'title' => '', 'description' => '', 'status' => 'active',
    'fields' => [],
    'settings' => [
        'submit_label' => 'Enviar', 'success_message' => 'Formulario enviado correctamente.',
        'success_action' => 'message', 'success_redirect' => '', 'enable_ajax' => true,
        'css_class' => '', 'layout' => 'stacked',
        'steps' => [ ['step' => 1, 'title' => ''] ],
    ],
    'notifications' => [],
    'anti_spam' => ['honeypot' => true, 'rate_limit' => 3, 'rate_limit_window' => 60],
], JSON_UNESCAPED_UNICODE );

?>
<?php klytos_do_action( 'admin.form_editor.before', $form ); ?>

<!-- ═══ Editor-specific CSS ═══ -->
<style nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
.fe-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
.fe-header-left { display:flex; align-items:center; gap:1rem; }
.fe-header-right { display:flex; align-items:center; gap:0.5rem; }

.fe-body { display:flex; gap:0; min-height: calc(100vh - 220px); border:1px solid var(--admin-border); border-radius:var(--admin-radius); overflow:hidden; }
.fe-canvas { flex:1; padding:1.5rem; overflow-y:auto; background:var(--admin-bg); }
.fe-sidebar { width:320px; min-width:320px; border-left:1px solid var(--admin-border); background:var(--admin-surface); display:flex; flex-direction:column; overflow:hidden; }

/* Sidebar tabs */
.fe-sidebar-tabs { display:flex; border-bottom:1px solid var(--admin-border); flex-shrink:0; }
.fe-sidebar-tab { flex:1; padding:0.7rem 0.5rem; font-size:0.8125rem; font-weight:600; text-align:center; background:transparent; border:none; border-bottom:3px solid transparent; color:var(--admin-text-muted); cursor:pointer; transition:all 0.15s; }
.fe-sidebar-tab:hover { color:var(--admin-text); }
.fe-sidebar-tab.active { color:var(--admin-primary); border-bottom-color:var(--admin-primary); }
.fe-sidebar-body { flex:1; overflow-y:auto; }
.fe-sidebar-panel { display:none; }
.fe-sidebar-panel.active { display:block; }

/* Field type grid */
.fe-type-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:0.5rem; padding:1rem; }
.fe-type-btn { display:flex; flex-direction:column; align-items:center; gap:0.35rem; padding:0.7rem 0.25rem; border:1px solid var(--admin-border); border-radius:var(--admin-radius); background:var(--admin-surface); cursor:pointer; font-size:0.7rem; color:var(--admin-text-muted); transition:all 0.15s; text-align:center; line-height:1.2; }
.fe-type-btn:hover { border-color:var(--admin-primary); color:var(--admin-primary); background:rgba(37,99,235,0.04); }
.fe-type-btn i { font-size:1.25rem; }

/* Field preview cards */
.fe-field { position:relative; padding:0.85rem 1rem; margin-bottom:0.5rem; border:2px solid var(--admin-border); border-radius:var(--admin-radius); background:var(--admin-surface); cursor:pointer; transition:all 0.15s; }
.fe-field:hover { border-color:var(--admin-primary-hover, #93c5fd); }
.fe-field.selected { border-color:var(--admin-primary); box-shadow:0 0 0 3px rgba(37,99,235,0.12); }
.fe-field-label { font-size:0.85rem; font-weight:600; color:var(--admin-text); margin-bottom:0.35rem; display:flex; align-items:center; gap:0.4rem; }
.fe-field-label .badge-status { font-size:0.65rem; }
.fe-field-input-preview { width:100%; padding:0.4rem 0.6rem; border:1px solid var(--admin-border); border-radius:4px; background:var(--admin-bg); font-size:0.8rem; color:var(--admin-text-muted); pointer-events:none; }
select.fe-field-input-preview { appearance:none; }
textarea.fe-field-input-preview { height:50px; resize:none; }

/* Floating toolbar */
.fe-toolbar { position:absolute; top:0.4rem; right:0.4rem; display:none; gap:2px; }
.fe-field:hover .fe-toolbar, .fe-field.selected .fe-toolbar { display:flex; }
.fe-toolbar button { width:26px; height:26px; display:flex; align-items:center; justify-content:center; border:1px solid var(--admin-border); border-radius:4px; background:var(--admin-surface); cursor:pointer; font-size:0.7rem; color:var(--admin-text-muted); padding:0; }
.fe-toolbar button:hover { background:var(--admin-bg); color:var(--admin-text); }
.fe-toolbar button.danger:hover { background:var(--admin-error); color:#fff; border-color:var(--admin-error); }

/* Sidebar settings sections */
.fe-section { padding:1rem; border-bottom:1px solid var(--admin-border); }
.fe-section-title { font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:var(--admin-text-muted); margin:0 0 0.75rem 0; }
.fe-label { display:block; font-size:0.8125rem; font-weight:500; margin-bottom:0.25rem; color:var(--admin-text); }
.fe-input { width:100%; padding:0.4rem 0.6rem; border:1px solid var(--admin-border); border-radius:4px; font-size:0.8125rem; margin-bottom:0.6rem; background:var(--admin-surface); color:var(--admin-text); }
.fe-input:focus { outline:none; border-color:var(--admin-primary); box-shadow:0 0 0 2px rgba(37,99,235,0.1); }
textarea.fe-input { resize:vertical; min-height:60px; }
.fe-checkbox-row { display:flex; align-items:center; gap:0.4rem; font-size:0.8125rem; margin-bottom:0.6rem; color:var(--admin-text); }

/* Empty canvas */
.fe-empty { text-align:center; padding:4rem 2rem; color:var(--admin-text-muted); }
.fe-empty i { font-size:3rem; margin-bottom:1rem; opacity:0.3; }
.fe-empty p { font-size:0.95rem; }
</style>

<?php if ( !empty( $error ) ): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>
<?php if ( !empty( $success ) ): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>

<!-- ═══ Header bar ═══ -->
<div class="fe-header">
    <div class="fe-header-left">
        <a href="plugin-page.php?plugin=klytos-forms&page=forms" class="btn btn-outline btn-sm">&larr; Formularios</a>
        <strong style="font-size:1.1rem;" id="feFormTitle"><?php echo klytos_esc_html( $form['title'] ?? 'Nuevo formulario' ); ?></strong>
    </div>
    <div class="fe-header-right">
        <button type="button" class="btn btn-primary" id="feSaveBtn">
            <i class="fa-solid fa-floppy-disk"></i> Guardar formulario
        </button>
    </div>
</div>

<!-- ═══ Main tabs ═══ -->
<div class="tabs">
    <a href="#" class="tab active" data-main-tab="fields">Campos</a>
    <a href="#" class="tab" data-main-tab="settings">Configuracion</a>
    <a href="#" class="tab" data-main-tab="notifications">Notificaciones</a>
    <a href="#" class="tab" data-main-tab="antispam">Anti-spam</a>
    <?php if ( !$isNew ): ?>
    <a href="#" class="tab" data-main-tab="insert">Insertar</a>
    <?php endif; ?>
</div>

<!-- ═══ TAB: Fields (2-column editor) ═══ -->
<div class="main-panel" id="mp-fields">
    <div class="fe-body">
        <!-- Canvas (left) -->
        <div class="fe-canvas" id="feCanvas">
            <div class="fe-empty" id="feEmpty">
                <i class="fa-solid fa-rectangle-list"></i>
                <p>Haz clic en un tipo de campo de la derecha para empezar a construir tu formulario.</p>
            </div>
        </div>

        <!-- Sidebar (right) -->
        <div class="fe-sidebar">
            <div class="fe-sidebar-tabs">
                <button class="fe-sidebar-tab active" data-stab="add">Anadir campos</button>
                <button class="fe-sidebar-tab" data-stab="settings">Ajustes de campo</button>
            </div>
            <div class="fe-sidebar-body">
                <!-- Panel: Add fields -->
                <div class="fe-sidebar-panel active" id="sp-add">
                    <div style="padding:0.75rem 1rem 0;font-size:0.75rem;color:var(--admin-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.04em;">Campos estandar</div>
                    <div class="fe-type-grid">
                        <button class="fe-type-btn" data-type="text"><i class="fa-solid fa-font"></i>Texto</button>
                        <button class="fe-type-btn" data-type="email"><i class="fa-solid fa-envelope"></i>Email</button>
                        <button class="fe-type-btn" data-type="url"><i class="fa-solid fa-link"></i>URL</button>
                        <button class="fe-type-btn" data-type="phone"><i class="fa-solid fa-phone"></i>Telefono</button>
                        <button class="fe-type-btn" data-type="number"><i class="fa-solid fa-hashtag"></i>Numero</button>
                        <button class="fe-type-btn" data-type="textarea"><i class="fa-solid fa-align-left"></i>Parrafo</button>
                        <button class="fe-type-btn" data-type="select"><i class="fa-solid fa-list"></i>Desplegable</button>
                        <button class="fe-type-btn" data-type="radio"><i class="fa-solid fa-circle-dot"></i>Radio</button>
                        <button class="fe-type-btn" data-type="checkbox"><i class="fa-solid fa-square-check"></i>Checkbox</button>
                        <button class="fe-type-btn" data-type="checkbox_group"><i class="fa-solid fa-list-check"></i>Multi-check</button>
                        <button class="fe-type-btn" data-type="date"><i class="fa-solid fa-calendar"></i>Fecha</button>
                        <button class="fe-type-btn" data-type="time"><i class="fa-solid fa-clock"></i>Hora</button>
                    </div>
                    <div style="padding:0.75rem 1rem 0;font-size:0.75rem;color:var(--admin-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.04em;">Campos avanzados</div>
                    <div class="fe-type-grid">
                        <button class="fe-type-btn" data-type="file"><i class="fa-solid fa-upload"></i>Archivo</button>
                        <button class="fe-type-btn" data-type="hidden"><i class="fa-solid fa-eye-slash"></i>Oculto</button>
                        <button class="fe-type-btn" data-type="html"><i class="fa-solid fa-code"></i>HTML</button>
                        <button class="fe-type-btn" data-type="section"><i class="fa-solid fa-minus"></i>Separador</button>
                        <button class="fe-type-btn" data-type="consent"><i class="fa-solid fa-shield-halved"></i>Consent</button>
                        <button class="fe-type-btn" data-type="password"><i class="fa-solid fa-lock"></i>Password</button>
                        <button class="fe-type-btn" data-type="range"><i class="fa-solid fa-sliders"></i>Slider</button>
                    </div>
                </div>

                <!-- Panel: Field settings (shown when a field is selected) -->
                <div class="fe-sidebar-panel" id="sp-settings">
                    <div id="feFieldSettings">
                        <div class="fe-section" style="text-align:center;color:var(--admin-text-muted);padding:3rem 1rem;">
                            Selecciona un campo para ver sus ajustes.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══ TAB: Settings ═══ -->
<div class="main-panel" id="mp-settings" style="display:none;">
    <div class="card" style="padding:1.5rem;max-width:700px;">
        <div class="form-group"><label class="form-label">Titulo del formulario</label><input type="text" id="settTitle" class="form-control"></div>
        <div class="form-group"><label class="form-label">Descripcion</label><textarea id="settDesc" class="form-control" rows="2"></textarea></div>
        <div class="grid-2">
            <div class="form-group"><label class="form-label">Estado</label><select id="settStatus" class="form-control"><option value="active">Activo</option><option value="inactive">Inactivo</option></select></div>
            <div class="form-group"><label class="form-label">Layout</label><select id="settLayout" class="form-control"><option value="stacked">Apilado</option><option value="inline">Inline</option></select></div>
        </div>
        <div class="form-group"><label class="form-label">Texto del boton</label><input type="text" id="settSubmitLabel" class="form-control" placeholder="Enviar"></div>
        <div class="form-group"><label class="form-label">Accion al enviar</label><select id="settSuccessAction" class="form-control"><option value="message">Mostrar mensaje</option><option value="redirect">Redirigir</option></select></div>
        <div class="form-group"><label class="form-label">Mensaje de exito</label><input type="text" id="settSuccessMsg" class="form-control"></div>
        <div class="form-group" id="settRedirectGroup" style="display:none;"><label class="form-label">URL de redireccion</label><input type="url" id="settRedirectUrl" class="form-control"></div>
        <div class="form-group"><label class="form-label">Clase CSS</label><input type="text" id="settCssClass" class="form-control" placeholder="Opcional"></div>
    </div>
</div>

<!-- ═══ TAB: Notifications ═══ -->
<div class="main-panel" id="mp-notifications" style="display:none;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
        <h3>Notificaciones</h3>
        <button type="button" class="btn btn-primary btn-sm" id="addNotifBtn">+ Anadir notificacion</button>
    </div>
    <div id="notifsContainer"></div>
    <p style="font-size:0.85rem;color:var(--admin-text-muted);margin-top:1rem;">Variables: <code>{{field_id}}</code> <code>{{site_name}}</code> <code>{{site_email}}</code> <code>{{entry_id}}</code> <code>{{all_fields}}</code></p>
</div>

<!-- ═══ TAB: Anti-spam ═══ -->
<div class="main-panel" id="mp-antispam" style="display:none;">
    <div class="card" style="padding:1.5rem;max-width:500px;">
        <div class="form-group"><label class="form-label" style="display:flex;align-items:center;gap:0.5rem;"><input type="checkbox" id="settHoneypot" checked> Honeypot (campo invisible anti-bots)</label></div>
        <div class="form-group"><label class="form-label">Limite de envios por IP</label><input type="number" id="settRateLimit" class="form-control" value="3" min="0" style="max-width:120px;"><small class="form-help">0 = sin limite</small></div>
        <div class="form-group"><label class="form-label">Ventana de tiempo (segundos)</label><input type="number" id="settRateWindow" class="form-control" value="60" min="10" style="max-width:120px;"></div>
    </div>
</div>

<?php if ( !$isNew ): ?>
<!-- ═══ TAB: Insert ═══ -->
<div class="main-panel" id="mp-insert" style="display:none;">
    <div class="card" style="padding:1.5rem;max-width:500px;">
        <h3 style="margin-bottom:1rem;">Insertar en una pagina</h3>
        <p>Copia este shortcode en el contenido de cualquier pagina:</p>
        <div style="background:var(--admin-bg);padding:1rem;border-radius:var(--admin-radius);margin:1rem 0;display:flex;align-items:center;justify-content:space-between;border:1px solid var(--admin-border);">
            <code style="font-size:1.1rem;" id="shortcodeText">{{form:<?php echo klytos_esc_html( $formId ); ?>}}</code>
            <button type="button" class="btn btn-sm btn-outline" id="copyShortcodeBtn">Copiar</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══ Notification Modal ═══ -->
<div class="modal-overlay" id="notifModal">
    <div class="modal" style="max-width:600px;">
        <h3 id="notifModalTitle">Notificacion</h3>
        <div class="form-group"><label>Nombre</label><input type="text" id="nfName" class="form-control"></div>
        <div class="form-group"><label>Destinatario</label><input type="text" id="nfTo" class="form-control" placeholder="admin@ejemplo.com o {{field_email}}"></div>
        <div class="form-group"><label>Reply-To</label><input type="text" id="nfReplyTo" class="form-control"></div>
        <div class="form-group"><label>Asunto</label><input type="text" id="nfSubject" class="form-control"></div>
        <div class="form-group"><label>Cuerpo</label><textarea id="nfBody" class="form-control" rows="5"></textarea></div>
        <div class="form-group"><label>Formato</label><select id="nfFormat" class="form-control"><option value="text">Texto plano</option><option value="html">HTML</option></select></div>
        <input type="hidden" id="nfEditIdx" value="-1">
        <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem;">
            <button type="button" class="btn btn-outline" id="nfCancel">Cancelar</button>
            <button type="button" class="btn btn-primary" id="nfSave">Guardar</button>
        </div>
    </div>
</div>

<!-- Hidden save form -->
<form method="post" id="feSaveForm" style="display:none;">
    <?php echo klytos_csrf_field(); ?>
    <input type="hidden" name="action" value="save">
    <textarea name="form_json" id="feSaveJson"></textarea>
</form>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
(function() {
    var F = <?php echo $formJson; ?>;
    var selectedIdx = -1;
    var OPTS_TYPES = ['select','radio','checkbox_group'];
    var TYPE_LABELS = {text:'Texto',email:'Email',url:'URL',phone:'Telefono',number:'Numero',textarea:'Parrafo',select:'Desplegable',radio:'Radio',checkbox:'Checkbox',checkbox_group:'Multi-check',date:'Fecha',time:'Hora',file:'Archivo',hidden:'Oculto',html:'HTML',section:'Separador',consent:'Consent',password:'Password',range:'Slider'};

    // ─── Main tabs ──────────────────────────────────────────
    document.querySelectorAll('[data-main-tab]').forEach(function(t) {
        t.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('[data-main-tab]').forEach(function(x) { x.classList.remove('active'); });
            document.querySelectorAll('.main-panel').forEach(function(p) { p.style.display = 'none'; });
            t.classList.add('active');
            document.getElementById('mp-' + t.dataset.mainTab).style.display = '';
        });
    });

    // ─── Sidebar tabs ───────────────────────────────────────
    document.querySelectorAll('[data-stab]').forEach(function(t) {
        t.addEventListener('click', function() {
            document.querySelectorAll('[data-stab]').forEach(function(x) { x.classList.remove('active'); });
            document.querySelectorAll('.fe-sidebar-panel').forEach(function(p) { p.classList.remove('active'); });
            t.classList.add('active');
            document.getElementById('sp-' + t.dataset.stab).classList.add('active');
        });
    });

    function showSidebarTab(name) {
        document.querySelectorAll('[data-stab]').forEach(function(x) { x.classList.remove('active'); });
        document.querySelectorAll('.fe-sidebar-panel').forEach(function(p) { p.classList.remove('active'); });
        var tab = document.querySelector('[data-stab="' + name + '"]');
        if (tab) tab.classList.add('active');
        var panel = document.getElementById('sp-' + name);
        if (panel) panel.classList.add('active');
    }

    // ─── Add field from grid ────────────────────────────────
    document.querySelectorAll('.fe-type-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var type = btn.dataset.type;
            var label = TYPE_LABELS[type] || type;
            F.fields = F.fields || [];
            var newField = {
                id: 'field_' + Math.random().toString(36).substr(2,6),
                type: type, label: label, placeholder: '', required: false,
                validation: {}, css_class: '', default_value: '', step: 1,
                order: F.fields.length + 1, conditional: null
            };
            if (OPTS_TYPES.indexOf(type) !== -1) {
                newField.options = [{value:'option1',label:'Opcion 1'},{value:'option2',label:'Opcion 2'}];
            }
            F.fields.push(newField);
            selectedIdx = F.fields.length - 1;
            renderCanvas();
            renderFieldSettings();
            showSidebarTab('settings');
        });
    });

    // ─── Render canvas ──────────────────────────────────────
    function renderCanvas() {
        var canvas = document.getElementById('feCanvas');
        var empty = document.getElementById('feEmpty');
        var fields = F.fields || [];

        if (fields.length === 0) {
            canvas.innerHTML = '';
            canvas.appendChild(empty);
            empty.style.display = '';
            return;
        }
        empty.style.display = 'none';

        var html = '';
        fields.forEach(function(f, i) {
            var sel = i === selectedIdx ? ' selected' : '';
            html += '<div class="fe-field' + sel + '" data-idx="' + i + '">';
            html += '<div class="fe-toolbar">';
            if (i > 0) html += '<button data-act="up" data-i="' + i + '" title="Subir"><i class="fa-solid fa-arrow-up"></i></button>';
            if (i < fields.length - 1) html += '<button data-act="down" data-i="' + i + '" title="Bajar"><i class="fa-solid fa-arrow-down"></i></button>';
            html += '<button data-act="dup" data-i="' + i + '" title="Duplicar"><i class="fa-solid fa-clone"></i></button>';
            html += '<button data-act="del" data-i="' + i + '" title="Eliminar" class="danger"><i class="fa-solid fa-trash"></i></button>';
            html += '</div>';

            // Label
            html += '<div class="fe-field-label">';
            html += esc(f.label || f.id);
            if (f.required) html += ' <span style="color:var(--admin-error);">*</span>';
            html += ' <span class="badge-status badge-draft" style="font-size:0.65rem;">' + (TYPE_LABELS[f.type] || f.type) + '</span>';
            if (f.conditional) html += ' <span class="badge-status badge-medium" style="font-size:0.6rem;">Condicional</span>';
            html += '</div>';

            // Input preview
            html += renderInputPreview(f);
            html += '</div>';
        });

        canvas.innerHTML = html;
        canvas.appendChild(empty);

        // Bind clicks
        canvas.querySelectorAll('.fe-field').forEach(function(el) {
            el.addEventListener('click', function(e) {
                if (e.target.closest('.fe-toolbar')) return;
                selectedIdx = parseInt(el.dataset.idx);
                renderCanvas();
                renderFieldSettings();
                showSidebarTab('settings');
            });
        });

        // Toolbar actions
        canvas.querySelectorAll('.fe-toolbar button').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var i = parseInt(btn.dataset.i);
                var act = btn.dataset.act;
                if (act === 'up' && i > 0) { var t = fields[i]; fields[i] = fields[i-1]; fields[i-1] = t; if (selectedIdx === i) selectedIdx--; else if (selectedIdx === i-1) selectedIdx++; }
                if (act === 'down' && i < fields.length-1) { var t = fields[i]; fields[i] = fields[i+1]; fields[i+1] = t; if (selectedIdx === i) selectedIdx++; else if (selectedIdx === i+1) selectedIdx--; }
                if (act === 'dup') { var c = JSON.parse(JSON.stringify(fields[i])); c.id = 'field_' + Math.random().toString(36).substr(2,6); fields.splice(i+1,0,c); selectedIdx = i+1; }
                if (act === 'del') { fields.splice(i,1); if (selectedIdx === i) selectedIdx = -1; else if (selectedIdx > i) selectedIdx--; }
                F.fields = fields;
                renderCanvas();
                renderFieldSettings();
            });
        });
    }

    function renderInputPreview(f) {
        switch (f.type) {
            case 'textarea': return '<textarea class="fe-field-input-preview" placeholder="' + esc(f.placeholder) + '"></textarea>';
            case 'select': return '<select class="fe-field-input-preview"><option>' + (f.options && f.options.length ? esc(f.options[0].label) : 'Seleccionar...') + '</option></select>';
            case 'checkbox': case 'consent': return '<label style="display:flex;align-items:center;gap:0.4rem;font-size:0.8rem;color:var(--admin-text-muted);"><input type="checkbox" disabled> ' + esc(f.consent_text || f.label || '') + '</label>';
            case 'radio': return (f.options || []).map(function(o) { return '<label style="display:flex;align-items:center;gap:0.3rem;font-size:0.8rem;color:var(--admin-text-muted);"><input type="radio" disabled> ' + esc(o.label) + '</label>'; }).join('');
            case 'checkbox_group': return (f.options || []).map(function(o) { return '<label style="display:flex;align-items:center;gap:0.3rem;font-size:0.8rem;color:var(--admin-text-muted);"><input type="checkbox" disabled> ' + esc(o.label) + '</label>'; }).join('');
            case 'file': return '<input type="file" class="fe-field-input-preview" disabled>';
            case 'hidden': return '<div style="font-size:0.75rem;color:var(--admin-text-muted);font-style:italic;">Campo oculto: ' + esc(f.default_value || '') + '</div>';
            case 'html': return '<div style="font-size:0.75rem;color:var(--admin-text-muted);font-style:italic;">&lt;/&gt; Contenido HTML</div>';
            case 'section': return '<hr style="border:none;border-top:1px dashed var(--admin-border);">';
            case 'range': return '<input type="range" style="width:100%;" disabled>';
            default: return '<input type="' + (f.type === 'phone' ? 'tel' : f.type) + '" class="fe-field-input-preview" placeholder="' + esc(f.placeholder || '') + '" disabled>';
        }
    }

    // ─── Field settings sidebar ─────────────────────────────
    function renderFieldSettings() {
        var container = document.getElementById('feFieldSettings');
        if (selectedIdx < 0 || !F.fields || !F.fields[selectedIdx]) {
            container.innerHTML = '<div class="fe-section" style="text-align:center;color:var(--admin-text-muted);padding:3rem 1rem;">Selecciona un campo para ver sus ajustes.</div>';
            return;
        }
        var f = F.fields[selectedIdx];
        var hasOpts = OPTS_TYPES.indexOf(f.type) !== -1;

        var h = '';
        // General
        h += '<div class="fe-section">';
        h += '<div class="fe-section-title">General</div>';
        h += '<div style="margin-bottom:0.6rem;"><span class="badge-status badge-active">' + (TYPE_LABELS[f.type] || f.type) + '</span> <span style="font-size:0.75rem;color:var(--admin-text-muted);">ID: ' + esc(f.id) + '</span></div>';
        h += '<label class="fe-label">Etiqueta</label><input class="fe-input" id="fs-label" value="' + esc(f.label || '') + '">';
        h += '<label class="fe-label">Placeholder</label><input class="fe-input" id="fs-placeholder" value="' + esc(f.placeholder || '') + '">';
        h += '<label class="fe-label">Valor por defecto</label><input class="fe-input" id="fs-default" value="' + esc(f.default_value || '') + '">';
        h += '<label class="fe-checkbox-row"><input type="checkbox" id="fs-required"' + (f.required ? ' checked' : '') + '> Obligatorio</label>';
        h += '</div>';

        // Options
        if (hasOpts) {
            var optsText = (f.options || []).map(function(o) { return o.value === o.label ? o.value : o.value + '|' + o.label; }).join('\n');
            h += '<div class="fe-section">';
            h += '<div class="fe-section-title">Opciones</div>';
            h += '<label class="fe-label">Una por linea (valor|etiqueta)</label>';
            h += '<textarea class="fe-input" id="fs-options" rows="4">' + esc(optsText) + '</textarea>';
            h += '</div>';
        }

        // Validation
        h += '<div class="fe-section">';
        h += '<div class="fe-section-title">Validacion</div>';
        var v = f.validation || {};
        if (['text','textarea','email','url','phone','password'].indexOf(f.type) !== -1) {
            h += '<div style="display:flex;gap:0.5rem;">';
            h += '<div style="flex:1;"><label class="fe-label">Min caracteres</label><input class="fe-input" id="fs-minlen" type="number" value="' + (v.min_length || '') + '"></div>';
            h += '<div style="flex:1;"><label class="fe-label">Max caracteres</label><input class="fe-input" id="fs-maxlen" type="number" value="' + (v.max_length || '') + '"></div>';
            h += '</div>';
            h += '<label class="fe-label">Pattern (regex)</label><input class="fe-input" id="fs-pattern" value="' + esc(v.pattern || '') + '">';
        }
        if (f.type === 'number' || f.type === 'range') {
            h += '<div style="display:flex;gap:0.5rem;">';
            h += '<div style="flex:1;"><label class="fe-label">Min</label><input class="fe-input" id="fs-min" type="number" value="' + (v.min || '') + '"></div>';
            h += '<div style="flex:1;"><label class="fe-label">Max</label><input class="fe-input" id="fs-max" type="number" value="' + (v.max || '') + '"></div>';
            h += '</div>';
        }
        h += '</div>';

        // Conditional
        h += '<div class="fe-section">';
        h += '<div class="fe-section-title">Logica condicional</div>';
        var hasCond = f.conditional && f.conditional.rules && f.conditional.rules.length > 0;
        h += '<label class="fe-checkbox-row"><input type="checkbox" id="fs-cond-enable"' + (hasCond ? ' checked' : '') + '> Activar logica condicional</label>';
        if (hasCond) {
            var c = f.conditional;
            h += '<div style="display:flex;gap:0.5rem;margin-bottom:0.5rem;">';
            h += '<select class="fe-input" id="fs-cond-action" style="flex:1;margin-bottom:0;"><option value="show"' + (c.action==='show'?' selected':'') + '>Mostrar</option><option value="hide"' + (c.action==='hide'?' selected':'') + '>Ocultar</option></select>';
            h += '<span style="font-size:0.8rem;color:var(--admin-text-muted);padding-top:0.4rem;">este campo si</span>';
            h += '<select class="fe-input" id="fs-cond-logic" style="flex:1;margin-bottom:0;"><option value="all"' + (c.logic==='all'?' selected':'') + '>Todo</option><option value="any"' + (c.logic==='any'?' selected':'') + '>Alguno</option></select>';
            h += '<span style="font-size:0.8rem;color:var(--admin-text-muted);padding-top:0.4rem;">coincide:</span>';
            h += '</div>';
            (c.rules || []).forEach(function(r, ri) {
                h += '<div style="display:flex;gap:0.4rem;margin-bottom:0.4rem;align-items:center;">';
                h += '<select class="fe-input" data-cr-field="' + ri + '" style="flex:2;margin-bottom:0;">';
                (F.fields || []).forEach(function(of) {
                    if (of.id !== f.id) h += '<option value="' + esc(of.id) + '"' + (r.field_id===of.id?' selected':'') + '>' + esc(of.label||of.id) + '</option>';
                });
                h += '</select>';
                h += '<select class="fe-input" data-cr-op="' + ri + '" style="flex:1;margin-bottom:0;"><option value="is"' + (r.operator==='is'?' selected':'') + '>es</option><option value="is_not"' + (r.operator==='is_not'?' selected':'') + '>no es</option><option value="contains"' + (r.operator==='contains'?' selected':'') + '>contiene</option><option value="is_empty"' + (r.operator==='is_empty'?' selected':'') + '>vacio</option><option value="is_not_empty"' + (r.operator==='is_not_empty'?' selected':'') + '>no vacio</option></select>';
                h += '<input class="fe-input" data-cr-val="' + ri + '" style="flex:2;margin-bottom:0;" value="' + esc(r.value || '') + '" placeholder="valor">';
                h += '<button class="btn btn-sm btn-danger" data-cr-del="' + ri + '" style="padding:0.3rem 0.5rem;">x</button>';
                h += '</div>';
            });
            h += '<button class="btn btn-sm btn-outline" id="fs-cond-add-rule" style="margin-top:0.25rem;">+ Regla</button>';
        }
        h += '</div>';

        // Advanced
        h += '<div class="fe-section">';
        h += '<div class="fe-section-title">Avanzado</div>';
        h += '<label class="fe-label">Clase CSS</label><input class="fe-input" id="fs-css" value="' + esc(f.css_class || '') + '">';
        h += '<label class="fe-label">Paso (multi-step)</label><input class="fe-input" id="fs-step" type="number" min="1" value="' + (f.step || 1) + '">';
        h += '</div>';

        container.innerHTML = h;
        bindFieldSettings();
    }

    function bindFieldSettings() {
        if (selectedIdx < 0) return;
        var f = F.fields[selectedIdx];

        function upd(id, key) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', function() {
                f[key] = el.value;
                renderCanvas();
            });
        }
        upd('fs-label','label'); upd('fs-placeholder','placeholder'); upd('fs-default','default_value'); upd('fs-css','css_class');

        var reqEl = document.getElementById('fs-required');
        if (reqEl) reqEl.addEventListener('change', function() { f.required = reqEl.checked; renderCanvas(); });

        var stepEl = document.getElementById('fs-step');
        if (stepEl) stepEl.addEventListener('input', function() { f.step = parseInt(stepEl.value) || 1; });

        // Options
        var optsEl = document.getElementById('fs-options');
        if (optsEl) optsEl.addEventListener('input', function() {
            f.options = optsEl.value.split('\n').filter(function(l){return l.trim();}).map(function(l) {
                var p = l.split('|'); return {value:p[0].trim(), label:(p[1]||p[0]).trim()};
            });
            renderCanvas();
        });

        // Validation
        ['fs-minlen','fs-maxlen','fs-pattern','fs-min','fs-max'].forEach(function(id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', function() {
                f.validation = f.validation || {};
                var map = {'fs-minlen':'min_length','fs-maxlen':'max_length','fs-pattern':'pattern','fs-min':'min','fs-max':'max'};
                var v = el.value;
                if (v === '') delete f.validation[map[id]];
                else f.validation[map[id]] = isNaN(v) ? v : parseInt(v);
            });
        });

        // Conditional enable
        var condEn = document.getElementById('fs-cond-enable');
        if (condEn) condEn.addEventListener('change', function() {
            if (condEn.checked) {
                f.conditional = f.conditional || {action:'show',logic:'all',rules:[{field_id:'',operator:'is',value:''}]};
                if (!f.conditional.rules || !f.conditional.rules.length) f.conditional.rules = [{field_id:'',operator:'is',value:''}];
            } else {
                f.conditional = null;
            }
            renderFieldSettings();
        });

        // Conditional selects
        document.querySelectorAll('[data-cr-field]').forEach(function(el) {
            el.addEventListener('change', function() { f.conditional.rules[parseInt(el.dataset.crField)].field_id = el.value; });
        });
        document.querySelectorAll('[data-cr-op]').forEach(function(el) {
            el.addEventListener('change', function() { f.conditional.rules[parseInt(el.dataset.crOp)].operator = el.value; });
        });
        document.querySelectorAll('[data-cr-val]').forEach(function(el) {
            el.addEventListener('input', function() { f.conditional.rules[parseInt(el.dataset.crVal)].value = el.value; });
        });
        document.querySelectorAll('[data-cr-del]').forEach(function(el) {
            el.addEventListener('click', function() {
                f.conditional.rules.splice(parseInt(el.dataset.crDel),1);
                if (!f.conditional.rules.length) f.conditional = null;
                renderFieldSettings(); renderCanvas();
            });
        });
        var addRule = document.getElementById('fs-cond-add-rule');
        if (addRule) addRule.addEventListener('click', function() {
            f.conditional.rules.push({field_id:'',operator:'is',value:''});
            renderFieldSettings();
        });
        var condAct = document.getElementById('fs-cond-action');
        if (condAct) condAct.addEventListener('change', function() { f.conditional.action = condAct.value; });
        var condLog = document.getElementById('fs-cond-logic');
        if (condLog) condLog.addEventListener('change', function() { f.conditional.logic = condLog.value; });
    }

    // ─── Settings tab ───────────────────────────────────────
    function loadSettings() {
        var s = F.settings || {};
        document.getElementById('settTitle').value = F.title || '';
        document.getElementById('settDesc').value = F.description || '';
        document.getElementById('settStatus').value = F.status || 'active';
        document.getElementById('settLayout').value = s.layout || 'stacked';
        document.getElementById('settSubmitLabel').value = s.submit_label || 'Enviar';
        document.getElementById('settSuccessAction').value = s.success_action || 'message';
        document.getElementById('settSuccessMsg').value = s.success_message || '';
        document.getElementById('settRedirectUrl').value = s.success_redirect || '';
        document.getElementById('settCssClass').value = s.css_class || '';
        var as = F.anti_spam || {};
        document.getElementById('settHoneypot').checked = as.honeypot !== false;
        document.getElementById('settRateLimit').value = as.rate_limit ?? 3;
        document.getElementById('settRateWindow').value = as.rate_limit_window ?? 60;
        toggleRedirect();
        document.getElementById('feFormTitle').textContent = F.title || 'Nuevo formulario';
    }
    function toggleRedirect() {
        document.getElementById('settRedirectGroup').style.display = document.getElementById('settSuccessAction').value === 'redirect' ? '' : 'none';
    }
    document.getElementById('settSuccessAction').addEventListener('change', toggleRedirect);
    document.getElementById('settTitle').addEventListener('input', function() {
        document.getElementById('feFormTitle').textContent = this.value || 'Nuevo formulario';
    });

    function collectSettings() {
        F.title = document.getElementById('settTitle').value;
        F.description = document.getElementById('settDesc').value;
        F.status = document.getElementById('settStatus').value;
        F.settings = F.settings || {};
        F.settings.submit_label = document.getElementById('settSubmitLabel').value;
        F.settings.success_action = document.getElementById('settSuccessAction').value;
        F.settings.success_message = document.getElementById('settSuccessMsg').value;
        F.settings.success_redirect = document.getElementById('settRedirectUrl').value;
        F.settings.layout = document.getElementById('settLayout').value;
        F.settings.css_class = document.getElementById('settCssClass').value;
        F.anti_spam = {
            honeypot: document.getElementById('settHoneypot').checked,
            rate_limit: parseInt(document.getElementById('settRateLimit').value) || 3,
            rate_limit_window: parseInt(document.getElementById('settRateWindow').value) || 60
        };
    }

    // ─── Save ───────────────────────────────────────────────
    document.getElementById('feSaveBtn').addEventListener('click', function() {
        collectSettings();
        document.getElementById('feSaveJson').value = JSON.stringify(F);
        document.getElementById('feSaveForm').submit();
    });

    // ─── Copy shortcode ─────────────────────────────────────
    var copyBtn = document.getElementById('copyShortcodeBtn');
    if (copyBtn) copyBtn.addEventListener('click', function() {
        navigator.clipboard.writeText(document.getElementById('shortcodeText').textContent);
        copyBtn.textContent = 'Copiado!';
        setTimeout(function() { copyBtn.textContent = 'Copiar'; }, 1500);
    });

    // ─── Notifications ──────────────────────────────────────
    var notifModal = document.getElementById('notifModal');
    document.getElementById('addNotifBtn').addEventListener('click', function() {
        document.getElementById('notifModalTitle').textContent = 'Anadir notificacion';
        document.getElementById('nfEditIdx').value = -1;
        document.getElementById('nfName').value = '';
        document.getElementById('nfTo').value = '{{site_email}}';
        document.getElementById('nfReplyTo').value = '';
        document.getElementById('nfSubject').value = 'Nuevo envio: ' + (F.title || 'formulario');
        document.getElementById('nfBody').value = '{{all_fields}}';
        document.getElementById('nfFormat').value = 'text';
        notifModal.classList.add('active');
    });
    document.getElementById('nfCancel').addEventListener('click', function() { notifModal.classList.remove('active'); });
    notifModal.addEventListener('click', function(e) { if (e.target === notifModal) notifModal.classList.remove('active'); });
    document.getElementById('nfSave').addEventListener('click', function() {
        var n = { id:'notif_'+Date.now(), name:document.getElementById('nfName').value, enabled:true,
            to:document.getElementById('nfTo').value, reply_to:document.getElementById('nfReplyTo').value,
            subject:document.getElementById('nfSubject').value, body:document.getElementById('nfBody').value,
            format:document.getElementById('nfFormat').value, conditional:null };
        if (!n.name || !n.to) return;
        F.notifications = F.notifications || [];
        var i = parseInt(document.getElementById('nfEditIdx').value);
        if (i >= 0) { n.id = F.notifications[i].id; F.notifications[i] = n; } else { F.notifications.push(n); }
        notifModal.classList.remove('active');
        renderNotifs();
    });

    function renderNotifs() {
        var c = document.getElementById('notifsContainer');
        var ns = F.notifications || [];
        if (!ns.length) { c.innerHTML = '<div class="empty-state"><p>No hay notificaciones configuradas.</p></div>'; return; }
        var h = '';
        ns.forEach(function(n,i) {
            h += '<div class="card" style="padding:0.75rem 1rem;margin-bottom:0.5rem;"><div style="display:flex;justify-content:space-between;align-items:center;">';
            h += '<div><strong>' + esc(n.name) + '</strong> <span style="color:var(--admin-text-muted);">&rarr; ' + esc(n.to) + '</span></div>';
            h += '<div style="display:flex;gap:0.25rem;">';
            h += '<button class="btn btn-sm btn-outline" data-en="' + i + '">Editar</button>';
            h += '<button class="btn btn-sm btn-danger" data-dn="' + i + '">Eliminar</button>';
            h += '</div></div></div>';
        });
        c.innerHTML = h;
        c.querySelectorAll('[data-en]').forEach(function(b) {
            b.addEventListener('click', function() {
                var n = ns[parseInt(b.dataset.en)];
                document.getElementById('notifModalTitle').textContent = 'Editar notificacion';
                document.getElementById('nfEditIdx').value = b.dataset.en;
                document.getElementById('nfName').value = n.name||'';
                document.getElementById('nfTo').value = n.to||'';
                document.getElementById('nfReplyTo').value = n.reply_to||'';
                document.getElementById('nfSubject').value = n.subject||'';
                document.getElementById('nfBody').value = n.body||'';
                document.getElementById('nfFormat').value = n.format||'text';
                notifModal.classList.add('active');
            });
        });
        c.querySelectorAll('[data-dn]').forEach(function(b) {
            b.addEventListener('click', function() { if(confirm('Eliminar?')){ns.splice(parseInt(b.dataset.dn),1);F.notifications=ns;renderNotifs();} });
        });
    }

    // ─── Utility ────────────────────────────────────────────
    function esc(s) { var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

    // ─── Init ───────────────────────────────────────────────
    loadSettings();
    renderCanvas();
    renderNotifs();
})();
</script>

<?php klytos_do_action( 'admin.form_editor.after', $form ); ?>
