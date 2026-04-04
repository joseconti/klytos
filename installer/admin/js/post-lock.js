/**
 * Klytos — Post Lock
 * Client-side editing lock management with heartbeat.
 *
 * Expects data attributes on #post-lock-data element:
 *   data-slug, data-user-id, data-api-url, data-csrf
 *
 * @since 0.26.0
 */
(function() {
    'use strict';

    var config = document.getElementById('post-lock-data');
    if (!config) return;

    var slug    = config.getAttribute('data-slug');
    var userId  = config.getAttribute('data-user-id');
    var apiUrl  = config.getAttribute('data-api-url');
    var csrf    = config.getAttribute('data-csrf');
    var heartbeatInterval = null;

    function request(action, callback) {
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf
            },
            body: JSON.stringify({ action: action, slug: slug })
        })
        .then(function(r) { return r.json(); })
        .then(callback)
        .catch(function() {});
    }

    // Acquire lock on page load.
    request('acquire', function(data) {
        if (data.locked === false) {
            // Another user has the lock.
            showLockWarning(data.lock_owner_name || 'Another user');
        } else {
            // Lock acquired — start heartbeat.
            startHeartbeat();
        }
    });

    function startHeartbeat() {
        heartbeatInterval = setInterval(function() {
            request('heartbeat', function(data) {
                if (!data.renewed) {
                    // Lock lost.
                    clearInterval(heartbeatInterval);
                    showLockLostWarning();
                }
            });
        }, 60000); // Every 60 seconds.
    }

    function showLockWarning(ownerName) {
        var overlay = document.createElement('div');
        overlay.className = 'modal-overlay active';
        overlay.innerHTML =
            '<div class="modal" style="max-width:400px;padding:2rem;text-align:center">' +
                '<h3>' + escHtml(ownerName) + ' is editing this page</h3>' +
                '<p style="color:var(--klytos-text-muted);margin:1rem 0">If you take over, their unsaved changes may be lost.</p>' +
                '<div class="flex flex-gap-sm" style="justify-content:center">' +
                    '<button class="btn btn-outline" id="lock-go-back">Go back</button>' +
                    '<button class="btn btn-primary" id="lock-takeover">Take over</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);

        document.getElementById('lock-go-back').addEventListener('click', function() {
            history.back();
        });
        document.getElementById('lock-takeover').addEventListener('click', function() {
            request('takeover', function(data) {
                if (data.locked) {
                    overlay.remove();
                    startHeartbeat();
                }
            });
        });
    }

    function showLockLostWarning() {
        var banner = document.createElement('div');
        banner.className = 'alert alert-warning';
        banner.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:9999;text-align:center;border-radius:0';
        banner.textContent = 'Your editing lock has been taken by another user. Save your work and reload.';
        document.body.prepend(banner);
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // Release lock on page unload.
    window.addEventListener('beforeunload', function() {
        if (heartbeatInterval) {
            navigator.sendBeacon(apiUrl, JSON.stringify({
                action: 'release',
                slug: slug,
                csrf_token: csrf
            }));
        }
    });
})();
