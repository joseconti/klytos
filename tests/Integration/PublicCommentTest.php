<?php

/**
 * Klytos CMS — the public comment endpoint (Sprint 1, slice 7 — audit S-09).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;

/**
 * S-09: public comment submission works, off the admin path.
 *
 * WHY HTTP: every property under test is a property of a real anonymous
 * request. The endpoint's whole defect was that it could not be reached
 * without a session, and the fix moves it out of the admin tree entirely —
 * neither is observable in-process.
 *
 * WHY THE REQUESTS CARRY NO COOKIE: that is the point. A form on the generated
 * static site cannot send the admin session cookie, because
 * Auth::startSession() scopes it to `path=<base>/admin/` with
 * `SameSite=Strict` (core/auth.php:52-62). The rate limit therefore cannot be
 * per-session and be a rate limit at all — which is what
 * testRateLimitHoldsAcrossSessions pins.
 */
final class PublicCommentTest extends AdminHttpTestCase
{
    /** Ports 8099-8102 are taken by the other HTTP test classes. */
    protected static function serverPort(): int
    {
        return 8103;
    }

    /** The endpoint as a generated page would address it: no admin segment. */
    private const ENDPOINT = '/comment-submit.php';

    protected function setUp(): void
    {
        parent::setUp();

        // Comments ship disabled, so every test here would otherwise assert
        // against the 403 branch. The write lands in installer/data/config/,
        // which PlaygroundState snapshots and restores around each test.
        $this->app->getSiteConfig()->setValue( 'comments_enabled', true );
        $this->app->getSiteConfig()->setValue( 'comments_honeypot', true );
    }

    /**
     * How many comments the playground currently holds.
     *
     * Asserted against rather than trusting the response, so a test cannot
     * pass on a success message that stored nothing.
     */
    private function commentCount(): int
    {
        return count( $this->storage->list( 'comments' ) );
    }

    /**
     * A valid submission body.
     *
     * @param  array<string,string> $overrides
     * @return array<string,string>
     */
    private function fields( array $overrides = [] ): array
    {
        return array_merge( [
            'page_slug'    => 'about',
            'author_name'  => 'Anonymous Visitor',
            'author_email' => 'visitor@example.test',
            'content'      => 'A comment from someone with no account.',
        ], $overrides );
    }

    /**
     * Assert a response body carries no PHP error.
     *
     * L-009: this endpoint answers 200/201 with the error rendered INTO the
     * body once output has begun, so a status-only assertion can pass against
     * a fatal.
     *
     * @param array{status:int, body:string, content_type:string, location:string} $response
     */
    private function assertNoPhpError( array $response ): void
    {
        foreach ( [ 'Fatal error', 'Uncaught Error', 'Call to undefined', 'Warning:' ] as $marker ) {
            self::assertStringNotContainsString(
                $marker,
                $response['body'],
                'The endpoint emitted a PHP error into its response body: ' . $response['body']
            );
        }
    }

    // ─── The positive case, asserted first (L-008) ───────────────────

    public function testAnonymousSubmissionSucceeds(): void
    {
        $before = $this->commentCount();

        $response = $this->post( self::ENDPOINT, $this->fields(), null );

        $this->assertNoPhpError( $response );

        self::assertSame(
            201,
            $response['status'],
            'An anonymous visitor could not submit a comment (S-09). Body: ' . $response['body']
        );

        $decoded = json_decode( $response['body'], true );

        self::assertIsArray( $decoded, 'The endpoint did not answer JSON: ' . $response['body'] );
        self::assertTrue( $decoded['success'] ?? false );
        self::assertNotEmpty( $decoded['id'] ?? '', 'No comment ID was returned.' );

        // The response claiming success is not evidence that anything was
        // stored; the storage count is.
        self::assertSame( $before + 1, $this->commentCount(), 'The comment was not persisted.' );

        $stored = $this->storage->read( 'comments', $decoded['id'] );

        self::assertSame( 'pending', $stored['status'], 'A public comment must never be auto-approved.' );
        self::assertSame( 'about', $stored['page_slug'] );
        self::assertArrayNotHasKey(
            'author_email',
            $stored,
            'The raw email must not be stored — only author_email_hash.'
        );
    }

    // ─── Honeypot ────────────────────────────────────────────────────

