
    // =========================================================
    // EXECUTOR (runs all registered hooks on DOMContentLoaded)
    // =========================================================

    document.addEventListener('DOMContentLoaded', function() {
        var hookPoints = document.querySelectorAll('[data-klytos-hook]');
        hookPoints.forEach(function(el) {
            var name = el.getAttribute('data-klytos-hook');
            if (hooks[name]) {
                hooks[name].forEach(function(h) {
                    try {
                        h.callback(el, pageData);
                    } catch(e) {
                        console.error('Klytos hook error [' + name + ']:', e);
                    }
                });
            }
        });
    });
})();
