/**
 * Klytos Admin — Plugins Page
 * Handles all plugin operations via AJAX: activate, deactivate, delete, uninstall,
 * install from ZIP, backup/restore, and bulk actions.
 *
 * All fetch requests use credentials: 'same-origin' and send CSRF via X-CSRF-Token
 * header (for JSON) or form field (for file uploads). The script is loaded with a
 * CSP nonce attribute so it will not be blocked by Content-Security-Policy.
 *
 * @package Klytos
 * @since   0.15.0
 */
(function () {
    'use strict';

    var Plugins = {
        el: {},
        csrf: '',
        apiUrl: '',
        selected: new Set(),
        busy: false,

        // ─── Init ────────────────────────────────────────────────
        init: function () {
            var container = document.getElementById('plugins-container');
            if (!container) return;

            this.csrf   = container.dataset.csrf || '';
            this.apiUrl = container.dataset.apiUrl || '';

            // Cache elements.
            this.el.table          = container.querySelector('#plugins-table');
            this.el.tbody          = container.querySelector('#plugins-tbody');
            this.el.selectAll      = container.querySelector('#plugin-select-all');
            this.el.bulkAction     = container.querySelector('#plugin-bulk-action');
            this.el.bulkApply      = container.querySelector('#plugin-bulk-apply');
            this.el.modal          = document.getElementById('plugin-delete-modal');
            this.el.installModal   = document.getElementById('plugin-install-modal');
            this.el.restoreModal   = document.getElementById('plugin-restore-modal');
            this.el.toasts         = document.getElementById('plugin-toast-container');

            this.bindEvents();
        },

        bindEvents: function () {
            var self = this;

            // Event delegation on table body for action buttons.
            if (this.el.tbody) {
                this.el.tbody.addEventListener('click', function (e) {
                    var btn = e.target.closest('[data-action]');
                    if (!btn) return;

                    var action   = btn.dataset.action;
                    var pluginId = btn.dataset.plugin;
                    if (!pluginId) return;

                    switch (action) {
                        case 'activate':   self.activate(pluginId); break;
                        case 'deactivate': self.deactivate(pluginId); break;
                        case 'delete':     self.confirmDelete(pluginId); break;
                        case 'update':     self.updatePlugin(pluginId); break;
                        case 'restore':    self.showRestoreModal(pluginId); break;
                    }
                });

                // Checkboxes for row selection.
                this.el.tbody.addEventListener('change', function (e) {
                    if (e.target.classList.contains('plugin-checkbox')) {
                        var pluginId = e.target.dataset.plugin;
                        if (e.target.checked) {
                            self.selected.add(pluginId);
                        } else {
                            self.selected.delete(pluginId);
                        }
                        self.updateBulkUI();
                    }
                });
            }

            // Select all.
            if (this.el.selectAll) {
                this.el.selectAll.addEventListener('change', function () {
                    self.selectAll(self.el.selectAll.checked);
                });
            }

            // Bulk apply.
            if (this.el.bulkApply) {
                this.el.bulkApply.addEventListener('click', function () { self.applyBulk(); });
            }

            // Delete modal events.
            if (this.el.modal) {
                this.el.modal.querySelector('.plugin-delete-modal-close').addEventListener('click', function () { self.hideDeleteModal(); });
                this.el.modal.querySelector('#plugin-modal-cancel').addEventListener('click', function () { self.hideDeleteModal(); });
                this.el.modal.querySelector('#plugin-modal-confirm').addEventListener('click', function () { self.executeDelete(); });
                this.el.modal.addEventListener('click', function (e) {
                    if (e.target === self.el.modal) self.hideDeleteModal();
                });
            }

            // Install modal events.
            if (this.el.installModal) {
                var installBtn = document.getElementById('plugin-install-btn');
                if (installBtn) {
                    installBtn.addEventListener('click', function () { self.showInstallModal(); });
                }
                this.el.installModal.querySelector('.plugin-delete-modal-close').addEventListener('click', function () { self.hideInstallModal(); });
                this.el.installModal.querySelector('#plugin-install-cancel').addEventListener('click', function () { self.hideInstallModal(); });
                this.el.installModal.querySelector('#plugin-install-confirm').addEventListener('click', function () { self.executeInstall(); });
                this.el.installModal.addEventListener('click', function (e) {
                    if (e.target === self.el.installModal) self.hideInstallModal();
                });

                // File input and drag-and-drop.
                var uploadArea = document.getElementById('plugin-upload-area');
                var fileInput  = document.getElementById('plugin-install-file');

                if (uploadArea && fileInput) {
                    uploadArea.addEventListener('click', function () { fileInput.click(); });
                    fileInput.addEventListener('change', function () { self.onFileSelected(fileInput); });

                    uploadArea.addEventListener('dragover', function (e) {
                        e.preventDefault();
                        uploadArea.classList.add('dragover');
                    });
                    uploadArea.addEventListener('dragleave', function () {
                        uploadArea.classList.remove('dragover');
                    });
                    uploadArea.addEventListener('drop', function (e) {
                        e.preventDefault();
                        uploadArea.classList.remove('dragover');
                        if (e.dataTransfer.files.length > 0) {
                            fileInput.files = e.dataTransfer.files;
                            self.onFileSelected(fileInput);
                        }
                    });
                }
            }

            // Restore modal events.
            if (this.el.restoreModal) {
                this.el.restoreModal.querySelector('.plugin-delete-modal-close').addEventListener('click', function () { self.hideRestoreModal(); });
                this.el.restoreModal.querySelector('#plugin-restore-cancel').addEventListener('click', function () { self.hideRestoreModal(); });
                this.el.restoreModal.addEventListener('click', function (e) {
                    if (e.target === self.el.restoreModal) self.hideRestoreModal();
                });
            }

            // Global Escape key for all modals.
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    if (self.el.modal && self.el.modal.classList.contains('active')) self.hideDeleteModal();
                    if (self.el.installModal && self.el.installModal.classList.contains('active')) self.hideInstallModal();
                    if (self.el.restoreModal && self.el.restoreModal.classList.contains('active')) self.hideRestoreModal();
                }
            });
        },

        // ─── API (JSON) ──────────────────────────────────────────
        api: function (action, plugins, extra) {
            var body = { action: action, plugins: plugins };
            if (extra) {
                for (var k in extra) {
                    if (extra.hasOwnProperty(k)) body[k] = extra[k];
                }
            }
            return fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrf,
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            })
            .then(function (r) { return r.json(); })
            .catch(function (err) { return { success: false, results: {}, error: 'Network error: ' + err.message }; });
        },

        // ─── API (File Upload) ───────────────────────────────────
        apiUpload: function (file) {
            var formData = new FormData();
            formData.append('action', 'install');
            formData.append('file', file);
            formData.append('csrf', this.csrf);

            return fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.csrf,
                },
                credentials: 'same-origin',
                body: formData,
            })
            .then(function (r) { return r.json(); })
            .catch(function (err) { return { success: false, error: 'Network error: ' + err.message }; });
        },

        // ─── Individual Actions ──────────────────────────────────
        activate: function (pluginId) {
            var self = this;
            var btn = this.el.tbody.querySelector('[data-action="activate"][data-plugin="' + pluginId + '"]');
            if (!btn) return;

            this.showSpinner(btn);
            this.api('activate', [pluginId]).then(function (result) {
                self.hideSpinner(btn);
                if (result.results && result.results[pluginId] && result.results[pluginId].success) {
                    self.setRowState(pluginId, 'active');
                    self.toast('Plugin activated successfully', 'success');
                } else {
                    var err = (result.results && result.results[pluginId] && result.results[pluginId].error) || result.error || 'Unknown error';
                    self.toast(err, 'error');
                }
            });
        },

        deactivate: function (pluginId) {
            var self = this;
            var btn = this.el.tbody.querySelector('[data-action="deactivate"][data-plugin="' + pluginId + '"]');
            if (!btn) return;

            this.showSpinner(btn);
            this.api('deactivate', [pluginId]).then(function (result) {
                self.hideSpinner(btn);
                if (result.results && result.results[pluginId] && result.results[pluginId].success) {
                    self.setRowState(pluginId, 'inactive');
                    self.toast('Plugin deactivated successfully', 'success');
                } else {
                    var err = (result.results && result.results[pluginId] && result.results[pluginId].error) || result.error || 'Unknown error';
                    self.toast(err, 'error');
                }
            });
        },

        updatePlugin: function (pluginId) {
            var self = this;
            var btn = this.el.tbody.querySelector('[data-action="update"][data-plugin="' + pluginId + '"]');
            if (!btn) return;

            var row = this.el.tbody.querySelector('tr[data-plugin="' + pluginId + '"]');
            if (row) row.classList.add('updating');

            this.showSpinner(btn);
            this.api('update', [pluginId]).then(function (result) {
                self.hideSpinner(btn);
                if (row) row.classList.remove('updating');

                if (result.results && result.results[pluginId] && result.results[pluginId].success) {
                    self.toast('Plugin updated successfully', 'success');
                    setTimeout(function () { location.reload(); }, 800);
                } else {
                    var err = (result.results && result.results[pluginId] && result.results[pluginId].error) || result.error || 'Unknown error';
                    self.toast(err, 'error');
                }
            });
        },

        // ─── Delete Flow ─────────────────────────────────────────
        _pendingDeleteId: null,
        _pendingDeleteName: '',
        _pendingDeleteBulk: null,

        confirmDelete: function (pluginId) {
            var row  = this.el.tbody.querySelector('tr[data-plugin="' + pluginId + '"]');
            var name = row ? row.dataset.pluginName : pluginId;
            this._pendingDeleteBulk = null;
            this.showDeleteModal(pluginId, name);
        },

        confirmBulkDelete: function (pluginIds) {
            var self = this;
            this._pendingDeleteBulk = pluginIds;
            var names = pluginIds.map(function (id) {
                var row = self.el.tbody.querySelector('tr[data-plugin="' + id + '"]');
                return row ? row.dataset.pluginName : id;
            });
            this.showDeleteModal(null, names.join(', '));
        },

        executeDelete: function () {
            var self = this;
            var withData = this.el.modal.querySelector('#plugin-delete-data').checked;
            var action   = withData ? 'uninstall' : 'delete';
            var pluginIds = this._pendingDeleteBulk || [this._pendingDeleteId];
            this.hideDeleteModal();

            for (var i = 0; i < pluginIds.length; i++) {
                var row = this.el.tbody.querySelector('tr[data-plugin="' + pluginIds[i] + '"]');
                if (row) row.classList.add('updating');
            }

            this.api(action, pluginIds).then(function (result) {
                var deletedCount = 0;
                for (var j = 0; j < pluginIds.length; j++) {
                    var pid = pluginIds[j];
                    if (result.results && result.results[pid] && result.results[pid].success) {
                        self.removeRow(pid);
                        self.selected.delete(pid);
                        deletedCount++;
                    } else {
                        var r = self.el.tbody.querySelector('tr[data-plugin="' + pid + '"]');
                        if (r) r.classList.remove('updating');
                        var err = (result.results && result.results[pid] && result.results[pid].error) || 'Delete failed';
                        self.toast(err, 'error');
                    }
                }
                if (deletedCount > 0) {
                    var msg = deletedCount === 1 ? 'Plugin deleted successfully' : deletedCount + ' plugins deleted successfully';
                    self.toast(msg, 'success');
                }
                self.updateBulkUI();
            });
        },

        // ─── Install Flow ────────────────────────────────────────
        _installFile: null,

        showInstallModal: function () {
            if (!this.el.installModal) return;
            this._installFile = null;
            var fileInput = document.getElementById('plugin-install-file');
            if (fileInput) fileInput.value = '';
            var filename = document.getElementById('plugin-install-filename');
            if (filename) { filename.style.display = 'none'; filename.textContent = ''; }
            var progress = document.getElementById('plugin-install-progress');
            if (progress) progress.style.display = 'none';
            var confirmBtn = document.getElementById('plugin-install-confirm');
            if (confirmBtn) confirmBtn.disabled = true;
            this.el.installModal.classList.add('active');
        },

        hideInstallModal: function () {
            if (!this.el.installModal) return;
            this.el.installModal.classList.remove('active');
            this._installFile = null;
        },

        onFileSelected: function (fileInput) {
            if (!fileInput.files || fileInput.files.length === 0) return;
            var file = fileInput.files[0];

            // Validate extension.
            if (!file.name.toLowerCase().endsWith('.zip')) {
                this.toast('Only .zip files are accepted', 'error');
                fileInput.value = '';
                return;
            }

            // Validate size (50MB).
            if (file.size > 50 * 1024 * 1024) {
                this.toast('File too large. Maximum: 50MB', 'error');
                fileInput.value = '';
                return;
            }

            this._installFile = file;
            var filename = document.getElementById('plugin-install-filename');
            if (filename) {
                filename.textContent = file.name + ' (' + this.formatSize(file.size) + ')';
                filename.style.display = 'block';
            }
            var confirmBtn = document.getElementById('plugin-install-confirm');
            if (confirmBtn) confirmBtn.disabled = false;
        },

        executeInstall: function () {
            if (!this._installFile) return;
            var self = this;
            var progress  = document.getElementById('plugin-install-progress');
            var fill      = document.getElementById('plugin-progress-fill');
            var status    = document.getElementById('plugin-install-status');
            var confirmBtn = document.getElementById('plugin-install-confirm');
            var cancelBtn  = document.getElementById('plugin-install-cancel');

            if (progress) progress.style.display = 'block';
            if (fill) fill.style.width = '30%';
            if (status) status.textContent = 'Uploading plugin...';
            if (confirmBtn) confirmBtn.disabled = true;
            if (cancelBtn) cancelBtn.disabled = true;

            this.apiUpload(this._installFile).then(function (result) {
                if (fill) fill.style.width = '100%';

                if (result.success) {
                    if (status) status.textContent = 'Plugin installed successfully!';
                    self.toast('Plugin installed successfully', 'success');
                    setTimeout(function () {
                        self.hideInstallModal();
                        location.reload();
                    }, 1000);
                } else {
                    if (status) status.textContent = 'Error: ' + (result.error || 'Unknown error');
                    self.toast(result.error || 'Install failed', 'error');
                    if (confirmBtn) confirmBtn.disabled = false;
                    if (cancelBtn) cancelBtn.disabled = false;
                }
            });
        },

        // ─── Restore Flow ────────────────────────────────────────
        _restorePluginId: null,

        showRestoreModal: function (pluginId) {
            if (!this.el.restoreModal) return;
            var self = this;
            this._restorePluginId = pluginId;

            var row  = this.el.tbody ? this.el.tbody.querySelector('tr[data-plugin="' + pluginId + '"]') : null;
            var name = row ? row.dataset.pluginName : pluginId;

            this.el.restoreModal.querySelector('#plugin-restore-name').textContent = name;

            var list = this.el.restoreModal.querySelector('#plugin-restore-list');
            list.innerHTML = '<p style="color: var(--admin-text-muted);">Loading backups...</p>';

            this.el.restoreModal.classList.add('active');

            // Fetch backups.
            this.api('list_backups', [pluginId]).then(function (result) {
                var backups = (result.results && result.results[pluginId] && result.results[pluginId].backups) || [];

                if (backups.length === 0) {
                    list.innerHTML = '<p style="color: var(--admin-text-muted);">No backups available for this plugin.</p>';
                    return;
                }

                var html = '';
                for (var i = 0; i < backups.length; i++) {
                    var b = backups[i];
                    html += '<div class="plugin-backup-item" style="display:flex;justify-content:space-between;align-items:center;padding:0.6rem 0;border-bottom:1px solid var(--admin-border);">';
                    html += '<div>';
                    html += '<strong>v' + self.escHtml(b.version) + '</strong>';
                    html += '<span style="color:var(--admin-text-muted);font-size:0.82rem;margin-left:0.75rem;">' + self.escHtml(b.date) + '</span>';
                    html += '</div>';
                    html += '<button type="button" class="btn btn-primary btn-sm" data-restore-backup="' + self.escAttr(b.name) + '">Restore</button>';
                    html += '</div>';
                }
                list.innerHTML = html;

                // Bind restore buttons.
                list.querySelectorAll('[data-restore-backup]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        self.executeRestore(pluginId, btn.dataset.restoreBackup, btn);
                    });
                });
            });
        },

        hideRestoreModal: function () {
            if (!this.el.restoreModal) return;
            this.el.restoreModal.classList.remove('active');
            this._restorePluginId = null;
        },

        executeRestore: function (pluginId, backupName, btn) {
            var self = this;
            this.showSpinner(btn);

            this.api('restore', [pluginId], { backup_name: backupName }).then(function (result) {
                self.hideSpinner(btn);
                if (result.results && result.results[pluginId] && result.results[pluginId].success) {
                    self.toast('Plugin restored successfully', 'success');
                    self.hideRestoreModal();
                    setTimeout(function () { location.reload(); }, 800);
                } else {
                    var err = (result.results && result.results[pluginId] && result.results[pluginId].error) || result.error || 'Restore failed';
                    self.toast(err, 'error');
                }
            });
        },

        // ─── Bulk Actions ────────────────────────────────────────
        selectAll: function (checked) {
            var self = this;
            var checkboxes = this.el.tbody.querySelectorAll('.plugin-checkbox');
            checkboxes.forEach(function (cb) {
                cb.checked = checked;
                var pluginId = cb.dataset.plugin;
                if (checked) {
                    self.selected.add(pluginId);
                } else {
                    self.selected.delete(pluginId);
                }
            });
            this.updateBulkUI();
        },

        updateBulkUI: function () {
            var total   = this.el.tbody ? this.el.tbody.querySelectorAll('.plugin-checkbox').length : 0;
            var checked = this.selected.size;

            if (this.el.selectAll) {
                this.el.selectAll.checked       = checked > 0 && checked === total;
                this.el.selectAll.indeterminate = checked > 0 && checked < total;
            }

            if (this.el.bulkAction) this.el.bulkAction.disabled = checked === 0;
            if (this.el.bulkApply)  this.el.bulkApply.disabled  = checked === 0;
        },

        applyBulk: function () {
            if (this.busy || this.selected.size === 0) return;
            var action = this.el.bulkAction ? this.el.bulkAction.value : '';
            if (!action) {
                this.toast('Please select an action', 'error');
                return;
            }

            var pluginIds = Array.from(this.selected);

            if (action === 'delete' || action === 'uninstall') {
                this.confirmBulkDelete(pluginIds);
                return;
            }

            var self = this;
            this.busy = true;

            var processNext = function (index) {
                if (index >= pluginIds.length) {
                    self.toast('Bulk action completed', 'success');
                    self.busy = false;
                    return;
                }

                var pluginId = pluginIds[index];
                var row = self.el.tbody.querySelector('tr[data-plugin="' + pluginId + '"]');
                if (row) row.classList.add('updating');

                self.api(action, [pluginId]).then(function (result) {
                    if (row) row.classList.remove('updating');

                    if (result.results && result.results[pluginId] && result.results[pluginId].success) {
                        if (action === 'activate') self.setRowState(pluginId, 'active');
                        else if (action === 'deactivate') self.setRowState(pluginId, 'inactive');
                    } else {
                        var err = (result.results && result.results[pluginId] && result.results[pluginId].error) || 'Error';
                        self.toast(pluginId + ': ' + err, 'error');
                    }

                    setTimeout(function () { processNext(index + 1); }, 200);
                });
            };

            processNext(0);
        },

        // ─── UI Updates ──────────────────────────────────────────
        setRowState: function (pluginId, state) {
            var row = this.el.tbody.querySelector('tr[data-plugin="' + pluginId + '"]');
            if (!row) return;

            var badge   = row.querySelector('.plugin-status-badge');
            var actions = row.querySelector('.plugin-actions');

            if (badge) {
                badge.className = 'badge-status plugin-status-badge ' + (state === 'active' ? 'badge-active' : 'badge-inactive');
                badge.textContent = state === 'active' ? 'Active' : 'Inactive';
            }

            if (actions) {
                var pid = this.escAttr(pluginId);
                var html = '';
                if (state === 'active') {
                    html += '<button type="button" class="btn btn-outline btn-sm" data-action="deactivate" data-plugin="' + pid + '">Deactivate</button>';
                } else {
                    html += '<button type="button" class="btn btn-primary btn-sm" data-action="activate" data-plugin="' + pid + '">Activate</button>';
                }
                html += '<button type="button" class="btn btn-outline btn-sm" data-action="restore" data-plugin="' + pid + '">Restore</button>';
                html += '<button type="button" class="btn btn-danger btn-sm" data-action="delete" data-plugin="' + pid + '">Delete</button>';
                actions.innerHTML = html;
            }
        },

        removeRow: function (pluginId) {
            var self = this;
            var row = this.el.tbody.querySelector('tr[data-plugin="' + pluginId + '"]');
            if (!row) return;

            row.classList.add('removing');
            row.addEventListener('transitionend', function () {
                row.remove();
                if (self.el.tbody.children.length === 0) location.reload();
            }, { once: true });

            setTimeout(function () {
                if (row.parentNode) {
                    row.remove();
                    if (self.el.tbody.children.length === 0) location.reload();
                }
            }, 500);
        },

        showSpinner: function (btn) {
            if (!btn) return;
            btn._originalHTML = btn.innerHTML;
            btn.innerHTML = '<span class="plugin-spinner"></span>';
            btn.disabled = true;
        },

        hideSpinner: function (btn) {
            if (!btn) return;
            btn.innerHTML = btn._originalHTML || '';
            btn.disabled = false;
        },

        toast: function (message, type) {
            if (!this.el.toasts) return;

            var icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            var toast = document.createElement('div');
            toast.className = 'plugin-toast plugin-toast-' + type;
            toast.innerHTML = '<i class="fa-solid ' + icon + '"></i> ' + this.escHtml(message);

            this.el.toasts.appendChild(toast);
            toast.offsetHeight; // force reflow
            toast.classList.add('visible');

            setTimeout(function () {
                toast.classList.remove('visible');
                toast.classList.add('hiding');
                setTimeout(function () { toast.remove(); }, 300);
            }, 3000);
        },

        // ─── Delete Modal ────────────────────────────────────────
        showDeleteModal: function (pluginId, pluginName) {
            if (!this.el.modal) return;
            this._pendingDeleteId   = pluginId;
            this._pendingDeleteName = pluginName;
            this.el.modal.querySelector('#plugin-modal-name').textContent = pluginName;
            this.el.modal.querySelector('#plugin-delete-data').checked = false;
            this.el.modal.classList.add('active');
            var self = this;
            setTimeout(function () {
                self.el.modal.querySelector('#plugin-modal-cancel').focus();
            }, 100);
        },

        hideDeleteModal: function () {
            if (!this.el.modal) return;
            this.el.modal.classList.remove('active');
            this._pendingDeleteId = null;
            this._pendingDeleteName = '';
            this._pendingDeleteBulk = null;
        },

        // ─── Helpers ─────────────────────────────────────────────
        escHtml: function (str) {
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },

        escAttr: function (str) {
            return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        },

        formatSize: function (bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        },
    };

    document.addEventListener('DOMContentLoaded', function () {
        Plugins.init();
    });
})();