    public function testHoneypotRejectsABotAndStoresNothing(): void
    {
        $before = $this->commentCount();

        $response = $this->post(
            self::ENDPOINT,
            $this->fields( [ '_honeypot' => 'http://spam.example/' ] ),
            null
        );

        $this->assertNoPhpError( $response );

        // The bot is told exactly what a human is told — same status, same shape,
        // right down to a syntactically valid id. Any difference here is a tell
        // a spammer can test for once and then skip the field forever.
        self::assertSame(
            201,
            $response['status'],
            'The honeypot answers a different status from a real submission, so a bot can '
            . 'detect the trap by comparing status codes.'
        );

        $decoded = json_decode( $response['body'], true );

        self::assertTrue( $decoded['success'] ?? false, 'The honeypot response must mimic success.' );
        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{32}$/',
            $decoded['id'] ?? '',
            'The camouflage id must look exactly like a real comment id.'
        );

        // This is the assertion that matters: the response is a decoy, the
        // storage is the truth.
        self::assertSame(
            $before,
            $this->commentCount(),
            'The honeypot answered success AND stored the comment — the check is decorative.'
        );

        self::assertFalse(
            $this->storage->exists( 'comments', $decoded['id'] ),
            'The camouflage id refers to a real stored record — the bot was not discarded.'
        );
    }

    // ─── The rate limit, across sessions ─────────────────────────────

    public function testRateLimitHoldsAcrossSessions(): void
    {
        // Distinct, deliberately different session cookies on every request.
        // If the limit were still keyed on the session — as it was before this
        // slice — each of these would land in its own bucket and all of them
        // would succeed.
        $accepted = 0;
        $limited  = 0;

        for ( $i = 0; $i < 6; $i++ ) {
            $response = $this->post(
                self::ENDPOINT,
                $this->fields( [ 'content' => 'Submission number ' . $i . '.' ] ),
                null,
                [ 'Cookie: klytos_session=' . str_repeat( (string) $i, 26 ) ]
            );

            $this->assertNoPhpError( $response );

            if ( $response['status'] === 429 ) {
                $limited++;
            } elseif ( $response['status'] === 201 ) {
                $accepted++;
            } else {
                self::fail(
                    'Unexpected status ' . $response['status'] . ' on request ' . $i
                    . '. Body: ' . $response['body']
                );
            }
        }

        self::assertGreaterThan(
            0,
            $accepted,
            'Every request was refused — the endpoint is not accepting comments at all.'
        );

        self::assertGreaterThan(
            0,
            $limited,
            'Six submissions from one address, each with a DIFFERENT session cookie, were all '
            . 'accepted. The rate limit is keyed on something the caller controls (S-09).'
        );

        // The refusal has to be the rate limiter itself, not an incidental
        // failure that happens to share the status.
        $final = $this->post( self::ENDPOINT, $this->fields(), null );

        self::assertSame( 429, $final['status'] );

        $decoded = json_decode( $final['body'], true );

        self::assertIsArray( $decoded, 'The refusal was not JSON: ' . $final['body'] );
        self::assertArrayHasKey( 'error', $decoded );
        self::assertNotEmpty( $decoded['error'], 'The 429 carried no message.' );

        // Nothing was stored by the refused requests beyond the accepted ones.
        self::assertSame(
            $accepted,
            $this->commentCount(),
            'A rate-limited submission was stored anyway.'
        );
    }

    // ─── No admin directory name anywhere a visitor can see ──────────

    public function testTheEndpointIsNotUnderTheAdminDirectory(): void
    {
        // NOTE: asserting on self::ENDPOINT itself would only test this file's
        // own constant and could never fail for a reason connected to the fix.
        // The real coverage is the round trip below.
        //
        // The old admin-path handler is gone rather than merely exempted,
        // so there is no second, admin-scoped way in. Anonymous callers get
        // the gate's 401/403 or a 404 — never a working submission.
        $legacy = $this->post(
            '/installer/admin/api/comment-submit.php',
            $this->fields(),
            null
        );

        self::assertNotSame(
            201,
            $legacy['status'],
            'The admin-path comment endpoint still accepts anonymous submissions. Publishing '
            . 'that URL on a generated page would disclose the randomized admin directory.'
        );

        self::assertContains( $legacy['status'], [ 401, 403, 404 ] );
    }

    // ─── Ordering: cheap checks run before expensive ones ────────────

    /**
     * A flood that trips the honeypot on every request must still be counted.
     *
     * The first cut of this slice checked the honeypot BEFORE the rate limiter,
     * so a bot that simply set `_honeypot` on every request took the cheap 200
     * branch and never consumed a slot — the one control meant to bound
     * repeated abuse never engaged for precisely the traffic it exists for.
     * Found by the slice's own security-auditor pass.
     */
    public function testHoneypotSubmissionsAreRateLimitedToo(): void
    {
        $statuses = [];

        for ( $i = 0; $i < 4; $i++ ) {
            $statuses[] = $this->post(
                self::ENDPOINT,
                $this->fields( [ '_honeypot' => 'bot-' . $i ] ),
                null
            )['status'];
        }

        self::assertContains(
            429,
            $statuses,
            'Four honeypot submissions in one window were all accepted. Tripping the honeypot '
            . 'buys an attacker an uncounted request, so the rate limit can never engage. '
            . 'Statuses: ' . implode( ', ', $statuses )
        );
    }

    /**
     * The flood ceiling has to hold even when comments are switched OFF.
     *
     * That is the observable consequence of it running BEFORE App::boot():
     * `comments_enabled` cannot be read without a booted App, so if the refusal
     * still arrives once the ceiling is spent, the limiter necessarily ran
     * first. Without that ordering every anonymous request pays for a full boot
     * — config decryption, ~25 managers and every plugin's init.php — at a
     * fixed URL present on every install.
     */
    public function testTheFloodCeilingHoldsEvenWhenCommentsAreDisabled(): void
    {
        $this->app->getSiteConfig()->setValue( 'comments_enabled', false );

        $statuses = [];

        for ( $i = 0; $i < 12; $i++ ) {
            $statuses[] = $this->post( self::ENDPOINT, $this->fields(), null )['status'];
        }

        self::assertContains( 403, $statuses, 'Comments were not actually disabled.' );

        self::assertContains(
            429,
            $statuses,
            'Twelve anonymous requests were served past the flood ceiling while comments were '
            . 'disabled, so the ceiling is not being enforced ahead of the boot-dependent '
            . 'checks. Statuses: ' . implode( ', ', $statuses )
        );
    }

    // ─── Threading survives the new parent_id format check ───────────

    /**
     * Slice 7 began dropping any parent_id that is not 32 hex characters,
     * because the raw value was previously stored and echoed back. That check
     * is only correct if it matches what the product actually mints:
     * Helpers::randomHex( 16 ) is 16 BYTES, i.e. 32 hex characters. Getting
     * that wrong would silently flatten every threaded reply, and nothing else
     * in the suite would notice.
     */
    public function testAGenuineParentIdIsKeptAndAForgedOneIsDropped(): void
    {
        $parent = json_decode(
            $this->post( self::ENDPOINT, $this->fields(), null )['body'],
            true
        );

        self::assertNotEmpty( $parent['id'] ?? '', 'The parent comment was not created.' );
        self::assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $parent['id'] );

        $reply = json_decode(
            $this->post(
                self::ENDPOINT,
                $this->fields( [ 'content' => 'A reply.', 'parent_id' => $parent['id'] ] ),
                null
            )['body'],
            true
        );

        self::assertNotEmpty( $reply['id'] ?? '', 'The reply was not created.' );

        $stored = $this->storage->read( 'comments', $reply['id'] );

        self::assertSame(
            $parent['id'],
            $stored['parent_id'],
            'A legitimate parent_id was discarded — threading is silently flattened.'
        );
    }

    public function testAForgedParentIdIsDiscardedRatherThanStored(): void
    {
        $response = $this->post(
            self::ENDPOINT,
            $this->fields( [ 'parent_id' => '../../etc/passwd<script>' ] ),
            null
        );

        $decoded = json_decode( $response['body'], true );

        self::assertSame( 201, $response['status'] );

        $stored = $this->storage->read( 'comments', $decoded['id'] );

        self::assertSame(
            '',
            $stored['parent_id'],
            'A caller-supplied parent_id that is not a comment ID was stored verbatim.'
        );
    }

    public function testDisabledCommentsAreRefused(): void
    {
        $this->app->getSiteConfig()->setValue( 'comments_enabled', false );

        $before   = $this->commentCount();
        $response = $this->post( self::ENDPOINT, $this->fields(), null );

        $this->assertNoPhpError( $response );

        self::assertSame( 403, $response['status'] );
        self::assertSame( $before, $this->commentCount() );
    }
}
