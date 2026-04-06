<?php

/**
 * Klytos Admin — Block Data Editor
 * Edit the global data for a specific block (scope=global).
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
use Klytos\Core\BuildEngine;

$pageTitle    = __( 'design.blocks' );
$auth         = $app->getAuth();
$blockManager = new BlockManager( $app->getStorage() );
$success      = '';
$error        = '';

$blockId = $_GET['id'] ?? '';
if ( empty( $blockId ) ) {
    header( 'Location: blocks.php' );
    exit;
}

try {
    $block = $blockManager->get( $blockId );
} catch ( \Throwable $e ) {
    header( 'Location: blocks.php' );
    exit;
}

// Only global blocks can be edited here.
if ( ( $block['scope'] ?? '' ) !== 'global' ) {
    header( 'Location: blocks.php' );
    exit;
}

$slots      = $block['slots'] ?? [];
$globalData = $blockManager->getGlobalData( $blockId );

// ─── Handle form submission ──────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $auth->verifyCsrf( $_POST['_csrf'] ?? '' );

    $newData = [];
    foreach ( $slots as $slot ) {
        $name = $slot['name'] ?? '';
        if ( empty( $name ) ) {
            continue;
        }
        if ( isset( $_POST[ $name ] ) ) {
            $newData[ $name ] = $_POST[ $name ];
        }
    }

    try {
        $blockManager->setGlobalData( $blockId, $newData );
        $globalData = $newData;
        $success    = 'Block data saved.';

        // Smart rebuild if requested.
        if ( isset( $_POST['rebuild'] ) ) {
            require_once $app->getCorePath() . '/build-engine.php';
            $engine = new BuildEngine( $app );
            $result = $engine->smartRebuildBlock( $blockId );
            $success .= " Rebuilt in {$result['files_updated']} files.";
        }
    } catch ( \Throwable $e ) {
        $error = $e->getMessage();
    }
}

// Preview with current data.
$previewHtml = '';
try {
    $previewHtml = $blockManager->render( $blockId, $globalData );
} catch ( \Throwable $e ) {
    // Ignore preview errors.
}

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.block_data.before' ); ?>

<div class="mb-2">
    <a href="blocks.php" style="color:var(--klytos-primary);text-decoration:none;">&larr; <?php echo __( 'design.blocks' ); ?></a>
</div>

<?php if ( !empty( $success ) ): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>
<?php if ( !empty( $error ) ): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<div class="grid-2" style="gap:1.5rem;align-items:start;">
    <!-- Form -->
    <div class="card">
        <div class="card-header">
            <h3><?php echo klytos_esc_html( $block['name'] ?? $blockId ); ?> — Global Data</h3>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="_csrf" value="<?php echo klytos_esc_attr( $auth->getCsrfToken() ); ?>">

                <?php foreach ( $slots as $slot ):
                    $name    = $slot['name'] ?? '';
                    $label   = $slot['label'] ?? ucfirst( $name );
                    $type    = $slot['type'] ?? 'text';
                    $value   = $globalData[ $name ] ?? ( $slot['default'] ?? '' );
                    $inputId = 'field-' . $name;
                    ?>
                    <div class="form-group mb-2">
                        <label for="<?php echo klytos_esc_attr( $inputId ); ?>" style="display:block;font-weight:500;margin-bottom:0.3rem;">
                            <?php echo klytos_esc_html( $label ); ?>
                            <span class="text-muted text-xs" style="font-weight:400;">(<?php echo klytos_esc_html( $type ); ?>)</span>
                        </label>
                        <?php if ( $type === 'richtext' || $type === 'html' ): ?>
                            <textarea
                                id="<?php echo klytos_esc_attr( $inputId ); ?>"
                                name="<?php echo klytos_esc_attr( $name ); ?>"
                                rows="5"
                                class="form-control"
                                class="w-full text-mono text-sm"
                            ><?php echo klytos_esc_html( (string) $value ); ?></textarea>
                        <?php elseif ( $type === 'boolean' ): ?>
                            <label class="flex flex-center flex-gap-sm">
                                <input type="checkbox" name="<?php echo klytos_esc_attr( $name ); ?>" value="1" <?php echo $value ? 'checked' : ''; ?>>
                                <?php echo klytos_esc_html( $label ); ?>
                            </label>
                        <?php else: ?>
                            <input
                                type="text"
                                id="<?php echo klytos_esc_attr( $inputId ); ?>"
                                name="<?php echo klytos_esc_attr( $name ); ?>"
                                value="<?php echo klytos_esc_attr( (string) $value ); ?>"
                                class="form-control"
                                class="w-full"
                            >
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="flex mt-3" style="gap:0.75rem;">
                    <button type="submit" class="btn btn-primary"><?php echo __( 'common.save' ); ?></button>
                    <button type="submit" name="rebuild" value="1" class="btn btn-outline">Save &amp; Rebuild</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview -->
    <div class="card">
        <div class="card-header">
            <h3><?php echo __( 'common.preview' ); ?></h3>
        </div>
        <div style="border:1px solid var(--klytos-border);border-radius:var(--klytos-radius);overflow:hidden;background:#fff;">
            <?php if ( !empty( $previewHtml ) ): ?>
                <iframe srcdoc="<?php echo klytos_esc_attr( '<!DOCTYPE html><html><head><style>body{font-family:system-ui,sans-serif;padding:1.5rem;margin:0;}</style></head><body>' . $previewHtml . '</body></html>' ); ?>" style="width:100%;height:300px;border:none;"></iframe>
            <?php else: ?>
                <p class="text-muted text-center p-3">No preview available.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php klytos_do_action( 'admin.block_data.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
