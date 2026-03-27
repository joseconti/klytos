<?php
/**
 * Klytos Admin — Dashboard
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$pageTitle  = __( 'dashboard.title' );
$auth       = $app->getAuth();
$siteConfig = $app->getSiteConfig()->get();
$pageCount  = $app->getPages()->count('all');
$published  = $app->getPages()->count('published');
$drafts     = $app->getPages()->count('draft');
$tokens     = $app->getAuth()->listBearerTokens();
$lastBuild  = $siteConfig['last_build'] ?? null;

$indexingEnabled = $siteConfig['indexing_enabled'] ?? false;

// Handle enable indexing action.
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ( $_POST['action'] ?? '' ) === 'enable_indexing' ) {
    if ( $auth->validateCsrf( $_POST['csrf'] ?? '' ) ) {
        $app->getSiteConfig()->set( ['indexing_enabled' => true] );
        $indexingEnabled = true;
        $siteConfig['indexing_enabled'] = true;
    }
}

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';

// Show noindex warning banner.
if ( ! $indexingEnabled ) : ?>
<div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:1rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
    <div style="display:flex;align-items:center;gap:0.75rem;">
        <span style="font-size:1.5rem;">&#9888;&#65039;</span>
        <div>
            <strong style="color:#92400e;"><?php echo __( 'indexing.disabled_title' ); ?></strong>
            <p style="color:#a16207;margin:0.25rem 0 0;font-size:0.85rem;"><?php echo __( 'indexing.disabled_description' ); ?></p>
        </div>
    </div>
    <form method="post" style="margin:0;">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $auth->getCsrfToken() ); ?>">
        <input type="hidden" name="action" value="enable_indexing">
        <button type="submit" style="background:#059669;color:white;border:none;padding:0.5rem 1.25rem;border-radius:6px;font-size:0.85rem;font-weight:600;cursor:pointer;">
            <?php echo __( 'indexing.enable_button' ); ?>
        </button>
    </form>
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label"><?php echo __( 'dashboard.total_pages' ); ?></div>
        <div class="stat-value"><?php echo $pageCount; ?></div>
        <div class="stat-detail"><?php echo $published; ?> <?php echo __( 'pages.published' ); ?> / <?php echo $drafts; ?> <?php echo __( 'pages.draft' ); ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-label"><?php echo __( 'dashboard.last_build' ); ?></div>
        <div class="stat-value" style="font-size:1rem;">
            <?php echo $lastBuild ? date( 'Y-m-d H:i', strtotime($lastBuild)) : '—'; ?>
        </div>
        <div class="stat-detail"><?php echo $lastBuild ? 'UTC' : __( 'pages.no_pages' ); ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-label"><?php echo __( 'dashboard.mcp_status' ); ?></div>
        <div class="stat-value" style="font-size:1rem;">
            <span class="badge-status badge-<?php echo count( $tokens) > 0 ? 'active' : 'inactive'; ?>">
                <?php echo count( $tokens) > 0 ? __( 'common.status' ) . ': OK' : __( 'mcp.no_tokens' ); ?>
            </span>
        </div>
        <div class="stat-detail"><?php echo count( $tokens); ?> <?php echo __( 'mcp.tokens' ); ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-label"><?php echo __( 'dashboard.klytos_version' ); ?></div>
        <div class="stat-value" style="font-size:1rem;">
            v<?php echo htmlspecialchars( $app->getVersion()); ?>
        </div>
        <div class="stat-detail">PHP <?php echo PHP_VERSION; ?></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
    <div class="card">
        <div class="card-header">
            <h3><?php echo __( 'dashboard.quick_actions' ); ?></h3>
        </div>
        <div style="display:flex;flex-direction:column;gap:0.5rem;">
            <a href="pages.php" class="btn btn-primary"><?php echo __( 'pages.create_page' ); ?></a>
            <a href="theme.php" class="btn btn-outline"><?php echo __( 'theme.title' ); ?></a>
            <a href="mcp.php" class="btn btn-outline"><?php echo __( 'mcp.create_token' ); ?></a>
            <a href="ai-images.php" class="btn btn-outline"><?php echo __( 'ai_images.generate' ); ?></a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><?php echo __( 'dashboard.system_info' ); ?></h3>
        </div>
        <table>
            <tr><td style="font-weight:600;"><?php echo __( 'dashboard.klytos_version' ); ?></td><td><?php echo htmlspecialchars( $app->getVersion()); ?></td></tr>
            <tr><td style="font-weight:600;"><?php echo __( 'dashboard.php_version' ); ?></td><td><?php echo PHP_VERSION; ?></td></tr>
            <tr><td style="font-weight:600;">Server</td><td><?php echo htmlspecialchars( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?></td></tr>
            <tr><td style="font-weight:600;"><?php echo __( 'license.domain' ); ?></td><td><?php echo htmlspecialchars( $license['domain'] ?? ''); ?></td></tr>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
