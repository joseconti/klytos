<?php

/**
 * Klytos CMS — a webhook test send reaches the webhook it names.
 *
 * Found by the per-screen survey of manifest entry 24 (Webhooks), run against
 * the shipped product before the first line of the redesign was written.
 *
 * `admin/webhooks.php` offers "Send Test Event" and the MCP tool
 * `klytos_test_webhook` offers the same thing per webhook. Both call
 * `WebhookManager::dispatch( 'test.ping', … )`, and `dispatch()` resolves its
 * targets through `getWebhooksForEvent()`, which keeps only the webhooks whose
 * stored `events` array CONTAINS that event.
 *
 * Nothing can contain it. `test.ping` is not in `CORE_EVENTS`, and the one
 * `webhooks.events` filter in the tree (`core/x402-bootstrap.php`) adds three
 * `x402.*` events and not this one — while `create()` refuses any event that
 * `getAvailableEvents()` does not list. So no webhook has ever been subscribed
 * to `test.ping` on any install, `dispatch()` returns at its own empty guard,
 * and BOTH callers then report success: the admin screen says "Test event
 * dispatched to all active webhooks" and the MCP tool returns
 * `success: true` with the endpoint's URL in the message.
 *
 * That is L-041's shape again — a feature that reaches nobody behind a
 * confident report — and the MCP tool carries a second half of the same
 * defect: its declared contract takes a `webhook_id` ("Send a test event to a
 * webhook") and the handler ignores it entirely, dispatching by event instead.
 *
 * The fix is therefore not new product but the code meeting its own published
 * contract: a test send goes to the webhook it was given, whatever that
 * webhook is subscribed to (user decision, 2026-08-10).
 *
 * THE RED WAS OBSERVED BEFORE THE FIX and it names the absent behaviour — a
 * test send that reaches its own webhook — not a typo and not a missing import.
 *
 * No socket is opened by any test here. The fixtures store a webhook whose URL
 * SafeHttp refuses at delivery time, which is the case `sendHttpPost()`'s own
 * comment describes (a host that resolved publicly when it was stored can
 * resolve privately by the time an event fires). The delivery is refused before
 * any network I/O, and the delivery LOG — written on every attempt, refused or
 * not — is what proves the attempt was made at all.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\WebhookManager;
use Klytos\Tests\UnitTestCase;

/**
 * The test-send path of the webhook manager.
 *
 * Both directions are asserted, because one alone is half a test (L-010): a
 * test send reaches the webhook it names EVEN WHEN that webhook subscribes to
 * nothing matching, and it reaches ONLY that webhook — a test that sprayed
 * every endpoint would be a worse defect than one that reached none, since it
 * would deliver unexpected traffic to somebody else's production listener.
 */
final class WebhookTestEventTest extends UnitTestCase
{
    /** @var string A URL SafeHttp refuses, so no socket is ever opened. */
    private const REFUSED_URL = 'http://127.0.0.1/hook';

    private function makeManager(): WebhookManager
    {
        return new WebhookManager( $this->storage );
    }

    /**
     * Store a webhook directly.
     *
     * `create()` is deliberately NOT used: it refuses a private address, and
     * the state under test is the one the manager itself documents — a stored
     * webhook whose URL is no longer fetchable. Writing it through storage is
     * how that state is reached without a network round trip.
     *
     * @param string        $id     Webhook id.
     * @param array<string> $events The events it subscribes to.
     */
    private function seedWebhook( string $id, array $events ): void
    {
        $this->storage->write( 'webhooks', $id, [
            'id'             => $id,
            'url'            => self::REFUSED_URL,
            'events'         => $events,
            'secret'         => str_repeat( 'a', 64 ),
            'description'    => '',
            'status'         => 'active',
            'created_at'     => '2026-08-10T00:00:00+00:00',
            'updated_at'     => '2026-08-10T00:00:00+00:00',
            'last_triggered' => null,
            'failure_count'  => 0,
        ] );
    }

    /**
     * Every delivery-log record written for a webhook.
     *
     * @param  string $webhookId Webhook id.
     * @return array<int,array<string,mixed>>
     */
    private function deliveryLogFor( string $webhookId ): array
    {
        return array_values( array_filter(
            $this->storage->list( 'webhook-logs' ),
            static fn( array $row ): bool => ( $row['webhook_id'] ?? '' ) === $webhookId
        ) );
    }

