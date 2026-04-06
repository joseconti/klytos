<?php

/**
 * Klytos Admin — Privacy Tools (GDPR)
 * Data export (Right of Access, Art. 15) and data erasure (Right to Erasure, Art. 17).
 *
 * Two tabs:
 * 1. Data Export — Search a user, preview data, download JSON/HTML or send by email.
 * 2. Data Erasure — Search a user, show erasable sections, erase selected data.
 *
 * Plugins participate via hooks:
 * - 'privacy.export_data'       — Append data sections to export.
 * - 'privacy.erasable_data'     — Declare erasable/retained data sections.
 * - 'privacy.erase_plugin_data' — Perform plugin-specific erasure.
 *
 * @package Klytos
 * @since   0.18.0
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

use Klytos\Core\Helpers;

$pageTitle      = __( 'privacy.title' );
$privacyManager = $app->getPrivacyManager();
$auth           = $app->getAuth();
$success        = '';
$error          = '';
$foundUser      = null;
$exportData     = null;
$erasableData   = null;
$erasureResults = null;

$currentTab = $_GET['tab'] ?? 'export';

// ─── Handle POST actions ─────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $action = $_POST['action'] ?? '';

    if ( $action === 'search_user' ) {
        $query     = trim( $_POST['query'] ?? '' );
        $foundUser = $privacyManager->findUser( $query );

        if ( $foundUser === null ) {
            $error = __( 'privacy.user_not_found' );
        } else {
            // Pre-load data for the current tab.
            try {
                if ( $currentTab === 'export' ) {
                    $exportData = $privacyManager->collectUserData( $foundUser['id'] );
                } else {
                    $erasableData = $privacyManager->collectErasableData( $foundUser['id'] );
                }
            } catch ( \Throwable $e ) {
                $error = $e->getMessage();
            }
        }
    } elseif ( $action === 'export_json' ) {
        $userId = $_POST['user_id'] ?? '';
        try {
            $json     = $privacyManager->exportAsJson( $userId );
            $user     = $app->getUserManager()->getById( $userId );
            $filename = 'privacy-export-' . $user['username'] . '-' . date( 'Y-m-d' ) . '.json';

            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            header( 'Content-Length: ' . strlen( $json ) );
            echo $json;
            exit;
        } catch ( \Throwable $e ) {
            $error = $e->getMessage();
        }
    } elseif ( $action === 'export_html' ) {
        $userId = $_POST['user_id'] ?? '';
        try {
            $html     = $privacyManager->exportAsHtml( $userId );
            $user     = $app->getUserManager()->getById( $userId );
            $filename = 'privacy-export-' . $user['username'] . '-' . date( 'Y-m-d' ) . '.html';

            header( 'Content-Type: text/html; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            header( 'Content-Length: ' . strlen( $html ) );
            echo $html;
            exit;
        } catch ( \Throwable $e ) {
            $error = $e->getMessage();
        }
    } elseif ( $action === 'send_export_email' ) {
        $userId = $_POST['user_id'] ?? '';
        try {
            $user = $app->getUserManager()->getById( $userId );
            $json = $privacyManager->exportAsJson( $userId );

            $emailBody  = '<h2>' . klytos_esc_html( __( 'privacy.export_title' ) ) . '</h2>';
            $emailBody .= '<p>' . klytos_esc_html( __( 'privacy.export_desc' ) ) . '</p>';
            $emailBody .= '<pre style="background:#f5f5f5;padding:1rem;border-radius:6px;font-size:0.8rem;overflow:auto;max-height:600px;">';
            $emailBody .= htmlspecialchars( $json );
            $emailBody .= '</pre>';

            $sent = $app->getMailer()->send(
                $user['email'],
                __( 'privacy.export_title' ),
                $emailBody,
            );

            if ( $sent ) {
                $success = __( 'privacy.export_success' );
                klytos_do_action( 'privacy.export_sent', $userId );
            } else {
                $error = 'Failed to send email.';
            }
        } catch ( \Throwable $e ) {
            $error = $e->getMessage();
        }
    } elseif ( $action === 'erase_data' ) {
        $userId   = $_POST['user_id'] ?? '';
        $sections = $_POST['sections'] ?? [];

        if ( empty( $sections ) ) {
            $error = __( 'privacy.select_sections' );
        } else {
            try {
                $erasureResults = $privacyManager->eraseUserData(
                    $userId,
                    $sections,
                    $auth->getUserId(),
                );

                // Check if any sections were skipped.
                $skipped = array_filter( $erasureResults, fn( $r ) => ( $r['status'] ?? '' ) === 'skipped' );
                $success = empty( $skipped )
                    ? __( 'privacy.erasure_success' )
                    : __( 'privacy.erasure_partial' );
            } catch ( \Throwable $e ) {
                $error = $e->getMessage();
            }
        }
    }
}

$adminPath = Helpers::getBasePath() . 'admin/';

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.privacy.before' ); ?>

<?php if ( $success ): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>
<?php if ( $error ): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<!-- Tabs -->
<div class="tabs">
    <a class="tab <?php echo $currentTab === 'export' ? 'active' : ''; ?>"
       href="<?php echo klytos_esc_url( $adminPath . 'privacy.php?tab=export' ); ?>"><?php echo klytos_esc_html( __( 'privacy.tab_export' ) ); ?></a>
    <a class="tab <?php echo $currentTab === 'erasure' ? 'active' : ''; ?>"
       href="<?php echo klytos_esc_url( $adminPath . 'privacy.php?tab=erasure' ); ?>"><?php echo klytos_esc_html( __( 'privacy.tab_erasure' ) ); ?></a>
</div>

<?php if ( $currentTab === 'export' ): ?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- DATA EXPORT TAB                                                -->
<!-- ═══════════════════════════════════════════════════════════════ -->

<div class="card" style="border-left:4px solid var(--klytos-primary);">
    <h3 class="mb-1"><?php echo klytos_esc_html( __( 'privacy.export_title' ) ); ?></h3>
    <p class="text-muted mb-2 text-sm">
        <?php echo klytos_esc_html( __( 'privacy.export_desc' ) ); ?>
    </p>

    <!-- Search form -->
    <form method="post" action="<?php echo klytos_esc_url( $adminPath . 'privacy.php?tab=export' ); ?>">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="action" value="search_user">
        <div class="flex flex-center flex-gap-sm">
            <input type="text" name="query" class="form-control flex-1" style="max-width:400px;"
                   placeholder="<?php echo klytos_esc_attr( __( 'privacy.search_placeholder' ) ); ?>"
                   value="<?php echo klytos_esc_attr( $_POST['query'] ?? '' ); ?>" required>
            <button type="submit" class="btn btn-primary"><?php echo klytos_esc_html( __( 'privacy.search_button' ) ); ?></button>
        </div>
    </form>
</div>

<?php if ( $foundUser !== null ): ?>
    <!-- User found card -->
    <div class="card mt-2">
        <div class="flex-between mb-2">
            <div>
                <h4 class="mb-0"><?php echo klytos_esc_html( $foundUser['display_name'] ); ?></h4>
                <p class="text-muted text-sm" style="margin:0.25rem 0 0;">
                    <strong>@<?php echo klytos_esc_html( $foundUser['username'] ); ?></strong>
                    &middot; <?php echo klytos_esc_html( $foundUser['email'] ); ?>
                    &middot; <span class="badge badge-primary"><?php echo klytos_esc_html( $foundUser['role'] ); ?></span>
                </p>
            </div>
        </div>

        <?php if ( $exportData !== null && !empty( $exportData['sections'] ) ): ?>
            <h5 class="mb-1"><?php echo klytos_esc_html( __( 'privacy.export_sections' ) ); ?></h5>
            <table class="table mb-3">
                <thead>
                    <tr>
                        <th><?php echo klytos_esc_html( __( 'privacy.section' ) ); ?></th>
                        <th class="text-right"><?php echo klytos_esc_html( __( 'privacy.items' ) ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $exportData['sections'] as $section ): ?>
                    <tr>
                        <td>
                            <?php echo klytos_esc_html( $section['label'] ); ?>
                            <?php if ( ( $section['source'] ?? 'core' ) !== 'core' ): ?>
                                <span class="badge badge-info text-xs" style="margin-left:0.25rem;"><?php echo klytos_esc_html( $section['source'] ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?php echo (int) ( $section['count'] ?? 1 ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Export action buttons -->
            <div class="flex flex-gap-sm flex-wrap">
                <form method="post" action="<?php echo klytos_esc_url( $adminPath . 'privacy.php?tab=export' ); ?>" class="inline-form">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="action" value="export_json">
                    <input type="hidden" name="user_id" value="<?php echo klytos_esc_attr( $foundUser['id'] ); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-download"></i> <?php echo klytos_esc_html( __( 'privacy.export_json' ) ); ?>
                    </button>
                </form>

                <form method="post" action="<?php echo klytos_esc_url( $adminPath . 'privacy.php?tab=export' ); ?>" class="inline-form">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="action" value="export_html">
                    <input type="hidden" name="user_id" value="<?php echo klytos_esc_attr( $foundUser['id'] ); ?>">
                    <button type="submit" class="btn btn-secondary">
                        <i class="fa-solid fa-file-lines"></i> <?php echo klytos_esc_html( __( 'privacy.export_html' ) ); ?>
                    </button>
                </form>

                <form method="post" action="<?php echo klytos_esc_url( $adminPath . 'privacy.php?tab=export' ); ?>" class="inline-form">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="action" value="send_export_email">
                    <input type="hidden" name="user_id" value="<?php echo klytos_esc_attr( $foundUser['id'] ); ?>">
                    <button type="submit" class="btn btn-secondary">
                        <i class="fa-solid fa-envelope"></i> <?php echo klytos_esc_html( __( 'privacy.export_email' ) ); ?>
                    </button>
                </form>
            </div>
        <?php elseif ( $exportData !== null ): ?>
            <p class="text-muted"><?php echo klytos_esc_html( __( 'privacy.no_data' ) ); ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php elseif ( $currentTab === 'erasure' ): ?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- DATA ERASURE TAB                                               -->
<!-- ═══════════════════════════════════════════════════════════════ -->

<div class="card" style="border-left:4px solid var(--klytos-danger, #e74c3c);">
    <h3 class="mb-1"><?php echo klytos_esc_html( __( 'privacy.erasure_title' ) ); ?></h3>
    <p class="text-muted mb-2 text-sm">
        <?php echo klytos_esc_html( __( 'privacy.erasure_desc' ) ); ?>
    </p>

    <!-- Search form -->
    <form method="post" action="<?php echo klytos_esc_url( $adminPath . 'privacy.php?tab=erasure' ); ?>">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="action" value="search_user">
        <div class="flex flex-center flex-gap-sm">
            <input type="text" name="query" class="form-control flex-1" style="max-width:400px;"
                   placeholder="<?php echo klytos_esc_attr( __( 'privacy.search_placeholder' ) ); ?>"
                   value="<?php echo klytos_esc_attr( $_POST['query'] ?? '' ); ?>" required>
            <button type="submit" class="btn btn-primary"><?php echo klytos_esc_html( __( 'privacy.search_button' ) ); ?></button>
        </div>
    </form>
</div>

<?php if ( $foundUser !== null ): ?>
    <!-- User found card -->
    <div class="card mt-2">
        <div class="flex-between mb-2">
            <div>
                <h4 class="mb-0"><?php echo klytos_esc_html( $foundUser['display_name'] ); ?></h4>
                <p class="text-muted text-sm" style="margin:0.25rem 0 0;">
                    <strong>@<?php echo klytos_esc_html( $foundUser['username'] ); ?></strong>
                    &middot; <?php echo klytos_esc_html( $foundUser['email'] ); ?>
                    &middot; <span class="badge badge-primary"><?php echo klytos_esc_html( $foundUser['role'] ); ?></span>
                </p>
            </div>
        </div>

        <?php if ( ( $foundUser['role'] ?? '' ) === 'owner' ): ?>
            <div class="alert alert-warning mb-2">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?php echo klytos_esc_html( __( 'privacy.owner_cannot_erase' ) ); ?>
            </div>
        <?php endif; ?>

        <?php if ( $erasureResults !== null ): ?>
            <!-- Erasure results -->
            <h5 class="mb-1"><?php echo klytos_esc_html( __( 'privacy.status' ) ); ?></h5>
            <table class="table mb-2">
                <thead>
                    <tr>
                        <th><?php echo klytos_esc_html( __( 'privacy.section' ) ); ?></th>
                        <th><?php echo klytos_esc_html( __( 'privacy.status' ) ); ?></th>
                        <th class="text-right"><?php echo klytos_esc_html( __( 'privacy.items' ) ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $erasureResults as $result ): ?>
                    <tr>
                        <td><?php echo klytos_esc_html( $result['section'] ); ?></td>
                        <td>
                            <?php
                            $statusKey = $result['status'] ?? 'unknown';
                            $badgeClass = match ( $statusKey ) {
                                'deleted', 'erased'  => 'badge-success',
                                'anonymized'         => 'badge-info',
                                'skipped'            => 'badge-warning',
                                default              => 'badge-secondary',
                            };
                            ?>
                            <span class="badge <?php echo $badgeClass; ?>"><?php echo klytos_esc_html( __( 'privacy.' . $statusKey ) ); ?></span>
                            <?php if ( !empty( $result['reason'] ) && $result['reason'] === 'legally_retained' ): ?>
                                <span class="text-muted text-xs" style="margin-left:0.25rem;">
                                    <i class="fa-solid fa-lock"></i> <?php echo klytos_esc_html( __( 'privacy.legally_retained' ) ); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?php echo (int) ( $result['count'] ?? 0 ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php elseif ( $erasableData !== null ): ?>
            <!-- Erasure form -->
            <form method="post" action="<?php echo klytos_esc_url( $adminPath . 'privacy.php?tab=erasure' ); ?>" id="erasure-form">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="action" value="erase_data">
                <input type="hidden" name="user_id" value="<?php echo klytos_esc_attr( $foundUser['id'] ); ?>">

                <table class="table mb-3">
                    <thead>
                        <tr>
                            <th style="width:40px;"></th>
                            <th><?php echo klytos_esc_html( __( 'privacy.section' ) ); ?></th>
                            <th><?php echo klytos_esc_html( __( 'privacy.method' ) ); ?></th>
                            <th class="text-right"><?php echo klytos_esc_html( __( 'privacy.items' ) ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $erasableData as $section ): ?>
                        <tr>
                            <td>
                                <?php if ( $section['erasable'] ): ?>
                                    <input type="checkbox" name="sections[]"
                                           value="<?php echo klytos_esc_attr( $section['id'] ); ?>">
                                <?php else: ?>
                                    <i class="fa-solid fa-lock text-muted"
                                       title="<?php echo klytos_esc_attr( $section['retention_reason'] ?? __( 'privacy.legally_retained' ) ); ?>"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo klytos_esc_html( $section['label'] ); ?>
                                <?php if ( ( $section['source'] ?? 'core' ) !== 'core' ): ?>
                                    <span class="badge badge-info text-xs" style="margin-left:0.25rem;"><?php echo klytos_esc_html( $section['source'] ); ?></span>
                                <?php endif; ?>
                                <?php if ( !$section['erasable'] && !empty( $section['retention_reason'] ) ): ?>
                                    <br><span class="text-muted text-xs">
                                        <i class="fa-solid fa-scale-balanced"></i>
                                        <?php echo klytos_esc_html( $section['retention_reason'] ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $method    = $section['erasure_method'] ?? 'delete';
                                $methodKey = 'privacy.method_' . $method;
                                ?>
                                <span class="badge <?php echo $method === 'anonymize' ? 'badge-info' : 'badge-warning'; ?>">
                                    <?php echo klytos_esc_html( __( $methodKey ) ); ?>
                                </span>
                            </td>
                            <td class="text-right"><?php echo (int) ( $section['item_count'] ?? 0 ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php
                // Only show erase button if there are erasable sections and user is not the owner.
                $hasErasable = !empty( array_filter( $erasableData, fn( $s ) => $s['erasable'] ) );
                ?>
                <?php if ( $hasErasable ): ?>
                    <button type="submit" class="btn btn-danger" id="erase-btn">
                        <i class="fa-solid fa-trash-can"></i> <?php echo klytos_esc_html( __( 'privacy.erasure_button' ) ); ?>
                    </button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php endif; ?>

<?php klytos_do_action( 'admin.privacy.after' ); ?>

<!-- Confirmation dialog for erasure (CSP-compliant) -->
<script nonce="<?php echo $cspNonce ?? ''; ?>">
(function() {
    var form = document.getElementById('erasure-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            var checked = form.querySelectorAll('input[name="sections[]"]:checked');
            if (checked.length === 0) {
                e.preventDefault();
                alert('<?php echo klytos_esc_html( __( 'privacy.select_sections' ) ); ?>');
                return;
            }
            if (!confirm('<?php echo klytos_esc_html( __( 'privacy.erasure_confirm' ) ); ?>')) {
                e.preventDefault();
            }
        });
    }
})();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
