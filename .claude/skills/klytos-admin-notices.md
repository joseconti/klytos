---
name: klytos-admin-notices
description: Guide for displaying admin notices in Klytos CMS. Use when showing success/error/warning/info messages to the admin user.
trigger: When the user needs to show admin notices, flash messages, alerts, success/error feedback, or persistent warnings in the Klytos admin panel.
---

# Klytos Admin Notices API

## When to Use This Skill

Use this reference when you need to display messages to admin users. Klytos provides two types of notices:

1. **Transient notices** — Flash messages shown once (e.g., "Settings saved", "Page deleted")
2. **Persistent notices** — Stored messages that survive across page loads until dismissed or a condition changes (e.g., "Indexing is disabled")

## Key Rules

- **Text-only**: All notice messages are plain text. HTML is automatically stripped via `strip_tags()`.
- **Type controls color**: Pass one of `success`, `error`, `warning`, `info` to control the visual style.
- **Dismissible flag**: `true` shows an X button (AJAX dismiss for persistent). `false` means no close button.
- **No inline handlers**: Dismiss JS uses `addEventListener`, never `onclick`.
- **CSP nonce**: The dismiss script is injected via the `admin.footer` hook with the proper nonce.

---

## 1. Transient Notices (Flash Messages)

Shown once on the current page load. Survive a single POST-redirect-GET cycle via the session.

### Function

```php
klytos_add_notice( string $message, string $type = 'info', bool $dismissible = true ): void
```

### Parameters

| Parameter      | Type   | Default | Description                              |
|---------------|--------|---------|------------------------------------------|
| `$message`     | string | —       | Plain text message (HTML stripped)        |
| `$type`        | string | `'info'` | `'success'`, `'error'`, `'warning'`, `'info'` |
| `$dismissible` | bool   | `true`  | Whether user can close with X button     |

### Examples

```php
// Success message after saving settings.
klytos_add_notice( __( 'settings.saved' ), 'success' );

// Error that cannot be dismissed.
klytos_add_notice( __( 'settings.save_failed' ), 'error', false );

// Info notice.
klytos_add_notice( 'Plugin activated successfully.', 'info' );

// Warning.
klytos_add_notice( 'This action cannot be undone.', 'warning' );
```

### Typical Usage in a Plugin

```php
// In your admin page handler (e.g., plugins/my-plugin/admin/settings.php):
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $result = save_my_settings( $_POST );
    if ( $result ) {
        klytos_add_notice( __( 'myplugin.settings_saved' ), 'success' );
    } else {
        klytos_add_notice( __( 'myplugin.settings_failed' ), 'error' );
    }
    // Redirect to avoid form resubmission — notice survives the redirect.
    header( 'Location: ' . $_SERVER['REQUEST_URI'] );
    exit;
}
```

---

## 2. Persistent Notices

Stored in the `notices` collection. Survive across page loads and sessions. Can be dismissed per-user (via session) or removed programmatically.

### Function

```php
klytos_add_persistent_notice(
    string $id,
    string $message,
    string $type = 'info',
    bool $dismissible = true,
    array $options = []
): array
```

### Parameters

| Parameter      | Type   | Default | Description                                  |
|---------------|--------|---------|----------------------------------------------|
| `$id`          | string | —       | Unique identifier (slug format)              |
| `$message`     | string | —       | Plain text message                           |
| `$type`        | string | `'info'` | `'success'`, `'error'`, `'warning'`, `'info'` |
| `$dismissible` | bool   | `true`  | Whether user can close with X button         |
| `$options`     | array  | `[]`    | Optional: `context`, `condition_hook`        |

### Options Array

| Key              | Type   | Description                                                |
|-----------------|--------|------------------------------------------------------------|
| `context`        | string | Only show on this admin page (e.g., `'dashboard'`, `'settings'`). Empty = all pages. |
| `condition_hook` | string | Filter hook name. Notice only renders when the filter returns `true`. |

### The `condition_hook` Pattern

This is the key innovation. A persistent notice declares a filter hook name. At render time, the Notice API calls `klytos_apply_filters( $condition_hook, true )`. If it returns `false`, the notice is silently skipped — no code needs to delete it.

```php
// Register a conditional persistent notice.
klytos_add_persistent_notice( 'ssl-missing', 'Your site is not using HTTPS.', 'error', false, [
    'condition_hook' => 'notice.condition.ssl_missing',
] );

// Register the condition filter.
klytos_add_filter( 'notice.condition.ssl_missing', function ( bool $show ): bool {
    return ! isset( $_SERVER['HTTPS'] ) || $_SERVER['HTTPS'] !== 'on';
} );
// Notice auto-hides when HTTPS is enabled — no code needs to remove it.
```

### Idempotent

`klytos_add_persistent_notice()` is idempotent by ID. Calling it multiple times with the same ID updates the existing notice instead of creating duplicates. Safe to call on every page load.

### Examples