    /**
     * The cause, stated as a fact rather than inferred: no webhook can ever be
     * subscribed to the event both test controls dispatch.
     *
     * This one passes before the fix as well as after — it documents WHY the
     * behaviour below was absent, and it fails the day somebody "fixes" the
     * defect by quietly adding `test.ping` to the event list, which was
     * considered and rejected (it would put a synthetic event in every
     * install's subscription checklist and still only reach endpoints that had
     * opted in).
     */
    public function testTestPingIsNotASubscribableEvent(): void
    {
        $events = $this->makeManager()->getAvailableEvents();

        $this->assertArrayNotHasKey(
            'test.ping',
            $events,
            'test.ping must not be offered as a subscribable event: a test send '
            . 'reaches its webhook directly, it is not something to subscribe to.'
        );
    }

    /**
     * The shipped defect, reproduced: dispatching `test.ping` reaches nobody.
     *
     * The webhook below subscribes to every event `create()` would have
     * accepted, which is the most generous case any install can produce, and
     * the dispatch still writes no delivery record at all.
     */
    public function testDispatchingTestPingReachesNoWebhook(): void
    {
        $manager = $this->makeManager();

        $this->seedWebhook( 'wh-generous', array_keys( $manager->getAvailableEvents() ) );

        $manager->dispatch( 'test.ping', [ 'message' => 'Test event from Klytos.' ] );

        $this->assertSame(
            [],
            $this->deliveryLogFor( 'wh-generous' ),
            'dispatch( test.ping ) attempted a delivery, so the premise of '
            . 'sendTestEvent() no longer holds — re-read the survey before changing this.'
        );
    }

    /** A test send attempts delivery to the webhook it was given. */
    public function testSendTestEventReachesTheNamedWebhook(): void
    {
        $manager = $this->makeManager();

        // Subscribed to ONE unrelated event, so nothing about this webhook's
        // subscriptions can explain a delivery: only the id it was given can.
        $this->seedWebhook( 'wh-named', [ 'page.created' ] );

        $result = $manager->sendTestEvent( 'wh-named' );

        $this->assertCount(
            1,
            $this->deliveryLogFor( 'wh-named' ),
            'A test send must attempt exactly one delivery to the webhook it names.'
        );

        $this->assertFalse( $result['success'], 'A refused target is not a success.' );
        $this->assertNotSame( '', $result['error'], 'A failed test send states why.' );
    }

    /** A test send reaches that webhook ONLY — never every other endpoint. */
    public function testSendTestEventReachesNoOtherWebhook(): void
    {
        $manager = $this->makeManager();

        $this->seedWebhook( 'wh-named', [ 'page.created' ] );
        $this->seedWebhook( 'wh-bystander', [ 'page.created' ] );

        $manager->sendTestEvent( 'wh-named' );

        $this->assertSame(
            [],
            $this->deliveryLogFor( 'wh-bystander' ),
            'A test send delivered to a webhook nobody asked about.'
        );
    }

    /**
     * A test send is one attempt, not the retry ladder.
     *
     * `deliver()` retries five times with 1-2-4-8-second sleeps, which is right
     * for a real event nobody is watching and wrong for a control a person just
     * pressed. §24's own delta makes retry a deliberate act — "Retry is a form
     * post per delivery" — so the test path states the outcome once and lets
     * the person decide.
     */
    public function testSendTestEventDoesNotRetry(): void
    {
        $manager = $this->makeManager();

        $this->seedWebhook( 'wh-once', [ 'page.created' ] );

        $started = microtime( true );
        $manager->sendTestEvent( 'wh-once' );
        $elapsed = microtime( true ) - $started;

        $log = $this->deliveryLogFor( 'wh-once' );

        $this->assertSame( 1, $log[0]['attempts'] ?? 0, 'A test send makes exactly one attempt.' );
        $this->assertLessThan( 1.0, $elapsed, 'A test send must not sleep through the retry ladder.' );
    }

    /**
     * A failed TEST send does not damage the endpoint's standing.
     *
     * `deliver()` increments `failure_count` and disables a webhook at ten
     * consecutive failures. Pressing a diagnostic control must not be able to
     * disable a live integration — the person testing an endpoint is trying to
     * find out whether it works, not to vote on whether it keeps running.
     */
    public function testAFailedTestSendDoesNotCountAgainstTheWebhook(): void
    {
        $manager = $this->makeManager();

        $this->seedWebhook( 'wh-standing', [ 'page.created' ] );

        $manager->sendTestEvent( 'wh-standing' );

        $stored = $this->storage->read( 'webhooks', 'wh-standing' );

        $this->assertSame( 0, $stored['failure_count'] ?? -1, 'A test send changed the failure count.' );
        $this->assertSame( 'active', $stored['status'] ?? '', 'A test send changed the webhook status.' );
    }

    /** An unknown webhook id is refused, not silently treated as a success. */
    public function testSendTestEventRefusesAnUnknownWebhook(): void
    {
        $this->expectException( \InvalidArgumentException::class );

        $this->makeManager()->sendTestEvent( 'wh-does-not-exist' );
    }
}
