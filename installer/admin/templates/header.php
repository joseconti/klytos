<?php
/**
 * Klytos Admin — Header Template
 * Shared header for all admin pages.
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

use Klytos\Core\Auth;
use Klytos\Core\Helpers;

$cspNonce = Auth::generateCspNonce();
Auth::sendSecurityHeaders($cspNonce, $customCsp ?? null);
$basePath    = Helpers::getBasePath();
$adminPath   = $basePath . 'admin/';
$pageTitle   = $pageTitle ?? __( 'dashboard.title' );
$adminTheme  = $app->getSiteConfig()->getValue('admin_theme', 'light');
$version   = $app->getVersion();
?>
<!DOCTYPE html>
<html lang="<?php echo $app->getI18n()->getLocale(); ?>" data-theme="<?php echo klytos_esc_attr($adminTheme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo klytos_esc_html( $pageTitle ); ?> — Klytos Admin</title>
    <link rel="stylesheet" href="<?php echo klytos_esc_url( Helpers::getBasePath() . 'admin/assets/vendor/fontawesome/css/all.min.css' ); ?>">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --admin-primary: #2563eb;
            --admin-primary-hover: #1d4ed8;
            --admin-bg: #f1f5f9;
            --admin-surface: #ffffff;
            --admin-sidebar: #1e293b;
            --admin-sidebar-text: #cbd5e1;
            --admin-sidebar-active: #2563eb;
            --admin-text: #1e293b;
            --admin-text-muted: #64748b;
            --admin-border: #e2e8f0;
            --admin-success: #22c55e;
            --admin-warning: #f59e0b;
            --admin-error: #ef4444;
            --admin-radius: 8px;
            --admin-card-bg: #ffffff;
        }
        /* Dark theme overrides */
        [data-theme="dark"] {
            --admin-primary: #6366f1;
            --admin-primary-hover: #8b5cf6;
            --admin-bg: #0f172a;
            --admin-surface: #1e293b;
            --admin-sidebar: #0f172a;
            --admin-sidebar-text: #94a3b8;
            --admin-sidebar-active: #6366f1;
            --admin-text: #e2e8f0;
            --admin-text-muted: #94a3b8;
            --admin-border: #334155;
            --admin-success: #22c55e;
            --admin-warning: #f59e0b;
            --admin-error: #ef4444;
            --admin-card-bg: #1e293b;
        }
        [data-theme="dark"] .alert-success { background: rgba(34,197,94,0.12); color: #86efac; border-color: rgba(34,197,94,0.3); }
        [data-theme="dark"] .alert-error   { background: rgba(239,68,68,0.12); color: #fca5a5; border-color: rgba(239,68,68,0.3); }
        [data-theme="dark"] .alert-warning { background: rgba(245,158,11,0.12); color: #fcd34d; border-color: rgba(245,158,11,0.3); }
        [data-theme="dark"] .alert-info    { background: rgba(99,102,241,0.12); color: #a5b4fc; border-color: rgba(99,102,241,0.3); }
        [data-theme="dark"] .badge-published,
        [data-theme="dark"] .badge-active    { background: rgba(34,197,94,0.15); color: #86efac; }
        [data-theme="dark"] .badge-draft     { background: #334155; color: #94a3b8; }
        [data-theme="dark"] .badge-inactive  { background: rgba(239,68,68,0.15); color: #fca5a5; }
        [data-theme="dark"] .badge-premium   { background: rgba(245,158,11,0.15); color: #fcd34d; }
        [data-theme="dark"] .badge-urgent    { background: rgba(239,68,68,0.15); color: #fca5a5; }
        [data-theme="dark"] .badge-high      { background: rgba(249,115,22,0.15); color: #fdba74; }
        [data-theme="dark"] .badge-medium    { background: rgba(99,102,241,0.15); color: #a5b4fc; }
        [data-theme="dark"] .badge-low       { background: rgba(34,197,94,0.15); color: #86efac; }
        [data-theme="dark"] .badge-owner     { background: rgba(124,58,237,0.15); color: #c4b5fd; }
        [data-theme="dark"] .badge-admin     { background: rgba(99,102,241,0.15); color: #a5b4fc; }
        [data-theme="dark"] .badge-editor    { background: rgba(34,197,94,0.15); color: #86efac; }
        [data-theme="dark"] .badge-viewer    { background: #334155; color: #94a3b8; }
        [data-theme="dark"] .badge-open      { background: rgba(99,102,241,0.15); color: #a5b4fc; }
        [data-theme="dark"] .badge-in_progress { background: rgba(245,158,11,0.15); color: #fcd34d; }
        [data-theme="dark"] .badge-completed { background: rgba(34,197,94,0.15); color: #86efac; }
        [data-theme="dark"] .badge-dismissed { background: #334155; color: #94a3b8; }
        [data-theme="dark"] .token-display { background: #0f172a; color: #e2e8f0; }
        [data-theme="dark"] .form-control  { background: #0f172a; color: #e2e8f0; border-color: #334155; }
        [data-theme="dark"] .form-control:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
        [data-theme="dark"] select.form-control option { background: #1e293b; color: #e2e8f0; }
        [data-theme="dark"] .modal { box-shadow: 0 20px 60px rgba(0,0,0,0.6); }
        [data-theme="dark"] .modal-overlay { background: rgba(0,0,0,0.7); }
        [data-theme="dark"] .btn-outline { border-color: #334155; color: #e2e8f0; }
        [data-theme="dark"] .btn-outline:hover { background: #334155; }
        [data-theme="dark"] .sidebar-brand { border-bottom-color: rgba(255,255,255,0.05); }
        [data-theme="dark"] tr:hover td { background: rgba(255,255,255,0.03); }

        /* Security page — dark mode overrides */
        [data-theme="dark"] .security-status-active { background: rgba(34,197,94,0.15); color: #86efac; }
        [data-theme="dark"] .security-status-inactive { background: rgba(239,68,68,0.15); color: #fca5a5; }
        [data-theme="dark"] .security-method-badge { background: rgba(99,102,241,0.15); color: #a5b4fc; }
        [data-theme="dark"] .security-active-text { color: #86efac; }
        [data-theme="dark"] .security-recovery-card { border-color: rgba(245,158,11,0.4); background: rgba(245,158,11,0.08); }
        [data-theme="dark"] .security-recovery-text { color: #fcd34d; }
        [data-theme="dark"] .security-recovery-code { background: #0f172a; color: #e2e8f0; border-color: #334155; }
        [data-theme="dark"] .totp-setup-box { background: #0f172a; }
        [data-theme="dark"] .totp-manual-key { background: #0f172a; color: #e2e8f0; border-color: #334155; }
        [data-theme="dark"] .security-danger-card { border-color: rgba(239,68,68,0.4); }

        /* AI provider logos — theme-aware visibility */
        .ai-logo-dark  { display: none; }
        .ai-logo-light { display: inline; }
        [data-theme="dark"] .ai-logo-dark  { display: inline; }
        [data-theme="dark"] .ai-logo-light { display: none; }

        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Inter', sans-serif; background: var(--admin-bg); color: var(--admin-text); line-height: 1.5; }
        a { color: var(--admin-primary); text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* Layout */
        .admin-layout { display: flex; min-height: 100vh; }
        .admin-sidebar {
            width: 260px; background: var(--admin-sidebar); color: var(--admin-sidebar-text);
            padding: 0; position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto;
            z-index: 50; transition: width 0.25s ease;
        }
        .admin-content { flex: 1; margin-left: 260px; padding: 0; transition: margin-left 0.25s ease; min-width: 0; overflow-x: hidden; }
        .admin-topbar { background: var(--admin-surface); border-bottom: 1px solid var(--admin-border); padding: 0.75rem 1.5rem; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 40; }

        /* Sidebar toggle button */
        .sidebar-toggle { background: none; border: none; color: var(--admin-text); cursor: pointer; font-size: 1.25rem; padding: 0.25rem 0.5rem; border-radius: var(--admin-radius); transition: background 0.15s; display: flex; align-items: center; }
        .sidebar-toggle:hover { background: var(--admin-bg); }

        /* Collapsed sidebar */
        .admin-sidebar.collapsed { width: 60px; overflow: visible; }
        .admin-sidebar.collapsed + .admin-content,
        .admin-layout.sidebar-collapsed .admin-content { margin-left: 60px; }

        .admin-sidebar.collapsed .sidebar-brand { padding: 1.25rem 0; text-align: center; }
        .admin-sidebar.collapsed .sidebar-brand h2 { font-size: 0.9rem; }
        .admin-sidebar.collapsed .sidebar-brand small { display: none; }

        .admin-sidebar.collapsed .sidebar-section { font-size: 0; padding: 0.5rem 0 0.25rem; text-align: center; }
        .admin-sidebar.collapsed .sidebar-section::after { content: ''; display: block; width: 24px; height: 1px; background: rgba(255,255,255,0.15); margin: 0 auto; }

        .admin-sidebar.collapsed .sidebar-nav a { justify-content: center; padding: 0.65rem 0; position: relative; }
        .admin-sidebar.collapsed .sidebar-nav a span { margin: 0; }
        .admin-sidebar.collapsed .sidebar-nav a .sidebar-label,
        .admin-sidebar.collapsed .sidebar-nav a .badge { display: none; }

        .admin-sidebar.collapsed .sidebar-nav a.sidebar-child { display: none; }

        /* Tooltip on hover (collapsed) */
        .sidebar-item-wrap { position: relative; }
        .sidebar-tooltip {
            display: none; position: fixed; left: 60px;
            background: var(--admin-sidebar); border: 1px solid rgba(255,255,255,0.12);
            border-radius: 0 var(--admin-radius) var(--admin-radius) 0;
            padding: 0.5rem 0.75rem; min-width: 180px; z-index: 200;
            box-shadow: 4px 4px 16px rgba(0,0,0,0.4);
        }
        .sidebar-tooltip .tooltip-title {
            padding: 0.5rem 1.25rem; color: #fff; font-size: 0.88rem; font-weight: 500;
            white-space: nowrap; display: block; text-decoration: none;
        }
        .sidebar-tooltip .tooltip-title:hover { background: rgba(255,255,255,0.08); text-decoration: none; }
        .sidebar-tooltip .tooltip-child {
            display: block; padding: 0.35rem 1.25rem 0.35rem 2rem;
            color: rgba(255,255,255,0.6); font-size: 0.82rem; text-decoration: none;
            white-space: nowrap;
        }
        .sidebar-tooltip .tooltip-child:hover { color: #fff; background: rgba(255,255,255,0.08); text-decoration: none; }

        .admin-sidebar.collapsed .sidebar-item-wrap:hover .sidebar-tooltip { display: block; }
        .admin-main { padding: 1.5rem; min-width: 0; overflow-x: auto; }

        /* Sidebar search */
        .sidebar-search { padding: 0.75rem 1rem; }
        .sidebar-search-wrap { display: flex; align-items: center; gap: 0.5rem; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1); border-radius: var(--admin-radius); padding: 0.4rem 0.65rem; transition: border-color 0.15s, background 0.15s; }
        .sidebar-search-wrap:focus-within { background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.25); }
        .sidebar-search-icon { color: rgba(255,255,255,0.4); font-size: 0.8rem; flex-shrink: 0; }
        .sidebar-search-wrap input { background: none; border: none; outline: none; color: #fff; font-size: 0.82rem; width: 100%; }
        .sidebar-search-wrap input::placeholder { color: rgba(255,255,255,0.35); }
        .sidebar-search-kbd { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.4); font-size: 0.65rem; padding: 0.1rem 0.4rem; border-radius: 4px; font-family: inherit; flex-shrink: 0; line-height: 1.4; border: 1px solid rgba(255,255,255,0.1); }
        .admin-sidebar.collapsed .sidebar-search { display: none; }
        .sidebar-item-wrap.search-hidden { display: none; }
        .sidebar-section.search-hidden { display: none; }
        .sidebar-search-no-results { padding: 1rem 1.5rem; color: rgba(255,255,255,0.4); font-size: 0.82rem; text-align: center; display: none; }
        .sidebar-search-no-results.visible { display: block; }
        /* Force show children during search */
        .sidebar-item-wrap.search-child-match a.sidebar-child { display: flex !important; }

        /* Sidebar */
        .sidebar-brand { padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand h2 { color: #fff; font-size: 1.2rem; font-weight: 700; }
        .sidebar-brand small { color: var(--admin-sidebar-text); font-size: 0.75rem; }
        .sidebar-nav { padding: 0.5rem 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 1.5rem; color: var(--admin-sidebar-text); font-size: 0.9rem; transition: all 0.15s; text-decoration: none; }
        .sidebar-nav a i.fa-solid, .sidebar-nav a i.fa-regular, .sidebar-nav a i.fa-brands { width: 1.25rem; text-align: center; font-size: 0.95rem; flex-shrink: 0; }
        .sidebar-nav a:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .sidebar-nav a.active { background: var(--admin-sidebar-active); color: #fff; font-weight: 500; }
        .sidebar-section { padding: 0.75rem 1.5rem 0.25rem; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: rgba(255,255,255,0.4); }
        .sidebar-nav .badge { background: var(--admin-error); color: #fff; font-size: 0.7rem; padding: 0.1rem 0.5rem; border-radius: 10px; margin-left: auto; }
        .sidebar-nav a.sidebar-child { padding: 0.4rem 1.5rem 0.4rem 3.25rem; font-size: 0.82rem; color: rgba(255,255,255,0.55); }
        .sidebar-nav a.sidebar-child:hover { color: #fff; }
        .sidebar-nav a.sidebar-child.active { color: #fff; font-weight: 500; background: rgba(37,99,235,0.3); }

        /* Cards */
        .card { background: var(--admin-surface); border-radius: var(--admin-radius); border: 1px solid var(--admin-border); padding: 1.5rem; margin-bottom: 1rem; }
        .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .card-header h3 { font-size: 1.1rem; font-weight: 600; }

        /* Buttons */
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border: none; border-radius: var(--admin-radius); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all 0.15s; text-decoration: none; }
        .btn-primary { background: var(--admin-primary); color: #fff; }
        .btn-primary:hover { background: var(--admin-primary-hover); text-decoration: none; }
        .btn-danger { background: var(--admin-error); color: #fff; }
        .btn-danger:hover { background: #dc2626; }
        .btn-outline { background: transparent; border: 1px solid var(--admin-border); color: var(--admin-text); }
        .btn-outline:hover { background: var(--admin-bg); }
        .btn-sm { padding: 0.3rem 0.7rem; font-size: 0.8rem; }

        /* Forms */
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.25rem; color: var(--admin-text); }
        .form-control { width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--admin-border); border-radius: var(--admin-radius); font-size: 0.9rem; transition: border-color 0.15s; }
        .form-control:focus { outline: none; border-color: var(--admin-primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        textarea.form-control { resize: vertical; min-height: 100px; }
        select.form-control { appearance: auto; }
        .form-help { font-size: 0.8rem; color: var(--admin-text-muted); margin-top: 0.2rem; }

        /* Tables */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.65rem 0.75rem; text-align: left; border-bottom: 1px solid var(--admin-border); font-size: 0.9rem; }
        th { font-weight: 600; color: var(--admin-text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.03em; }
        tr:hover td { background: var(--admin-bg); }

        /* Alerts */
        .alert { padding: 0.75rem 1rem; border-radius: var(--admin-radius); margin-bottom: 1rem; font-size: 0.9rem; }
        .alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-warning { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .alert-info { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--admin-surface); border: 1px solid var(--admin-border); border-radius: var(--admin-radius); padding: 1.25rem; }
        .stat-card .stat-label { font-size: 0.8rem; color: var(--admin-text-muted); text-transform: uppercase; letter-spacing: 0.03em; }
        .stat-card .stat-value { font-size: 1.8rem; font-weight: 700; color: var(--admin-text); margin-top: 0.25rem; }
        .stat-card .stat-detail { font-size: 0.8rem; color: var(--admin-text-muted); margin-top: 0.25rem; }

        /* Status badges */
        .badge-status { display: inline-block; padding: 0.15rem 0.6rem; border-radius: 10px; font-size: 0.75rem; font-weight: 500; }
        .badge-published { background: #dcfce7; color: #15803d; }
        .badge-draft { background: #f1f5f9; color: #64748b; }
        .badge-active { background: #dcfce7; color: #15803d; }
        .badge-inactive { background: #fef2f2; color: #dc2626; }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-sidebar { width: 60px; overflow: visible; }
            .admin-sidebar .sidebar-brand { padding: 1.25rem 0; text-align: center; }
            .admin-sidebar .sidebar-brand h2 { font-size: 0.9rem; }
            .admin-sidebar .sidebar-brand small { display: none; }
            .admin-sidebar .sidebar-search { display: none; }
            .admin-sidebar .sidebar-section { font-size: 0; padding: 0.5rem 0 0.25rem; text-align: center; }
            .admin-sidebar .sidebar-section::after { content: ''; display: block; width: 24px; height: 1px; background: rgba(255,255,255,0.15); margin: 0 auto; }
            .admin-sidebar .sidebar-nav a { justify-content: center; padding: 0.65rem 0; }
            .admin-sidebar .sidebar-nav a .sidebar-label,
            .admin-sidebar .sidebar-nav a .badge { display: none; }
            .admin-sidebar .sidebar-nav a.sidebar-child { display: none; }
            .admin-sidebar .sidebar-item-wrap:hover .sidebar-tooltip { display: block; }
            .admin-content { margin-left: 60px !important; min-width: 0; }
            .admin-main { padding: 1rem; }
            .admin-topbar { padding: 0.5rem 1rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .card { padding: 1rem; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
        }

        /* Security page */
        .totp-setup-box { background: #f8fafc; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem; }
        .totp-manual-key { display: block; background: #fff; padding: 0.5rem; border-radius: 6px; font-size: 0.9rem; word-break: break-all; border: 1px solid #e5e7eb; }

        /* Code/Mono */
        .mono { font-family: 'JetBrains Mono', 'Fira Code', monospace; font-size: 0.85rem; }
        .token-display { background: #f1f5f9; border: 1px solid var(--admin-border); border-radius: var(--admin-radius); padding: 0.75rem; font-family: monospace; font-size: 0.85rem; word-break: break-all; }

        /* Color picker */
        input[type="color"] { width: 40px; height: 32px; border: 1px solid var(--admin-border); border-radius: 4px; padding: 2px; cursor: pointer; }
        .color-row { display: flex; align-items: center; gap: 0.5rem; }
        .color-row input[type="text"] { flex: 1; }

        /* Empty state */
        .empty-state { text-align: center; padding: 3rem 1rem; color: var(--admin-text-muted); }
        .empty-state h3 { color: var(--admin-text); margin-bottom: 0.5rem; }

        /* Extra badges */
        .badge-premium { background: #fef3c7; color: #b45309; }
        .badge-urgent { background: #fef2f2; color: #dc2626; }
        .badge-high { background: #fff7ed; color: #ea580c; }
        .badge-medium { background: #eff6ff; color: #2563eb; }
        .badge-low { background: #f0fdf4; color: #15803d; }
        .badge-owner { background: #faf5ff; color: #7c3aed; }
        .badge-admin { background: #eff6ff; color: #2563eb; }
        .badge-editor { background: #f0fdf4; color: #15803d; }
        .badge-viewer { background: #f1f5f9; color: #64748b; }
        .badge-open { background: #eff6ff; color: #2563eb; }
        .badge-in_progress { background: #fffbeb; color: #b45309; }
        .badge-completed { background: #dcfce7; color: #15803d; }
        .badge-dismissed { background: #f1f5f9; color: #64748b; }

        /* Tabs */
        .tabs { display: flex; gap: 0; border-bottom: 2px solid var(--admin-border); margin-bottom: 1.5rem; }
        .tab { padding: 0.6rem 1.2rem; font-size: 0.9rem; font-weight: 500; color: var(--admin-text-muted); border-bottom: 2px solid transparent; margin-bottom: -2px; cursor: pointer; text-decoration: none; transition: all 0.15s; }
        .tab:hover { color: var(--admin-text); text-decoration: none; }
        .tab.active { color: var(--admin-primary); border-bottom-color: var(--admin-primary); }

        /* Inline grid */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }

        /* Action bar */
        .action-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        .action-bar .filters { display: flex; gap: 0.5rem; align-items: center; }

        /* Priority dot */
        .priority-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 0.4rem; }
        .priority-dot.urgent { background: #ef4444; }
        .priority-dot.high { background: #f97316; }
        .priority-dot.medium { background: #3b82f6; }
        .priority-dot.low { background: #22c55e; }

        /* Modal overlay */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: var(--admin-surface); border-radius: 12px; padding: 2rem; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal h3 { margin-bottom: 1rem; }

        /* Chart placeholder */
        .chart-bar { display: flex; align-items: flex-end; gap: 2px; height: 120px; padding: 0; }
        .chart-bar-item { flex: 1; background: var(--admin-primary); border-radius: 3px 3px 0 0; min-height: 2px; transition: height 0.3s; opacity: 0.8; }
        .chart-bar-item:hover { opacity: 1; }

        /* Responsive tweaks */
        @media (max-width: 768px) {
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
            .action-bar { flex-direction: column; gap: 0.75rem; align-items: stretch; }
        }
    </style>
</head>
<body>
<div class="admin-layout">
