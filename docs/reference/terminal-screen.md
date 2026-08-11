# Terminal screen — extension points

Manifest entry 23. The screen lives at `installer/admin/terminal.php` and the
command registry behind it in `installer/core/terminal-executor.php`.

It is the most tightly gated surface in the admin, and every extension point
here inherits that: `terminal.access` is granted to **`owner` alone**
(`user-manager.php`), the screen additionally refuses to render its console
unless the account has a second factor active, and the executor demands a fresh
TOTP code before the first command of every terminal session and again after
ten idle minutes.

**What is built and what is not.** The console itself is the deferred engine
interior recorded in `docs/roadmap.md` §0c (D-104): the product renders into an
**xterm.js canvas**, where `SPEC/screens/template-console-stream.md` draws a
`<pre>` stream with a line model, streamed output, elapsed seconds, an exit code
and a working Stop. Nothing behind it supports those — `dispatch()` buffers with
`ob_start()`/`ob_get_clean()` and returns one whole string, nothing measures a
command's duration, the return type carries no numeric status, and handlers run
synchronously with no interrupt point. Everything AROUND the canvas is built to
the spec. A plugin extending this screen should expect the canvas to be replaced
wholesale when that interior lands, and should not depend on its DOM.

## Actions

Both take no arguments.

| Action | Where it fires |
|---|---|
| `admin.terminal.before` | At the top of the screen, above the control row |
| `admin.terminal.after` | At the tail, after the console and the command reference |

`admin.terminal.after` **also fires on the refusal path** — the screen that
renders when the account has no second factor and returns early. That is
deliberate: a listener that adds a panel to this screen should not silently
vanish on the one state a person is most likely to be stuck on. A listener that
must not run there can check for itself:

```php
klytos_add_action( 'admin.terminal.before', function (): void {
    echo '<p class="k-status-line k-status-line--info">'
        . klytos_esc_html( __( 'my_plugin.terminal_notice' ) )
        . '</p>';
} );
```

## Filters

### `terminal.commands`

Filters the whole command table before anything reads it. This is how a plugin
adds a command; `TerminalExecutor::registerCommand()` is the equivalent
imperative route and validates the same four required fields.

```php
klytos_add_filter( 'terminal.commands', function ( array $commands ): array {
    $commands['shop:reindex'] = [
        'description' => __( 'my_plugin.cmd_reindex_desc' ),
        'usage'       => 'shop:reindex [--full]',
        'category'    => 'content',
        'permission'  => 'pages.publish',
        'handler'     => function ( array $args, array $flags ): string {
            return __( 'my_plugin.cmd_reindex_done' );
        },
    ];

    return $commands;
} );
```

**`description`, `usage` and `category` are UNTRUSTED at the render boundary,
and the product treats them that way.** They reach the browser through
`api/terminal-autocomplete.php` and are drawn into the command reference panel;
that panel is built with `textContent` and `setAttribute` and never with
`innerHTML`, because a description assembled by string concatenation was a
script-execution primitive in the owner's admin (fixed 2026-08-10, `106f6a8`;
the reproduction is in `tests/E2E/terminal.spec.js`). Anything reading this
filter's output into markup owes the same mechanism.

**Write them through `__()`.** They are user-facing strings on a screen served
in 20 locales.

### `terminal.category_labels`

Filters the nine category labels. It is applied in **two** places that must
agree — the `help` command's own output (`terminal-executor.php`) and the
screen's command reference panel (`terminal.php`) — so renaming a category
renames it in both. Before stage 6 slice 2 the screen carried its own hardcoded
copy of six of them, which is why `backup`, `update` and `config` used to print
their raw slug in `help`.

```php
klytos_add_filter( 'terminal.category_labels', function ( array $labels ): array {
    $labels['shop'] = __( 'my_plugin.terminal_category_shop' );

    return $labels;
} );
```

A category with no label falls back to its own id rather than to a guess.

### `terminal.command_output`

Applied to a command's output after it runs, receiving the output and the
command name. It sees the string the handler returned, before history and the
audit log record it.

## JavaScript

### `window.KlytosShell.trapFocus( container, event )`

Exported by `installer/admin/assets/js/klytos-shell.js`, which every admin page
loads. Given an overlay's container element and a `keydown` event, it keeps Tab
and Shift+Tab inside that container — `accessibility.md` §3.2's requirement for
every overlay in this admin.

It is the command palette's and the drawer's own implementation, exported rather
than copied when entry 23's revalidation dialog needed it. Call it from a
`keydown` listener on the overlay and guard for its presence, so a page that
somehow loads without the shell degrades to an untrapped dialog rather than
throwing:

```js
modal.addEventListener( 'keydown', function ( event ) {
    if ( event.key === 'Tab' && window.KlytosShell && window.KlytosShell.trapFocus ) {
        window.KlytosShell.trapFocus( modal, event );
    }
} );
```

It only moves focus at the ends of the tab ring; opening the overlay, moving
focus into it, `Esc`, and returning focus to the opener remain the caller's.

## Strings

Every string on this screen and in the executor is a `terminal.*` key present in
all 20 catalogues (`installer/core/lang/`). Audit finding **NEW-33** — the whole
surface hardcoded in Spanish, some of it unaccented — closed here.

Command **`usage`** strings are the deliberate exception and are **not**
translated: `build:page <slug>` is syntax, like the command name itself, and a
localised flag would not run.
