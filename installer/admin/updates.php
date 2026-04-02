<?php

/**
 * Klytos Admin — Updates (via GitHub Releases)
 *
 * @copyright 2024-2026 José Conti. All rights reserved.
 * @license   Elastic License 2.0 (ELv2)
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\Helpers;
use Klytos\Core\Updater;

$pageTitle = __( 'updates.title' );
$currentPage = 'updates';
$auth    = $app->getAuth();
$updater = $app->getUpdater();
$success = '';
$error   = '';

$currentVersion = $updater->getCurrentVersion();
$currentChannel = $updater->getChannel();
$updateInfo     = null;

// Handle actions.
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $action = $_POST['action'] ?? '';

    if ( $action === 'set_channel' ) {
        $newChannel = $_POST['channel'] ?? Updater::CHANNEL_STABLE;
        $updater->setChannel( $newChannel );
        $currentChannel = $updater->getChannel();
        // Save max backups setting and prune excess.
        $maxBackups = (int) ( $_POST['max_backups'] ?? 10 );
        $updater->setMaxBackups( $maxBackups );
        $updater->pruneBackups();
        // Force refresh after changing channel.
        $updateInfo = $updater->checkForUpdate( true );
        if ( $updateInfo === null ) {
            $success = __( 'updates.up_to_date' );
        }
    } elseif ( $action === 'check' ) {
        $updateInfo = $updater->checkForUpdate( true );
        if ( $updateInfo === null ) {
            $success = __( 'updates.up_to_date' );
        }
    } elseif ( $action === 'restore' ) {
        $backupName = $_POST['backup_name'] ?? '';
        if ( empty( $backupName ) ) {
            $error = __( 'common.error' );
        } else {
            $result = $updater->restoreFromBackup( $backupName );
            if ( $result['success'] ) {
                $success = 'Restored to v' . $result['to_version'] . ' from backup.';
                $currentVersion = $result['to_version'];
            } else {
                $error = 'Restore failed: ' . $result['error'];
            }
        }
    } elseif ( $action === 'install' ) {
        $downloadUrl = $_POST['download_url'] ?? '';

        if ( empty( $downloadUrl ) ) {
            $error = __( 'common.error' );
        } else {
            $result = $updater->install( $downloadUrl );
            if ( $result['success'] ) {
                $success = __( 'updates.update_success', [
                    'from' => $result['from_version'],
                    'to'   => $result['to_version'],
                ] );
                $currentVersion = $result['to_version'];
            } else {
                $error = __( 'updates.update_failed', [ 'error' => $result['error'] ] );
            }
        }
    }
} else {
    // Auto-check on page load (uses cache).
    $updateInfo = $updater->checkForUpdate();
}

$history = $updater->getLog();
$csrf    = $auth->getCsrfToken();

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<div class="admin-main">

<?php if ( $success ): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>
<?php if ( $error ): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<!-- Current Version + Check -->
<div class="card">
    <div class="flex-between flex-wrap flex-gap-md">
        <div>
            <div class="text-xs text-muted text-upper"><?php echo __( 'updates.current_version' ); ?></div>
            <span class="text-2xl font-heavy">v<?php echo klytos_esc_html( $currentVersion ); ?></span>
            <?php
            $currentChannelLabel = Updater::versionChannel( $currentVersion );
            if ( $currentChannelLabel !== 'stable' ):
                ?>
                <span class="badge-status badge-draft ml-auto"><?php echo klytos_esc_html( strtoupper( $currentChannelLabel ) ); ?></span>
            <?php endif; ?>
        </div>
        <form method="post">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="action" value="check">
            <button type="submit" class="btn btn-outline"><?php echo __( 'updates.latest_version' ); ?></button>
        </form>
    </div>
</div>

<!-- Update Channel -->
<div class="card">
    <div class="card-header"><h3>Update Channel</h3></div>
    <form method="post">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="action" value="set_channel">

        <div class="selection-cards">

            <label class="selection-card">
                <input type="radio" name="channel" value="stable" <?php echo $currentChannel === Updater::CHANNEL_STABLE ? 'checked' : ''; ?>>
                <div class="selection-card-body horizontal">
                    <div>
                        <span class="selection-card-title">Stable</span>
                        <span class="badge-status badge-published ml-auto">Recommended</span>
                        <span class="selection-card-desc mt-1">Only final releases (v2.1.0). Maximum stability for production sites.</span>
                    </div>
                </div>
            </label>

            <label class="selection-card">
                <input type="radio" name="channel" value="rc" <?php echo $currentChannel === Updater::CHANNEL_RC ? 'checked' : ''; ?>>
                <div class="selection-card-body horizontal">
                    <div>
                        <span class="selection-card-title">Release Candidate</span>
                        <span class="badge-status badge-draft ml-auto">Developers</span>
                        <span class="selection-card-desc mt-1">Stable + RC versions (v2.1.0-rc.1). Nearly final, for testing before release.</span>
                    </div>
                </div>
            </label>

            <label class="selection-card">
                <input type="radio" name="channel" value="beta" <?php echo $currentChannel === Updater::CHANNEL_BETA ? 'checked' : ''; ?>>
                <div class="selection-card-body horizontal">
                    <div>
                        <span class="selection-card-title">Beta</span>
                        <span class="badge-status badge-urgent ml-auto">Developers</span>
                        <span class="selection-card-desc mt-1">All versions including beta (v2.1.0-beta.1). Early access, may contain bugs.</span>
                    </div>
                </div>
            </label>

        </div>

        <div class="mt-3 border-t pt-2">
            <label class="font-bold text-sm">Maximum backups to keep</label>
            <div class="flex-center flex-gap mt-1">
                <input type="number" name="max_backups" value="<?php echo (int) $updater->getMaxBackups(); ?>" min="1" max="100" style="width:80px;" class="form-control">
                <span class="text-sm text-muted">Older backups are automatically deleted when this limit is exceeded.</span>
            </div>
        </div>

        <div class="mt-2">
            <button type="submit" class="btn btn-primary">Save &amp; check for updates</button>
        </div>
    </form>
</div>

<!-- Update Available -->
<?php if ( $updateInfo ): ?>
<div class="card" style="border-left:4px solid var(--klytos-accent);">
    <div class="card-header">
        <h3>
            <?php echo __( 'updates.available', [ 'version' => $updateInfo['version_label'] ?? $updateInfo['new_version'] ] ); ?>
            <?php
            $releaseChannel = $updateInfo['release_channel'] ?? 'stable';
            if ( $releaseChannel !== 'stable' ):
                ?>
                <span class="badge-status <?php echo $releaseChannel === 'beta' ? 'badge-urgent' : 'badge-draft'; ?>">
                    <?php echo klytos_esc_html( strtoupper( $releaseChannel ) ); ?>
                </span>
            <?php endif; ?>
        </h3>
    </div>

    <?php if ( ! empty( $updateInfo['is_major'] ) ): ?>
        <div class="alert alert-warning"><?php echo __( 'updates.major_warning' ); ?></div>
    <?php endif; ?>

    <!-- Version comparison -->
    <div class="mb-3" style="display:grid;grid-template-columns:1fr auto 1fr;gap:1rem;align-items:center;">
        <div class="text-center p-2 rounded" style="background:var(--klytos-bg);">
            <div class="text-xs text-muted text-upper">Current</div>
            <div class="mono text-xl font-bold">v<?php echo klytos_esc_html( $currentVersion ); ?></div>
        </div>
        <div class="text-xl text-muted">&rarr;</div>
        <div class="text-center p-2 rounded" style="background:var(--klytos-bg);">
            <div class="text-xs text-muted text-upper">New</div>
            <div class="mono text-xl font-heavy text-accent">v<?php echo klytos_esc_html( $updateInfo['new_version'] ); ?></div>
        </div>
    </div>

    <?php if ( ! empty( $updateInfo['published_at'] ) ): ?>
        <div class="text-sm text-muted mb-2">
            Released: <?php echo date( 'F j, Y', strtotime( $updateInfo['published_at'] ) ); ?>
            <?php if ( ! empty( $updateInfo['html_url'] ) ): ?>
                — <a href="<?php echo klytos_esc_url( $updateInfo['html_url'] ); ?>" target="_blank" rel="noopener">View on GitHub &rarr;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Changelog / What's New -->
    <?php if ( ! empty( $updateInfo['changelog'] ) ): ?>
        <div class="mb-3">
            <h4 class="mb-1 text-base">What's new in this version</h4>
            <div class="p-2 rounded text-sm" style="background:var(--klytos-bg);line-height:1.7;max-height:400px;overflow-y:auto;">
                <?php
                    $changelogHtml = klytos_esc_html( $updateInfo['changelog'] );
                    // Convert markdown to HTML (basic GitHub release notes support)
                    // Headings: ## Heading
                    $changelogHtml = preg_replace( '/^### (.+)$/m', '<h5 style="margin:0.75rem 0 0.25rem;">$1</h5>', $changelogHtml );
                    $changelogHtml = preg_replace( '/^## (.+)$/m', '<h4 style="margin:0.75rem 0 0.25rem;">$1</h4>', $changelogHtml );
                    // Bold: **text**
                    $changelogHtml = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $changelogHtml );
                    // Links: [text](url) — already escaped, so reconstruct
                    $changelogHtml = preg_replace(
                        '/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/',
                        '<a href="$2" target="_blank" rel="noopener" style="color:var(--klytos-accent);">$1</a>',
                        $changelogHtml
                    );
                    // Bare URLs (not already in href)
                    $changelogHtml = preg_replace(
                        '/(?<!href="|">)(https?:\/\/[^\s<]+)/',
                        '<a href="$1" target="_blank" rel="noopener" style="color:var(--klytos-accent);">$1</a>',
                        $changelogHtml
                    );
                    // List items: * item or - item
                    $changelogHtml = preg_replace( '/^\* (.+)$/m', '<li style="margin-left:1rem;">$1</li>', $changelogHtml );
                    $changelogHtml = preg_replace( '/^- (.+)$/m', '<li style="margin-left:1rem;">$1</li>', $changelogHtml );
                    // Wrap consecutive <li> in <ul>
                    $changelogHtml = preg_replace( '/(<li[^>]*>.+<\/li>\n?)+/', '<ul style="list-style:disc;padding-left:0.5rem;margin:0.5rem 0;">$0</ul>', $changelogHtml );
                    // Line breaks for remaining lines
                    $changelogHtml = nl2br( $changelogHtml );
                    echo $changelogHtml;
                ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Update button -->
    <?php if ( ! empty( $updateInfo['download_url'] ) ): ?>
        <button type="button" class="btn btn-primary btn-lg" id="btnUpdate"
            data-download-url="<?php echo klytos_esc_attr( $updateInfo['download_url'] ); ?>"
            data-new-version="<?php echo klytos_esc_attr( $updateInfo['new_version'] ); ?>">
            <?php echo __( 'updates.update_now' ); ?>: v<?php echo klytos_esc_html( $updateInfo['new_version'] ); ?>
        </button>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Update History -->
<?php if ( ! empty( $history ) ): ?>
<div class="card">
    <div class="card-header"><h3><?php echo __( 'updates.history' ); ?></h3></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th><?php echo __( 'common.date' ); ?></th>
                    <th>From</th>
                    <th>To</th>
                    <th><?php echo __( 'common.status' ); ?></th>
                    <th>Backup</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $history as $entry ): ?>
                <tr>
                    <td><?php echo ! empty( $entry['date'] ) ? date( 'Y-m-d H:i', strtotime( $entry['date'] ) ) : ''; ?></td>
                    <td class="mono"><?php echo klytos_esc_html( $entry['from'] ?? '' ); ?></td>
                    <td class="mono font-bold"><?php echo klytos_esc_html( $entry['to'] ?? '' ); ?></td>
                    <td>
                        <?php
                        $statusClass = match( $entry['status'] ?? '' ) {
                            'success'  => 'badge-published',
                            'restored' => 'badge-published',
                            'rollback' => 'badge-draft',
                            default    => 'badge-urgent',
                        };
    ?>
                        <span class="badge-status <?php echo $statusClass; ?>">
                            <?php echo klytos_esc_html( ucfirst( $entry['status'] ?? 'unknown' ) ); ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $hasBackup    = ! empty( $entry['backup_path'] ) && $entry['backup_path'] !== '—';
                        $backupExists = $hasBackup && is_dir( $updater->getBackupsDir() . '/' . $entry['backup_path'] );
                        ?>
                        <?php if ( $backupExists ): ?>
                            <div class="flex-center flex-gap-sm">
                                <span class="mono text-xs"><?php echo klytos_esc_html( $entry['backup_path'] ); ?></span>
                                <form method="post" class="inline-form form-confirm-restore">
                                    <?php echo klytos_csrf_field(); ?>
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="backup_name" value="<?php echo klytos_esc_attr( $entry['backup_path'] ); ?>">
                                    <button type="submit" class="btn btn-outline btn-sm">Restore</button>
                                </form>
                            </div>
                        <?php elseif ( $hasBackup ): ?>
                            <span class="mono text-xs text-muted">
                                <?php echo klytos_esc_html( $entry['backup_path'] ); ?> (deleted)
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Update Progress Overlay -->
<div id="updateOverlay" class="modal-overlay" style="z-index:9999;backdrop-filter:blur(4px);">
    <div class="modal max-w-md p-3" style="box-shadow:0 25px 50px rgba(0,0,0,0.4);">
        <h3 id="updateOverlayTitle" class="mb-1 text-xl text-center">
            <?php echo klytos_esc_html( __( 'updates.progress_title' ) ); ?>
        </h3>
        <p id="updateOverlaySubtitle" class="mb-4 text-center text-sm text-muted">
            <?php echo klytos_esc_html( __( 'updates.progress_do_not_close' ) ); ?>
        </p>

        <div id="updateSteps" class="flex-col flex-gap-md">
            <div class="update-step" data-step="0">
                <span class="step-icon">1</span>
                <span class="step-label"><?php echo klytos_esc_html( __( 'updates.progress_checking' ) ); ?></span>
            </div>
            <div class="update-step" data-step="1">
                <span class="step-icon">2</span>
                <span class="step-label"><?php echo klytos_esc_html( __( 'updates.progress_backup' ) ); ?></span>
            </div>
            <div class="update-step" data-step="2">
                <span class="step-icon">3</span>
                <span class="step-label"><?php echo klytos_esc_html( __( 'updates.progress_downloading' ) ); ?></span>
            </div>
            <div class="update-step" data-step="3">
                <span class="step-icon">4</span>
                <span class="step-label"><?php echo klytos_esc_html( __( 'updates.progress_installing' ) ); ?></span>
            </div>
            <div class="update-step" data-step="4">
                <span class="step-icon">5</span>
                <span class="step-label"><?php echo klytos_esc_html( __( 'updates.progress_verifying' ) ); ?></span>
            </div>
        </div>

        <!-- Result message (hidden initially) -->
        <div id="updateResult" class="hidden mt-3 p-2 rounded text-center font-bold"></div>
        <div id="updateResultAction" class="hidden mt-2 text-center">
            <button type="button" class="btn btn-primary" id="btnReloadPage"><?php echo klytos_esc_html( __( 'common.reload' ) ); ?></button>
        </div>
    </div>
</div>

<script nonce="<?php echo $cspNonce; ?>">
( function() {
    var csrfToken = '<?php echo klytos_esc_attr( $csrf ); ?>';

    // ── Reload button ─────────────────────────────────────────
    var btnReload = document.getElementById( 'btnReloadPage' );
    if ( btnReload ) {
        btnReload.addEventListener( 'click', function() {
            location.reload();
        } );
    }

    // ── Update via AJAX with progress overlay ─────────────────
    var btnUpdate = document.getElementById( 'btnUpdate' );
    if ( btnUpdate ) {
        btnUpdate.addEventListener( 'click', function() {
            if ( ! confirm( 'Update Klytos? A backup will be created automatically before updating.' ) ) {
                return;
            }

            var downloadUrl = btnUpdate.getAttribute( 'data-download-url' );
            var overlay     = document.getElementById( 'updateOverlay' );
            var steps       = document.querySelectorAll( '.update-step' );
            var currentStep = 0;
            var stepTimer;
            var requestDone = false;
            var requestResult = null;

            // Show overlay.
            overlay.style.display = 'flex';
            btnUpdate.style.opacity = '0.7';
            btnUpdate.style.pointerEvents = 'none';

            // Activate a step (highlight current, mark previous as done).
            function activateStep( index ) {
                steps.forEach( function( step, i ) {
                    var icon  = step.querySelector( '.step-icon' );
                    var label = step.querySelector( '.step-label' );
                    if ( i < index ) {
                        // Done.
                        icon.textContent = '\u2713';
                        icon.style.background = '#22c55e';
                        icon.style.color = '#fff';
                        label.style.color = 'var(--admin-text)';
                    } else if ( i === index ) {
                        // Active (spinner via animation).
                        icon.textContent = '';
                        icon.style.background = 'linear-gradient(135deg, #6366f1, #8b5cf6)';
                        icon.style.color = '#fff';
                        icon.style.animation = 'updatePulse 1s infinite';
                        label.style.color = 'var(--admin-text)';
                        label.style.fontWeight = '600';
                    } else {
                        // Pending.
                        icon.textContent = String( i + 1 );
                        icon.style.background = 'var(--admin-border)';
                        icon.style.color = 'var(--admin-text-muted)';
                        icon.style.animation = '';
                        label.style.color = 'var(--admin-text-muted)';
                        label.style.fontWeight = '';
                    }
                } );
            }

            // Advance steps on a timer (simulated progress).
            var stepDelays = [ 0, 2000, 4000, 6000, 10000 ];
            function scheduleStep( index ) {
                if ( index >= steps.length ) return;
                stepTimer = setTimeout( function() {
                    if ( requestDone ) return;
                    currentStep = index;
                    activateStep( index );
                    scheduleStep( index + 1 );
                }, index === 0 ? 0 : stepDelays[ index ] - stepDelays[ index - 1 ] );
            }

            scheduleStep( 0 );

            // Send AJAX request.
            fetch( 'api/update-install.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify( { download_url: downloadUrl } )
            } )
            .then( function( response ) { return response.json(); } )
            .then( function( data ) {
                requestDone = true;
                clearTimeout( stepTimer );

                if ( data.success ) {
                    // Mark all steps done.
                    steps.forEach( function( step ) {
                        var icon  = step.querySelector( '.step-icon' );
                        var label = step.querySelector( '.step-label' );
                        icon.textContent = '\u2713';
                        icon.style.background = '#22c55e';
                        icon.style.color = '#fff';
                        icon.style.animation = '';
                        label.style.color = 'var(--admin-text)';
                        label.style.fontWeight = '';
                    } );

                    // Show success result.
                    var resultEl = document.getElementById( 'updateResult' );
                    resultEl.style.display = 'block';
                    resultEl.style.background = 'rgba(34,197,94,0.15)';
                    resultEl.style.color = '#22c55e';
                    resultEl.textContent = '<?php echo klytos_esc_html( __( 'updates.progress_done' ) ); ?>';

                    document.getElementById( 'updateOverlaySubtitle' ).style.display = 'none';
                    document.getElementById( 'updateResultAction' ).style.display = 'block';
                } else {
                    showError( data.error || 'Unknown error' );
                }
            } )
            .catch( function( err ) {
                requestDone = true;
                clearTimeout( stepTimer );
                showError( err.message || 'Network error' );
            } );

            function showError( message ) {
                // Mark current step as failed.
                steps.forEach( function( step, i ) {
                    var icon = step.querySelector( '.step-icon' );
                    icon.style.animation = '';
                    if ( i === currentStep ) {
                        icon.textContent = '\u2717';
                        icon.style.background = '#ef4444';
                        icon.style.color = '#fff';
                    }
                } );

                var resultEl = document.getElementById( 'updateResult' );
                resultEl.style.display = 'block';
                resultEl.style.background = 'rgba(239,68,68,0.15)';
                resultEl.style.color = '#ef4444';
                resultEl.textContent = '<?php echo klytos_esc_html( __( 'updates.progress_error' ) ); ?>: ' + message;

                document.getElementById( 'updateOverlaySubtitle' ).style.display = 'none';
                document.getElementById( 'updateResultAction' ).style.display = 'block';
            }
        } );
    }

    // ── Confirm before restoring a backup ─────────────────────
    document.querySelectorAll( '.form-confirm-restore' ).forEach( function( form ) {
        form.addEventListener( 'submit', function( e ) {
            if ( ! confirm( 'Restore this backup? Current code files (core/, admin/, templates/) will be overwritten with the backup version.' ) ) {
                e.preventDefault();
            }
        } );
    } );

    // ── Highlight selected channel radio ──────────────────────
    document.querySelectorAll( 'input[name="channel"]' ).forEach( function( radio ) {
        radio.addEventListener( 'change', function() {
            document.querySelectorAll( 'input[name="channel"]' ).forEach( function( r ) {
                r.closest( 'label' ).style.borderColor = 'var(--admin-border)';
                var dot = r.closest( 'label' ).querySelector( '.channel-dot' );
                dot.style.borderColor = 'var(--admin-border)';
                dot.innerHTML = '';
            } );
            this.closest( 'label' ).style.borderColor = 'var(--admin-primary)';
            var activeDot = this.closest( 'label' ).querySelector( '.channel-dot' );
            activeDot.style.borderColor = 'var(--admin-primary)';
            activeDot.innerHTML = '<span style="position:absolute;top:3px;left:3px;width:8px;height:8px;border-radius:50%;background:var(--admin-primary);"></span>';
        } );
    } );
} )();
</script>

<style nonce="<?php echo $cspNonce; ?>">
@keyframes updatePulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(0.9); }
}
</style>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
