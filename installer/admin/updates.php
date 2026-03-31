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
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div>
            <div style="font-size:0.8rem;color:var(--admin-text-muted);text-transform:uppercase;letter-spacing:0.05em;"><?php echo __( 'updates.current_version' ); ?></div>
            <span style="font-size:2rem;font-weight:700;">v<?php echo klytos_esc_html( $currentVersion ); ?></span>
            <?php
            $currentChannelLabel = Updater::versionChannel( $currentVersion );
            if ( $currentChannelLabel !== 'stable' ):
                ?>
                <span class="badge-status badge-draft" style="margin-left:0.5rem;"><?php echo klytos_esc_html( strtoupper( $currentChannelLabel ) ); ?></span>
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

        <div style="display:flex;flex-direction:column;gap:0.75rem;">

            <label style="display:flex;align-items:flex-start;gap:0.75rem;cursor:pointer;padding:0.75rem;border-radius:var(--admin-radius);border:2px solid <?php echo $currentChannel === Updater::CHANNEL_STABLE ? 'var(--admin-primary)' : 'var(--admin-border)'; ?>;">
                <input type="radio" name="channel" value="stable" <?php echo $currentChannel === Updater::CHANNEL_STABLE ? 'checked' : ''; ?> style="margin-top:0.2rem;">
                <div>
                    <strong>Stable</strong>
                    <span class="badge-status badge-published" style="margin-left:0.5rem;">Recommended</span>
                    <div style="font-size:0.85rem;color:var(--admin-text-muted);margin-top:0.2rem;">
                        Only final releases (v2.1.0). Maximum stability for production sites.
                    </div>
                </div>
            </label>

            <label style="display:flex;align-items:flex-start;gap:0.75rem;cursor:pointer;padding:0.75rem;border-radius:var(--admin-radius);border:2px solid <?php echo $currentChannel === Updater::CHANNEL_RC ? 'var(--admin-primary)' : 'var(--admin-border)'; ?>;">
                <input type="radio" name="channel" value="rc" <?php echo $currentChannel === Updater::CHANNEL_RC ? 'checked' : ''; ?> style="margin-top:0.2rem;">
                <div>
                    <strong>Release Candidate</strong>
                    <span class="badge-status badge-draft" style="margin-left:0.5rem;">Developers</span>
                    <div style="font-size:0.85rem;color:var(--admin-text-muted);margin-top:0.2rem;">
                        Stable + RC versions (v2.1.0-rc.1). Nearly final, for testing before release.
                    </div>
                </div>
            </label>

            <label style="display:flex;align-items:flex-start;gap:0.75rem;cursor:pointer;padding:0.75rem;border-radius:var(--admin-radius);border:2px solid <?php echo $currentChannel === Updater::CHANNEL_BETA ? 'var(--admin-primary)' : 'var(--admin-border)'; ?>;">
                <input type="radio" name="channel" value="beta" <?php echo $currentChannel === Updater::CHANNEL_BETA ? 'checked' : ''; ?> style="margin-top:0.2rem;">
                <div>
                    <strong>Beta</strong>
                    <span class="badge-status badge-urgent" style="margin-left:0.5rem;">Developers</span>
                    <div style="font-size:0.85rem;color:var(--admin-text-muted);margin-top:0.2rem;">
                        All versions including beta (v2.1.0-beta.1). Early access, may contain bugs.
                    </div>
                </div>
            </label>

        </div>

        <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--admin-border);">
            <label style="font-weight:600;font-size:0.9rem;">Maximum backups to keep</label>
            <div style="display:flex;align-items:center;gap:0.75rem;margin-top:0.5rem;">
                <input type="number" name="max_backups" value="<?php echo (int) $updater->getMaxBackups(); ?>" min="1" max="100" style="width:80px;" class="form-control">
                <span style="font-size:0.85rem;color:var(--admin-text-muted);">Older backups are automatically deleted when this limit is exceeded.</span>
            </div>
        </div>

        <div style="margin-top:1rem;">
            <button type="submit" class="btn btn-primary">Save &amp; check for updates</button>
        </div>
    </form>
