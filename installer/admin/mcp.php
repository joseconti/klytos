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
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
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
    } elseif ( $action === 'save_ai_key' ) {
        $aiProviderId   = $_POST['ai_provider'] ?? '';
        $aiApiKey       = $_POST['ai_api_key'] ?? '';
        $aiDefaultModel = $_POST['ai_default_model'] ?? '';
        if ( !empty( $aiProviderId ) && !empty( $aiApiKey ) ) {
            $aiKeys = new \Klytos\Core\Ai\AiKeyManager( $app->getStorage(), $app->getConfigPath() );
            if ( isset( \Klytos\Core\Ai\AiKeyManager::PROVIDERS[$aiProviderId] ) ) {
                $aiKeys->setKey( $aiProviderId, $aiApiKey, $aiDefaultModel ?: $aiKeys->getDefaultModelForProvider( $aiProviderId ) );
                $success = __( 'ai_keys.saved' );
            } else {
                $error = __( 'ai_keys.invalid' );
            }
        }
    } elseif ( $action === 'remove_ai_key' ) {
        $aiProviderId = $_POST['ai_provider'] ?? '';
        $aiKeys       = new \Klytos\Core\Ai\AiKeyManager( $app->getStorage(), $app->getConfigPath() );
        $aiKeys->removeKey( $aiProviderId );
        $success = __( 'ai_keys.removed' );
    } elseif ( $action === 'set_active_ai' ) {
        $aiProviderId = $_POST['ai_provider'] ?? '';
        $aiModelId    = $_POST['ai_model'] ?? '';
        $aiKeys       = new \Klytos\Core\Ai\AiKeyManager( $app->getStorage(), $app->getConfigPath() );
        $aiKeys->setActive( $aiProviderId, $aiModelId );
        $success = __( 'common.success' );
    }
}

$currentTab = $_GET['tab'] ?? 'mcp';

$appPasswords = $auth->listAppPasswords();
$oauthClients = $oauthServer->listClients();
$mcpEndpoint  = Helpers::siteUrl( 'mcp' );
$username     = $auth->getUsername();

// Build the example JSON config (shown after creating an app password).
$oauthAuthorizeUrl = Helpers::siteUrl( 'oauth/authorize' );
$oauthTokenUrl     = Helpers::siteUrl( 'oauth/token' );
$oauthMetadataUrl  = Helpers::siteUrl( '.well-known/oauth-authorization-server' );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.mcp.before' ); ?>

<?php if ( $success ): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>
<?php if ( $error ): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<!-- Tabs -->
<div class="tabs">
    <a class="tab <?php echo $currentTab === 'mcp' ? 'active' : ''; ?>"
       href="<?php echo klytos_esc_url( $adminPath . 'mcp.php?tab=mcp' ); ?>">MCP</a>
    <a class="tab <?php echo $currentTab === 'api-ia' ? 'active' : ''; ?>"
       href="<?php echo klytos_esc_url( $adminPath . 'mcp.php?tab=api-ia' ); ?>"><?php echo klytos_esc_html( __( 'ai_keys.title' ) ); ?></a>
</div>

