/**
 * Klytos — Sidebar Sort / Reorder
 * Enables drag-and-drop reordering of sidebar menu items and sections.
 * Requires SortableJS to be loaded before this script.
 *
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 Jose Conti
 */
(function () {
    'use strict';

    // ── DOM references ──────────────────────────────────────
    var sidebar    = document.getElementById('sidebar');
    var container  = document.getElementById('sidebarSectionsContainer');
    var toggleBtn  = document.getElementById('sidebarEditToggle');
    var resetBtn   = document.getElementById('sidebarEditReset');
    var searchInput = document.getElementById('sidebarSearchInput');

    if (!sidebar || !container || !toggleBtn || !resetBtn) {
        return;
    }

    var apiUrl = sidebar.getAttribute('data-api-url') || '';
    var csrf   = sidebar.getAttribute('data-csrf') || '';

    // ── State ───────────────────────────────────────────────
    var editMode         = false;
    var sectionSortable  = null;
    var itemSortables    = [];

    // ── API helper ──────────────────────────────────────────
    function apiCall(action, data) {
        var body = { action: action };
        if (data) {
            body.order = data;
        }
        return fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf
            },
            body: JSON.stringify(body)
        });
    }

    // ── Read current DOM order ──────────────────────────────
    function readOrder() {
        var sections = [];
        var items    = {};

        container.querySelectorAll('.sidebar-section-group').forEach(function (group) {
            var section = group.getAttribute('data-section');
            sections.push(section);
            items[section] = [];
            group.querySelectorAll('.sidebar-item-wrap[data-item-id]').forEach(function (item) {
                items[section].push(item.getAttribute('data-item-id'));
            });
        });

        return { sections: sections, items: items };
    }

    // ── Save current order ──────────────────────────────────
    function saveOrder() {
        apiCall('save', readOrder());
    }

    // ── Prevent navigation during edit mode ─────────────────
    function preventNavigation(e) {
        if (!editMode) {
            return;
        }
        var link = e.target.closest('a');
        if (link && link.closest('.sidebar-nav')) {
            e.preventDefault();
            e.stopPropagation();
        }
    }

    // ── Enter edit mode ─────────────────────────────────────
    function enterEditMode() {
        editMode = true;
        sidebar.classList.add('sidebar-editing');
        resetBtn.style.display = '';

        // Auto-expand if collapsed.
        if (sidebar.classList.contains('collapsed')) {
            sidebar.classList.remove('collapsed');
            var content = sidebar.nextElementSibling;
            if (content) {
                content.style.marginLeft = '260px';
            }
            localStorage.setItem('klytos_sidebar_collapsed', '0');
        }

        // Init SortableJS on section container (reorder sections).
        sectionSortable = new Sortable(container, {
            animation: 150,
            handle: '.sidebar-section-drag-handle',
            draggable: '.sidebar-section-group',
            ghostClass: 'sidebar-sortable-ghost',
            chosenClass: 'sidebar-sortable-chosen',
            onEnd: saveOrder
        });

        // Init SortableJS on each item list (reorder items within/across sections).
        container.querySelectorAll('.sidebar-section-items').forEach(function (el) {
            itemSortables.push(new Sortable(el, {
                animation: 150,
                handle: '.sidebar-item-drag-handle',
                draggable: '.sidebar-item-wrap',
                group: 'sidebar-items',
                ghostClass: 'sidebar-sortable-ghost',
                chosenClass: 'sidebar-sortable-chosen',
                onEnd: saveOrder
            }));
        });

        // Intercept clicks on menu links.
        sidebar.addEventListener('click', preventNavigation, true);
    }

    // ── Exit edit mode ──────────────────────────────────────
    function exitEditMode() {
        editMode = false;
        sidebar.classList.remove('sidebar-editing');
        resetBtn.style.display = 'none';

        // Destroy all Sortable instances.
        if (sectionSortable) {
            sectionSortable.destroy();
            sectionSortable = null;
        }
        itemSortables.forEach(function (s) {
            s.destroy();
        });
        itemSortables = [];

        // Remove click interception.
        sidebar.removeEventListener('click', preventNavigation, true);
    }

    // ── Reset order ─────────────────────────────────────────
    function resetOrder() {
        apiCall('reset', null).then(function () {
            window.location.reload();
        });
    }

    // ── Event listeners (no inline handlers) ────────────────
    toggleBtn.addEventListener('click', function () {
        if (editMode) {
            exitEditMode();
        } else {
            enterEditMode();
        }
    });

    resetBtn.addEventListener('click', resetOrder);

    // Exit edit mode when search input gets focus.
    if (searchInput) {
        searchInput.addEventListener('focus', function () {
            if (editMode) {
                exitEditMode();
            }
        });
    }
})();
