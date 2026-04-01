<?php

/**
 * Klytos Admin — Site Importer
 * Upload WordPress XML exports, enter URLs, and manage import sessions.
 *
 * @package KlytosImporter
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 */

declare( strict_types=1 );

$pageTitle = __( 'klytos_importer.page_title' );
$session   = klytos_importer();
$error     = '';
$success   = '';
// $cspNonce is already defined by admin/templates/header.php (Auth::generateCspNonce).

// ─── Handle POST actions ────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $postAction = $_POST['action'] ?? '';

    // Upload XML file.
    if ( $postAction === 'upload_xml' ) {
        $file = $_FILES['xml_file'] ?? null;

        if ( $file && $file['error'] === UPLOAD_ERR_OK ) {
            $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

            if ( $ext !== 'xml' ) {
                $error = __( 'klytos_importer.invalid_file' );
            } elseif ( $file['size'] > 500 * 1024 * 1024 ) {
                $error = __( 'klytos_importer.file_too_large' );
            } else {
                $dataDir  = klytos_storage()->getDataDir();
                $uploadDir = $dataDir . '/imports';

                if ( !is_dir( $uploadDir ) ) {
                    mkdir( $uploadDir, 0700, true );
                }

                $safeName = 'wp-export-' . date( 'Ymd-His' ) . '.xml';
                $destPath = $uploadDir . '/' . $safeName;

                if ( move_uploaded_file( $file['tmp_name'], $destPath ) ) {
                    if ( \KlytosImporter\ImportValidator::validateXmlFile( $destPath ) ) {
                        $success = __( 'klytos_importer.upload_success' );
                    } else {
                        unlink( $destPath );
                        $error = __( 'klytos_importer.invalid_file' );
                    }
                } else {
                    $error = __( 'klytos_importer.upload_error' );
                }
            }
        } else {
            $error = __( 'klytos_importer.upload_error' );
        }
    }

    // Delete session.
    if ( $postAction === 'delete_session' ) {
        $sessionId = $_POST['session_id'] ?? '';
        if ( !empty( $sessionId ) ) {
            $session->delete( $sessionId );
            $success = __( 'klytos_importer.session_deleted' );
        }
    }
}

// ─── Tab state ──────────────────────────────────────────────
$activeTab = $_GET['tab'] ?? 'wordpress';
$sessions  = $session->list( [], 50 );

// Sort sessions by created_at descending.
usort( $sessions, fn( $a, $b ) => strcmp( $b['created_at'] ?? '', $a['created_at'] ?? '' ) );

?>

<?php klytos_do_action( 'importer.before_page' ); ?>

