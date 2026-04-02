<?php

/**
 * Klytos Admin — File Integrity Verification
 * Displays the integrity status of core and plugin files.
 *
 * @package Klytos
 * @since   2.1.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 Jose Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\Helpers;

$pageTitle = 'Integrity Verification';
$auth      = $app->getAuth();
$csrf      = $auth->getCsrfToken();
$checker   = $app->getIntegrityChecker();
$report    = $checker->getLastReport();

$apiUrl = klytos_admin_url( 'api/integrity.php' );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<!-- Global Status Banner -->
<div class="card mb-2" id="integrityBanner">
    <div class="card-header flex flex-between flex-center">
        <h3><i class="fa-solid fa-shield-check"></i> Integrity Verification</h3>
        <div class="flex flex-gap-sm">
            <button class="btn btn-primary btn-sm" id="btnVerify">
                <i class="fa-solid fa-rotate"></i> Verify Now
            </button>
            <button class="btn btn-outline btn-sm" id="btnForceVerify">
                <i class="fa-solid fa-arrows-rotate"></i> Force Refresh
            </button>
        </div>
    </div>
    <div class="p-2" id="statusContainer">
        <?php if ( $report === null ): ?>
            <div class="flex flex-center flex-gap-sm">
                <i class="fa-solid fa-circle-question text-muted" style="font-size:1.5rem"></i>
                <div>
                    <strong>No verification has been run yet.</strong>
                    <p class="text-muted text-sm mb-0">Click "Verify Now" to run the first integrity check.</p>
                </div>
            </div>
        <?php else: ?>
            <?php
            $statusClass = match ( $report['status'] ?? 'unknown' ) {
                'ok'      => 'text-success',
                'warning' => 'text-warning',
                'error'   => 'text-danger',
                default   => 'text-muted',
            };
            $statusIcon = match ( $report['status'] ?? 'unknown' ) {
                'ok'      => 'fa-circle-check',
                'warning' => 'fa-triangle-exclamation',
                'error'   => 'fa-circle-xmark',
                default   => 'fa-circle-question',
            };
            $statusLabel = match ( $report['status'] ?? 'unknown' ) {
                'ok'      => 'All files verified — no issues detected',
                'warning' => 'Some warnings detected',
                'error'   => 'Integrity issues detected',
                default   => 'Unknown status',
            };
            ?>
            <div class="flex flex-center flex-gap-sm">
                <i class="fa-solid <?php echo $statusIcon; ?> <?php echo $statusClass; ?>" style="font-size:1.5rem"></i>
                <div>
                    <strong class="<?php echo $statusClass; ?>"><?php echo klytos_esc_html( $statusLabel ); ?></strong>
                    <p class="text-muted text-sm mb-0">
                        Last check: <?php echo klytos_esc_html( $report['checked_at'] ?? 'never' ); ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Core Section -->
<div class="card mb-2" id="coreSection">
    <div class="card-header"><h3><i class="fa-solid fa-cube"></i> Core</h3></div>
    <div class="p-2" id="coreDetails">
        <?php if ( $report !== null && isset( $report['core'] ) ): ?>
            <?php $core = $report['core']; ?>
            <?php if ( $core['status'] === 'ok' ): ?>
                <div class="alert alert-success mb-0">
                    <i class="fa-solid fa-check"></i>
                    Core v<?php echo klytos_esc_html( $core['version'] ?? KLYTOS_VERSION ); ?> —
                    <?php echo (int) ( $core['checked'] ?? 0 ); ?> files verified.
                </div>
            <?php else: ?>
                <div class="alert alert-<?php echo $core['status'] === 'error' ? 'error' : 'warning'; ?>">
                    <?php if ( !empty( $core['message'] ) ): ?>
                        <?php echo klytos_esc_html( $core['message'] ); ?>
                    <?php endif; ?>
                    <?php if ( !empty( $core['modified'] ) ): ?>
                        <details class="mt-1"><summary><strong><?php echo count( $core['modified'] ); ?> modified</strong></summary>
                            <ul class="text-sm mt-1"><?php foreach ( $core['modified'] as $f ): ?>
                                <li><code><?php echo klytos_esc_html( $f ); ?></code></li>
                            <?php endforeach; ?></ul>
                        </details>
                    <?php endif; ?>
                    <?php if ( !empty( $core['added'] ) ): ?>
                        <details class="mt-1"><summary><strong><?php echo count( $core['added'] ); ?> added</strong></summary>
                            <ul class="text-sm mt-1"><?php foreach ( $core['added'] as $f ): ?>
                                <li><code><?php echo klytos_esc_html( $f ); ?></code></li>
                            <?php endforeach; ?></ul>
                        </details>
                    <?php endif; ?>
                    <?php if ( !empty( $core['missing'] ) ): ?>
                        <details class="mt-1"><summary><strong><?php echo count( $core['missing'] ); ?> missing</strong></summary>
                            <ul class="text-sm mt-1"><?php foreach ( $core['missing'] as $f ): ?>
                                <li><code><?php echo klytos_esc_html( $f ); ?></code></li>
                            <?php endforeach; ?></ul>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-muted mb-0">Run a verification to see core integrity status.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Plugins Section -->
<div class="card mb-2" id="pluginsSection">
    <div class="card-header"><h3><i class="fa-solid fa-puzzle-piece"></i> Plugins</h3></div>
    <div class="p-2" id="pluginsDetails">
        <?php if ( $report !== null && !empty( $report['plugins'] ) ): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Plugin</th>
                            <th>Version</th>
                            <th>Trust Level</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $report['plugins'] as $pluginId => $result ): ?>
                            <?php
                            $loader   = $app->getPluginLoader();
                            $pManifest = $loader->getManifest( $pluginId );
                            $source   = $pManifest['source'] ?? 'unknown';

                            $trustIcon = match ( true ) {
                                $source === 'marketplace'                      => '<i class="fa-solid fa-shield-check text-success" title="Verified (Klytos)"></i> Klytos',
                                isset( $pManifest['integrity_url'] )           => '<i class="fa-solid fa-shield-halved text-warning" title="Verified (Developer)"></i> Developer',
                                default                                        => '<i class="fa-solid fa-shield text-muted" title="Unverified"></i> None',
                            };

                            $statusBadge = match ( $result['status'] ?? 'unknown' ) {
                                'ok'         => '<span class="badge badge-success">OK</span>',
                                'warning'    => '<span class="badge badge-warning">Warning</span>',
                                'error'      => '<span class="badge badge-danger">Error</span>',
                                'unverified' => '<span class="badge badge-muted">Unverified</span>',
                                default      => '<span class="badge">Unknown</span>',
                            };
                            ?>
                            <tr>
                                <td><strong><?php echo klytos_esc_html( $pManifest['name'] ?? $pluginId ); ?></strong></td>
                                <td><?php echo klytos_esc_html( $pManifest['version'] ?? '—' ); ?></td>
                                <td><?php echo $trustIcon; ?></td>
                                <td><?php echo $statusBadge; ?></td>
                                <td>
                                    <?php if ( !empty( $result['message'] ) ): ?>
                                        <span class="text-sm"><?php echo klytos_esc_html( $result['message'] ); ?></span>
                                    <?php elseif ( $result['status'] === 'ok' ): ?>
                                        <span class="text-sm text-muted"><?php echo (int) ( $result['checked'] ?? 0 ); ?> files verified</span>
                                    <?php else: ?>
                                        <?php
                                        $issues = [];
                                        if ( !empty( $result['modified'] ) ) $issues[] = count( $result['modified'] ) . ' modified';
                                        if ( !empty( $result['added'] ) )    $issues[] = count( $result['added'] ) . ' added';
                                        if ( !empty( $result['missing'] ) )  $issues[] = count( $result['missing'] ) . ' missing';
                                        ?>
                                        <span class="text-sm"><?php echo klytos_esc_html( implode( ', ', $issues ) ); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">Run a verification to see plugin integrity status.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Summary -->
<?php if ( $report !== null && !empty( $report['summary'] ) ): ?>
<div class="card mb-2">
    <div class="card-header"><h3><i class="fa-solid fa-chart-pie"></i> Summary</h3></div>
    <div class="p-2">
        <div class="grid grid-3 gap-2">
            <div class="text-center">
                <div class="text-2xl font-bold"><?php echo (int) ( $report['summary']['total_plugins'] ?? 0 ); ?></div>
                <div class="text-sm text-muted">Total Plugins</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-success"><?php echo (int) ( $report['summary']['plugins_ok'] ?? 0 ); ?></div>
                <div class="text-sm text-muted">Plugins OK</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-warning"><?php echo (int) ( $report['summary']['unverified'] ?? 0 ); ?></div>
                <div class="text-sm text-muted">Unverified</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Loading overlay (shown during AJAX) -->
<div id="integrityOverlay" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.3);z-index:9999;display:none">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--klytos-surface);padding:2rem;border-radius:var(--klytos-radius-lg);text-align:center">
        <i class="fa-solid fa-spinner fa-spin" style="font-size:2rem;margin-bottom:1rem;display:block"></i>
        <p id="overlayMessage">Running integrity verification...</p>
    </div>
</div>

<script nonce="<?php echo $cspNonce; ?>">
(function() {
    var apiUrl     = <?php echo json_encode( $apiUrl ); ?>;
    var csrfToken  = <?php echo json_encode( $csrf ); ?>;
    var overlay    = document.getElementById('integrityOverlay');
    var overlayMsg = document.getElementById('overlayMessage');

    function showOverlay(msg) {
        overlayMsg.textContent = msg || 'Running integrity verification...';
        overlay.style.display = 'block';
    }

    function hideOverlay() {
        overlay.style.display = 'none';
    }

    function runVerification(force) {
        showOverlay(force ? 'Downloading fresh manifests and verifying...' : 'Running integrity verification...');

        fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ action: force ? 'verify_force' : 'verify' })
        })
        .then(function(r) {
            if (!r.ok) {
                return r.text().then(function(t) { throw new Error('HTTP ' + r.status + ': ' + t.substring(0, 300)); });
            }
            return r.json();
        })
        .then(function(data) {
            hideOverlay();
            if (data.error) {
                alert('Error: ' + (data.message || data.error));
            } else {
                window.location.reload();
            }
        })
        .catch(function(err) {
            hideOverlay();
            alert('Verification failed: ' + err.message);
        });
    }

    var btnVerify = document.getElementById('btnVerify');
    if (btnVerify) {
        btnVerify.addEventListener('click', function() { runVerification(false); });
    }

    var btnForce = document.getElementById('btnForceVerify');
    if (btnForce) {
        btnForce.addEventListener('click', function() { runVerification(true); });
    }
})();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
