<?php
/**
 * Klytos Admin — Updates (via GitHub Releases)
 *
 * @copyright 2024-2026 José Conti. All rights reserved.
 * @license   Elastic License 2.0 (ELv2)
 */

declare( strict_types=1 );

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
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && $auth->validateCsrf( $_POST['csrf'] ?? '' ) ) {
    $action = $_POST['action'] ?? '';

    if ( $action === 'set_channel' ) {
        $newChannel = $_POST['channel'] ?? Updater::CHANNEL_STABLE;
        $updater->setChannel( $newChannel );
        $currentChannel = $updater->getChannel();
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

<div class="admin-content">
<div class="admin-topbar">
    <h1 style="font-size:1.1rem;font-weight:600;"><?php echo __( 'updates.title' ); ?></h1>
    <div style="display:flex;align-items:center;gap:0.75rem;">
        <?php echo htmlspecialchars( $auth->getUsername() ); ?>
        <a href="logout.php" class="btn btn-outline btn-sm"><?php echo __( 'auth.logout' ); ?></a>
    </div>
</div>
<div class="admin-main">

<?php if ( $success ): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars( $success ); ?></div>
<?php endif; ?>
<?php if ( $error ): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars( $error ); ?></div>
<?php endif; ?>

<!-- Current Version + Check -->
<div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div>
            <div style="font-size:0.8rem;color:var(--admin-text-muted);text-transform:uppercase;letter-spacing:0.05em;"><?php echo __( 'updates.current_version' ); ?></div>
            <span style="font-size:2rem;font-weight:700;">v<?php echo htmlspecialchars( $currentVersion ); ?></span>
            <?php
            $currentChannelLabel = Updater::versionChannel( $currentVersion );
            if ( $currentChannelLabel !== 'stable' ):
            ?>
                <span class="badge-status badge-draft" style="margin-left:0.5rem;"><?php echo htmlspecialchars( strtoupper( $currentChannelLabel ) ); ?></span>
            <?php endif; ?>
        </div>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf ); ?>">
            <input type="hidden" name="action" value="check">
            <button type="submit" class="btn btn-outline"><?php echo __( 'updates.latest_version' ); ?></button>
        </form>
    </div>
</div>

<!-- Update Channel -->
<div class="card">
    <div class="card-header"><h3>Update Channel</h3></div>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf ); ?>">
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

        <div style="margin-top:1rem;">
            <button type="submit" class="btn btn-primary">Save channel &amp; check for updates</button>
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
                    <?php echo htmlspecialchars( strtoupper( $releaseChannel ) ); ?>
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
            <div class="mono" style="font-size:1.25rem;font-weight:600;">v<?php echo htmlspecialchars( $currentVersion ); ?></div>
        </div>
        <div style="font-size:1.5rem;color:var(--admin-text-muted);">&rarr;</div>
        <div style="text-align:center;padding:1rem;background:var(--admin-bg);border-radius:var(--admin-radius);">
            <div style="font-size:0.75rem;color:var(--admin-text-muted);text-transform:uppercase;">New</div>
            <div class="mono" style="font-size:1.25rem;font-weight:700;color:var(--admin-primary);">v<?php echo htmlspecialchars( $updateInfo['new_version'] ); ?></div>
        </div>
    </div>

    <?php if ( ! empty( $updateInfo['published_at'] ) ): ?>
        <div style="font-size:0.85rem;color:var(--admin-text-muted);margin-bottom:1rem;">
            Released: <?php echo date( 'F j, Y', strtotime( $updateInfo['published_at'] ) ); ?>
            <?php if ( ! empty( $updateInfo['html_url'] ) ): ?>
                — <a href="<?php echo htmlspecialchars( $updateInfo['html_url'] ); ?>" target="_blank" rel="noopener">View on GitHub &rarr;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Changelog / What's New -->
    <?php if ( ! empty( $updateInfo['changelog'] ) ): ?>
        <div style="margin-bottom:1.5rem;">
            <h4 style="margin-bottom:0.75rem;font-size:1rem;">What's new in this version</h4>
            <div style="background:var(--admin-bg);padding:1.25rem;border-radius:var(--admin-radius);font-size:0.9rem;line-height:1.7;max-height:400px;overflow-y:auto;">
                <?php echo nl2br( htmlspecialchars( $updateInfo['changelog'] ) ); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Update button -->
    <?php if ( ! empty( $updateInfo['download_url'] ) ): ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf ); ?>">
            <input type="hidden" name="action" value="install">
            <input type="hidden" name="download_url" value="<?php echo htmlspecialchars( $updateInfo['download_url'] ); ?>">
            <button type="submit" class="btn btn-primary" id="btnUpdate" style="font-size:1rem;padding:0.75rem 2rem;">
                <?php echo __( 'updates.update_now' ); ?>: v<?php echo htmlspecialchars( $updateInfo['new_version'] ); ?>
            </button>
        </form>
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
                    <td class="mono"><?php echo htmlspecialchars( $entry['from'] ?? '' ); ?></td>
                    <td class="mono" style="font-weight:600;"><?php echo htmlspecialchars( $entry['to'] ?? '' ); ?></td>
                    <td>
                        <?php
                        $statusClass = match( $entry['status'] ?? '' ) {
                            'success'  => 'badge-published',
                            'rollback' => 'badge-draft',
                            default    => 'badge-urgent',
                        };
                        ?>
                        <span class="badge-status <?php echo $statusClass; ?>">
                            <?php echo htmlspecialchars( ucfirst( $entry['status'] ?? 'unknown' ) ); ?>
                        </span>
                    </td>
                    <td class="mono" style="font-size:0.8rem;"><?php echo htmlspecialchars( $entry['backup_path'] ?? '—' ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</div>
</div>

<script nonce="<?php echo $cspNonce; ?>">
( function() {
    // Confirm before updating.
    var btnUpdate = document.getElementById( 'btnUpdate' );
    if ( btnUpdate ) {
        btnUpdate.addEventListener( 'click', function( e ) {
            if ( ! confirm( 'Update Klytos? A backup will be created automatically before updating.' ) ) {
                e.preventDefault();
            } else {
                btnUpdate.textContent = '<?php echo __( 'updates.updating' ); ?>';
                btnUpdate.disabled = true;
                btnUpdate.style.opacity = '0.7';
            }
        } );
    }

    // Highlight selected channel radio.
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

<?php require_once __DIR__ . '/templates/footer.php'; ?>
