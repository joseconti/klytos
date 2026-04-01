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
                            // For multiple files, dataTransfer.files may not be
                            // assignable to input.files in all browsers, so we
                            // pass the FileList directly.
                            self.onFilesDropped(e.dataTransfer.files);
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
        _installFiles: [],

        showInstallModal: function () {
            if (!this.el.installModal) return;
            this._installFiles = [];
            var fileInput = document.getElementById('plugin-install-file');
            if (fileInput) fileInput.value = '';
            var fileList = document.getElementById('plugin-install-file-list');
            if (fileList) { fileList.style.display = 'none'; fileList.innerHTML = ''; }
            var progress = document.getElementById('plugin-install-progress');
            if (progress) { progress.style.display = 'none'; progress.innerHTML = ''; }
            var confirmBtn = document.getElementById('plugin-install-confirm');
            if (confirmBtn) confirmBtn.disabled = true;
            this.el.installModal.classList.add('active');
        },

        hideInstallModal: function () {
            if (!this.el.installModal) return;
            this.el.installModal.classList.remove('active');
            this._installFiles = [];
        },

        onFileSelected: function (fileInput) {
            if (!fileInput.files || fileInput.files.length === 0) return;
            var self = this;
            var validFiles = [];

            for (var i = 0; i < fileInput.files.length; i++) {
                var file = fileInput.files[i];

                if (!file.name.toLowerCase().endsWith('.zip')) {
                    this.toast(file.name + ': only .zip files are accepted', 'error');
                    continue;
                }

                if (file.size > 50 * 1024 * 1024) {
                    this.toast(file.name + ': file too large (max 50MB)', 'error');
                    continue;
                }

                validFiles.push(file);
            }

            if (validFiles.length === 0) {
                fileInput.value = '';
                return;
            }

            this._installFiles = validFiles;
            var fileList = document.getElementById('plugin-install-file-list');
            if (fileList) {
                var html = '';
                for (var j = 0; j < validFiles.length; j++) {
                    var f = validFiles[j];
                    html += '<div class="plugin-install-file-item">';
                    html += '<i class="fa-solid fa-file-zipper"></i> ';
                    html += '<span>' + self.escHtml(f.name) + '</span>';
                    html += '<span class="plugin-install-file-size">' + self.formatSize(f.size) + '</span>';
                    html += '</div>';
                }
                fileList.innerHTML = html;
                fileList.style.display = 'block';
            }

            var confirmBtn = document.getElementById('plugin-install-confirm');
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.textContent = validFiles.length > 1 ? 'Install All (' + validFiles.length + ')' : 'Install';
            }
        },

        /**
         * Handle files dropped via drag-and-drop (supports multiple files).
         */
        onFilesDropped: function (fileList) {
            if (!fileList || fileList.length === 0) return;
            var self = this;
            var validFiles = [];

            for (var i = 0; i < fileList.length; i++) {
                var file = fileList[i];

                if (!file.name.toLowerCase().endsWith('.zip')) {
                    this.toast(file.name + ': only .zip files are accepted', 'error');
                    continue;
                }

                if (file.size > 50 * 1024 * 1024) {
                    this.toast(file.name + ': file too large (max 50MB)', 'error');
                    continue;
                }

                validFiles.push(file);
            }

            if (validFiles.length === 0) return;

            this._installFiles = validFiles;
            var fileListEl = document.getElementById('plugin-install-file-list');
            if (fileListEl) {
                var html = '';
                for (var j = 0; j < validFiles.length; j++) {
                    var f = validFiles[j];
                    html += '<div class="plugin-install-file-item">';
                    html += '<i class="fa-solid fa-file-zipper"></i> ';
                    html += '<span>' + self.escHtml(f.name) + '</span>';
                    html += '<span class="plugin-install-file-size">' + self.formatSize(f.size) + '</span>';
                    html += '</div>';
                }
                fileListEl.innerHTML = html;
                fileListEl.style.display = 'block';
            }

            var confirmBtn = document.getElementById('plugin-install-confirm');
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.textContent = validFiles.length > 1 ? 'Install All (' + validFiles.length + ')' : 'Install';
            }
        },

        /**
         * Upload a single file using XMLHttpRequest for real progress tracking.
         * Returns a Promise that resolves with the JSON response.
         */
        apiUploadWithProgress: function (file, onProgress) {
            var self = this;
            return new Promise(function (resolve) {
                var formData = new FormData();
                formData.append('action', 'install');
                formData.append('file', file);
                formData.append('csrf', self.csrf);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', self.apiUrl, true);
                xhr.setRequestHeader('X-CSRF-Token', self.csrf);
                xhr.withCredentials = true;

                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable && onProgress) {
                        var pct = Math.round((e.loaded / e.total) * 100);
                        onProgress(pct);
                    }
                });

                xhr.addEventListener('load', function () {
                    try {
                        resolve(JSON.parse(xhr.responseText));
                    } catch (_e) {
                        resolve({ success: false, error: 'Invalid server response' });
                    }
                });

                xhr.addEventListener('error', function () {
                    resolve({ success: false, error: 'Network error' });
                });

                xhr.send(formData);
            });
        },

        executeInstall: function () {
            if (!this._installFiles.length) return;
            var self = this;
            var files = this._installFiles.slice();
            var progressContainer = document.getElementById('plugin-install-progress');
            var confirmBtn = document.getElementById('plugin-install-confirm');
            var cancelBtn  = document.getElementById('plugin-install-cancel');

            if (confirmBtn) confirmBtn.disabled = true;
            if (cancelBtn) cancelBtn.disabled = true;

            // Build progress UI for each file.
            var html = '';
            for (var i = 0; i < files.length; i++) {
                html += '<div class="plugin-upload-progress-item" id="plugin-upload-item-' + i + '">';
                html += '<div class="plugin-upload-progress-header">';
                html += '<span class="plugin-upload-progress-name">' + self.escHtml(files[i].name) + '</span>';
                html += '<span class="plugin-upload-progress-pct" id="plugin-upload-pct-' + i + '">0%</span>';
                html += '</div>';
                html += '<div class="plugin-progress-bar">';
                html += '<div class="plugin-progress-fill" id="plugin-upload-fill-' + i + '"></div>';
                html += '</div>';
                html += '<p class="plugin-upload-progress-status" id="plugin-upload-status-' + i + '">Waiting...</p>';
                html += '</div>';
            }
            if (progressContainer) {
                progressContainer.innerHTML = html;
                progressContainer.style.display = 'block';
            }

            var successCount = 0;
            var errorCount = 0;

            // Upload files sequentially.
            var uploadNext = function (index) {
                if (index >= files.length) {
                    // All done.
                    if (cancelBtn) cancelBtn.disabled = false;
                    if (successCount > 0) {
                        var msg = successCount === 1
                            ? 'Plugin installed successfully'
                            : successCount + ' plugins installed successfully';
                        self.toast(msg, 'success');
                        setTimeout(function () {
                            self.hideInstallModal();
                            location.reload();
                        }, 1000);
                    } else if (errorCount > 0) {
                        if (confirmBtn) confirmBtn.disabled = false;
                        if (cancelBtn) cancelBtn.disabled = false;
                    }
                    return;
                }

                var fill   = document.getElementById('plugin-upload-fill-' + index);
                var pct    = document.getElementById('plugin-upload-pct-' + index);
                var status = document.getElementById('plugin-upload-status-' + index);
                var item   = document.getElementById('plugin-upload-item-' + index);

                if (status) status.textContent = 'Uploading...';
                if (item) item.classList.add('uploading');

                self.apiUploadWithProgress(files[index], function (percent) {
                    if (fill) fill.style.width = percent + '%';
                    if (pct) pct.textContent = percent + '%';
                }).then(function (result) {
                    if (fill) fill.style.width = '100%';
                    if (pct) pct.textContent = '100%';

                    if (result.success) {
                        if (status) status.textContent = 'Installed';
                        if (item) { item.classList.remove('uploading'); item.classList.add('success'); }
                        successCount++;
                    } else {
                        var errMsg = result.error || 'Install failed';
                        if (status) status.textContent = errMsg;
                        if (item) { item.classList.remove('uploading'); item.classList.add('error'); }
                        errorCount++;
                    }

                    uploadNext(index + 1);
                });
            };

            uploadNext(0);
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
