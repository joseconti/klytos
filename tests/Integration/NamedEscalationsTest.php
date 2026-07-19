<?php

/**
 * Klytos CMS — one named test per audit escalation
 * (Sprint 1, slice 5 / S-01, S-02, S-03, S-05, S-06, S-12).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;

/**
 * Each finding proved individually, by its own name.
 *
 * Slice 4 closed the ACCESS half of all six at once, with a central
 * default-deny gate. That is why these tests exist rather than being redundant
 * with it: a systemic fix asserted only through representative surfaces proves
 * the MECHANISM, not that any particular reported escalation is actually shut.
 * When someone asks "is S-01 fixed?", the answer should be a test with S-01 in
 * its name that fails if it regresses — not an argument about coverage.
 *
 * Every test here asserts the POSITIVE case too — a role that SHOULD reach the
 * surface gets through. L-008 records why: the per-role HTTP tests once sent
 * the wrong session cookie name, so every request arrived anonymous and every
 * refusal assertion passed for entirely the wrong reason. A refusal test with
 * no allow-path counterpart cannot distinguish "correctly denied" from "never
 * arrived".
 */
final class NamedEscalationsTest extends AdminHttpTestCase
{
    protected static function serverPort(): int
    {
        return 8100;
    }

    /**
     * S-01 — a viewer cannot promote itself to owner. **CRITICAL**
     *
     * The audit's headline finding: users.php handled `create_user` and
     * `update_role` POSTs with zero permission checks, so a viewer could POST
     * `update_role` and make itself owner. CSRF was checked, which is why this
     * needed an authenticated session rather than an anonymous request — and
     * why the test carries a real token (see AdminHttpTestCase::post()).
     *
     * Asserting the 403 alone would be too weak. The property that matters is
     * that the ROLE DID NOT CHANGE, so the stored record is read back through
     * UserManager afterwards. A gate that returned 403 while the handler had
     * already run would satisfy a status-only assertion.
     *
     * @return void
     */
    public function testS01ViewerCannotPromoteItselfToOwner(): void
    {
        $viewer = $this->users->getByUsername( 'viewer' );

        self::assertSame( 'viewer', $viewer['role'], 'Fixture precondition.' );

        $response = $this->post(
            'installer/admin/users.php',
            [
                'action'  => 'update_role',
                'user_id' => $viewer['id'],
                'role'    => 'owner',
            ],
            'viewer'
        );

        self::assertSame(
            403,
            $response['status'],
            'A viewer must be refused user management — users.manage is owner-only.'
        );

        // The decisive assertion: the escalation did not happen.
        $after = $this->users->getByUsername( 'viewer' );

        self::assertSame(
            'viewer',
            $after['role'],
            'S-01 REGRESSION: the viewer changed its own role. This is full vertical '
            . 'privilege escalation — the account can now do anything.'
        );

        // Positive half: the owner genuinely reaches this surface.
        self::assertSame(
            200,
            $this->request( 'installer/admin/users.php', 'owner' )['status'],
            'The owner holds users.manage and must reach user management.'
        );
    }

    /**
     * S-02 — no non-owner reaches the plugin install endpoint. **CRITICAL**
     *
     * Installing a plugin ZIP is uploading PHP that Klytos then executes, so
     * this is remote code execution for anyone who passes the gate. Before
     * slice 4 only CSRF stood in front of it — every authenticated user,
     * viewer included, could install, activate, delete or uninstall.
     *
     * All three non-owner roles are asserted rather than just viewer: admin is
     * the interesting one, because `plugins.manage` is owner-only in the matrix
     * while an admin holds nearly everything else.
     *
     * @return void
     */
    public function testS02NonOwnersCannotReachThePluginInstallEndpoint(): void
    {
        foreach ( [ 'admin', 'editor', 'viewer' ] as $role ) {
            $response = $this->postJson(
                'installer/admin/api/plugins.php',
                [ 'action' => 'install' ],
                $role
            );

            self::assertSame(
                403,
                $response['status'],
                "S-02 REGRESSION: {$role} reached the plugin endpoint. Installing a plugin ZIP "
                . 'is arbitrary PHP execution; plugins.manage is owner-only.'
            );
            self::assertStringContainsString(
                'application/json',
                $response['content_type'],
                'This endpoint is called by XHR — its refusal must be parseable.'
            );
        }

        // Positive half: the owner is not blocked by the gate. The endpoint may
        // still reject the request on its own terms (no uploaded file), which
        // is a different and correct answer — what must not happen is 403.
        self::assertNotSame(
            403,
            $this->postJson( 'installer/admin/api/plugins.php', [ 'action' => 'list' ], 'owner' )['status'],
            'The owner holds plugins.manage and must pass the gate.'
        );
    }

