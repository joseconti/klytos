/**
 * Klytos Inline Editor — Front-End Content Editing
 *
 * Activated by adding ?klytos_edit=1 to any page URL.
 * Allows authenticated users to edit page content directly on the front-end.
 *
 * Features:
 * - Click on any editable element (within .klytos-main) to edit it.
 * - Mini floating toolbar: Bold, Italic, Link.
 * - Changes are saved via the admin API inline-edit endpoint.
 * - Autosave every 60 seconds when changes are detected.
 *
 * Requirements:
 * - Active admin session (session cookie).
 * - CSRF token available via meta tag.
 *
 * @license    Elastic License 2.0 (ELv2)
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 */
(function () {
    'use strict';

    // Only activate if ?klytos_edit=1 is in the URL.
    if (!window.location.search.includes('klytos_edit=1')) return;

    var pageSlug = window.location.pathname.replace(/^\/|\/$/g, '') || 'index';
    var hasChanges = false;
    var autosaveInterval = null;

    // ─── Make content editable ───────────────────────────────
    var mainContent = document.querySelector('.klytos-main');
    if (!mainContent) return;

    mainContent.setAttribute('contenteditable', 'true');
    mainContent.style.outline = 'none';
    mainContent.style.minHeight = '200px';

    // Track changes.
    mainContent.addEventListener('input', function () {
        hasChanges = true;
    });

    // ─── Floating Toolbar ────────────────────────────────────
    var toolbar = document.createElement('div');
    toolbar.id = 'klytos-editor-toolbar';
    toolbar.innerHTML = [
        '<style>',
        '  #klytos-editor-toolbar { display:none; position:fixed; background:#1e293b; color:#fff; padding:6px 8px; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.4); z-index:99999; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }',
        '  #klytos-editor-toolbar button { background:none; border:none; color:#e2e8f0; font-size:14px; padding:4px 10px; cursor:pointer; border-radius:4px; font-weight:600; }',
        '  #klytos-editor-toolbar button:hover { background:#334155; }',
        '  #klytos-editor-toolbar .ket-sep { width:1px; height:18px; background:#475569; margin:0 4px; display:inline-block; vertical-align:middle; }',
        '</style>',
        '<button data-cmd="bold" title="Bold (Ctrl+B)"><b>B</b></button>',
        '<button data-cmd="italic" title="Italic (Ctrl+I)"><i>I</i></button>',
        '<button data-cmd="createLink" title="Insert Link">🔗</button>',
        '<span class="ket-sep"></span>',
        '<button id="ket-save" title="Save changes" style="color:#22c55e">Save</button>',
    ].join('');
    document.body.appendChild(toolbar);

    // Show toolbar on text selection.
    document.addEventListener('selectionchange', function () {
        var sel = window.getSelection();
        if (!sel || sel.isCollapsed || !sel.rangeCount) {
            toolbar.style.display = 'none';
            return;
        }

        // Only show if selection is within the editable area.
        var range = sel.getRangeAt(0);
        if (!mainContent.contains(range.commonAncestorContainer)) {
            toolbar.style.display = 'none';
            return;
        }

        var rect = range.getBoundingClientRect();
        toolbar.style.display = 'block';
        toolbar.style.top = (rect.top - 50 + window.scrollY) + 'px';
        toolbar.style.left = (rect.left + rect.width / 2 - 80) + 'px';
    });

    // Toolbar button actions.
    toolbar.addEventListener('click', function (e) {
        var btn = e.target.closest('button');
        if (!btn) return;

        var cmd = btn.getAttribute('data-cmd');
        if (cmd === 'createLink') {
            var url = prompt('Enter URL:');
            if (url) document.execCommand('createLink', false, url);
        } else if (cmd) {
            document.execCommand(cmd, false, null);
        }

        if (btn.id === 'ket-save') {
            saveContent();
        }
    });

    // ─── Save Content ────────────────────────────────────────
    function saveContent() {
        if (!hasChanges) return;

        var data = {
            csrf: getCsrfToken(),
            slug: pageSlug,
            content_html: mainContent.innerHTML,
        };

        fetch(getAdminApiUrl('inline-edit.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
            credentials: 'same-origin',
        })
        .then(function (r) { return r.json(); })
        .then(function (result) {
            if (result.success) {
                hasChanges = false;
                showNotification('Saved!', 'success');
            } else {
                showNotification('Error: ' + (result.error || 'Unknown'), 'error');
            }
        })
        .catch(function (err) {
            showNotification('Network error', 'error');
        });
    }

    // ─── Autosave (every 60 seconds) ─────────────────────────
    autosaveInterval = setInterval(function () {
        if (hasChanges) {
            var data = {
                csrf: getCsrfToken(),
                slug: pageSlug,
                content_html: mainContent.innerHTML,
            };

            fetch(getAdminApiUrl('autosave.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
                credentials: 'same-origin',
            })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                if (result.success) {
                    hasChanges = false;
                    showNotification('Autosaved', 'info');
                }
            })
            .catch(function () { /* Silent autosave failure */ });
        }
    }, 60000);

    // ─── Editor Status Bar ───────────────────────────────────
    var statusBar = document.createElement('div');
    statusBar.id = 'klytos-editor-status';
    statusBar.innerHTML = [
        '<style>',
        '  #klytos-editor-status { position:fixed; top:0; left:0; right:0; background:#1e293b; color:#e2e8f0; padding:8px 16px; z-index:99998; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; font-size:13px; display:flex; justify-content:space-between; align-items:center; }',
        '  #klytos-editor-status .kes-badge { background:#2563eb; color:#fff; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; }',
        '  #klytos-editor-status button { background:#475569; color:#e2e8f0; border:none; padding:4px 12px; border-radius:4px; font-size:12px; cursor:pointer; }',
        '  #klytos-editor-status button:hover { background:#64748b; }',
        '</style>',
        '<div><span class="kes-badge">EDIT MODE</span> Editing: <strong>' + pageSlug + '</strong></div>',
        '<div style="display:flex;gap:8px;align-items:center">',
        '  <span id="kes-notification" style="font-size:12px"></span>',
        '  <button onclick="window.location.search=\'\'">Exit Editor</button>',
        '</div>',
    ].join('');
    document.body.appendChild(statusBar);
    document.body.style.marginTop = '40px';

    // ─── Helpers ─────────────────────────────────────────────
    function getAdminApiUrl(endpoint) {
        var meta = document.querySelector('meta[name="klytos-admin-api"]');
        if (meta) return meta.getAttribute('content') + endpoint;
        return '/admin/api/' + endpoint;
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="klytos-csrf"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function showNotification(msg, type) {
        var el = document.getElementById('kes-notification');
        el.textContent = msg;
        el.style.color = type === 'error' ? '#ef4444' : type === 'success' ? '#22c55e' : '#94a3b8';
        setTimeout(function () { el.textContent = ''; }, 3000);
    }

    // ─── Keyboard Shortcuts ──────────────────────────────────
    document.addEventListener('keydown', function (e) {
        // Ctrl/Cmd + S = Save.
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveContent();
        }
    });
})();