<?php if ( $currentTab === 'mcp' ): ?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- QUICK START GUIDE                                              -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="card" style="border-left:4px solid var(--admin-primary);">
    <h3 class="mb-1">🚀 <?php echo __( 'mcp.quick_start' ); ?></h3>
    <p class="text-muted text-sm mb-2">
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
    <div class="token-display text-sm"><?php echo klytos_esc_html( $mcpEndpoint ); ?></div>
    <p class="text-xs text-muted mt-1">
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
<div class="alert alert-warning text-sm">
    <strong>⚠️ <?php echo __( 'app_passwords.password_created' ); ?></strong>

    <!-- ① MCP URL with credentials (PRIMARY — copy and paste) -->
    <h4 class="mt-2 mb-1">🔗 <?php echo __( 'mcp.url_title' ); ?></h4>
    <p class="text-xs text-muted mb-1">
        <?php echo __( 'mcp.url_desc' ); ?>
    </p>
    <div style="position:relative;">
        <div class="token-display text-sm break-all" id="mcpAuthUrl"
             style="background:var(--klytos-warning-subtle);padding-right:4.5rem;">
            <?php echo klytos_esc_html( $mcpAuthUrl ); ?>
        </div>
        <button class="btn btn-sm btn-primary" id="btnCopyUrl"
                style="position:absolute;top:0.5rem;right:0.5rem;">
            <?php echo __( 'common.copy' ); ?>
        </button>
    </div>

    <!-- Divider -->
    <div class="border-t mt-2 mb-2"></div>

    <!-- ② JSON config (SECONDARY — for manual configuration) -->
    <details>
        <summary class="font-bold text-sm">
            📋 <?php echo __( 'mcp.json_config_title' ); ?>
        </summary>
        <p class="text-xs text-muted mt-1 mb-1">
            <?php echo __( 'mcp.json_config_desc' ); ?>
        </p>
        <div style="position:relative;">
            <pre class="config-block" id="mcpConfig"><?php echo klytos_esc_html( $configJson ); ?></pre>
            <button class="btn btn-sm" id="btnCopyConfig"
                    style="position:absolute;top:0.5rem;right:0.5rem;background:rgba(255,255,255,0.15);color:#e2e8f0;border:1px solid rgba(255,255,255,0.2);">
                <?php echo __( 'common.copy' ); ?>
            </button>
        </div>
    </details>

    <!-- Divider -->
    <div class="border-t mt-2 mb-2"></div>

    <!-- ③ Raw credentials (TERTIARY — in case they need them separately) -->
    <details class="text-xs">
        <summary class="text-muted"><?php echo __( 'mcp.show_raw_credentials' ); ?></summary>
        <div class="mt-1">
            <div class="mb-1">
                <span class="text-muted"><?php echo __( 'auth.username' ); ?>:</span>
                <code><?php echo klytos_esc_html( $username ); ?></code>
            </div>
            <div>
                <span class="text-muted"><?php echo __( 'auth.password' ); ?>:</span>
                <code style="background:var(--klytos-warning-subtle);padding:0.2rem 0.4rem;border-radius:4px;"><?php echo klytos_esc_html( $newAppPass ); ?></code>
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
    <p class="text-muted text-sm mb-2">
        <?php echo __( 'mcp.app_password_desc' ); ?>
    </p>

    <form method="post" class="flex flex-gap-sm mb-3" style="align-items:flex-end;">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="action" value="create_app_password">
        <div class="form-group flex-1 mb-0">
            <label><?php echo __( 'mcp.connection_name' ); ?></label>
            <input type="text" name="label" class="form-control"
                   placeholder="<?php echo __( 'app_passwords.label_placeholder' ); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">
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
                            <strong><?php echo klytos_esc_html( $appPass['label'] ?? '' ); ?></strong>
                        </td>
                        <td class="text-sm text-muted">
                            <?php echo $appPass['created_at'] ? date( 'M j, Y', strtotime( $appPass['created_at'] ) ) : ''; ?>
                        </td>
                        <td class="text-sm text-muted">
                            <?php echo $appPass['last_used'] ? date( 'M j, Y H:i', strtotime( $appPass['last_used'] ) ) : '—'; ?>
                        </td>
                        <td class="text-right">
                            <form method="post" class="inline-form confirm-revoke-form">
                                <?php echo klytos_csrf_field(); ?>
                                <input type="hidden" name="action" value="revoke_app_password">
                                <input type="hidden" name="password_id" value="<?php echo klytos_esc_attr( $appPass['id'] ?? '' ); ?>">
                                <button type="submit" class="btn btn-danger btn-sm"><?php echo __( 'app_passwords.revoke' ); ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted text-sm">
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
        <span id="oauthToggleIcon" class="text-muted" style="font-size:1.2rem;">▸</span>
    </div>

    <div id="oauthSection" class="hidden">
        <p class="text-muted text-sm mb-2">
            <?php echo __( 'mcp.oauth_desc' ); ?>
        </p>

        <!-- OAuth Endpoints -->
        <div class="p-2 mb-3 rounded" style="background:var(--admin-bg);">
            <h4 class="text-sm mb-1"><?php echo __( 'oauth.endpoints' ); ?></h4>
            <table class="text-xs">
                <tr><td style="padding:0.2rem 1rem 0.2rem 0;color:var(--admin-text-muted);">Authorization</td><td><code><?php echo klytos_esc_html( $oauthAuthorizeUrl ); ?></code></td></tr>
                <tr><td style="padding:0.2rem 1rem 0.2rem 0;color:var(--admin-text-muted);">Token</td><td><code><?php echo klytos_esc_html( $oauthTokenUrl ); ?></code></td></tr>
                <tr><td style="padding:0.2rem 1rem 0.2rem 0;color:var(--admin-text-muted);">Metadata</td><td><code><?php echo klytos_esc_html( $oauthMetadataUrl ); ?></code></td></tr>
            </table>
        </div>

        <!-- New OAuth Client Result -->
        <?php if ( $newOAuthClient ): ?>
        <div class="alert alert-warning text-sm">
            <strong>⚠️ <?php echo __( 'oauth.client_created' ); ?></strong>
            <div class="mt-1">
                <div class="text-xs text-muted">Client ID</div>
                <div class="token-display mb-1"><?php echo klytos_esc_html( $newOAuthClient['client_id'] ?? '' ); ?></div>
                <?php if ( isset( $newOAuthClient['client_secret'] ) ): ?>
                    <div class="text-xs text-muted">Client Secret</div>
                    <div class="token-display" style="background:var(--klytos-warning-subtle);"><?php echo klytos_esc_html( $newOAuthClient['client_secret'] ); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Create OAuth Client -->
        <form method="post">
            <?php echo klytos_csrf_field(); ?>
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
        <div class="table-wrap mt-3">
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
                        <td><strong><?php echo klytos_esc_html( $oaClient['name'] ?? '' ); ?></strong></td>
                        <td>
                            <span class="badge-status <?php echo ( $oaClient['is_confidential'] ?? true ) ? 'badge-active' : 'badge-draft'; ?>">
                                <?php echo ( $oaClient['is_confidential'] ?? true ) ? __( 'oauth.confidential' ) : __( 'oauth.public' ); ?>
                            </span>
                        </td>
                        <td class="text-xs" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;">
                            <?php echo klytos_esc_url( $oaClient['redirect_uri'] ?? '' ); ?>
                        </td>
                        <td class="text-sm text-muted">
                            <?php echo $oaClient['created_at'] ? date( 'M j, Y', strtotime( $oaClient['created_at'] ) ) : ''; ?>
                        </td>
                        <td class="text-right">
                            <form method="post" class="inline-form confirm-revoke-form">
                                <?php echo klytos_csrf_field(); ?>
                                <input type="hidden" name="action" value="revoke_oauth_client">
                                <input type="hidden" name="oauth_client_id" value="<?php echo klytos_esc_attr( $oaClient['client_id'] ?? '' ); ?>">
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

