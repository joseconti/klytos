---
name: klytos-escape-and-sanitization
description: Complete guide for escape and sanitization functions in Klytos CMS. Use when outputting data to HTML, processing user input, filtering HTML content, implementing CSRF protection, validating data, or protecting forms against CSRF attacks. Essential for secure output escaping, input sanitization, HTML filtering with KSES, and form security.
---

# Klytos Escape & Sanitization Reference

## When to Use This Skill

The golden rules:
- **ALWAYS escape output** before rendering in HTML, attributes, URLs, or JavaScript.
- **ALWAYS sanitize input** before storing or processing user-provided data.
- **NEVER trust external data** — treat everything from `$_POST`, `$_GET`, MCP params, and API inputs as untrusted.

---

## Output Escaping Functions

### klytos_esc_html — For HTML Body Content

```php
klytos_esc_html(string $text): string
```
Converts `&`, `<`, `>`, `"`, `'` to HTML entities. Safe against double-encoding.

```php
<p><?php echo klytos_esc_html($userInput); ?></p>
```

### klytos_esc_attr — For HTML Attributes

```php
klytos_esc_attr(string $text): string
```
Same as `esc_html` but also strips tabs, newlines, carriage returns to prevent attribute-injection.

```php
<input type="text" value="<?php echo klytos_esc_attr($value); ?>">
```

### klytos_esc_url — For URLs (href, src)

```php
klytos_esc_url(string $url, array $protocols = ['http', 'https', 'mailto', 'tel']): string
```
Validates protocol allowlist. **Rejects** `javascript:`, `data:`, `vbscript:`. Returns empty string if invalid.

```php
<a href="<?php echo klytos_esc_url($link); ?>">
```

### klytos_esc_js — For JavaScript String Literals

```php
klytos_esc_js(string $string): string
```
Escapes `'`, `"`, `\`, newlines, and `</script>`.

```php
<script>var name = '<?php echo klytos_esc_js($name); ?>';</script>
```

### klytos_esc_textarea — For Textarea Content

```php
klytos_esc_textarea(string $text): string
```

### Quick Reference

| Context | Function |
|---|---|
| Inside `<p>`, `<h1>`, `<span>` | `klytos_esc_html()` |
| Inside `value=""`, `data-*=""` | `klytos_esc_attr()` |
| Inside `href=""`, `src=""` | `klytos_esc_url()` |
| Inside `<script>` string | `klytos_esc_js()` |
| Inside `<textarea>` | `klytos_esc_textarea()` |

---

## Input Sanitization Functions

```php
klytos_sanitize_text(string $text): string        // Strip tags, normalize whitespace, trim
klytos_sanitize_email(string $email): string      // Validate + lowercase (empty if invalid)
klytos_sanitize_url(string $url): string          // Strip control chars, reject dangerous protocols
klytos_sanitize_filename(string $name): string    // basename, replace unsafe chars, fallback 'unnamed'
klytos_sanitize_key(string $key): string          // Lowercase a-z0-9_- only
klytos_sanitize_title(string $title): string      // Delegates to sanitizeSlug()
klytos_sanitize_html(string $html): string        // Strip dangerous tags, remove event handlers
klytos_sanitize_int(mixed $value): int            // Cast to safe int
klytos_sanitize_float(mixed $value): float        // Cast to safe float
```

---

## HTML Filtering (KSES)

### klytos_kses — Custom Tag Allowlist

```php
klytos_kses(string $html, array $allowedTags): string
```

```php
$safe = klytos_kses($input, [
    'a'      => ['href' => true, 'title' => true],
    'strong' => [],
    'em'     => [],
    'p'      => ['class' => true],
]);
```

### klytos_kses_post — Page Content Allowlist

```php
klytos_kses_post(string $html): string
```

**Allowed** (~40 tags): h1-h6, p, br, hr, a, img, ul, ol, li, table, thead, tbody, tr, th, td, strong, em, b, i, u, s, blockquote, pre, code, span, div, section, article, header, footer, nav, main, aside, figure, figcaption, video, audio, source, details, summary, mark, small, sub, sup, dl, dt, dd.

**Excluded**: script, style, iframe, form, object, embed, svg.

**Extendable** via `kses_post_allowed_tags` filter.

---

## CSRF Protection

```php
klytos_csrf_field(): string       // Returns <input type="hidden" name="csrf" value="...">
klytos_verify_csrf(): bool        // Checks POST['csrf'], X-CSRF-Token header, GET['csrf']
```

```php
<form method="POST">
    <?php echo klytos_csrf_field(); ?>
    <button type="submit">Save</button>
</form>

// Processing:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf()) {
    // Safe to process
}
```

**IMPORTANT**: Every POST form in admin MUST include CSRF protection.

---

## Validation Helpers

```php
klytos_is_email(string $email): bool    // filter_var(FILTER_VALIDATE_EMAIL)
klytos_is_url(string $url): bool        // Valid http/https URL
```

---

## Complete Example: Secure Form

```php
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf()) {
    $name  = klytos_sanitize_text($_POST['name'] ?? '');
    $email = klytos_sanitize_email($_POST['email'] ?? '');
    $count = klytos_sanitize_int($_POST['count'] ?? 0);

    if ($name === '') { $error = 'Name required'; }
    elseif (!klytos_is_email($email)) { $error = 'Invalid email'; }
    else {
        klytos_set_option('my-plugin.name', $name);
        klytos_set_option('my-plugin.email', $email);
        $success = 'Saved';
    }
}
$name = klytos_get_option('my-plugin.name', '');
?>

<form method="POST">
    <?php echo klytos_csrf_field(); ?>
    <input name="name" value="<?php echo klytos_esc_attr($name); ?>">
    <button type="submit" class="btn btn-primary">Save</button>
</form>
```

---

## Source Files

- Escape/sanitization: `core/helpers.php` (lines 423-944)
- Global wrappers: `core/helpers-global.php`
- KSES tags: `core/helpers.php` (line 709)
- CSRF: `core/helpers.php` (lines 913-944)