<div class="klytos-importer-page">

    <?php if ( $error ): ?>
        <div class="klytos-notice klytos-notice-error"><?= klytos_esc_html( $error ) ?></div>
    <?php endif; ?>

    <?php if ( $success ): ?>
        <div class="klytos-notice klytos-notice-success"><?= klytos_esc_html( $success ) ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="importer-tabs">
        <button type="button"
                class="importer-tab<?= $activeTab === 'wordpress' ? ' active' : '' ?>"
                data-tab="wordpress">
            <i class="fa-brands fa-wordpress"></i>
            <?= klytos_esc_html( __( 'klytos_importer.tab_wordpress' ) ) ?>
        </button>
        <button type="button"
                class="importer-tab<?= $activeTab === 'url' ? ' active' : '' ?>"
                data-tab="url">
            <i class="fa-solid fa-globe"></i>
            <?= klytos_esc_html( __( 'klytos_importer.tab_url' ) ) ?>
        </button>
        <button type="button"
                class="importer-tab<?= $activeTab === 'sessions' ? ' active' : '' ?>"
                data-tab="sessions">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <?= klytos_esc_html( __( 'klytos_importer.tab_sessions' ) ) ?>
            <?php if ( count( $sessions ) > 0 ): ?>
                <span class="badge"><?= count( $sessions ) ?></span>
            <?php endif; ?>
        </button>
    </div>

    <!-- Tab: WordPress XML -->
    <div class="importer-tab-content<?= $activeTab === 'wordpress' ? ' active' : '' ?>" id="tab-wordpress">
        <?php klytos_do_action( 'importer.before_upload_form' ); ?>

        <form method="POST" enctype="multipart/form-data" class="importer-upload-form">
            <?= klytos_csrf_field() ?>
            <input type="hidden" name="action" value="upload_xml">

            <div class="upload-zone" id="upload-zone">
                <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
                <p class="upload-title"><?= klytos_esc_html( __( 'klytos_importer.upload_xml' ) ) ?></p>
                <p class="upload-hint"><?= klytos_esc_html( __( 'klytos_importer.upload_hint' ) ) ?></p>
                <p class="upload-formats"><?= klytos_esc_html( __( 'klytos_importer.upload_formats' ) ) ?></p>
                <input type="file" name="xml_file" id="xml-file-input" accept=".xml" class="sr-only">
                <button type="button" class="btn btn-outline" id="browse-btn">
                    <i class="fa-solid fa-folder-open"></i> Browse
                </button>
            </div>

            <div class="upload-file-info" id="upload-file-info" style="display:none;">
                <i class="fa-solid fa-file-code"></i>
                <span id="upload-file-name"></span>
                <span id="upload-file-size"></span>
                <button type="button" class="btn-icon" id="upload-clear" title="Remove">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <button type="submit" class="btn btn-primary" id="analyze-btn" disabled>
                <i class="fa-solid fa-magnifying-glass-chart"></i>
                <?= klytos_esc_html( __( 'klytos_importer.analyze_btn' ) ) ?>
            </button>
        </form>

        <?php klytos_do_action( 'importer.after_upload_form' ); ?>
    </div>

    <!-- Tab: URL Import -->
    <div class="importer-tab-content<?= $activeTab === 'url' ? ' active' : '' ?>" id="tab-url">
        <?php $chatUrl = \Klytos\Core\Helpers::getBasePath() . 'admin/ai-chat.php'; ?>

        <div class="importer-url-form">

            <!-- Step 1: Select method -->
            <div class="form-group" id="step-method">
                <label class="step-label"><?= klytos_esc_html( __( 'klytos_importer.step_method' ) ) ?></label>
                <div class="method-cards">
                    <label class="method-card">
                        <input type="radio" name="import_method" value="auto">
                        <div class="method-card-body">
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                            <strong><?= klytos_esc_html( __( 'klytos_importer.method_auto_title' ) ) ?></strong>
                            <small><?= klytos_esc_html( __( 'klytos_importer.method_auto_desc' ) ) ?></small>
                        </div>
                    </label>
                    <label class="method-card">
                        <input type="radio" name="import_method" value="sitemap">
                        <div class="method-card-body">
                            <i class="fa-solid fa-sitemap"></i>
                            <strong><?= klytos_esc_html( __( 'klytos_importer.method_sitemap_title' ) ) ?></strong>
                            <small><?= klytos_esc_html( __( 'klytos_importer.method_sitemap_desc' ) ) ?></small>
                        </div>
                    </label>
                    <label class="method-card">
                        <input type="radio" name="import_method" value="crawl">
                        <div class="method-card-body">
                            <i class="fa-solid fa-spider"></i>
                            <strong><?= klytos_esc_html( __( 'klytos_importer.method_crawl_title' ) ) ?></strong>
                            <small><?= klytos_esc_html( __( 'klytos_importer.method_crawl_desc' ) ) ?></small>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Step 2: URL input (hidden until method selected) -->
            <div class="form-group" id="step-url" style="display:none;">
                <label for="import-url" id="url-label"></label>
                <input type="url" id="import-url" class="form-control" placeholder="">
                <button type="button" class="btn btn-primary" id="generate-prompt-btn" disabled>
                    <i class="fa-solid fa-bolt"></i>
                    <?= klytos_esc_html( __( 'klytos_importer.generate_prompt' ) ) ?>
                </button>
            </div>

            <!-- Step 3: Generated prompt (hidden until generated) -->
            <div id="step-prompt" style="display:none;">
                <div class="importer-prompt-box">
                    <label class="prompt-label"><?= klytos_esc_html( __( 'klytos_importer.prompt_label' ) ) ?></label>
                    <div class="prompt-preview">
                        <code id="prompt-text"></code>
                        <button type="button" class="btn btn-sm btn-outline" id="copy-prompt-btn" title="<?= klytos_esc_attr( __( 'klytos_importer.copy_prompt' ) ) ?>">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                    </div>
                    <div class="prompt-actions">
                        <a href="<?= klytos_esc_url( $chatUrl ) ?>" class="btn btn-primary" id="open-chat-btn">
                            <i class="fa-solid fa-robot"></i>
                            <?= klytos_esc_html( __( 'klytos_importer.open_ai_chat' ) ) ?>
                        </a>
                    </div>
                    <p class="prompt-hint"><?= klytos_esc_html( __( 'klytos_importer.prompt_hint' ) ) ?></p>
                </div>
            </div>

        </div>
    </div>

    <!-- Tab: Sessions -->
    <div class="importer-tab-content<?= $activeTab === 'sessions' ? ' active' : '' ?>" id="tab-sessions">
        <?php if ( empty( $sessions ) ): ?>
            <div class="empty-state">
                <i class="fa-solid fa-inbox"></i>
                <p><?= klytos_esc_html( __( 'klytos_importer.no_sessions' ) ) ?></p>
            </div>
        <?php else: ?>
            <table class="klytos-table">
                <thead>
                    <tr>
                        <?php
                        $columns = klytos_apply_filters( 'importer.session_columns', [
                            'source'   => __( 'klytos_importer.session_source' ),
                            'date'     => __( 'klytos_importer.session_date' ),
                            'status'   => __( 'klytos_importer.session_status' ),
                            'progress' => __( 'klytos_importer.session_progress' ),
                            'actions'  => __( 'klytos_importer.session_actions' ),
                        ] );
                        foreach ( $columns as $col => $label ): ?>
                            <th><?= klytos_esc_html( $label ) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $sessions as $sess ): ?>
                        <?php
                        $progress  = $sess['progress'] ?? [];
                        $total     = $progress['total'] ?? 0;
                        $imported  = $progress['imported'] ?? 0;
                        $pct       = $total > 0 ? round( ( $imported / $total ) * 100 ) : 0;
                        $statusKey = 'klytos_importer.status_' . ( $sess['status'] ?? 'ready' );
                        ?>
                        <tr>
                            <td>
                                <span class="source-badge source-<?= klytos_esc_attr( $sess['source'] ?? '' ) ?>">
                                    <?= klytos_esc_html( ucfirst( $sess['source'] ?? '' ) ) ?>
                                </span>
                                <br>
                                <small><?= klytos_esc_html( $sess['source_url'] ?? '' ) ?></small>
                            </td>
                            <td><?= klytos_esc_html( $sess['created_at'] ?? '' ) ?></td>
                            <td>
                                <span class="status-badge status-<?= klytos_esc_attr( $sess['status'] ?? '' ) ?>">
                                    <?= klytos_esc_html( __( $statusKey ) ) ?>
                                </span>
                            </td>
                            <td>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: <?= $pct ?>%"></div>
                                </div>
                                <small><?= $imported ?> <?= __( 'klytos_importer.of' ) ?> <?= $total ?> <?= __( 'klytos_importer.pages' ) ?></small>
                            </td>
                            <td>
                                <form method="POST" class="inline-form">
                                    <?= klytos_csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_session">
                                    <input type="hidden" name="session_id" value="<?= klytos_esc_attr( $sess['id'] ?? '' ) ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"
                                            data-confirm="<?= klytos_esc_attr( __( 'klytos_importer.delete_confirm' ) ) ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<?php klytos_do_action( 'importer.after_page' ); ?>
