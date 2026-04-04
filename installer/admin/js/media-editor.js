/**
 * Klytos — Media Editor
 * Client-side image editing with Cropper.js integration.
 *
 * Requires: Cropper.js loaded before this script.
 * Expects: CSRF_TOKEN and API_BASE global variables set by the admin page.
 *
 * @since 0.26.0
 */
(function() {
    'use strict';

    var modal, img, cropper, currentPath, currentMime;
    var isCropping = false;

    // Build modal HTML.
    function createModal() {
        modal = document.createElement('div');
        modal.id = 'media-editor-modal';
        modal.innerHTML =
            '<div class="media-editor-container">' +
                '<div class="media-editor-header">' +
                    '<h3 id="media-editor-title">Edit Image</h3>' +
                    '<button type="button" class="btn btn-outline btn-sm" id="media-editor-close">&times;</button>' +
                '</div>' +
                '<div class="media-editor-canvas">' +
                    '<img id="media-editor-img" src="" alt="Edit">' +
                '</div>' +
                '<div class="media-editor-toolbar">' +
                    '<button type="button" class="btn btn-outline btn-sm" data-action="crop" title="Crop">Crop</button>' +
                    '<button type="button" class="btn btn-outline btn-sm" data-action="rotate-left" title="Rotate Left">&#x21BA; 90&deg;</button>' +
                    '<button type="button" class="btn btn-outline btn-sm" data-action="rotate-right" title="Rotate Right">&#x21BB; 90&deg;</button>' +
                    '<button type="button" class="btn btn-outline btn-sm" data-action="flip-h" title="Flip Horizontal">&#x2194; Flip H</button>' +
                    '<button type="button" class="btn btn-outline btn-sm" data-action="flip-v" title="Flip Vertical">&#x2195; Flip V</button>' +
                    '<span class="media-editor-resize">' +
                        '<label>W:</label><input type="number" id="media-resize-w" min="1" max="9999" class="form-control">' +
                        '<label>H:</label><input type="number" id="media-resize-h" min="1" max="9999" class="form-control">' +
                        '<button type="button" class="btn btn-outline btn-sm" data-action="resize">Resize</button>' +
                    '</span>' +
                '</div>' +
                '<div class="media-editor-footer">' +
                    '<button type="button" class="btn btn-outline btn-sm" id="media-editor-save-copy">Save as copy</button>' +
                    '<button type="button" class="btn btn-primary btn-sm" id="media-editor-save">Save</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(modal);

        img = document.getElementById('media-editor-img');
        document.getElementById('media-editor-close').addEventListener('click', close);
        modal.addEventListener('click', function(e) { if (e.target === modal) close(); });

        // Toolbar actions.
        modal.querySelectorAll('[data-action]').forEach(function(btn) {
            btn.addEventListener('click', function() { handleAction(btn.getAttribute('data-action')); });
        });

        document.getElementById('media-editor-save').addEventListener('click', function() { save(false); });
        document.getElementById('media-editor-save-copy').addEventListener('click', function() { save(true); });
    }

    function open(path, src, mime) {
        if (!modal) createModal();
        currentPath = path;
        currentMime = mime || 'image/jpeg';
        img.src = src + '?t=' + Date.now();
        modal.classList.add('active');
        isCropping = false;
        if (cropper) { cropper.destroy(); cropper = null; }
    }

    function close() {
        modal.classList.remove('active');
        if (cropper) { cropper.destroy(); cropper = null; }
        isCropping = false;
    }

    function handleAction(action) {
        switch (action) {
            case 'crop':
                if (!isCropping) {
                    if (typeof Cropper === 'undefined') { alert('Cropper.js not loaded'); return; }
                    cropper = new Cropper(img, { viewMode: 1, autoCropArea: 0.8 });
                    isCropping = true;
                } else {
                    // Apply crop.
                    var data = cropper.getData(true);
                    sendEdit({ crop: { x: data.x, y: data.y, width: data.width, height: data.height } });
                    cropper.destroy(); cropper = null;
                    isCropping = false;
                }
                break;
            case 'rotate-left':
                sendEdit({ rotate: 270 });
                break;
            case 'rotate-right':
                sendEdit({ rotate: 90 });
                break;
            case 'flip-h':
                sendEdit({ flip: 'horizontal' });
                break;
            case 'flip-v':
                sendEdit({ flip: 'vertical' });
                break;
            case 'resize':
                var w = parseInt(document.getElementById('media-resize-w').value, 10);
                var h = parseInt(document.getElementById('media-resize-h').value, 10);
                if (w > 0) {
                    var resizeOp = { width: w };
                    if (h > 0) resizeOp.height = h;
                    sendEdit({ resize: resizeOp });
                }
                break;
        }
    }

    function sendEdit(operations, saveAs) {
        var body = { path: currentPath };
        Object.keys(operations).forEach(function(k) { body[k] = operations[k]; });
        if (saveAs) body.save_as = saveAs;

        var apiUrl = (typeof API_BASE !== 'undefined' ? API_BASE : 'api/') + 'image-edit.php';

        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''
            },
            body: JSON.stringify(body)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.asset) {
                // Reload image.
                currentPath = data.asset.path || currentPath;
                img.src = img.src.split('?')[0] + '?t=' + Date.now();
            } else {
                alert(data.error || 'Edit failed');
            }
        })
        .catch(function(err) { alert('Error: ' + err.message); });
    }

    function save(asCopy) {
        if (asCopy) {
            var ext = currentPath.split('.').pop();
            var base = currentPath.replace(/\.[^.]+$/, '');
            var copyName = base + '-copy.' + ext;
            // Send a no-op edit with save_as to create a copy.
            sendEdit({}, copyName.split('/').pop());
        }
        close();
        // Refresh the page to show updated image.
        if (typeof location !== 'undefined') location.reload();
    }

    // Expose global open function for asset detail panel.
    window.klytosMediaEditor = { open: open, close: close };
})();
