<?php
/**
 * Klytos Admin — Page Templates
 * View, preview, and manage page templates.
 *
 * @package Klytos
 * @since   2.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\PageTemplateManager;
use Klytos\Core\BlockManager;

$pageTitle       = __( 'design.templates' );
$auth            = $app->getAuth();
$blockManager    = new BlockManager( $app->getStorage() );
$templateManager = new PageTemplateManager( $app->getStorage(), $blockManager );
$success         = '';
$error           = '';
$previewHtml     = '';
$previewType     = '';

// ─── Handle preview request ──────────────────────────────────
if ( isset( $_GET['preview'] ) && !empty( $_GET['preview'] ) ) {
    $previewType = $_GET['preview'];
    try {
        $template    = $templateManager->get( $previewType );
        $previewHtml = $templateManager->renderPage( $previewType, [] );
    } catch ( \Throwable $e ) {
        $error = $e->getMessage();
    }
}

// ─── Load all templates ──────────────────────────────────────
$templates      = $templateManager->list();
$availableTypes = $templateManager->getAvailableTypes();

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php if ( !empty( $success ) ): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars( $success ); ?></div>
<?php endif; ?>
<?php if ( !empty( $error ) ): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars( $error ); ?></div>
<?php endif; ?>

<p style="color:var(--admin-text-muted);margin-bottom:1.5rem;">
    <?php echo __( 'design.templates_subtitle' ); ?>
</p>

<?php if ( !empty( $previewHtml ) ): ?>
    <!-- Template Preview -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <h3><?php echo htmlspecialchars( ucfirst( $previewType ) ); ?> — <?php echo __( 'common.preview' ); ?></h3>
            <a href="<?php echo htmlspecialchars( strtok( $_SERVER['REQUEST_URI'], '?' ) ); ?>" class="btn btn-outline btn-sm"><?php echo __( 'common.close' ); ?></a>
        </div>
        <div style="border:1px solid var(--admin-border);border-radius:var(--admin-radius);overflow:hidden;background:#fff;">
            <iframe srcdoc="<?php echo htmlspecialchars( $previewHtml ); ?>" style="width:100%;height:600px;border:none;"></iframe>
        </div>
    </div>
<?php endif; ?>

<!-- Templates Grid -->
<div class="templates-grid">
    <?php if ( empty( $templates ) ): ?>
        <div class="empty-state">
            <h3><?php echo __( 'pages.no_pages' ); ?></h3>
            <p>Create templates via MCP or the CLI to get started.</p>
        </div>
    <?php else: ?>
        <?php foreach ( $templates as $tpl ): ?>
            <?php
            $type       = $tpl['type'] ?? 'unknown';
            $blocks     = $tpl['structure'] ?? [];
            $blockCount = count( $blocks );
            $status     = $tpl['status'] ?? 'draft';
            $desc       = $availableTypes[$type] ?? ucfirst( $type );
            ?>
            <div class="template-card">
                <div class="template-card-preview">
                    <div class="template-card-blocks">
                        <?php foreach ( array_slice( $blocks, 0, 5 ) as $block ): ?>
                            <div class="template-block-indicator" title="<?php echo htmlspecialchars( $block['block_id'] ?? '' ); ?>">
                                <?php echo htmlspecialchars( $block['block_id'] ?? '?' ); ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if ( $blockCount > 5 ): ?>
                            <div class="template-block-indicator" style="opacity:0.5;">+<?php echo $blockCount - 5; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="template-card-info">
                    <h4><?php echo htmlspecialchars( ucfirst( $type ) ); ?></h4>
                    <p class="template-card-desc"><?php echo htmlspecialchars( $desc ); ?></p>
                    <div class="template-card-meta">
                        <span class="badge-status badge-<?php echo $status === 'approved' ? 'active' : 'draft'; ?>">
                            <?php echo ucfirst( htmlspecialchars( $status ) ); ?>
                        </span>
                        <span><?php echo $blockCount; ?> blocks</span>
                    </div>
                    <div class="template-card-actions">
                        <a href="?preview=<?php echo urlencode( $type ); ?>" class="btn btn-outline btn-sm">
                            <?php echo __( 'common.preview' ); ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
    .templates-grid {
        display: grid;
        grid-template-columns: repeat( auto-fill, minmax( 280px, 1fr ) );
        gap: 1.25rem;
    }
    .template-card {
        background: var( --admin-surface );
        border: 1px solid var( --admin-border );
        border-radius: var( --admin-radius );
        overflow: hidden;
        transition: box-shadow 0.2s, transform 0.2s;
    }
    .template-card:hover {
        box-shadow: 0 4px 20px rgba( 0, 0, 0, 0.08 );
        transform: translateY( -2px );
    }
    .template-card-preview {
        background: linear-gradient( 135deg, #f1f5f9 0%, #e2e8f0 100% );
        padding: 1.5rem;
        min-height: 140px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .template-card-blocks {
        display: flex;
        flex-direction: column;
        gap: 4px;
        width: 100%;
    }
    .template-block-indicator {
        background: var( --admin-surface );
        border: 1px solid var( --admin-border );
        border-radius: 4px;
        padding: 0.3rem 0.6rem;
        font-size: 0.7rem;
        font-family: monospace;
        color: var( --admin-text-muted );
        text-align: center;
    }
    .template-card-info {
        padding: 1rem 1.25rem;
    }
    .template-card-info h4 {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    .template-card-desc {
        font-size: 0.82rem;
        color: var( --admin-text-muted );
        margin-bottom: 0.75rem;
    }
    .template-card-meta {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.8rem;
        color: var( --admin-text-muted );
        margin-bottom: 0.75rem;
    }
    .template-card-actions {
        display: flex;
        gap: 0.5rem;
    }
</style>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
