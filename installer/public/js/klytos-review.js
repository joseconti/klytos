/**
 * Klytos Review Widget — Front-End Task Creation
 *
 * Activated by adding ?klytos_review=1 to any page URL.
 * Allows authenticated users to:
 * - Click on any page element to select it.
 * - Write a review note/task description.
 * - Set priority (low, medium, high, urgent).
 * - Submit the task to the admin API.
 *
 * The widget overlay appears at the bottom of the page.
 * Selected elements get a visual highlight (blue outline).
 *
 * Requirements:
 * - User must have an active admin session (session cookie).
 * - CSRF token is fetched from a data attribute or API.
 *
 * @license    Elastic License 2.0 (ELv2)
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 */
(function () {
    'use strict';

    // Only activate if ?klytos_review=1 is in the URL.
    if (!window.location.search.includes('klytos_review=1')) return;

    // ─── State ───────────────────────────────────────────────
    var selectedElement = null;
    var selectedSelector = '';
    var pageSlug = window.location.pathname.replace(/^\/|\/$/g, '') || 'index';

    // ─── Inject Widget HTML ──────────────────────────────────
    var widget = document.createElement('div');
    widget.id = 'klytos-review-widget';
    widget.innerHTML = [
        '<style>',
        '  #klytos-review-widget { position:fixed; bottom:0; left:0; right:0; background:#1e293b; color:#e2e8f0; padding:1rem 1.5rem; z-index:99999; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; font-size:14px; box-shadow:0 -4px 20px rgba(0,0,0,0.3); }',
        '  #klytos-review-widget .krw-row { display:flex; gap:0.75rem; align-items:flex-end; }',
        '  #klytos-review-widget .krw-field { flex:1; }',
        '  #klytos-review-widget label { display:block; font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:#94a3b8; margin-bottom:4px; }',
        '  #klytos-review-widget input, #klytos-review-widget select, #klytos-review-widget textarea { width:100%; padding:6px 10px; border:1px solid #475569; border-radius:6px; background:#0f172a; color:#e2e8f0; font-size:13px; }',
        '  #klytos-review-widget textarea { resize:none; height:42px; }',
        '  #klytos-review-widget .krw-btn { padding:8px 16px; border:none; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; }',
        '  #klytos-review-widget .krw-btn-primary { background:#2563eb; color:#fff; }',
        '  #klytos-review-widget .krw-btn-primary:hover { background:#1d4ed8; }',
        '  #klytos-review-widget .krw-btn-close { background:#475569; color:#e2e8f0; }',
        '  #klytos-review-widget .krw-status { font-size:12px; color:#94a3b8; margin-top:6px; }',
        '  .klytos-review-highlight { outline:3px solid #2563eb !important; outline-offset:2px; cursor:crosshair !important; }',
        '  .klytos-review-selected { outline:3px solid #f59e0b !important; outline-offset:2px; }',
        '</style>',
        '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">',
        '  <strong style="color:#fff">Klytos Review Mode</strong>',
        '  <span style="font-size:12px;color:#94a3b8">Click any element to select it, then describe the task.</span>',
        '</div>',
        '<div class="krw-row">',
        '  <div class="krw-field" style="flex:0 0 200px">',
        '    <label>Selected Element</label>',
        '    <input type="text" id="krw-selector" readonly placeholder="Click an element..." value="">',
        '  </div>',
        '  <div class="krw-field" style="flex:2">',
        '    <label>Description</label>',
        '    <textarea id="krw-description" placeholder="What needs to be changed?"></textarea>',
        '  </div>',
        '  <div class="krw-field" style="flex:0 0 120px">',
        '    <label>Priority</label>',
        '    <select id="krw-priority">',
        '      <option value="medium">Medium</option>',
        '      <option value="low">Low</option>',
        '      <option value="high">High</option>',
        '      <option value="urgent">Urgent</option>',
        '    </select>',
        '  </div>',
        '  <div style="display:flex;gap:6px">',
        '    <button class="krw-btn krw-btn-primary" id="krw-submit">Create Task</button>',
        '    <button class="krw-btn krw-btn-close" id="krw-close">Exit</button>',
        '  </div>',
        '</div>',
        '<div class="krw-status" id="krw-status"></div>',
    ].join('\n');
    document.body.appendChild(widget);

    // ─── Element Selection ───────────────────────────────────
    document.addEventListener('mouseover', function (e) {
        if (e.target.closest('#klytos-review-widget')) return;
        e.target.classList.add('klytos-review-highlight');
    });

    document.addEventListener('mouseout', function (e) {
        e.target.classList.remove('klytos-review-highlight');
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('#klytos-review-widget')) return;
        e.preventDefault();
        e.stopPropagation();

        // Remove previous selection.
        if (selectedElement) {
            selectedElement.classList.remove('klytos-review-selected');
        }

        selectedElement = e.target;
        selectedElement.classList.remove('klytos-review-highlight');
        selectedElement.classList.add('klytos-review-selected');

        // Generate a CSS selector for this element.
        selectedSelector = generateSelector(e.target);
        document.getElementById('krw-selector').value = selectedSelector;
    }, true);

    // ─── Submit Task ─────────────────────────────────────────
    document.getElementById('krw-submit').addEventListener('click', function () {
        var description = document.getElementById('krw-description').value.trim();
        if (!description) {
            setStatus('Please enter a description.', 'error');
            return;
        }

        var data = {
            action: 'create',
            csrf: getCsrfToken(),
            page_slug: pageSlug,
            css_selector: selectedSelector,
            description: description,
            priority: document.getElementById('krw-priority').value,
        };

        setStatus('Creating task...', 'info');

        fetch(getAdminApiUrl('tasks.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
            credentials: 'same-origin',
        })
        .then(function (r) { return r.json(); })
        .then(function (result) {
            if (result.success) {
                setStatus('Task created!', 'success');
                document.getElementById('krw-description').value = '';
                if (selectedElement) {
                    selectedElement.classList.remove('klytos-review-selected');
                    selectedElement = null;
                    selectedSelector = '';
                    document.getElementById('krw-selector').value = '';
                }
            } else {
                setStatus('Error: ' + (result.error || 'Unknown'), 'error');
            }
        })
        .catch(function (err) {
            setStatus('Network error: ' + err.message, 'error');
        });
    });

    // ─── Close Widget ────────────────────────────────────────
    document.getElementById('krw-close').addEventListener('click', function () {
        widget.remove();
        // Remove ?klytos_review=1 from URL without reload.
        var url = new URL(window.location);
        url.searchParams.delete('klytos_review');
        window.history.replaceState({}, '', url);
    });

    // ─── Helpers ─────────────────────────────────────────────
    function generateSelector(el) {
        if (el.id) return '#' + el.id;
        var path = [];
        while (el && el.nodeType === 1) {
            var seg = el.tagName.toLowerCase();
            if (el.id) { path.unshift('#' + el.id); break; }
            if (el.className && typeof el.className === 'string') {
                var cls = el.className.trim().split(/\s+/).filter(function(c) {
                    return !c.startsWith('klytos-review');
                }).slice(0, 2).join('.');
                if (cls) seg += '.' + cls;
            }
            path.unshift(seg);
            el = el.parentElement;
        }
        return path.join(' > ');
    }

    function getAdminApiUrl(endpoint) {
        // Discover admin API URL from the page or fallback.
        var meta = document.querySelector('meta[name="klytos-admin-api"]');
        if (meta) return meta.getAttribute('content') + endpoint;
        // Fallback: assume standard structure.
        return '/admin/api/' + endpoint;
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="klytos-csrf"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function setStatus(msg, type) {
        var el = document.getElementById('krw-status');
        el.textContent = msg;
        el.style.color = type === 'error' ? '#ef4444' : type === 'success' ? '#22c55e' : '#94a3b8';
    }
})();
