/**
 * Klytos — Bulk Actions
 * Reusable checkbox selection + bulk action dropdown for admin list tables.
 *
 * Usage: Include this script on any page with:
 *   - A form with id="bulk-action-form"
 *   - A select with id="bulk-action-select"
 *   - A checkbox with id="bulk-select-all" in <thead>
 *   - Per-row checkboxes with class="bulk-checkbox" and name="bulk_slugs[]"
 *   - A span with id="bulk-count" for selected count display
 *
 * @since 0.26.0
 */
(function() {
    'use strict';

    var selectAll  = document.getElementById('bulk-select-all');
    var form       = document.getElementById('bulk-action-form');
    var actionSel  = document.getElementById('bulk-action-select');
    var countEl    = document.getElementById('bulk-count');
    var applyBtn   = document.getElementById('bulk-apply-btn');

    if (!selectAll || !form) return;

    function getCheckboxes() {
        return document.querySelectorAll('.bulk-checkbox');
    }

    function updateCount() {
        var checked = document.querySelectorAll('.bulk-checkbox:checked').length;
        if (countEl) {
            countEl.textContent = checked > 0 ? checked : '';
            countEl.style.display = checked > 0 ? 'inline' : 'none';
        }
        if (applyBtn) {
            applyBtn.disabled = checked === 0;
        }
    }

    // Select all / deselect all.
    selectAll.addEventListener('change', function() {
        var checked = selectAll.checked;
        getCheckboxes().forEach(function(cb) {
            cb.checked = checked;
        });
        updateCount();
    });

    // Individual checkbox changes.
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('bulk-checkbox')) {
            var all = getCheckboxes();
            var checked = document.querySelectorAll('.bulk-checkbox:checked');
            selectAll.checked = all.length > 0 && checked.length === all.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
            updateCount();
        }
    });

    // Form submission with confirmation for destructive actions.
    form.addEventListener('submit', function(e) {
        var action = actionSel ? actionSel.value : '';
        var checked = document.querySelectorAll('.bulk-checkbox:checked').length;

        if (!action || checked === 0) {
            e.preventDefault();
            return;
        }

        // Confirm destructive actions.
        if (action === 'delete' || action === 'permanent_delete') {
            var msg = form.getAttribute('data-confirm-delete') || 'Permanently delete selected items?';
            if (!confirm(msg.replace('{count}', checked))) {
                e.preventDefault();
            }
        }
    });

    updateCount();
})();