    /**
     * S-03 — no non-owner can trigger a core update install. **HIGH**
     *
     * The update path downloads and unpacks code over the running
     * installation, so an ungated trigger is a supply-chain problem, not just
     * an availability one. CSRF only, no `updates.manage`, before slice 4.
     *
     * @return void
     */
    public function testS03NonOwnersCannotTriggerACoreUpdateInstall(): void
    {
        foreach ( [ 'admin', 'editor', 'viewer' ] as $role ) {
            self::assertSame(
                403,
                $this->postJson( 'installer/admin/api/update-install.php', [], $role )['status'],
                "S-03 REGRESSION: {$role} could trigger a core update install. updates.manage "
                . 'is owner-only.'
            );
        }

        self::assertNotSame(
            403,
            $this->postJson( 'installer/admin/api/update-install.php', [], 'owner' )['status'],
            'The owner holds updates.manage and must pass the gate.'
        );
    }

    /**
     * S-05 — a viewer cannot upload files. **HIGH**
     *
     * `assets.manage` is owner/admin/editor, so the boundary here is between
     * editor and viewer rather than around the owner. That makes the positive
     * half unusually load-bearing: a gate that refused everyone would satisfy
     * the refusal assertion while breaking the feature for the three roles that
     * are supposed to have it.
     *
     * @return void
     */
    public function testS05ViewerCannotUploadMediaButEditorCan(): void
    {
        $response = $this->post( 'installer/admin/api/media-upload.php', [], 'viewer' );

        self::assertSame(
            403,
            $response['status'],
            'S-05 REGRESSION: a viewer reached the upload endpoint. assets.manage excludes viewer.'
        );

        // Positive half, and the reason it matters: editor MUST keep upload.
        // A 400 ("no file uploaded") is the correct answer for a request that
        // passed the gate and carried no file — what must not appear is 403.
        foreach ( [ 'owner', 'admin', 'editor' ] as $role ) {
            self::assertNotSame(
                403,
                $this->post( 'installer/admin/api/media-upload.php', [], $role )['status'],
                "{$role} holds assets.manage and must not be refused the upload endpoint."
            );
        }
    }

