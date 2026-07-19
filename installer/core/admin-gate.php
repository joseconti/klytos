<?php

/**
 * Klytos — Central admin authorization gate (Sprint 1, slice 4 / audit S-07).
 *
 * S-07 recorded that only ~23% of admin surfaces asked for a capability at all
 * (5 real gates in 42 pages, 9 in 24 API endpoints). The systemic reason is
 * mechanical, not sloppiness: plugin-registered dynamic routes DO get gated,
 * because they pass through Router::handleDynamicRoute() (core/router.php:438),
 * while the 66 static admin files never touch that router. Each one therefore
 * had to remember its own gate, and 51 of them did not.
 *
 * So the fix is not "add 51 calls" — that has the same failure mode, one file
 * later. It is to move the decision to the one place every admin surface
 * provably passes through (admin/bootstrap.php, required by all 66) and to
 * make the ABSENCE of an entry a refusal rather than a grant. A new admin file
 * is denied until someone deliberately maps it.
 *
 * The map is data, not code, precisely so it can be diffed, reviewed and
 * checked mechanically: scripts/keel-verify fails the build when a file under
 * admin/ has no entry here.
 *
 * @package Klytos
 * @since   0.31.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

/**
 * The capability required by every admin surface, keyed by path.
 *
 * Keys are relative to installer/admin/ — 'users.php', 'api/plugins.php'.
 * The path is used rather than the basename because admin/ and admin/api/
 * both contain ai-chat.php, logs.php, plugins.php, tasks.php, terminal.php
 * and translations.php; a basename key would silently collapse six pairs of
 * unrelated surfaces onto one capability.
 *
 * A value of NULL means "no capability required", and is the audited
 * exception list — every null carries the reason it is one. It does NOT mean
 * unauthenticated: bootstrap.php's auth guard runs first and independently.
 *
 * A file ABSENT from this map is denied. That is the point of the slice.
 *
 * @return array<string, string|null>
 */
