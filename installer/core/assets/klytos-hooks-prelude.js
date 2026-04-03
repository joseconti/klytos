/**
 * klytos-hooks.js (prelude)
 * Generated automatically by Klytos BuildEngine.
 *
 * This file provides the hook point registry for frontend plugin injection.
 * Plugins register callbacks for named hook points (data-klytos-hook attributes).
 * The executor (appended after plugin code) runs all callbacks on DOMContentLoaded.
 */
(function() {
    'use strict';

    // --- Registry -----------------------------------------------
    var hooks = {};

    function registerHook(name, callback, priority) {
        if (!hooks[name]) hooks[name] = [];
        hooks[name].push({ callback: callback, priority: priority || 10 });
        hooks[name].sort(function(a, b) { return a.priority - b.priority; });
    }

    // --- Page data (from embedded JSON) -------------------------
    var pageDataEl = document.getElementById('klytos-page-data');
    var pageData = pageDataEl ? JSON.parse(pageDataEl.textContent) : {};

    // --- Utilities for plugins ----------------------------------
    function esc(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Expose global API so plugin scripts loaded separately can register hooks.
    window.KlytosHooks = {
        register: registerHook,
        getData: function() { return pageData; },
        esc: esc
    };

    // =========================================================
    // PLUGIN HOOK REGISTRATIONS (inserted automatically below)
    // =========================================================