    /**
     * S-06 — the ungated write endpoints now require their capability. **MEDIUM**
     *
     * Six endpoints were named: autosave, notices, sidebar-order, tasks, plus
     * inline-edit and terminal-revalidate added by the kickoff re-validation.
     *
     * Two of the six are deliberately NOT refused, and that is recorded here
     * rather than left looking like a gap: notices.php and sidebar-order.php
     * carry per-user interface state (dismissed notices, sidebar order), mapped
     * to `ui.preferences`, which every role holds. Gating them at a content
     * tier would mean a viewer could not dismiss its own notice. They are
     * asserted as reachable so that a later "tightening" that breaks them
     * fails here instead of in a user's hands.
     *
     * @return void
     */
    public function testS06UngatedWriteEndpointsRequireTheirCapability(): void
    {
        // Endpoints a viewer must NOT reach, with the capability that stops it.
        $refused = [
            'installer/admin/api/autosave.php'            => 'pages.edit',
            'installer/admin/api/inline-edit.php'         => 'pages.edit',
            'installer/admin/api/post-lock.php'           => 'pages.edit',
            'installer/admin/api/tasks.php'               => 'tasks.create',
            'installer/admin/api/terminal-revalidate.php' => 'terminal.access',
        ];

        foreach ( $refused as $path => $capability ) {
            self::assertSame(
                403,
                $this->postJson( $path, [ 'action' => 'list' ], 'viewer' )['status'],
                "S-06 REGRESSION: a viewer reached {$path}, which requires {$capability}."
            );
        }

        // Per-user UI state — every role, by design. Not a gap.
        //
        // These two are driven differently on purpose. notices.php serves a
        // plain GET, but sidebar-order.php calls klytos_verify_csrf()
        // unconditionally (sidebar-order.php:39), before it does anything else
        // and regardless of method — so a bare GET is refused 403 for a MISSING
        // TOKEN, which is indistinguishable from a gate refusal by status
        // alone. Sending a valid token is what makes a surviving 403 mean the
        // gate and only the gate. The first version of this test read that
        // CSRF refusal as an authorization failure and reported a defect that
        // did not exist.
        self::assertNotSame(
            403,
            $this->request( 'installer/admin/api/notices.php', 'viewer' )['status'],
            'notices.php holds per-user interface state and is mapped to ui.preferences, which '
            . 'every role holds. Refusing it would stop a viewer dismissing its own notice.'
        );

        self::assertNotSame(
            403,
            $this->postJson( 'installer/admin/api/sidebar-order.php', [ 'order' => [] ], 'viewer' )['status'],
            'sidebar-order.php holds per-user interface state and is mapped to ui.preferences. '
            . 'With a valid CSRF token carried, a 403 here could only come from the gate.'
        );
    }

    /**
     * S-06 — the task API re-gates `tasks.manage` exactly as its page does.
     *
     * This is the residue slice 4's page-level map could not reach, and it was
     * found by reading the two surfaces side by side rather than by re-reading
     * the audit. `admin/tasks.php` is mapped `tasks.create` and calls
     * klytos_require_permission( 'tasks.manage' ) before complete/dismiss/delete
     * (tasks.php:38). `admin/api/tasks.php` is mapped `tasks.create` and did
     * NOT re-gate anything — so an editor was refused task completion through
     * the UI and allowed it through the API twin.
     *
     * An asymmetry between a page and its API is worth more than the sum of its
     * parts as a finding: it means the capability model was expressed in one
     * surface and not in the other, which is the same defect S-07 recorded at
     * scale.
     *
     * @return void
     */
    public function testS06TaskApiRegatesManageActionsLikeItsPage(): void
    {
        foreach ( [ 'complete', 'update' ] as $action ) {
            $response = $this->postJson(
                'installer/admin/api/tasks.php',
                [ 'action' => $action, 'task_id' => 'zz-nonexistent' ],
                'editor'
            );

            self::assertSame(
                403,
                $response['status'],
                "S-06 REGRESSION: an editor performed '{$action}' through the task API. The PAGE "
                . 'refuses this at tasks.manage (admin/tasks.php:38); the API must agree, or the '
                . 'capability model is enforced in one surface and not its twin.'
            );
        }

        // Positive half, both directions — this is what stops the fix being
        // "deny everything at the API".
        self::assertNotSame(
            403,
            $this->postJson(
                'installer/admin/api/tasks.php',
                [ 'action' => 'create', 'description' => 'slice-5 probe', 'page_slug' => 'home' ],
                'editor'
            )['status'],
            'An editor holds tasks.create and must still be able to create a task.'
        );

        self::assertNotSame(
            403,
            $this->postJson(
                'installer/admin/api/tasks.php',
                [ 'action' => 'complete', 'task_id' => 'zz-nonexistent' ],
                'admin'
            )['status'],
            'An admin holds tasks.manage and must still be able to complete a task.'
        );
    }