function klytos_admin_gate_map(): array
{
    $map = [
        // ── Pre-authentication surfaces ───────────────────────────
        // Exempted from the auth guard in bootstrap.php, so they are
        // reached with no identity at all. Nothing to authorize.
        'login.php'          => null,
        'logout.php'         => null,
        'reset-password.php' => null,

        // ── Content ───────────────────────────────────────────────
        // pages.php and index.php are mapped at the tier needed to SEE
        // them and re-gate their privileged POST branches inline. A
        // single page-level capability cannot express "an editor may
        // list pages but not purge the trash" without either locking
        // editors out of the list or leaving the purge open.
        'index.php'            => 'pages.view',
        'pages.php'            => 'pages.view',
        'page-editor.php'      => 'pages.edit',
        'taxonomy.php'         => 'pages.edit',
        'post-types.php'       => 'site.configure',
        'post-type-edit.php'   => 'site.configure',

        // ── Design ────────────────────────────────────────────────
        'theme.php'            => 'theme.manage',
        'blocks.php'           => 'blocks.manage',
        'block-data.php'       => 'blocks.manage',
        'templates.php'        => 'templates.manage',
        'template-preview.php' => 'templates.manage',

        // ── Media ─────────────────────────────────────────────────
        'assets.php'           => 'assets.manage',
        'ai-images.php'        => 'assets.manage',

        // ── Operations ────────────────────────────────────────────
        'analytics.php'          => 'analytics.view',
        'tasks.php'              => 'tasks.create',
        'scheduled-actions.php'  => 'site.configure',
        'logs.php'               => 'site.configure',
        'system-options.php'     => 'site.configure',
        'system-integrity.php'   => 'site.configure',
        'translations.php'       => 'site.configure',
        'consent.php'            => 'site.configure',
        'license.php'            => 'site.configure',
        'settings.php'           => 'site.configure',

        // ── Privileged administration ─────────────────────────────
        'users.php'    => 'users.manage',
        'privacy.php'  => 'users.manage',
        'plugins.php'  => 'plugins.manage',
        'updates.php'  => 'updates.manage',
        'mcp.php'      => 'mcp.manage',
        'webhooks.php' => 'webhooks.manage',
        'terminal.php' => 'terminal.access',

        // ── x402 ──────────────────────────────────────────────────
        'x402-dashboard.php'    => 'analytics.view',
        'x402-transactions.php' => 'analytics.view',
        'x402-settings.php'     => 'site.configure',

        // ── AI ────────────────────────────────────────────────────
        // Owner+admin only while NEW-02 is open — the chat executes MCP
        // tools and the tool layer has no permission checks until
        // Sprint 2 (D-020), so this surface is owner-equivalent power.
        'ai-chat.php' => 'ai.use',

        // ── Self-service ──────────────────────────────────────────
        // Held by every role. See the matrix in UserManager::hasPermission().
        'profile.php'  => 'profile.edit',
        'security.php' => 'security.self',

        // ── First-run ─────────────────────────────────────────────
        // Owner-only. Before slice 4 the wizard required authentication
        // but checked no role, so on a fresh install ANY authenticated
        // user could complete setup and mint themselves an MCP
        // application password (NEW-10).
        'setup-wizard.php' => 'setup.run',

        // ── Plugin-page host ──────────────────────────────────────
        // NULL because the capability is not knowable here: it comes
        // from the plugin manifest of whichever page was requested.
        // plugin-page.php resolves it and enforces it itself — and,
        // since slice 4, DENIES when a plugin declares none, instead of
        // skipping the gate entirely.
        'plugin-page.php' => null,

        // ── API: content ──────────────────────────────────────────
        'api/autosave.php'    => 'pages.edit',
        'api/inline-edit.php' => 'pages.edit',
        'api/post-lock.php'   => 'pages.edit',
        'api/oembed.php'      => 'pages.edit',
        'api/tasks.php'       => 'tasks.create',

        // ── API: media ────────────────────────────────────────────
        'api/assets-management.php' => 'assets.manage',
        'api/image-edit.php'        => 'assets.manage',
        'api/media-upload.php'      => 'assets.manage',

        // ── API: operations ───────────────────────────────────────
        'api/logs.php'               => 'site.configure',
        'api/integrity.php'          => 'site.configure',
        'api/options-management.php' => 'site.configure',
        'api/translations.php'       => 'site.configure',
        'api/translations-ai.php'    => 'site.configure',

        // ── API: privileged ───────────────────────────────────────
        'api/plugins.php'        => 'plugins.manage',
        'api/update-install.php' => 'updates.manage',
        // Exports the site's RSA private key. Owner-only via the
        // matrix, replacing a hand-rolled username comparison against
        // config['admin_user'] — the ROLE is the authority on privilege,
        // not a string equality with a config value.
        'api/download-identity.php' => 'users.manage',

        // ── API: terminal ─────────────────────────────────────────
        'api/terminal.php'              => 'terminal.access',
        'api/terminal-autocomplete.php' => 'terminal.access',
        // Extends the terminal session clock, so it gates the terminal
        // as surely as the executor does. It previously checked only
        // that 2FA was enabled — not that the caller may use the
        // terminal at all.
        'api/terminal-revalidate.php'   => 'terminal.access',

        // ── API: AI ───────────────────────────────────────────────
        'api/ai-chat.php' => 'ai.use',

        // ── API: per-user UI state ────────────────────────────────
        'api/notices.php'      => 'ui.preferences',
        'api/sidebar-order.php' => 'ui.preferences',

        // ── API: self-guarding ────────────────────────────────────
        // NULL because this endpoint serves users who are mid-2FA:
        // authenticated is FALSE but is2faPending() is true, so no
        // capability can be resolved for them. It enforces its own
        // (isAuthenticated || is2faPending) check, and slice 4 added it
        // to the bootstrap auth-guard exemption so that check can
        // actually run — before, bootstrap 302'd it to HTML and passkey
        // second-factor login could not work at all (NEW-09).
        'api/webauthn-challenge.php' => null,

        // NULL because it is public by design: an anonymous visitor
        // submitting a comment on the generated site. It is NOT
        // reachable anonymously today — bootstrap's auth guard still
        // blocks it — which is audit finding S-09, owned by slice 7.
        // Mapping it here records the authorization answer; making the
        // path reachable is that slice's work.
        'api/comment-submit.php' => null,
    ];

    /**
     * Filter the admin gate map.
     *
     * Lets a plugin gate its own admin files, and lets a deployment tighten
     * a shipped surface. Adding entries is the intended use.
     *
     * Note the direction of risk honestly: this filter CAN weaken a gate, in
     * exactly the way the pre-existing auth.capabilities filter can. Both are
     * plugin-trust boundaries, and plugins already run as first-party code in
     * this product. What the filter cannot do is create a hole by omission —
     * removing an entry denies the surface, it does not open it.
     *
     * @param array<string, string|null> $map Path relative to admin/ => capability.
     */
    return klytos_apply_filters( 'admin.gate_map', $map );
}

