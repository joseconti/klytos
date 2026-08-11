# The AI chat screen — extension points and the key test

`installer/admin/ai-chat.php` is manifest entry 12 of the admin redesign,
template `conversation`, H1 **Klytos AI**. This page documents the surfaces the
screen exposes to plugins, plus `ChatEngine::validateKey()`, which the screen's
sibling endpoint calls.

Read `docs/design/design-handoff/SPEC/screens/template-conversation.md` for what
the screen is; this file is only its public contract.

---

## What the screen does NOT do, and why

The delivery specifies a **streamed** turn: a `role="log"` region growing token
by token, a Stop button that keeps a partial answer, tool calls that pass
through *running* and *needs permission*, and a "Load earlier messages" link.

None of it is built, and that is a recorded decision (**D-104**,
`docs/roadmap.md` §0c), not an omission:

- `installer/admin/api/ai-chat.php` answers **once**, with the whole result, and
  `installer/core/ai/chat-engine.php` has no streaming path. A partial turn
  cannot exist — and Stop, a running tool call, an inline permission confirm and
  the *Stopped* state are all states **of** a partial turn.
- `ChatManager::getChat()` reads every message with no limit or offset, so
  "Load earlier messages" has no query to make and nothing to fetch.
- Nothing in the tree records a last screen visited, so the starters cannot be
  drawn from one. The three the delivery quotes are built literally.
- The copilot dock is an empty landmark in `templates/footer.php` — no mode
  enum, no per-user store, no launcher — so the dock modes are not built either.

Everything the product backs **is** built: the shell, the transcript as a polite
log, the per-turn `<article>` with its name and its always-present actions, the
finished tool-call rows in both outcomes, the context row, the composer with its
real label and hint, the provider-unreachable alert and the not-configured
state.

---

## Actions

### `admin.ai_chat.before`

Fires at the top of the screen, immediately inside `main` and above the
conversation. No payload; echo extra HTML.

```php
klytos_add_action( 'admin.ai_chat.before', function () {
    echo '<p class="k-status-line">' . klytos_esc_html( __( 'my_plugin.chat_notice' ) ) . '</p>';
} );
```

### `admin.ai_chat.after`

Fires at the tail of the screen, after the composer (or after the
not-configured line, when no provider is configured — both paths reach it). No
payload.

```php
klytos_add_action( 'admin.ai_chat.after', function () {
    echo '<p class="k-empty-text">' . klytos_esc_html( __( 'my_plugin.chat_footer' ) ) . '</p>';
} );
```

Both fire on **every** state of the screen, including the one where the composer
is absent, so a plugin that draws a control near the composer must not assume
one exists.

---

## `ChatEngine::validateKey()`

```php
public function validateKey( string $providerId, string $apiKey ): array
```

Tests an API key **against the provider it belongs to**, by making the smallest
real request the provider will price (one output token, no tools, no system
prompt) through the same client factory the chat itself uses. A key that passes
here works in the chat by construction.

| Parameter | Type | Meaning |
|---|---|---|
| `$providerId` | `string` | One of `AiKeyManager::PROVIDERS` — `anthropic`, `openai`, `gemini`, `openrouter`, `ollama`. |
| `$apiKey` | `string` | The key to test. **Never persisted, logged or echoed by this path**: it is passed to the client and discarded with it. |

**Returns** `array{valid: bool, status: string, message: string}`.

| `status` | Meaning |
|---|---|
| `valid` | The provider accepted the key. |
| `invalid` | The provider answered and refused it (a 4xx; `message` names the code). |
| `unreachable` | Nothing was learned about the key — DNS, TLS, timeout, connection refused, or a 5xx. |

**Three outcomes, not two, and the third is the point.** "I could not reach the
provider" is not "your key is wrong", and answering a network failure with
`invalid` sends a person off to regenerate a key that was fine. `valid` stays a
boolean so the response shape any existing client reads still holds.

Throws `\InvalidArgumentException` when `$providerId` is not a known provider.

```php
$verdict = $app->getChatEngine()->validateKey( 'anthropic', $key );

if ( $verdict['status'] === 'unreachable' ) {
    // Ask them to try again — say nothing about the key itself.
} elseif ( ! $verdict['valid'] ) {
    // The provider refused it: $verdict['message'] names the HTTP code.
}
```

### Why this exists

`admin/api/ai-chat.php`'s `validate_key` action shipped as
`$valid = ! empty( $apiKey ) && strlen( $apiKey ) > 10;` under a docblock
promising "Test an API key against the provider", so **any eleven characters
reported valid** and a revoked or mistyped key was confirmed as working. That is
entry 24's defect (a test control reporting success without reaching anything)
on a second screen, and it is closed here.

The claim is proven in the **PHP tier**
(`tests/Integration/AiChatValidateKeyHttpTest.php`), not the browser tier: a
real provider test opens a socket, and the browser tier's read-back duty fails
any flow that writes an error line — which a refused key legitimately does.
`tests/Integration/WebhookAdminRefusalHttpTest.php` is the worked precedent.

---

## The endpoint

`/admin/api/ai-chat.php` is gated at `ai.use`; every key-management action
inside it (`set_key`, `remove_key`, `validate_key`, `set_active`,
`get_providers`) requires `site.configure` on top, checked inline. See
`docs/reference/authorization.md`.

The legacy `?panel=dashboard|settings|users|profile` URLs on the screen itself
no longer render anything: they answer **302** to `index.php`, `settings.php`,
`users.php` and `profile.php`, each of which gates itself through the central
map. The four partials they used to include are gone.
