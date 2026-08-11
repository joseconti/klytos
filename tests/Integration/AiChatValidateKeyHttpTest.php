<?php

/**
 * Klytos CMS — `validate_key` does not report a key valid without testing it.
 *
 * The shipped endpoint was `$valid = !empty($apiKey) && strlen($apiKey) > 10;`
 * under a docblock promising "Test an API key against the provider" — so any
 * eleven characters reported valid, and a person who pasted a revoked or
 * mistyped key was told it worked. That is entry 24's defect exactly (a test
 * control reporting success without reaching anything), on a second screen.
 *
 * WHY THIS CLAIM LIVES IN THE PHP TIER AND NOT IN THE BROWSER TIER: a real
 * provider test opens a socket, and `tests/E2E/fixtures.js`'s read-back duty
 * fails any browser flow that produces an error line — which a refused key
 * legitimately does. `WebhookAdminRefusalHttpTest` is the worked precedent and
 * the reason is the same: the product is not made quieter to suit a test.
 *
 * WHY THE ASSERTION IS "NOT VALID" RATHER THAN "INVALID": the fix distinguishes
 * three outcomes, not two — valid, invalid, and unreachable — because "I could
 * not reach the provider" is not "your key is wrong", and collapsing them is
 * half of what was wrong with the shipped boolean. A machine with no outbound
 * network reports `unreachable`; a machine with one reports `invalid`. Both are
 * correct, both are ≠ valid, and the test is true on either.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;

final class AiChatValidateKeyHttpTest extends AdminHttpTestCase
{
    /** Its own slot in the reserved band; `KLYTOS_TEST_PORT_OFFSET` shifts the whole band. */
    protected static function serverPort(): int
    {
        return 8114;
    }

    /**
     * A key that no provider ever issued is never reported valid.
     */
    public function testAnUntestedKeyIsNeverReportedValid(): void
    {
        $response = $this->postJson(
            '/installer/admin/api/ai-chat.php',
            [
                'action'   => 'validate_key',
                'provider' => 'anthropic',
                'api_key'  => 'sk-ant-this-key-was-never-issued',
            ],
            'owner'
        );

        $payload = json_decode( $response['body'] ?? '', true );

        $this->assertIsArray( $payload, 'validate_key did not answer with JSON.' );
        $this->assertArrayHasKey( 'valid', $payload, 'validate_key answered without a verdict.' );

        $this->assertFalse(
            $payload['valid'],
            'A key that was never issued was reported valid — the endpoint tested nothing.'
        );

        // The three-way status is what makes the verdict readable: a person
        // whose network is down must not be told their key is wrong.
        $this->assertContains(
            $payload['status'] ?? '',
            [ 'invalid', 'unreachable' ],
            'validate_key gave no usable status beside its boolean.'
        );
    }

    /**
     * The endpoint stays gated where it always was. Reported here because the
     * fix now performs a real outbound request, which is a capability worth
     * proving is not reachable by a lower role than the one that manages keys.
     */
    public function testValidateKeyStaysGatedAtSiteConfigure(): void
    {
        $response = $this->postJson(
            '/installer/admin/api/ai-chat.php',
            [
                'action'   => 'validate_key',
                'provider' => 'anthropic',
                'api_key'  => 'sk-ant-this-key-was-never-issued',
            ],
            'editor'
        );

        $this->assertSame( 403, $response['status'] ?? 0, 'An editor reached validate_key.' );
    }
}
