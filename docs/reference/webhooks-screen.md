# Webhooks screen — extension points

The admin screen at `installer/admin/webhooks.php` (manifest entry 24, the
record-form half) and the test-send path of `Klytos\Core\WebhookManager`.

The screen is gated centrally: `installer/core/admin-gate.php` maps
`webhooks.php` to the `webhooks.manage` capability, held by `owner` and `admin`.

---

## `WebhookManager::sendTestEvent()`

```php
public function sendTestEvent( string $webhookId ): array
```

Sends one test delivery to the webhook named by `$webhookId`, **whatever that
webhook is subscribed to**, and returns the outcome of the single attempt.

| | |
|---|---|
| **Parameters** | `$webhookId` — the id of an existing webhook. |
| **Returns** | `array{success: bool, code: int, error: string}` — `code` is the HTTP status the endpoint answered with, or `0` when no response was obtained; `error` is empty on success. |
| **Throws** | `\InvalidArgumentException` when no webhook has that id. |
| **Capability** | Callers gate it themselves: the screen at `webhooks.manage` via the central gate, the MCP tool `klytos_test_webhook` at `webhooks.manage` via `tool-capabilities.php`. |

It differs from a real delivery in three deliberate ways:

- **One attempt.** `dispatch()` retries five times with 1-2-4-8-second sleeps,
  which is right for an event nobody is watching and wrong for a control a
  person just pressed. `SPEC/manifest.md` §24 makes retry a deliberate act of
  its own.
- **`failure_count` and `status` are untouched**, so a diagnostic can never trip
  the ten-consecutive-failures auto-disable and take a live integration down.
- **`last_triggered` is untouched**, because no event happened.

The attempt **is** written to the delivery log (`webhook-logs`): it is a real
outbound request, and the log is where an operator looks for what left the host.

```php
$manager = new \Klytos\Core\WebhookManager( $app->getStorage() );
$result  = $manager->sendTestEvent( 'a1b2c3d4' );

if ( $result['success'] ) {
    echo "The endpoint answered {$result['code']}.";
} else {
    echo "The test did not arrive: {$result['error']}";
}
```

### Why `test.ping` is not a subscribable event

`sendTestEvent()` puts `test.ping` in the payload's `event` field so the
receiving end can tell a drill from a real event, and that name is deliberately
**not** in `CORE_EVENTS` and must not be added by a `webhooks.events` filter. A
test send chooses its target by id, not by subscription; making `test.ping`
subscribable would put a synthetic event in every install's subscription list
and a test would still only reach endpoints that had opted in.

This replaced a defect: both test controls used to call
`dispatch( 'test.ping', … )`, which resolves targets by subscription, so the
test reached **no endpoint on any install** while both callers reported success.
See `tests/Unit/WebhookTestEventTest.php`.

---

## Actions

| Hook | Fires | Payload |
|---|---|---|
| `webhook.before_test` | Before a test delivery is attempted | the webhook record |
| `webhook.after_test` | After the attempt, whatever its outcome | the webhook record, then the result array |
| `admin.webhooks.before` | Top of the screen, above the status line | none |
| `admin.webhooks.before_cards` | Above the first card, inside the card stack | none |
| `admin.webhooks.before_form` | Above the add-endpoint form | none |
| `admin.webhooks.before_fields` | Above the first field, inside the form | none |
| `admin.webhooks.after_fields` | Below the last field, inside the form | none |
| `admin.webhooks.after_form` | Below the add-endpoint form | none |
| `admin.webhooks.after_cards` | Below the last card, inside the card stack | none |
| `admin.webhooks.after` | Very bottom of the screen | none |

`before_form` and `after_form` predate the redesign and keep firing: in the
shipped screen they bracketed the create modal, and here they bracket the form
that replaced it. A released plugin listening on either is unaffected.

```php
klytos_add_action( 'webhook.after_test', function ( array $webhook, array $result ): void {
    if ( ! $result['success'] ) {
        klytos_log_error( 'Test to ' . $webhook['url'] . ' failed: ' . $result['error'] );
    }
} );
```

---

## Filters

### `webhook.test_payload`

The decoded payload of a test send, before it is signed and sent.

```php
klytos_add_filter( 'webhook.test_payload', function ( array $payload, array $webhook ): array {
    $payload['data']['environment'] = 'staging';
    return $payload;
} );
```

Changing `event` here changes what the receiving end is told this delivery is.
The signature is computed from the filtered body, so a listener cannot break it.

### `admin.webhooks.event_choices`

The event checkboxes the add-endpoint form offers, as `event name => description`,
in the order they are drawn. A plugin that registers events through
`webhooks.events` can also order or trim what the form shows.

```php
klytos_add_filter( 'admin.webhooks.event_choices', function ( array $choices ): array {
    unset( $choices['user.login'] );
    return $choices;
} );
```

Removing a choice removes only the *affordance*: the handler still accepts any
event `getAvailableEvents()` lists, and `WebhookManager::create()` is the
authority on what may be stored.

---

## What this screen does not render, and why

Two of `SPEC/manifest.md` §24's four cards are deferred (`docs/roadmap.md` §0c):

- **HMAC secret ("read-only mono + rotate")** — the product has no rotate:
  `WebhookManager::update()`'s updatable list excludes `secret`, and nothing in
  the tree regenerates one. The card also assumes one site-wide secret while the
  product stores one per webhook. The signing secret **is** shown once, on the
  request that creates its webhook, which is where it can be handed over.
- **Delivery log (list-table)** — `logDelivery()` records `webhook_id`,
  `success`, `attempts`, `error` and `timestamp`, so three of the six specified
  columns (**Event**, **Code**, **Duration**) have no data source at all, and
  the "Retry is a form post per delivery" delta has no primitive because the log
  does not keep the payload.
