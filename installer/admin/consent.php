<?php

/**
 * Klytos Admin — Consent Manager
 * Configure cookie consent banner, manage plugin declarations, and export audit reports.
 *
 * @package Klytos
 * @since   0.17.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\BuildEngine;
use Klytos\Core\ConsentManager;
use Klytos\Core\Helpers;

$pageTitle      = 'Consent Manager';
$auth           = $app->getAuth();
$consentManager = $app->getConsentManager();
$success        = '';
$error          = '';
$csrf           = $auth->getCsrfToken();

// ─── Handle POST actions ─────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $action = $_POST['action'] ?? '';

    if ( $action === 'save_config' ) {
        try {
            $categories = [];
            if ( !empty( $_POST['custom_categories'] ) && is_array( $_POST['custom_categories'] ) ) {
                foreach ( $_POST['custom_categories'] as $cat ) {
                    $catId = trim( $cat['id'] ?? '' );
                    if ( empty( $catId ) ) {
                        continue;
                    }
                    $categories[$catId] = [
                        'name'        => trim( $cat['name'] ?? $catId ),
                        'description' => trim( $cat['description'] ?? '' ),
                        'required'    => !empty( $cat['required'] ),
                    ];
                }
            }

            $consentManager->saveConfig( [
                'enabled'     => !empty( $_POST['enabled'] ),
                'banner_text' => $_POST['banner_text'] ?? '',
                'privacy_url' => $_POST['privacy_url'] ?? '',
                'cookie_days' => (int) ( $_POST['cookie_days'] ?? 365 ),
                'categories'  => $categories,
            ] );

            // Rebuild static site so changes take effect.
            $buildEngine = new BuildEngine( $app );
            $buildEngine->buildAll();

            $success = 'Configuration saved and site rebuilt.';
        } catch ( \Throwable $e ) {
            $error = $e->getMessage();
        }
    } elseif ( $action === 'delete_declaration' ) {
        try {
            $consentManager->deletePluginDeclaration( $_POST['plugin_id'] ?? '' );
            $success = 'Declaration removed.';
        } catch ( \Throwable $e ) {
            $error = $e->getMessage();
        }
    } elseif ( $action === 'export_json' ) {
        $audit = $consentManager->getAuditReport();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="cookie-audit-' . date( 'Y-m-d' ) . '.json"' );
        echo json_encode( $audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        exit;
    } elseif ( $action === 'export_csv' ) {
        $declarations = $consentManager->getPluginDeclarations();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="cookie-audit-' . date( 'Y-m-d' ) . '.csv"' );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, ['Plugin ID', 'Plugin Name', 'Category', 'Cookie', 'Type', 'Duration', 'Description'] );
        foreach ( $declarations as $decl ) {
            if ( empty( $decl['cookies'] ) ) {
                fputcsv( $out, [$decl['plugin_id'], $decl['name'], $decl['category'], '-', '-', '-', '-'] );
            } else {
                foreach ( $decl['cookies'] as $cookie ) {
                    fputcsv( $out, [
                        $decl['plugin_id'],
                        $decl['name'],
                        $decl['category'],
                        $cookie['name'] ?? '',
                        $cookie['type'] ?? 'cookie',
                        $cookie['duration'] ?? '',
                        $cookie['description'] ?? '',
                    ] );
                }
            }
        }
        fclose( $out );
        exit;
    }

    $csrf = $auth->getCsrfToken();
}

// ─── Load data ───────────────────────────────────────────────
$config = $consentManager->getConfig();
$audit  = $consentManager->getAuditReport();

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.consent.before' ); ?>

<?php if ( !empty( $success ) ): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>
<?php if ( !empty( $error ) ): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Status</div>
        <div class="stat-value"><?php echo $config['enabled'] ? 'Enabled' : 'Disabled'; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Registered Plugins</div>
        <div class="stat-value"><?php echo $audit['total_plugins']; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Declared Cookies</div>
        <div class="stat-value"><?php echo $audit['total_cookies']; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">External Scripts</div>
        <div class="stat-value"><?php echo $audit['total_scripts']; ?></div>
    </div>
</div>

<!-- Configuration -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Configuration</h2>
    </div>
    <div class="card-body">
        <form method="post">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="action" value="save_config">

            <div class="form-group">
                <label class="toggle-label">
                    <input type="checkbox" name="enabled" value="1" <?php echo $config['enabled'] ? 'checked' : ''; ?>>
                    <span>Enable Consent Manager</span>
                </label>
                <p class="form-help">When enabled, a GDPR-compliant cookie consent banner is shown to visitors.</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="banner_text">Banner Text</label>
                <textarea name="banner_text" id="banner_text" class="form-control" rows="3"><?php echo klytos_esc_html( $config['banner_text'] ); ?></textarea>
                <p class="form-help">Message displayed in the consent banner. Supports plain text.</p>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:2">
                    <label class="form-label" for="privacy_url">Privacy Policy URL</label>
                    <input type="text" name="privacy_url" id="privacy_url" class="form-control"
                           value="<?php echo klytos_esc_attr( $config['privacy_url'] ); ?>"
                           placeholder="/politica-de-privacidad">
                    <p class="form-help">Link shown in the banner. Leave empty to hide.</p>
                </div>
                <div class="form-group" style="flex:1">
                    <label class="form-label" for="cookie_days">Consent Cookie Duration (days)</label>
                    <input type="number" name="cookie_days" id="cookie_days" class="form-control"
                           value="<?php echo (int) $config['cookie_days']; ?>" min="1" max="730">
                    <p class="form-help">How long to remember the visitor's choice.</p>
                </div>
            </div>

            <div class="mt-2">
                <button type="submit" class="btn btn-primary">Save & Rebuild</button>
            </div>
        </form>
    </div>
</div>

<!-- Audit: Declarations by category -->
<div class="card mt-3">
    <div class="card-header flex-between">
        <h2 class="card-title">Cookie & Script Audit</h2>
        <div class="flex flex-gap-sm">
            <form method="post" class="inline-form">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="action" value="export_json">
                <button type="submit" class="btn btn-outline btn-sm">Export JSON</button>
            </form>
            <form method="post" class="inline-form">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="action" value="export_csv">
                <button type="submit" class="btn btn-outline btn-sm">Export CSV</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <?php if ( $audit['total_plugins'] === 0 ): ?>
            <div class="empty-state">
                <h3>No plugin declarations</h3>
                <p>Plugins register their cookie and script declarations via the Consent Manager API or MCP tools.</p>
            </div>
        <?php else: ?>
            <?php foreach ( $audit['categories'] as $catId => $catData ): ?>
                <?php $cat = $catData['category']; ?>
                <?php $plugins = $catData['plugins']; ?>
                <?php if ( empty( $plugins ) ) { continue; } ?>

                <h3 class="mt-3 mb-1 flex flex-center flex-gap-sm" style="font-size:1rem">
                    <span class="badge-status badge-<?php echo $catId === 'necessary' ? 'active' : 'medium'; ?>">
                        <?php echo klytos_esc_html( $cat['name'] ?? $catId ); ?>
                    </span>
                    <span class="text-muted" style="font-weight:400">
                        <?php echo count( $plugins ); ?> plugin<?php echo count( $plugins ) !== 1 ? 's' : ''; ?>
                        <?php if ( !empty( $cat['required'] ) ): ?> — Always active<?php endif; ?>
                    </span>
                </h3>

                <?php foreach ( $plugins as $plugin ): ?>
                    <div class="card mb-1" style="border:1px solid var(--klytos-border)">
                        <div class="card-body p-2">
                            <div class="flex-between" style="align-items:start">
                                <div>
                                    <strong><?php echo klytos_esc_html( $plugin['name'] ); ?></strong>
                                    <code class="text-xs" style="margin-left:0.5rem"><?php echo klytos_esc_html( $plugin['plugin_id'] ); ?></code>
                                    <?php if ( !empty( $plugin['vendor'] ) ): ?>
                                        <span class="text-muted text-sm"> — <?php echo klytos_esc_html( $plugin['vendor'] ); ?></span>
                                    <?php endif; ?>
                                </div>
                                <form method="post" class="inline-form">
                                    <?php echo klytos_csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_declaration">
                                    <input type="hidden" name="plugin_id" value="<?php echo klytos_esc_attr( $plugin['plugin_id'] ); ?>">
                                    <button type="submit" class="btn btn-danger btn-sm btn-confirm-delete">Remove</button>
                                </form>
                            </div>

                            <?php if ( !empty( $plugin['description'] ) ): ?>
                                <p class="text-muted text-sm" style="margin-top:0.25rem">
                                    <?php echo klytos_esc_html( $plugin['description'] ); ?>
                                </p>
                            <?php endif; ?>

                            <?php if ( !empty( $plugin['privacy_url'] ) ): ?>
                                <p class="text-xs" style="margin-top:0.25rem">
                                    <a href="<?php echo klytos_esc_url( $plugin['privacy_url'] ); ?>" target="_blank" rel="noopener">Privacy policy</a>
                                </p>
                            <?php endif; ?>

                            <?php if ( !empty( $plugin['cookies'] ) ): ?>
                                <div class="table-wrap mt-1">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Cookie</th>
                                                <th>Type</th>
                                                <th>Duration</th>
                                                <th>Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ( $plugin['cookies'] as $ck ): ?>
                                            <tr>
                                                <td><code class="text-xs"><?php echo klytos_esc_html( $ck['name'] ); ?></code></td>
                                                <td><?php echo klytos_esc_html( $ck['type'] ?? 'cookie' ); ?></td>
                                                <td><?php echo klytos_esc_html( $ck['duration'] ?? 'Session' ); ?></td>
                                                <td class="text-sm"><?php echo klytos_esc_html( $ck['description'] ?? '' ); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <?php if ( !empty( $plugin['scripts'] ) ): ?>
                                <div class="mt-1">
                                    <strong class="text-xs">External scripts:</strong>
                                    <?php foreach ( $plugin['scripts'] as $script ): ?>
                                        <div class="text-xs text-mono text-muted break-all">
                                            <?php echo klytos_esc_html( $script ); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php klytos_do_action( 'admin.consent.after' ); ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
