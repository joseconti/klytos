/**
 * Klytos — Admin Bar
 * Floating toolbar on the public site for logged-in admins.
 * Only runs when the klytos_admin_bar cookie is present.
 *
 * @since 0.26.0
 */
(function() {
    'use strict';

    // Read admin cookie.
    var cookie = document.cookie.split(';').reduce(function(acc, c) {
        var parts = c.trim().split('=');
        if (parts[0] === 'klytos_admin_bar') {
            try { acc = JSON.parse(decodeURIComponent(parts.slice(1).join('='))); } catch(e) {}
        }
        return acc;
    }, null);

    if (!cookie || !cookie.admin_url) return;

    var adminUrl = cookie.admin_url;
    var pageSlug = document.body.getAttribute('data-page-slug') || '';

    // Build items from the filterable items array (set by PHP via admin_bar.items filter).
    var items = window.__klytos_admin_bar_items || [
        {id: 'dashboard', label: 'Dashboard', url: '{{admin_url}}index.php', position: 10},
        {id: 'edit_page', label: 'Edit Page', url: '{{admin_url}}page-editor.php?slug={{page_slug}}', position: 20, requires_slug: true}
    ];

    // Create the bar.
    var bar = document.createElement('div');
    bar.id = 'klytos-admin-bar';
    bar.setAttribute('role', 'navigation');
    bar.setAttribute('aria-label', 'Admin toolbar');

    var itemsHtml = '';
    items.forEach(function(item) {
        if (item.requires_slug && !pageSlug) return;
        var url = (item.url || '')
            .replace('{{admin_url}}', adminUrl)
            .replace('{{page_slug}}', encodeURIComponent(pageSlug));
        itemsHtml += '<a href="' + esc(url) + '" class="kab-item">' + esc(item.label || '') + '</a>';
    });

    bar.innerHTML =
        '<div class="kab-inner">' +
            '<a href="' + esc(adminUrl) + '" class="kab-item kab-brand">Klytos</a>' +
            itemsHtml +
            '<button type="button" class="kab-item kab-close" title="Close">&times;</button>' +
        '</div>';

    // Inline styles to avoid conflicts with the public site.
    var style = document.createElement('style');
    style.textContent =
        '#klytos-admin-bar{position:fixed;top:0;left:0;right:0;z-index:99999;' +
        'background:#1a1a1a;border-bottom:1px solid #333;font-family:-apple-system,BlinkMacSystemFont,sans-serif;font-size:13px}' +
        '#klytos-admin-bar .kab-inner{display:flex;align-items:center;max-width:1200px;margin:0 auto;padding:0 12px;height:32px}' +
        '#klytos-admin-bar .kab-item{color:#ccc;text-decoration:none;padding:0 10px;line-height:32px;white-space:nowrap}' +
        '#klytos-admin-bar .kab-item:hover{color:#fff;background:rgba(255,255,255,.08)}' +
        '#klytos-admin-bar .kab-brand{font-weight:600;color:#3b82f6}' +
        '#klytos-admin-bar .kab-close{margin-left:auto;background:none;border:none;cursor:pointer;font-size:16px;color:#888}' +
        '#klytos-admin-bar .kab-close:hover{color:#fff}' +
        'body.klytos-admin-bar-active{padding-top:32px}' +
        '@media(max-width:600px){#klytos-admin-bar .kab-inner{font-size:12px;padding:0 6px}' +
        '#klytos-admin-bar .kab-item{padding:0 6px}}';

    document.head.appendChild(style);
    document.body.prepend(bar);
    document.body.classList.add('klytos-admin-bar-active');

    // Close button — hide for session.
    bar.querySelector('.kab-close').addEventListener('click', function() {
        bar.remove();
        style.remove();
        document.body.classList.remove('klytos-admin-bar-active');
        try { sessionStorage.setItem('klytos_admin_bar_hidden', '1'); } catch(e) {}
    });

    // Check if hidden this session.
    try {
        if (sessionStorage.getItem('klytos_admin_bar_hidden') === '1') {
            bar.remove();
            style.remove();
            return;
        }
    } catch(e) {}

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
})();
