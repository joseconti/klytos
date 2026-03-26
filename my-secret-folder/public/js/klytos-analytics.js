/**
 * Klytos Analytics — Privacy-First Page View Tracker
 *
 * Features:
 * - Zero cookies. Zero fingerprinting. Zero external requests.
 * - Uses Beacon API (preferred) or fallback image pixel.
 * - Sends: page path, referrer domain, screen width category.
 * - Does NOT send: raw IP, full user agent, exact screen dimensions.
 * - ~1.5KB minified. No dependencies.
 *
 * Usage (injected automatically by the Klytos build engine):
 *   <script src="/js/klytos-analytics.js" defer></script>
 *
 * @license    Elastic License 2.0 (ELv2)
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 */
(function () {
    'use strict';

    // ─── Configuration ───────────────────────────────────────
    // The tracking endpoint URL is relative to the Klytos root.
    // It's discovered from the script's own src attribute.
    var scriptTag = document.currentScript;
    if (!scriptTag) return;

    var scriptSrc = scriptTag.src || '';
    var baseUrl   = scriptSrc.replace(/\/js\/klytos-analytics\.js.*$/, '');
    var endpoint  = baseUrl + '/t.php';

    // ─── Collect minimal, anonymized data ────────────────────
    var data = {
        p: window.location.pathname,                    // Page path only (no query/hash).
        r: document.referrer || '',                     // Referrer (server extracts domain only).
        w: window.innerWidth || screen.width || 0       // Screen width (server categorizes it).
    };

    // ─── Send via Beacon API (preferred: non-blocking) ───────
    if (navigator.sendBeacon) {
        try {
            var blob = new Blob(
                [JSON.stringify(data)],
                { type: 'application/json' }
            );
            navigator.sendBeacon(endpoint, blob);
            return; // Sent successfully.
        } catch (e) {
            // Beacon failed — fall through to image pixel.
        }
    }

    // ─── Fallback: 1x1 image pixel (blocking, but reliable) ─
    var img = new Image(1, 1);
    img.src = endpoint
        + '?p=' + encodeURIComponent(data.p)
        + '&r=' + encodeURIComponent(data.r)
        + '&w=' + data.w;
})();
