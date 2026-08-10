<?php

/**
 * Klytos CMS — the Webhooks screen never prints the manager's own exception.
 *
 * This claim belongs to the PHP tier and not to the browser tier, for a reason
 * the browser tier itself established: refusing a well-formed URL that SafeHttp
 * will not fetch writes a deliberate ERROR line to the product log, and
 * `tests/E2E/fixtures.js`'s read-back duty fails any browser test whose flow
 * produces one. That duty is RIGHT — a flow that logs an error while reporting
 * success is exactly what it exists to catch — so the assertion moves here
 * rather than the product being made quieter to suit a test.
 *
 * What the shipped screen did: `$error = $e->getMessage()`, printed straight
 * into the page. Every locale therefore read the manager's English sentence,
 * and on this particular path that sentence is deliberately generic — `create()`
 * collapses "malformed" and "resolves inside the network" into one message so a
 * refusal cannot be used to map the host's internal network one probe at a time.
 * Printing it gave the person no usable information AND put the manager's
 * wording on a public surface. It now goes to the log, where the operator can
 * read the real reason and the caller cannot.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;

final class WebhookAdminRefusalHttpTest extends AdminHttpTestCase
{
    /** Its own slot in the reserved band; `KLYTOS_TEST_PORT_OFFSET` shifts the whole band. */
    protected static function serverPort(): int
    {
        return 8113;
    }

    /**
     * A well-formed URL pointing inside the network is refused, and the page
     * says so in the person's own language without quoting the manager.
     */
    public function testASafeHttpRefusalNeverReachesThePage(): void
    {
        $response = $this->post(
            '/installer/admin/webhooks.php',
            [
                'action'   => 'add_endpoint',
                'url'      => 'http://127.0.0.1/internal',
                'events'   => [ 'page.created' ],
            ],
            'owner'
        );

        $body = $response['body'] ?? '';

        $this->assertStringNotContainsString(
            'Invalid webhook URL.',
            $body,
            "The manager's own English exception reached the page."
        );

        // The person still learns the endpoint was refused: the field carries
        // its error, which is the shape template-record-form.md §2 specifies.
        $this->assertStringContainsString(
            'webhooks.error.url',
            $body,
            'A refused URL produced no field-level error at all.'
        );

        // The draft SURVIVES the refusal — losing what somebody typed is its
        // own defect — so the refused address is legitimately still in the
        // form's value on THIS response. Asserting its absence here, which the
        // first version of this test did, was asserting against the behaviour
        // the screen wants.
        $this->assertStringContainsString(
            '127.0.0.1/internal',
            $body,
            'The refused draft was thrown away instead of being returned to the form.'
        );

        // Nothing was STORED, though, and only a fresh GET proves that: the
        // draft lives on the response to the refused POST and nowhere else.
        $fresh = $this->request( '/installer/admin/webhooks.php', 'owner' );

        $this->assertStringNotContainsString(
            '127.0.0.1/internal',
            $fresh['body'] ?? '',
            'The refused endpoint was stored anyway and appears in the endpoints list.'
        );
    }
}
