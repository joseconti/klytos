<?php

/**
 * Klytos Admin — Blocks
 * View and manage reusable design blocks.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\BlockManager;

$pageTitle    = __( 'design.blocks' );
$auth         = $app->getAuth();
$blockManager = new BlockManager( $app->getStorage() );
$error        = '';
$previewHtml  = '';
$previewId    = '';

// ─── Handle preview request ──────────────────────────────────
if ( isset( $_GET['preview'] ) && !empty( $_GET['preview'] ) ) {
    $previewId = $_GET['preview'];
    try {
        $previewHtml = $blockManager->render( $previewId );
    } catch ( \Throwable $e ) {
        $error = $e->getMessage();
    }
}

// ─── Load all blocks ─────────────────────────────────────────
$allBlocks = $blockManager->list();

// Group by category.
$grouped = [];
foreach ( $allBlocks as $block ) {
    $cat = $block['category'] ?? 'custom';
    $grouped[ $cat ][] = $block;
}

// Category display order.
$categoryOrder = [ 'structure', 'content', 'interaction', 'social-proof', 'custom' ];
$categoryLabels = [
    'structure'    => 'Structure',
    'content'      => 'Content',
    'interaction'  => 'Interaction',
    'social-proof' => 'Social Proof',
    'custom'       => 'Custom',
];

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.blocks.before' ); ?>

<?php if ( !empty( $error ) ): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<p class="text-muted mb-3">
    Reusable design blocks created by the AI. Global blocks (header, footer) can be edited below.
</p>

<?php if ( !empty( $previewHtml ) ): ?>
    <!-- Block Preview -->
    <div class="card mb-3">
        <div class="card-header">
            <h3><?php echo klytos_esc_html( $previewId ); ?> — <?php echo __( 'common.preview' ); ?></h3>
            <a href="<?php echo klytos_esc_url( strtok( $_SERVER['REQUEST_URI'], '?' ) ); ?>" class="btn btn-outline btn-sm"><?php echo __( 'common.close' ); ?></a>
        </div>
        <div style="border:1px solid var(--admin-border);border-radius:var(--admin-radius);overflow:hidden;background:#fff;">
            <iframe srcdoc="<?php echo klytos_esc_attr( '<!DOCTYPE html><html><head><style>body{font-family:system-ui,sans-serif;padding:2rem;margin:0;}</style></head><body>' . $previewHtml . '</body></html>' ); ?>" style="width:100%;height:300px;border:none;"></iframe>
        </div>
    </div>
<?php endif; ?>

<?php if ( empty( $allBlocks ) ): ?>
    <div class="empty-state">
        <h3>No blocks yet</h3>
        <p>Blocks are created automatically during installation or by the AI via MCP.</p>
    </div>
<?php else: ?>
    <?php foreach ( $categoryOrder as $cat ): ?>
        <?php if ( empty( $grouped[ $cat ] ) ) {
            continue;
        } ?>
        <div class="card mb-2">
            <div class="card-header">
                <h3><?php echo klytos_esc_html( $categoryLabels[ $cat ] ?? ucfirst( $cat ) ); ?></h3>
            </div>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?php echo __( 'common.name' ); ?></th>
                        <th>Scope</th>
                        <th>Version</th>
                        <th><?php echo __( 'common.status' ); ?></th>
                        <th><?php echo __( 'common.actions' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $grouped[ $cat ] as $block ): ?>
                        <?php
                        $blockId = $block['id'] ?? '';
                        $scope   = $block['scope'] ?? 'page';
                        $status  = $block['status'] ?? 'active';
                        $version = $block['version'] ?? '1';
                        ?>
                        <tr>
                            <td><code><?php echo klytos_esc_html( $blockId ); ?></code></td>
                            <td><?php echo klytos_esc_html( $block['name'] ?? $blockId ); ?></td>
                            <td>
                                <span class="badge-status badge-<?php echo $scope === 'global' ? 'active' : 'draft'; ?>">
                                    <?php echo klytos_esc_html( $scope ); ?>
                                </span>
                            </td>
                            <td>v<?php echo klytos_esc_html( (string) $version ); ?></td>
                            <td>
                                <span class="badge-status badge-<?php echo $status === 'active' ? 'active' : 'draft'; ?>">
                                    <?php echo ucfirst( klytos_esc_html( $status ) ); ?>
                                </span>
                            </td>
                            <td>
                                <a href="?preview=<?php echo urlencode( $blockId ); ?>" class="btn btn-outline btn-sm">
                                    <?php echo __( 'common.preview' ); ?>
                                </a>
                                <?php if ( $scope === 'global' ): ?>
                                    <a href="block-data.php?id=<?php echo urlencode( $blockId ); ?>" class="btn btn-primary btn-sm">
                                        Edit Data
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php klytos_do_action( 'admin.blocks.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
