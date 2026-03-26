<?php
/**
 * Klytos Admin — Header Template
 * Shared header for all admin pages.
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

use Klytos\Core\Auth;
use Klytos\Core\Helpers;

$cspNonce = Auth::generateCspNonce();
Auth::sendSecurityHeaders($cspNonce);
$basePath  = Helpers::getBasePath();
$adminPath = $basePath . 'admin/';
$pageTitle = $pageTitle ?? __( 'dashboard.title' );
$version   = $app->getVersion();
?>
<!DOCTYPE html>
<html lang="<?php echo $app->getI18n()->getLocale(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars( $pageTitle ); ?> — Klytos Admin</title>
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
        }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Inter', sans-serif; background: var(--admin-bg); color: var(--admin-text); line-height: 1.5; }
        a { color: var(--admin-primary); text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* Layout */
        .admin-layout { display: flex; min-height: 100vh; }
        .admin-sidebar { width: 260px; background: var(--admin-sidebar); color: var(--admin-sidebar-text); padding: 0; position: fixed; top: 0; left: 0; bottom: 0; overflow-y: auto; z-index: 50; transition: transform 0.3s; }
        .admin-content { flex: 1; margin-left: 260px; padding: 0; }
        .admin-topbar { background: var(--admin-surface); border-bottom: 1px solid var(--admin-border); padding: 0.75rem 1.5rem; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 40; }
        .admin-main { padding: 1.5rem; }

        /* Sidebar */
        .sidebar-brand { padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-brand h2 { color: #fff; font-size: 1.2rem; font-weight: 700; }
        .sidebar-brand small { color: var(--admin-sidebar-text); font-size: 0.75rem; }
        .sidebar-nav { padding: 0.5rem 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 1.5rem; color: var(--admin-sidebar-text); font-size: 0.9rem; transition: all 0.15s; text-decoration: none; }
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
            .admin-sidebar { transform: translateX(-100%); }
            .admin-sidebar.open { transform: translateX(0); }
            .admin-content { margin-left: 0; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

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