/**
 * Resolve the running script to its key in the gate map.
 *
 * Derived from SCRIPT_FILENAME — the file PHP actually executed — rather than
 * SCRIPT_NAME, which is URL-derived and therefore caller-influenced. A gate
 * that can be pointed at a different map row by rewriting a URL is not a gate.
 *
 * @param  string|null $scriptFilename Override. Testing seam.
 * @return string|null The map key ('users.php', 'api/plugins.php'), or null
 *                     when the script does not resolve inside admin/.
 */
function klytos_admin_gate_key( ?string $scriptFilename = null ): ?string
{
    $script = $scriptFilename ?? ( $_SERVER['SCRIPT_FILENAME'] ?? '' );

    if ( $script === '' ) {
        return null;
    }

    // Relative to core/, admin/ is a sibling. Resolving it this way keeps the
    // gate working after the installer renames installer/ to <hex>-admin.
    $adminDir = realpath( dirname( __DIR__ ) . '/admin' );
    $real     = realpath( $script );

    if ( $adminDir === false || $real === false ) {
        return null;
    }

    if ( ! str_starts_with( $real, $adminDir . DIRECTORY_SEPARATOR ) ) {
        return null;
    }

    return str_replace( DIRECTORY_SEPARATOR, '/', substr( $real, strlen( $adminDir ) + 1 ) );
}

/**
 * Enforce the gate for the current admin request. Denies by default.
 *
 * Called once, from admin/bootstrap.php, after the auth guard has established
 * that somebody is logged in. Every one of the 66 admin files requires that
 * bootstrap, which is what makes a single call sufficient — verified, not
 * assumed: see the slice-4 evidence in docs/05-test-points.md.
 *
 * @param  string|null $scriptFilename Override. Testing seam.
 * @return void
 */
function klytos_enforce_admin_gate( ?string $scriptFilename = null ): void
{
    $key = klytos_admin_gate_key( $scriptFilename );

    // Unresolvable path. Denied rather than skipped: if the gate cannot tell
    // WHICH surface is running, it cannot know what that surface may do.
    if ( $key === null ) {
        klytos_log_warning(
            'Admin gate: request did not resolve to a file inside admin/ — denied.',
            [ 'script' => $scriptFilename ?? ( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ],
            'security'
        );
        klytos_deny( 403, __( 'common.no_permission' ), 'forbidden' );
    }

    $map = klytos_admin_gate_map();

    // DEFAULT DENY. A new admin file is refused until it is mapped. This is
    // the single line that turns S-07 from "51 files forgot" into "a file
    // cannot forget".
    if ( ! array_key_exists( $key, $map ) ) {
        klytos_log_warning(
            'Admin gate: no entry in the gate map for this surface — denied by default.',
            [ 'surface' => $key ],
            'security'
        );
        klytos_deny( 403, __( 'common.no_permission' ), 'forbidden' );
    }

    $capability = $map[ $key ];

    // An audited null: no capability required. The auth guard has already run.
    if ( $capability === null ) {
        return;
    }

    klytos_require_permission( $capability );
}
