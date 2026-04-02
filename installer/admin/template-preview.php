<?php

/**
 * Klytos Admin — Template Preview
 * Full-page preview of a page template with block structure sidebar.
 *
 * @package Klytos
 * @since   1.0.0
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

use Klytos\Core\PageTemplateManager;
use Klytos\Core\BlockManager;

$pageTitle       = __( 'common.preview' );
$auth            = $app->getAuth();
$blockManager    = new BlockManager( $app->getStorage() );
$templateManager = new PageTemplateManager( $app->getStorage(), $blockManager );
$error           = '';

$type = $_GET['type'] ?? '';
if ( empty( $type ) ) {
    header( 'Location: templates.php' );
    exit;
}

try {
    $template    = $templateManager->get( $type );
    $previewHtml = $templateManager->preview( $type );
    $structure   = $template['structure'] ?? [];

    usort( $structure, fn( array $a, array $b ): int =>
        ( $a['order'] ?? 0 ) <=> ( $b['order'] ?? 0 )
    );
} catch ( \Throwable $e ) {
    $error       = $e->getMessage();
    $template    = [];
    $previewHtml = '';
    $structure   = [];
}

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<div class="mb-2">
    <a href="templates.php" style="color:var(--klytos-primary);text-decoration:none;">&larr; <?php echo __( 'design.templates' ); ?></a>
</div>

<?php if ( !empty( $error ) ): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<?php if ( !empty( $previewHtml ) ): ?>
    <div style="display:grid;grid-template-columns:250px 1fr;gap:1.5rem;align-items:start;">
        <!-- Block Structure Sidebar -->
        <div class="card">
            <div class="card-header">
                <h3><?php echo klytos_esc_html( ucfirst( $type ) ); ?></h3>
            </div>
            <div class="card-body" style="padding:0;">
                <p class="text-muted text-sm" style="padding:0.75rem 1rem;margin:0;border-bottom:1px solid var(--klytos-border);">
                    <?php echo klytos_esc_html( $template['description'] ?? '' ); ?>
                </p>
                <div style="padding:0.5rem;">
                    <?php foreach ( $structure as $i => $blockRef ): ?>
                        <?php $bid = $blockRef['block_id'] ?? ''; ?>
                        <div class="flex flex-center" style="gap:0.5rem;padding:0.5rem 0.75rem;border-radius:4px;margin-bottom:2px;background:var(--klytos-bg);">
                            <span class="text-muted text-center" style="font-size:0.75rem;width:1.5rem;"><?php echo $i + 1; ?></span>
                            <code class="text-xs"><?php echo klytos_esc_html( $bid ); ?></code>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="padding:0.75rem 1rem;border-top:1px solid var(--klytos-border);">
                    <span class="badge-status badge-<?php echo ( $template['status'] ?? 'draft' ) === 'active' ? 'active' : 'draft'; ?>">
                        <?php echo ucfirst( klytos_esc_html( $template['status'] ?? 'draft' ) ); ?>
                    </span>
                    <span class="text-muted text-xs" style="margin-left:0.5rem;">
                        <?php echo count( $structure ); ?> blocks
                    </span>
                </div>
            </div>
        </div>

        <!-- Preview iframe -->
        <div class="card">
            <div class="card-header">
                <h3><?php echo __( 'common.preview' ); ?></h3>
            </div>
            <div style="border:1px solid var(--klytos-border);border-radius:var(--klytos-radius);overflow:hidden;background:#fff;">
                <iframe srcdoc="<?php echo klytos_esc_attr( $previewHtml ); ?>" style="width:100%;height:700px;border:none;"></iframe>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
