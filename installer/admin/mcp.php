<?php
/**
 * Klytos Admin — MCP Connection
 * Simplified page to connect AI tools (Claude, Cursor, etc.) to Klytos via MCP.
 *
 * Two authentication methods:
 * 1. Application Password (recommended) — Simple, works with any MCP client.
 * 2. OAuth 2.0/2.1 (advanced) — For developers building integrations.
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

use Klytos\Core\Helpers;

$pageTitle      = __( 'mcp.title' );
$auth           = $app->getAuth();
$newAppPass     = '';
$newOAuthClient = null;
$success        = '';
$error          = '';

// Init OAuth server.
require_once $app->getCorePath() . '/mcp/oauth-server.php';
require_once $app->getCorePath() . '/mcp/rate-limiter.php';
$rateLimiter = new \Klytos\Core\MCP\RateLimiter( $app->getDataPath() );
$oauthServer = new \Klytos\Core\MCP\OAuthServer( $auth, $app->getStorage(), $rateLimiter );

// ─── Handle POST actions ─────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && $auth->validateCsrf( $_POST['csrf'] ?? '' ) ) {
    $action = $_POST['action'] ?? '';

    if ( $action === 'create_app_password' ) {
        $label = trim( $_POST['label'] ?? '' );
        try {
            $result     = $auth->createAppPassword( $label, $auth->getUsername() );
            $newAppPass = $result['password'];
            $success    = __( 'app_passwords.password_created' );
        } catch ( \RuntimeException $e ) {
            $error = $e->getMessage();
        }
    } elseif ( $action === 'revoke_app_password' ) {
        $passwordId = $_POST['password_id'] ?? '';
        if ( $auth->revokeAppPassword( $passwordId ) ) {
            $success = __( 'common.success' );
        } else {
            $error = __( 'common.error' );
        }
    } elseif ( $action === 'create_oauth_client' ) {
        $name        = trim( $_POST['client_name'] ?? '' );
        $redirectUri = trim( $_POST['redirect_uri'] ?? '' );
        // Default to confidential — the safe choice for server-side apps.
        $isConfidential = ( $_POST['client_type'] ?? 'confidential' ) === 'confidential';

        if ( empty( $name ) || empty( $redirectUri ) ) {
            $error = __( 'oauth.missing_fields' );
        } else {
            try {
                $newOAuthClient = $oauthServer->createClient( $name, $redirectUri, $isConfidential );
                $success = __( 'oauth.client_created' );
            } catch ( \RuntimeException $e ) {
                $error = $e->getMessage();
            }
        }
    } elseif ( $action === 'revoke_oauth_client' ) {
        $clientId = $_POST['oauth_client_id'] ?? '';
        if ( $oauthServer->revokeClient( $clientId ) ) {
            $success = __( 'common.success' );
        } else {
            $error = __( 'common.error' );
        }
    }
}

$appPasswords = $auth->listAppPasswords();
$oauthClients = $oauthServer->listClients();
$csrf         = $auth->getCsrfToken();
$mcpEndpoint  = Helpers::siteUrl( 'mcp' );
$username     = $auth->getUsername();

// Build the example JSON config (shown after creating an app password).
$oauthAuthorizeUrl = Helpers::siteUrl( 'oauth/authorize' );
$oauthTokenUrl     = Helpers::siteUrl( 'oauth/token' );
$oauthMetadataUrl  = Helpers::siteUrl( '.well-known/oauth-authorization-server' );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php if ( $success ): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars( $success ); ?></div>
<?php endif; ?>
<?php if ( $error ): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars( $error ); ?></div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- QUICK START GUIDE                                              -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="card" style="border-left:4px solid var(--admin-primary);">
    <h3 style="margin-bottom:0.5rem;">🚀 <?php echo __( 'mcp.quick_start' ); ?></h3>
    <p style="color:var(--admin-text-muted);margin-bottom:1rem;font-size:0.9rem;">
        <?php echo __( 'mcp.quick_start_desc' ); ?>
    </p>

    <div class="mcp-steps">
        <div class="mcp-step">
            <div class="mcp-step-number">1</div>
            <div>
                <strong><?php echo __( 'mcp.step1_title' ); ?></strong>
                <p><?php echo __( 'mcp.step1_desc' ); ?></p>
            </div>
        </div>
        <div class="mcp-step">
            <div class="mcp-step-number">2</div>
            <div>
                <strong><?php echo __( 'mcp.step2_title' ); ?></strong>
                <p><?php echo __( 'mcp.step2_desc' ); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- MCP ENDPOINT                                                   -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="card">
    <div class="card-header"><h3>MCP Endpoint</h3></div>
    <div class="token-display" style="font-size:0.95rem;"><?php echo htmlspecialchars( $mcpEndpoint ); ?></div>
    <p style="font-size:0.8rem;color:var(--admin-text-muted);margin-top:0.5rem;">
        <?php echo __( 'mcp.endpoint_desc' ); ?>
    </p>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- NEW APP PASSWORD RESULT                                        -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<?php if ( $newAppPass ): ?>
<?php
    // Build the full MCP URL with embedded credentials.
    // Format: https://user:pass@domain.com/path/mcp
    $parsedUrl    = parse_url( $mcpEndpoint );
    $mcpAuthUrl   = ( $parsedUrl['scheme'] ?? 'https' ) . '://'
                  . urlencode( $username ) . ':' . urlencode( $newAppPass )
                  . '@' . ( $parsedUrl['host'] ?? '' )
                  . ( isset( $parsedUrl['port'] ) ? ':' . $parsedUrl['port'] : '' )
                  . ( $parsedUrl['path'] ?? '' );

    $basicAuth  = base64_encode( $username . ':' . $newAppPass );
    $configJson = json_encode( [
        'mcpServers' => [
            'klytos' => [
                'url'     => $mcpEndpoint,
                'headers' => [
                    'Authorization' => 'Basic ' . $basicAuth,
                ],
            ],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
?>
<div class="alert alert-warning" style="font-size:0.9rem;">
    <strong>⚠️ <?php echo __( 'app_passwords.password_created' ); ?></strong>

    <!-- ① MCP URL with credentials (PRIMARY — copy and paste) -->
    <h4 style="margin-top:1.25rem;margin-bottom:0.5rem;">🔗 <?php echo __( 'mcp.url_title' ); ?></h4>
    <p style="font-size:0.82rem;color:var(--admin-text-muted);margin-bottom:0.5rem;">
        <?php echo __( 'mcp.url_desc' ); ?>
    </p>
    <div style="position:relative;">
        <div class="token-display" id="mcpAuthUrl"
             style="background:#fffbeb;font-size:0.85rem;word-break:break-all;padding-right:4.5rem;">
            <?php echo htmlspecialchars( $mcpAuthUrl ); ?>
        </div>
        <button class="btn btn-sm btn-primary" id="btnCopyUrl"
                style="position:absolute;top:0.5rem;right:0.5rem;">
            <?php echo __( 'common.copy' ); ?>
        </button>
    </div>

    <!-- Divider -->
    <div style="border-top:1px solid rgba(0,0,0,0.1);margin:1.25rem 0;"></div>

    <!-- ② JSON config (SECONDARY — for manual configuration) -->
    <details>
        <summary style="cursor:pointer;font-weight:600;font-size:0.85rem;">
            📋 <?php echo __( 'mcp.json_config_title' ); ?>
        </summary>
        <p style="font-size:0.82rem;color:var(--admin-text-muted);margin:0.5rem 0;">
            <?php echo __( 'mcp.json_config_desc' ); ?>
        </p>
        <div style="position:relative;">
            <pre class="config-block" id="mcpConfig"><?php echo htmlspecialchars( $configJson ); ?></pre>
            <button class="btn btn-sm" id="btnCopyConfig"
                    style="position:absolute;top:0.5rem;right:0.5rem;background:rgba(255,255,255,0.15);color:#e2e8f0;border:1px solid rgba(255,255,255,0.2);">
                <?php echo __( 'common.copy' ); ?>
            </button>
        </div>
    </details>

    <!-- Divider -->
    <div style="border-top:1px solid rgba(0,0,0,0.1);margin:1.25rem 0;"></div>

    <!-- ③ Raw credentials (TERTIARY — in case they need them separately) -->
    <details style="font-size:0.82rem;">
        <summary style="cursor:pointer;color:var(--admin-text-muted);"><?php echo __( 'mcp.show_raw_credentials' ); ?></summary>
        <div style="margin-top:0.75rem;">
            <div style="margin-bottom:0.5rem;">
                <span style="color:var(--admin-text-muted);"><?php echo __( 'auth.username' ); ?>:</span>
                <code><?php echo htmlspecialchars( $username ); ?></code>
            </div>
            <div>
                <span style="color:var(--admin-text-muted);"><?php echo __( 'auth.password' ); ?>:</span>
                <code style="background:#fffbeb;padding:0.2rem 0.4rem;border-radius:4px;"><?php echo htmlspecialchars( $newAppPass ); ?></code>
            </div>
        </div>
    </details>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- APPLICATION PASSWORDS (RECOMMENDED)                            -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="card">
    <div class="card-header">
        <h3><?php echo __( 'app_passwords.title' ); ?> <span class="badge-status badge-active" style="font-size:0.7rem;vertical-align:middle;"><?php echo __( 'mcp.recommended' ); ?></span></h3>
    </div>
    <p style="color:var(--admin-text-muted);font-size:0.85rem;margin-bottom:1rem;">
        <?php echo __( 'mcp.app_password_desc' ); ?>
    </p>

    <form method="post" style="display:flex;gap:0.5rem;align-items:flex-end;margin-bottom:1.5rem;">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="action" value="create_app_password">
        <div class="form-group" style="flex:1;margin-bottom:0;">
            <label><?php echo __( 'mcp.connection_name' ); ?></label>
            <input type="text" name="label" class="form-control"
                   placeholder="<?php echo __( 'app_passwords.label_placeholder' ); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary" style="white-space:nowrap;">
            <?php echo __( 'mcp.generate_password' ); ?>
        </button>
    </form>

    <?php if ( !empty( $appPasswords ) ): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><?php echo __( 'mcp.connection_name' ); ?></th>
                        <th><?php echo __( 'common.date' ); ?></th>
                        <th><?php echo __( 'app_passwords.last_used' ); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $appPasswords as $appPass ): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars( $appPass['label'] ?? '' ); ?></strong>
                        </td>
                        <td style="font-size:0.85rem;color:var(--admin-text-muted);">
                            <?php echo $appPass['created_at'] ? date( 'M j, Y', strtotime( $appPass['created_at'] ) ) : ''; ?>
                        </td>
                        <td style="font-size:0.85rem;color:var(--admin-text-muted);">
                            <?php echo $appPass['last_used'] ? date( 'M j, Y H:i', strtotime( $appPass['last_used'] ) ) : '—'; ?>
                        </td>
                        <td style="text-align:right;">
                            <form method="post" style="display:inline;" class="confirm-revoke-form">
                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="action" value="revoke_app_password">
                                <input type="hidden" name="password_id" value="<?php echo htmlspecialchars( $appPass['id'] ?? '' ); ?>">
                                <button type="submit" class="btn btn-danger btn-sm"><?php echo __( 'app_passwords.revoke' ); ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p style="color:var(--admin-text-muted);font-size:0.85rem;">
            <?php echo __( 'app_passwords.no_passwords' ); ?>
        </p>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- OAUTH 2.0/2.1 (ADVANCED — COLLAPSED BY DEFAULT)               -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="card">
    <div class="card-header" id="oauthToggleHeader" style="cursor:pointer;">
        <h3>OAuth 2.0 / 2.1 <span class="badge-status badge-draft" style="font-size:0.7rem;vertical-align:middle;"><?php echo __( 'mcp.advanced' ); ?></span></h3>
        <span id="oauthToggleIcon" style="font-size:1.2rem;color:var(--admin-text-muted);">▸</span>
    </div>

    <div id="oauthSection" style="display:none;">
        <p style="color:var(--admin-text-muted);font-size:0.85rem;margin-bottom:1rem;">
            <?php echo __( 'mcp.oauth_desc' ); ?>
        </p>

        <!-- OAuth Endpoints -->
        <div style="background:var(--admin-bg);border-radius:var(--admin-radius);padding:1rem;margin-bottom:1.5rem;">
            <h4 style="font-size:0.85rem;margin-bottom:0.5rem;"><?php echo __( 'oauth.endpoints' ); ?></h4>
            <table style="font-size:0.82rem;">
                <tr><td style="padding:0.2rem 1rem 0.2rem 0;color:var(--admin-text-muted);">Authorization</td><td><code><?php echo htmlspecialchars( $oauthAuthorizeUrl ); ?></code></td></tr>
                <tr><td style="padding:0.2rem 1rem 0.2rem 0;color:var(--admin-text-muted);">Token</td><td><code><?php echo htmlspecialchars( $oauthTokenUrl ); ?></code></td></tr>
                <tr><td style="padding:0.2rem 1rem 0.2rem 0;color:var(--admin-text-muted);">Metadata</td><td><code><?php echo htmlspecialchars( $oauthMetadataUrl ); ?></code></td></tr>
            </table>
        </div>

        <!-- New OAuth Client Result -->
        <?php if ( $newOAuthClient ): ?>
        <div class="alert alert-warning" style="font-size:0.9rem;">
            <strong>⚠️ <?php echo __( 'oauth.client_created' ); ?></strong>
            <div style="margin-top:0.75rem;">
                <div style="font-size:0.8rem;color:#64748b;">Client ID</div>
                <div class="token-display" style="margin-bottom:0.5rem;"><?php echo htmlspecialchars( $newOAuthClient['client_id'] ?? '' ); ?></div>
                <?php if ( isset( $newOAuthClient['client_secret'] ) ): ?>
                    <div style="font-size:0.8rem;color:#64748b;">Client Secret</div>
                    <div class="token-display" style="background:#fffbeb;"><?php echo htmlspecialchars( $newOAuthClient['client_secret'] ); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Create OAuth Client -->
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="create_oauth_client">

            <div class="grid-2">
                <div class="form-group">
                    <label><?php echo __( 'oauth.client_name' ); ?></label>
                    <input type="text" name="client_name" class="form-control" placeholder="e.g. My Web App" required>
                </div>
                <div class="form-group">
                    <label><?php echo __( 'oauth.redirect_uri' ); ?></label>
                    <input type="url" name="redirect_uri" class="form-control" placeholder="https://example.com/callback" required>
                    <div class="form-help"><?php echo __( 'oauth.redirect_uri_help' ); ?></div>
                </div>
            </div>

            <div class="form-group">
                <label><?php echo __( 'oauth.client_type' ); ?></label>
                <select name="client_type" class="form-control">
                    <option value="confidential"><?php echo __( 'oauth.confidential' ); ?> — <?php echo __( 'mcp.oauth_confidential_desc' ); ?></option>
                    <option value="public"><?php echo __( 'oauth.public' ); ?> — <?php echo __( 'mcp.oauth_public_desc' ); ?></option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary"><?php echo __( 'oauth.create_client' ); ?></button>
        </form>

        <!-- OAuth Client List -->
        <?php if ( !empty( $oauthClients ) ): ?>
        <div class="table-wrap" style="margin-top:1.5rem;">
            <table>
                <thead>
                    <tr>
                        <th><?php echo __( 'oauth.client_name' ); ?></th>
                        <th><?php echo __( 'oauth.client_type' ); ?></th>
                        <th>Redirect URI</th>
                        <th><?php echo __( 'common.date' ); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $oauthClients as $oaClient ): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars( $oaClient['name'] ?? '' ); ?></strong></td>
                        <td>
                            <span class="badge-status <?php echo ( $oaClient['is_confidential'] ?? true ) ? 'badge-active' : 'badge-draft'; ?>">
                                <?php echo ( $oaClient['is_confidential'] ?? true ) ? __( 'oauth.confidential' ) : __( 'oauth.public' ); ?>
                            </span>
                        </td>
                        <td style="font-size:0.8rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;">
                            <?php echo htmlspecialchars( $oaClient['redirect_uri'] ?? '' ); ?>
                        </td>
                        <td style="font-size:0.85rem;color:var(--admin-text-muted);">
                            <?php echo $oaClient['created_at'] ? date( 'M j, Y', strtotime( $oaClient['created_at'] ) ) : ''; ?>
                        </td>
                        <td style="text-align:right;">
                            <form method="post" style="display:inline;" class="confirm-revoke-form">
                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="action" value="revoke_oauth_client">
                                <input type="hidden" name="oauth_client_id" value="<?php echo htmlspecialchars( $oaClient['client_id'] ?? '' ); ?>">
                                <button type="submit" class="btn btn-danger btn-sm"><?php echo __( 'oauth.revoke_client' ); ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .mcp-steps {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .mcp-step {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }
    .mcp-step-number {
        width: 32px;
        height: 32px;
        background: var( --admin-primary );
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        flex-shrink: 0;
    }
    .mcp-step p {
        font-size: 0.85rem;
        color: var( --admin-text-muted );
        margin-top: 0.15rem;
    }
    .config-block {
        background: #0f172a;
        color: #e2e8f0;
        padding: 1rem;
        border-radius: 8px;
        margin-top: 0.5rem;
        overflow-x: auto;
        font-size: 0.82rem;
        line-height: 1.6;
    }
</style>

<script nonce="<?php echo $cspNonce; ?>">
(function() {
    // Toggle OAuth section.
    var header  = document.getElementById( 'oauthToggleHeader' );
    var section = document.getElementById( 'oauthSection' );
    var icon    = document.getElementById( 'oauthToggleIcon' );

    if ( header && section ) {
        header.addEventListener( 'click', function() {
            var isHidden = section.style.display === 'none';
            section.style.display = isHidden ? 'block' : 'none';
            icon.textContent      = isHidden ? '▾' : '▸';
        });
    }

    // Helper: copy text and flash button.
    function copyAndFlash( btn, text ) {
        navigator.clipboard.writeText( text.trim() ).then( function() {
            var original = btn.textContent;
            btn.textContent = '<?php echo __( 'common.copied' ); ?> ✓';
            setTimeout( function() { btn.textContent = original; }, 2000 );
        });
    }

    // Copy MCP URL.
    var btnCopyUrl = document.getElementById( 'btnCopyUrl' );
    var mcpAuthUrl = document.getElementById( 'mcpAuthUrl' );
    if ( btnCopyUrl && mcpAuthUrl ) {
        btnCopyUrl.addEventListener( 'click', function() {
            copyAndFlash( btnCopyUrl, mcpAuthUrl.textContent );
        });
    }

    // Copy JSON config.
    var btnCopyConfig = document.getElementById( 'btnCopyConfig' );
    var mcpConfig     = document.getElementById( 'mcpConfig' );
    if ( btnCopyConfig && mcpConfig ) {
        btnCopyConfig.addEventListener( 'click', function() {
            copyAndFlash( btnCopyConfig, mcpConfig.textContent );
        });
    }

    // Confirm before revoking anything.
    document.querySelectorAll( '.confirm-revoke-form' ).forEach( function( form ) {
        form.addEventListener( 'submit', function( e ) {
            if ( !confirm( '<?php echo __( 'mcp.confirm_revoke' ); ?>' ) ) {
                e.preventDefault();
            }
        });
    });
})();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
