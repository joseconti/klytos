/**
 * Klytos — Admin Bar
 * Floating toolbar on the public site for logged-in admins.
 * Supports dropdown menus, left/right alignment, and CPT-aware "+ Add".
 * Only runs when the klytos_admin_bar cookie is present.
 *
 * @since 0.26.0
 * @since 0.30.0 — Dropdowns, user menu, CPT-aware "+ Add".
 */
(function() {
    'use strict';

    // ── Read admin cookie ──────────────────────────────────────────
    var cookie = document.cookie.split(';').reduce(function(acc, c) {
        var parts = c.trim().split('=');
        if (parts[0] === 'klytos_admin_bar') {
            try { acc = JSON.parse(decodeURIComponent(parts.slice(1).join('='))); } catch(e) {}
        }
        return acc;
    }, null);

    if (!cookie || !cookie.admin_url) return;

    var adminUrl = cookie.admin_url;
    var userName = cookie.user_name || 'Admin';
    var pageSlug = document.body.getAttribute('data-page-slug') || '';

    // ── Load Font Awesome for admin bar icons (only for admins) ────
    if (!document.querySelector('link[href*="fontawesome"], link[href*="font-awesome"]')) {
        var faLink = document.createElement('link');
        faLink.rel = 'stylesheet';
        faLink.href = adminUrl.replace(/admin\/$/, '') + 'assets/vendor/fontawesome/css/all.min.css';
        document.head.appendChild(faLink);
    }

    // ── Helpers ────────────────────────────────────────────────────
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function resolveUrl(url) {
        return (url || '')
            .replace(/\{\{admin_url\}\}/g, adminUrl)
            .replace(/\{\{page_slug\}\}/g, encodeURIComponent(pageSlug))
            .replace(/\{\{user_name\}\}/g, esc(userName));
    }

    function resolveLabel(label) {
        return (label || '').replace(/\{\{user_name\}\}/g, esc(userName));
    }

    // ── Build the bar ──────────────────────────────────────────────
    var items = window.__klytos_admin_bar_items || [];

    var leftItems = items.filter(function(i) { return (i.align || 'left') === 'left'; });
    var rightItems = items.filter(function(i) { return i.align === 'right'; });

    var bar = document.createElement('div');
    bar.id = 'klytos-admin-bar';
    bar.setAttribute('role', 'navigation');
    bar.setAttribute('aria-label', 'Admin toolbar');

    var inner = document.createElement('div');
    inner.className = 'kab-inner';

    // Brand
    var brand = document.createElement('a');
    brand.href = adminUrl;
    brand.className = 'kab-item kab-brand';
    brand.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg> Klytos';
    inner.appendChild(brand);

    // Left items
    leftItems.forEach(function(item) {
        inner.appendChild(buildItem(item));
    });

    // Spacer
    var spacer = document.createElement('div');
    spacer.className = 'kab-spacer';
    inner.appendChild(spacer);

    // Right items
    rightItems.forEach(function(item) {
        inner.appendChild(buildItem(item));
    });

    bar.appendChild(inner);

    // ── Inject styles and bar ──────────────────────────────────────
    var style = document.createElement('style');
    style.id = 'klytos-admin-bar-css';
    style.textContent =
        '#klytos-admin-bar{position:fixed;top:0;left:0;right:0;z-index:99999;' +
        'background:#1e1e1e;border-bottom:1px solid #333;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:13px;line-height:1}' +
        '#klytos-admin-bar *{box-sizing:border-box}' +
        '#klytos-admin-bar .kab-inner{display:flex;align-items:center;max-width:100%;margin:0 auto;padding:0 12px;height:32px}' +
        '#klytos-admin-bar .kab-spacer{flex:1}' +
        '#klytos-admin-bar .kab-item{color:#c3c4c7;text-decoration:none;padding:0 8px;line-height:32px;white-space:nowrap;font-size:13px;display:inline-flex;align-items:center;gap:4px;border:0;background:none;cursor:pointer}' +
        '#klytos-admin-bar .kab-item:hover{color:#fff;background:rgba(255,255,255,.08)}' +
        '#klytos-admin-bar .kab-brand{font-weight:600;color:#72aee6;padding:0 12px 0 4px}' +
        '#klytos-admin-bar .kab-brand:hover{color:#fff}' +

        /* Dropdown wrapper */
        '#klytos-admin-bar .kab-dropdown{position:relative}' +
        '#klytos-admin-bar .kab-dropdown>.kab-item::after{content:"▾";font-size:10px;margin-left:2px;opacity:.6}' +

        /* Dropdown menu */
        '#klytos-admin-bar .kab-menu{display:none;position:absolute;top:32px;left:0;background:#2c3338;border:1px solid #444;border-radius:0 0 4px 4px;min-width:160px;padding:4px 0;box-shadow:0 4px 12px rgba(0,0,0,.3);z-index:100000}' +
        '#klytos-admin-bar .kab-dropdown.kab-right .kab-menu{left:auto;right:0}' +
        '#klytos-admin-bar .kab-menu a{display:block;padding:6px 14px;color:#c3c4c7;text-decoration:none;font-size:13px;line-height:1.5}' +
        '#klytos-admin-bar .kab-menu a:hover{color:#fff;background:rgba(255,255,255,.08)}' +
        '#klytos-admin-bar .kab-menu .kab-sep{border-top:1px solid #444;margin:4px 0}' +

        /* Body padding */
        'body.klytos-admin-bar-active{padding-top:32px!important}' +

        /* Responsive */
        '@media(max-width:782px){' +
        '#klytos-admin-bar .kab-inner{font-size:12px;padding:0 6px}' +
        '#klytos-admin-bar .kab-item{padding:0 6px;font-size:12px}' +
        '#klytos-admin-bar .kab-hide-mobile{display:none}' +
        '}';

    document.head.appendChild(style);
    document.body.prepend(bar);
    document.body.classList.add('klytos-admin-bar-active');

    // ── Close all dropdowns on click outside ───────────────────────
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#klytos-admin-bar .kab-dropdown')) {
            closeAllDropdowns();
        }
    });

    // ── Build a single item or dropdown ────────────────────────────
    function buildItem(item) {
        if (item.requires_slug && !pageSlug) {
            var placeholder = document.createElement('span');
            placeholder.style.display = 'none';
            return placeholder;
        }

        var hasChildren = item.children && item.children.length > 0;
        var iconHtml = item.icon ? '<i class="' + esc(item.icon) + '"></i> ' : '';

        if (!hasChildren) {
            var link = document.createElement('a');
            link.href = resolveUrl(item.url);
            link.className = 'kab-item' + (item.hide_mobile ? ' kab-hide-mobile' : '');
            link.innerHTML = iconHtml + resolveLabel(item.label);
            return link;
        }

        // Dropdown
        var wrapper = document.createElement('div');
        wrapper.className = 'kab-dropdown' + (item.align === 'right' ? ' kab-right' : '');

        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'kab-item';
        trigger.innerHTML = iconHtml + resolveLabel(item.label);
        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            var open = wrapper.classList.contains('kab-open');
            closeAllDropdowns();
            if (!open) {
                wrapper.classList.add('kab-open');
                menu.style.display = 'block';
            }
        });
        wrapper.appendChild(trigger);

        var menu = document.createElement('div');
        menu.className = 'kab-menu';

        item.children.forEach(function(child, idx) {
            if (child.requires_slug && !pageSlug) return;

            // Separator before logout
            if (child.id === 'logout' && idx > 0) {
                var sep = document.createElement('div');
                sep.className = 'kab-sep';
                menu.appendChild(sep);
            }

            var childLink = document.createElement('a');
            childLink.href = resolveUrl(child.url);
            var childIcon = child.icon ? '<i class="' + esc(child.icon) + '"></i> ' : '';
            childLink.innerHTML = childIcon + resolveLabel(child.label);
            menu.appendChild(childLink);
        });

        wrapper.appendChild(menu);
        return wrapper;
    }

    function closeAllDropdowns() {
        var openDropdowns = document.querySelectorAll('#klytos-admin-bar .kab-dropdown.kab-open');
        for (var i = 0; i < openDropdowns.length; i++) {
            openDropdowns[i].classList.remove('kab-open');
            var m = openDropdowns[i].querySelector('.kab-menu');
            if (m) m.style.display = 'none';
        }
    }
})();