<?php endif; // end tab=mcp ?>

<?php if ( $currentTab === 'api-ia' ):
    $aiActive       = ['provider' => null, 'model' => null];
    $allAiProviders = [];
    $aiUsage        = ['input_tokens' => 0, 'output_tokens' => 0, 'conversations' => 0, 'tool_executions' => 0];

    try {
        $aiKeys         = new \Klytos\Core\Ai\AiKeyManager( $app->getStorage(), $app->getConfigPath() );
        $aiActive       = $aiKeys->getActive();
        $allAiProviders = $aiKeys->listProviders();
        $currentUser    = klytos_current_user();
        $aiUsage        = ( new \Klytos\Core\Ai\ChatManager( $app->getStorage() ) )
                            ->getChatUsage( (int) ( $currentUser['id'] ?? 0 ), 'month' );
    } catch ( \Throwable $e ) {
        $error = 'AI module error: ' . $e->getMessage();
    }
    ?>

<!-- Info notice -->
<div class="alert alert-warning">
    <?php echo klytos_esc_html( __( 'ai_keys.cost_notice' ) ); ?><br>
    <?php echo klytos_esc_html( __( 'ai_keys.mcp_info' ) ); ?>
</div>

<!-- Providers list -->
    <?php
    $providerLogos = [
        'anthropic'  => [ 'color' => 'claude-color.webp' ],
        'openai'     => [ 'light' => 'openai-black.webp', 'dark' => 'openai-white.webp' ],
        'gemini'     => [ 'color' => 'gemini-color.webp' ],
        'openrouter' => [ 'light' => 'openrouter-black.webp', 'dark' => 'openrouter-white.webp' ],
    ];
    $providerKeyUrls = [
        'anthropic'  => 'https://console.anthropic.com',
        'openai'     => 'https://platform.openai.com',
        'gemini'     => 'https://aistudio.google.com',
        'openrouter' => 'https://openrouter.ai',
    ];
    $imgBase = \Klytos\Core\Helpers::getBasePath() . 'admin/assets/images/';
    ?>
    <?php foreach ( $allAiProviders as $p ):
        $hasKey     = $aiKeys->hasKey( $p['id'] );
        $maskedKey  = $aiKeys->getMasked( $p['id'] );
        $isActive   = ( $aiActive['provider'] ?? '' ) === $p['id'];
        $logo       = $providerLogos[ $p['id'] ] ?? null;
        ?>