```php
// Simple persistent notice (all pages, dismissible).
klytos_add_persistent_notice( 'welcome', 'Welcome to Klytos! Start by creating your first page.', 'info' );

// Page-specific, non-dismissible.
klytos_add_persistent_notice( 'no-pages', 'No pages published yet.', 'warning', false, [
    'context' => 'dashboard',
] );

// Conditional: only shows when a config value is missing.
klytos_add_persistent_notice( 'missing-email', 'Please configure your email settings.', 'warning', true, [
    'context'        => 'settings',
    'condition_hook' => 'notice.condition.missing_email',
] );

klytos_add_filter( 'notice.condition.missing_email', function ( bool $show ): bool {
    $config = klytos_app()->getSiteConfig()->get();
    return empty( $config['email_from'] ?? '' );
} );
```

---

## 3. Dismissing Notices

### Programmatically

```php
klytos_dismiss_notice( string $id ): void
```

Dismisses a persistent notice for the current user session. The notice stays in storage but won't render for this user until the session expires.

### Via AJAX (Automatic)

Dismissible notices render an X button. Clicking it:
1. Fades out the notice element with a CSS transition.
2. Sends a `POST` to `/admin/api/notices.php` with `action=dismiss` and the notice `id`.
3. The server stores the dismiss in the session.

No plugin code needed — this is handled automatically.

---

## 4. Getting Notices

```php
klytos_get_notices( string $currentPage = '' ): array
```

Returns all notices that should be rendered on the current page. Handles transient, session flash, and persistent notices. Filters by context, condition hooks, and dismiss state.

---

## 5. Manager Access (Advanced)

For advanced use cases, access the NoticeManager directly:

```php
$manager = klytos_app()->getNoticeManager();

// Create a persistent notice with full control.
$manager->create( [
    'id'             => 'my-notice',
    'message'        => 'Custom notice text.',
    'type'           => 'warning',
    'dismissible'    => true,
    'context'        => 'dashboard',
    'condition_hook' => 'notice.condition.my_custom',
] );

// Delete a persistent notice from storage entirely.
$manager->delete( 'my-notice' );

// List all persistent notices.
$notices = $manager->list();

// Ensure a system notice exists (idempotent create/update).
$manager->ensureSystemNotice( 'sys-check', [
    'message'     => 'System check warning.',
    'type'        => 'error',
    'dismissible' => false,
] );
```

---

## 6. Hooks & Filters

### Actions

| Hook                | Fired When                       | Arguments        |
|--------------------|----------------------------------|------------------|
| `notice.created`    | Persistent notice created        | `array $notice`  |
| `notice.dismissed`  | Notice dismissed                 | `string $id`     |
| `notice.deleted`    | Persistent notice deleted        | `string $id`     |
| `notice.render.before` | Before rendering notices      | `array $notices` |
| `notice.render.after`  | After rendering notices       | `array $notices` |

### Filters

| Hook                    | Purpose                                   | Arguments                    |
|------------------------|-------------------------------------------|------------------------------|
| `notice.transient.add`  | Modify transient notice before queuing    | `array $notice`              |
| `notice.before_render`  | Add/remove/reorder notices before render  | `array $notices, string $page` |
| `notice.render_html`    | Customize per-notice HTML output          | `string $html, array $notice` |
| `{condition_hook}`       | Control conditional persistent notices    | `bool $show`                 |

### Example: Add a notice from a plugin hook

```php
klytos_add_filter( 'notice.before_render', function ( array $notices, string $page ): array {
    if ( $page === 'settings' ) {
        $notices[] = [
            'id'          => 'plugin-tip',
            'message'     => 'Tip: Configure your social media links below.',
            'type'        => 'info',
            'dismissible' => true,
            'persistent'  => false,
        ];
    }
    return $notices;
} );
```

---

## 7. CSS Classes

Notices use the existing Klytos alert component classes:

| Class               | Purpose                           |
|---------------------|-----------------------------------|
| `.alert`            | Base alert styling                |
| `.alert-success`    | Green (success tokens)            |
| `.alert-error`      | Red (error tokens)                |
| `.alert-warning`    | Amber (warning tokens)            |
| `.alert-info`       | Blue (info tokens)                |
| `.alert-dismissible`| Flex layout with space for X btn  |
| `.alert-close`      | The X dismiss button              |

Colors come from `--klytos-{type}-subtle` (background), `--klytos-{type}-text` (text), defined in `klytos-tokens.css`.

---

## 8. AJAX Endpoint

**URL**: `/admin/api/notices.php`

| Method | Action    | Parameters | Description                |
|--------|-----------|------------|----------------------------|
| `GET`  | —         | `?page=`   | List renderable notices    |
| `POST` | `dismiss` | `id`       | Dismiss a persistent notice |

Requires authentication and CSRF token.

---

## Quick Reference

```php
// Flash notice (shown once).
klytos_add_notice( 'Settings saved!', 'success' );

// Persistent notice (survives page loads).
klytos_add_persistent_notice( 'my-warning', 'Check your config.', 'warning' );

// Conditional persistent (auto-hides when condition is false).
klytos_add_persistent_notice( 'check-ssl', 'HTTPS not enabled.', 'error', false, [
    'condition_hook' => 'notice.condition.no_ssl',
] );
klytos_add_filter( 'notice.condition.no_ssl', fn( bool $show ) => ! is_ssl() );

// Dismiss programmatically.
klytos_dismiss_notice( 'my-warning' );

// Delete from storage.
klytos_app()->getNoticeManager()->delete( 'my-warning' );
```