</div>

<!-- Update Available -->
<?php if ( $updateInfo ): ?>
<div class="card" style="border-left:4px solid var(--admin-primary);">
    <div class="card-header">
        <h3>
            <?php echo __( 'updates.available', [ 'version' => $updateInfo['version_label'] ?? $updateInfo['new_version'] ] ); ?>
            <?php
            $releaseChannel = $updateInfo['release_channel'] ?? 'stable';
            if ( $releaseChannel !== 'stable' ):
                ?>
                <span class="badge-status <?php echo $releaseChannel === 'beta' ? 'badge-urgent' : 'badge-draft'; ?>" style="margin-left:0.5rem;">
                    <?php echo klytos_esc_html( strtoupper( $releaseChannel ) ); ?>
                </span>
            <?php endif; ?>
        </h3>
    </div>

    <?php if ( ! empty( $updateInfo['is_major'] ) ): ?>
        <div class="alert alert-warning"><?php echo __( 'updates.major_warning' ); ?></div>
    <?php endif; ?>

    <!-- Version comparison -->
    <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:1rem;margin-bottom:1.5rem;align-items:center;">
        <div style="text-align:center;padding:1rem;background:var(--admin-bg);border-radius:var(--admin-radius);">
            <div style="font-size:0.75rem;color:var(--admin-text-muted);text-transform:uppercase;">Current</div>
            <div class="mono" style="font-size:1.25rem;font-weight:600;">v<?php echo klytos_esc_html( $currentVersion ); ?></div>
        </div>
        <div style="font-size:1.5rem;color:var(--admin-text-muted);">&rarr;</div>
        <div style="text-align:center;padding:1rem;background:var(--admin-bg);border-radius:var(--admin-radius);">
            <div style="font-size:0.75rem;color:var(--admin-text-muted);text-transform:uppercase;">New</div>
            <div class="mono" style="font-size:1.25rem;font-weight:700;color:var(--admin-primary);">v<?php echo klytos_esc_html( $updateInfo['new_version'] ); ?></div>
        </div>
    </div>

    <?php if ( ! empty( $updateInfo['published_at'] ) ): ?>
        <div style="font-size:0.85rem;color:var(--admin-text-muted);margin-bottom:1rem;">
            Released: <?php echo date( 'F j, Y', strtotime( $updateInfo['published_at'] ) ); ?>
            <?php if ( ! empty( $updateInfo['html_url'] ) ): ?>
                — <a href="<?php echo klytos_esc_url( $updateInfo['html_url'] ); ?>" target="_blank" rel="noopener">View on GitHub &rarr;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Changelog / What's New -->
    <?php if ( ! empty( $updateInfo['changelog'] ) ): ?>
        <div style="margin-bottom:1.5rem;">
            <h4 style="margin-bottom:0.75rem;font-size:1rem;">What's new in this version</h4>
            <div style="background:var(--admin-bg);padding:1.25rem;border-radius:var(--admin-radius);font-size:0.9rem;line-height:1.7;max-height:400px;overflow-y:auto;">
                <?php echo nl2br( klytos_esc_html( $updateInfo['changelog'] ) ); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Update button -->
    <?php if ( ! empty( $updateInfo['download_url'] ) ): ?>
        <button type="button" class="btn btn-primary" id="btnUpdate"
            style="font-size:1rem;padding:0.75rem 2rem;"
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
                    <td class="mono" style="font-weight:600;"><?php echo klytos_esc_html( $entry['to'] ?? '' ); ?></td>
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
                            <div style="display:flex;align-items:center;gap:0.5rem;">
                                <span class="mono" style="font-size:0.8rem;"><?php echo klytos_esc_html( $entry['backup_path'] ); ?></span>
                                <form method="post" style="display:inline;" class="form-confirm-restore">
                                    <?php echo klytos_csrf_field(); ?>
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="backup_name" value="<?php echo klytos_esc_attr( $entry['backup_path'] ); ?>">
                                    <button type="submit" class="btn btn-outline btn-sm">Restore</button>
                                </form>
                            </div>
                        <?php elseif ( $hasBackup ): ?>
                            <span class="mono" style="font-size:0.8rem;color:var(--admin-text-muted);">
                                <?php echo klytos_esc_html( $entry['backup_path'] ); ?> (deleted)
                            </span>
                        <?php else: ?>
                            <span style="color:var(--admin-text-muted);">—</span>
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
<div id="updateOverlay" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
    <div style="background:var(--admin-card-bg, #1e293b);border-radius:var(--admin-radius);padding:2.5rem;max-width:480px;width:90%;box-shadow:0 25px 50px rgba(0,0,0,0.4);">
        <h3 id="updateOverlayTitle" style="margin:0 0 0.5rem;font-size:1.25rem;text-align:center;">
            <?php echo klytos_esc_html( __( 'updates.progress_title' ) ); ?>
        </h3>
        <p id="updateOverlaySubtitle" style="margin:0 0 2rem;text-align:center;font-size:0.85rem;color:var(--admin-text-muted);">
            <?php echo klytos_esc_html( __( 'updates.progress_do_not_close' ) ); ?>
        </p>

        <div id="updateSteps" style="display:flex;flex-direction:column;gap:1rem;">
            <div class="update-step" data-step="0" style="display:flex;align-items:center;gap:0.75rem;">
                <span class="step-icon" style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.8rem;flex-shrink:0;background:var(--admin-border);color:var(--admin-text-muted);transition:all 0.3s;">1</span>
                <span class="step-label" style="font-size:0.95rem;color:var(--admin-text-muted);transition:color 0.3s;"><?php echo klytos_esc_html( __( 'updates.progress_checking' ) ); ?></span>
            </div>
            <div class="update-step" data-step="1" style="display:flex;align-items:center;gap:0.75rem;">
                <span class="step-icon" style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.8rem;flex-shrink:0;background:var(--admin-border);color:var(--admin-text-muted);transition:all 0.3s;">2</span>
                <span class="step-label" style="font-size:0.95rem;color:var(--admin-text-muted);transition:color 0.3s;"><?php echo klytos_esc_html( __( 'updates.progress_backup' ) ); ?></span>
            </div>
            <div class="update-step" data-step="2" style="display:flex;align-items:center;gap:0.75rem;">
                <span class="step-icon" style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.8rem;flex-shrink:0;background:var(--admin-border);color:var(--admin-text-muted);transition:all 0.3s;">3</span>
                <span class="step-label" style="font-size:0.95rem;color:var(--admin-text-muted);transition:color 0.3s;"><?php echo klytos_esc_html( __( 'updates.progress_downloading' ) ); ?></span>
            </div>
            <div class="update-step" data-step="3" style="display:flex;align-items:center;gap:0.75rem;">
                <span class="step-icon" style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.8rem;flex-shrink:0;background:var(--admin-border);color:var(--admin-text-muted);transition:all 0.3s;">4</span>
                <span class="step-label" style="font-size:0.95rem;color:var(--admin-text-muted);transition:color 0.3s;"><?php echo klytos_esc_html( __( 'updates.progress_installing' ) ); ?></span>
            </div>
            <div class="update-step" data-step="4" style="display:flex;align-items:center;gap:0.75rem;">
                <span class="step-icon" style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.8rem;flex-shrink:0;background:var(--admin-border);color:var(--admin-text-muted);transition:all 0.3s;">5</span>
                <span class="step-label" style="font-size:0.95rem;color:var(--admin-text-muted);transition:color 0.3s;"><?php echo klytos_esc_html( __( 'updates.progress_verifying' ) ); ?></span>
            </div>
        </div>

        <!-- Result message (hidden initially) -->
        <div id="updateResult" style="display:none;margin-top:1.5rem;padding:1rem;border-radius:var(--admin-radius);text-align:center;font-weight:600;"></div>
        <div id="updateResultAction" style="display:none;margin-top:1rem;text-align:center;">
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
            } );
            this.closest( 'label' ).style.borderColor = 'var(--admin-primary)';
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