    /**
     * S-12 — the identity export refuses a state-changing GET. **MEDIUM**
     *
     * This endpoint exports the site's RSA private key — the highest-value
     * secret in the system — and it wrote config on the way
     * (`identity_last_downloaded_at`, `identity_download_count`) in response to
     * a GET. An attacker could force the owner's browser to issue it with an
     * `<img src>`, burning the 24-hour rate limit and writing config. They
     * could not read the key back, because the response is an octet-stream
     * attachment; the exposure was denial-of-service and audit noise, not
     * exfiltration. It still breaks the project's own "don't change state on a
     * GET" rule, which is why it is fixed rather than argued down.
     *
     * @return void
     */
    public function testS12IdentityExportRefusesAStateChangingGet(): void
    {
        $response = $this->request( 'installer/admin/api/download-identity.php', 'owner' );

        self::assertSame(
            405,
            $response['status'],
            'S-12 REGRESSION: the identity export answered a GET. It writes config, so it must '
            . 'require POST.'
        );
        self::assertStringContainsString(
            'POST',
            $response['body'] . ' ' . $response['content_type'],
            'A 405 should say which method is expected.'
        );
    }

    /**
     * S-12 — the identity export requires a CSRF token.
     *
     * The confirmed half of the original finding: there was no
     * klytos_verify_csrf() anywhere in the file.
     *
     * @return void
     */
    public function testS12IdentityExportRequiresCsrf(): void
    {
        // postJson()/post() always attach a valid token, so the missing-token
        // case is built by hand rather than through the helpers.
        $url    = sprintf(
            'http://%s:%d/installer/admin/api/download-identity.php',
            self::HOST,
            static::serverPort()
        );
        $handle = curl_init( $url );

        curl_setopt_array( $handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query( [ 'nothing' => 'here' ] ),
            CURLOPT_COOKIE         => 'klytos_session=' . $this->sessionFor( 'owner' ),
        ] );

        $raw    = curl_exec( $handle );
        $status = (int) curl_getinfo( $handle, CURLINFO_HTTP_CODE );

        curl_close( $handle );

        self::assertNotFalse( $raw, 'The request failed to execute.' );
        self::assertSame(
            403,
            $status,
            'S-12 REGRESSION: the identity export accepted a POST with no CSRF token.'
        );
    }

    /**
     * S-12 — the export is owner-only and does not fatal.
     *
     * Carried over from slice 4, where it was the regression cover for NEW-12's
     * three stacked defects: Auth::isLoggedIn() (no such method), then
     * Logger::log() (no such method either, reached for the first time once the
     * first was fixed), and a hand-rolled owner check outside the matrix.
     *
     * The crash is asserted on the BODY, not the status, and that is the whole
     * point (L-009). This fatal was verified to return HTTP **200** with the
     * error rendered into the response, because output had already begun by the
     * time it threw. A status-only assertion could never fail against the
     * broken code — which is what made the first version of this test
     * decoration rather than evidence.
     *
     * @return void
     */
    public function testS12IdentityExportIsOwnerOnlyAndDoesNotFatal(): void
    {
        $path = 'installer/admin/api/download-identity.php';

        foreach ( [ 'admin', 'editor', 'viewer' ] as $role ) {
            self::assertSame(
                403,
                $this->request( $path, $role )['status'],
                "{$role} must not be able to export the site's private key — users.manage is "
                . 'owner-only.'
            );
        }

        // A GET as owner now yields 405 rather than the export, so the fatal
        // check rides on the response the method guard produces. That is still
        // the right place for it: the guard runs AFTER bootstrap and inside the
        // file, so reaching it proves the file was parsed and executed.
        $response = $this->request( $path, 'owner' );

        self::assertNotSame(
            403,
            $response['status'],
            'The owner holds users.manage and must pass the gate.'
        );

        foreach ( [ 'Uncaught Error', 'Call to undefined method', 'Fatal error' ] as $signature ) {
            self::assertStringNotContainsString(
                $signature,
                $response['body'],
                'The endpoint raised a PHP error instead of responding. Auth::isLoggedIn() does '
                . 'not exist — the methods are isAuthenticated() and is2faPending().'
            );
        }
    }
}