<div class="card" style="<?php echo $isActive ? 'border-left: 4px solid var(--admin-success);' : ''; ?>">
    <div class="card-header">
        <h3 class="flex flex-center flex-gap-sm">
            <?php if ( $logo ) : ?>
                <?php if ( isset( $logo['color'] ) ) : ?>
                    <img src="<?php echo klytos_esc_url( $imgBase . $logo['color'] ); ?>" alt="<?php echo klytos_esc_attr( $p['name'] ); ?>" class="ai-provider-logo" style="height: 24px; width: auto;">
                <?php else : ?>
                    <img src="<?php echo klytos_esc_url( $imgBase . $logo['light'] ); ?>" alt="<?php echo klytos_esc_attr( $p['name'] ); ?>" class="ai-provider-logo ai-logo-light" style="height: 24px; width: auto;">
                    <img src="<?php echo klytos_esc_url( $imgBase . $logo['dark'] ); ?>" alt="<?php echo klytos_esc_attr( $p['name'] ); ?>" class="ai-provider-logo ai-logo-dark" style="height: 24px; width: auto;">
                <?php endif; ?>
            <?php endif; ?>
            <?php echo klytos_esc_html( $p['name'] ); ?>
            <?php if ( $isActive ): ?>
                <span class="badge-status badge-active ml-auto">Active</span>
            <?php endif; ?>
        </h3>
    </div>

        <?php if ( $hasKey ): ?>
        <p class="mb-1">
            <strong>API Key:</strong> <code><?php echo klytos_esc_html( $maskedKey ); ?></code>
        </p>

        <!-- Set as active -->
            <?php if ( !$isActive ): ?>
        <form method="POST" class="inline-form">
                <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="action" value="set_active_ai">
            <input type="hidden" name="ai_provider" value="<?php echo klytos_esc_attr( $p['id'] ); ?>">
            <input type="hidden" name="ai_model" value="<?php echo klytos_esc_attr( $p['default_model'] ); ?>">
            <button type="submit" class="btn btn-sm btn-outline"><?php echo klytos_esc_html( __( 'common.activate' ) ); ?></button>
        </form>
            <?php endif; ?>

        <!-- Remove key -->
        <form method="POST" class="inline-form confirm-revoke-form">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="action" value="remove_ai_key">
            <input type="hidden" name="ai_provider" value="<?php echo klytos_esc_attr( $p['id'] ); ?>">
            <button type="submit" class="btn btn-sm btn-danger"><?php echo klytos_esc_html( __( 'ai_keys.remove' ) ); ?></button>
        </form>

        <?php else: ?>
        <!-- Configure key form -->
            <?php if ( isset( $providerKeyUrls[ $p['id'] ] ) ) : ?>
            <p class="mb-1 text-sm">
                Get your API key at <a href="<?php echo klytos_esc_url( $providerKeyUrls[ $p['id'] ] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo klytos_esc_html( str_replace( 'https://', '', $providerKeyUrls[ $p['id'] ] ) ); ?> ↗</a>
            </p>
            <?php endif; ?>
        <form method="POST">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="action" value="save_ai_key">
            <input type="hidden" name="ai_provider" value="<?php echo klytos_esc_attr( $p['id'] ); ?>">
            <div class="form-group">
                <label>API Key</label>
                <input type="password" name="ai_api_key" class="form-control" required placeholder="sk-...">
            </div>
            <div class="form-group">
                <label><?php echo klytos_esc_html( __( 'ai_keys.active_model' ) ); ?></label>
                <select name="ai_default_model" class="form-control">
                    <?php foreach ( $p['models'] as $model ): ?>
                        <option value="<?php echo klytos_esc_attr( $model['id'] ); ?>">
                            <?php echo klytos_esc_html( $model['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><?php echo klytos_esc_html( __( 'ai_keys.save' ) ); ?></button>
        </form>
        <?php endif; ?>
</div>
    <?php endforeach; ?>

<!-- Usage stats -->
<div class="card">
    <div class="card-header"><h3><?php echo klytos_esc_html( __( 'ai_keys.usage_this_month' ) ); ?></h3></div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label"><?php echo klytos_esc_html( __( 'ai_keys.tokens_in' ) ); ?></div>
            <div class="stat-value"><?php echo number_format( $aiUsage['input_tokens'] ); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label"><?php echo klytos_esc_html( __( 'ai_keys.tokens_out' ) ); ?></div>
            <div class="stat-value"><?php echo number_format( $aiUsage['output_tokens'] ); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label"><?php echo klytos_esc_html( __( 'ai_keys.conversations' ) ); ?></div>
            <div class="stat-value"><?php echo number_format( $aiUsage['conversations'] ); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label"><?php echo klytos_esc_html( __( 'ai_keys.tools_executed' ) ); ?></div>
            <div class="stat-value"><?php echo number_format( $aiUsage['tool_executions'] ); ?></div>
        </div>
    </div>
</div>

<!-- Privacy notice -->
<div class="alert alert-info">
    <?php echo klytos_esc_html( __( 'ai_keys.privacy_notice' ) ); ?>
</div>

<?php endif; // end tab=api-ia ?>

<?php klytos_do_action( 'admin.mcp.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
