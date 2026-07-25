# Hooks — actions observe, filters modify

> Created Sprint 4 slice 1 (2026-07-25), closing audit **NEW-03**. Decision: **D-054**.
> Related: **D-026** (the deferral this closes), **L-005** (found by booting the playground, not by
> reading), **L-014** (drive the feature, not the defect).

## The short version

Klytos has two extension mechanisms and the difference between them is now **enforced**, not merely
documented:

| | Actions | Filters |
|---|---|---|
| Purpose | **observe** — react to something that happened | **modify** — change a value on its way through |
| Register | `klytos_add_action( $hook, $cb, $priority )` | `klytos_add_filter( $hook, $cb, $priority )` |
| Fire | `klytos_do_action( $hook, ...$args )` | `$v = klytos_apply_filters( $hook, $v, ...$args )` |
| Return value | ignored | **required** — return the value, modified or not |
| Arguments | by value | by value |
| Can change what the caller does next? | **no** | yes, through the returned value |

**A listener that declares a by-reference parameter (`&$data`) is refused at registration**, with a
`Klytos\Core\HookContractException` naming the hook, the parameter and the file and line of the
callback. This applies to both actions and filters.

## Why by-reference is refused rather than supported

Both dispatch paths pass arguments **by value** — `doAction()` collects them variadically
(`mixed ...$args`) and `applyFilters()` spreads them into `call_user_func()`. PHP cannot bind a
by-reference parameter to a value.

What PHP does instead is worse than failing. It emits a warning, **invokes the callback against a
copy**, and discards the write. So the listener looks registered, its body demonstrably runs, and its
effect silently does not exist.

That is not hypothetical: it is exactly how the x402 post-type default sat broken in every production
install from adoption until Sprint 4 (audit NEW-03). Every page create in every install emitted
`Argument #1 ($data) must be passed by reference, value given`, and the code read as correct.

Supporting by-reference instead was **measured and rejected**. Making `doAction()` take
`mixed &...$args` does bind correctly, but PHP then refuses any non-variable argument with a fatal
`Error` — and 36+ call sites pass a literal, a `??` expression, a ternary or an array literal,
including `page-manager.php` itself. Worse, an undefined array key passed to a by-reference parameter
is silently *created* in the caller's array. The full measurement is in `docs/sprints/sprint-4.md`.

## Writing a listener

**Observe — use an action:**

```php
klytos_add_action( 'page.after_save', function ( array $page, string $action ): void {
    klytos_log( 'info', "Page {$page['slug']} was {$action}d" );
} );
```

**Modify — use a filter, and return the value:**

```php
klytos_add_filter( 'page.save_data', function ( array $page, string $action ): array {
    if ( $action === 'create' && empty( $page['meta_description'] ) ) {
        $page['meta_description'] = mb_substr( strip_tags( $page['content_html'] ), 0, 160 );
    }

    return $page;                       // ← required; a filter that returns nothing erases the value
} );
```

**What NOT to write** — this is refused at registration:

```php
klytos_add_action( 'page.save_data', function ( array &$page ): void {   // ← HookContractException
    $page['meta_description'] = '…';
} );
```

By-reference **capture** is a different mechanism, works correctly, and is unaffected:

```php
$collected = [];
klytos_add_action( 'page.after_save', function ( array $page ) use ( &$collected ): void {
    $collected[] = $page['slug'];       // ← fine: `use ( &$x )`, not a by-reference PARAMETER
} );
```

## `page.save_data` — modifying a page before it is written

Applied by `PageManager::create()` and `PageManager::update()` immediately before the record is
persisted, and **before** the `page.before_save` action, so an observer sees exactly what will be
written rather than a draft of it.

| | |
|---|---|
| Value | `array $page` — the complete record about to be written |
| Context | `string $action` — `'create'` or `'update'` |
| Returns | the `array`, modified or not |
| Fired from | `installer/core/page-manager.php` |

Core's own consumer is `installer/core/x402-bootstrap.php`, which injects the post type's x402
default into a new page.

> **Your filter runs AFTER validation, and nothing re-checks it.** `buildPageData()` sanitizes
> `content_html` through `Helpers::sanitizeHtml()` and validates `status` against the post type
> *before* this filter fires, and the value you return is written straight to storage. Page HTML is
> rendered raw at build time by design, so sanitize-at-write is the only control there is:
>
> - if you modify `content_html`, run it through `klytos_kses_post()` yourself;
> - if you modify `status`, do not set a scheduled status without a valid `publish_at`;
> - `slug` is not re-sanitized either, and it is the storage key.
>
> This is not a new privilege — a plugin can already call `StorageInterface::write()` directly — but
> it is a place where the surrounding safety is behind you rather than ahead of you.

## `post_type.updatable_fields` — persisting your own keys on a post type

`PostTypeManager::update()` writes only the fields on an allow-list. A plugin that adds keys through
`admin.post_type_edit.update_data` must also declare them here, or they are dropped.

| | |
|---|---|
| Value | `array $fields` — the field names `update()` will persist |
| Context | `string $id` — the post type being updated |
| Returns | the `array` |
| Fired from | `installer/core/post-type-manager.php` |

```php
klytos_add_filter( 'post_type.updatable_fields', function ( array $fields ): array {
    $fields[] = 'my_plugin_setting';

    return $fields;
} );
```

**Omission denies.** A key that is not declared is not persisted — the same shape as `admin.gate_map`
(D-032) and `mcp.tool_capabilities` (D-048), so no plugin can widen this into mass assignment by
accident.

Stated plainly because it was a live defect, not a design note: before Sprint 4 this allow-list was
hardcoded, so `admin.post_type_edit.update_data` — a filter whose entire purpose is letting a plugin
add data to a post type — had its output silently dropped. x402 rendered a checkbox on the post-type
edit form that could never save.

## The exception

`Klytos\Core\HookContractException extends \RuntimeException`, thrown by `Hooks::addAction()` and
`Hooks::addFilter()`.

| Accessor | Returns |
|---|---|
| `getHookName()` | the hook the callback was being registered on |
| `getKind()` | `'action'` or `'filter'` |
| `getCallbackLocation()` | `file:line` of the callback, or `''` if it has no source location |

**A plugin carrying such a listener fails to load; it does not take the CMS down.**
`PluginLoader::loadPlugin()` wraps every plugin entry point in `try/catch (\Throwable)` and records a
named load error. Code registered directly by core is **not** covered by that catch, which is why
`tests/Unit/HooksTest.php` asserts that core registers no by-reference listener — a safety interlock,
not a style check.

## What this does not do

- It does not make actions able to modify anything. That is the point: actions observe.
- It does not inspect callbacks at dispatch time. The check runs once per registration
  (27 across the whole repository), so it costs nothing per fire.
- It does not detect a callback PHP cannot introspect (an exotic callable shape). Such a callback is
  registered as before — refusing something possibly valid would be worse than leaving the previous
  behaviour in place for a case that does not occur in this codebase.
