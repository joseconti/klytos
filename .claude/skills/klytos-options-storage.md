---
name: klytos-options-storage
description: Guide for saving and retrieving plugin options and site configuration in Klytos CMS. Use when a plugin needs persistent settings.
trigger: When the user needs to save plugin settings, read options, manage site configuration, or store persistent key-value data.
---

# Klytos Options & Storage

## When to Use This Skill

Use this reference when your plugin needs to store and retrieve settings or configuration. Klytos provides three storage mechanisms:

1. **Options API** — Simple key-value pairs for plugin settings (most common)
2. **Site Config** — Global site configuration (name, language, social, SEO)
3. **Storage API** — Complex data collections (for advanced use cases)

---

## 1. Options API (Plugin Settings)

The primary way for plugins to store settings. Key-value pairs stored in the `'options'` collection with built-in request-level caching.

### Functions

```php
klytos_get_option(string $key, mixed $default = null): mixed
```
Get an option. Returns `$default` if not found.

```php
klytos_set_option(string $key, mixed $value): void
```
Create or update an option. Value must be JSON-serializable.

```php
klytos_delete_option(string $key): bool
```
Delete an option. Returns `true` if it existed.

```php
klytos_option_exists(string $key): bool
```
Check if an option exists.

### Key Naming Convention

**Format**: `plugin_id.setting_name`

```php
// Good — namespaced with plugin ID
klytos_set_option('my-gallery.columns', 3);
klytos_set_option('my-gallery.theme', 'dark');
klytos_set_option('seo-pro.sitemap_enabled', true);

// Bad — no namespace, risk of collision
klytos_set_option('columns', 3);
klytos_set_option('enabled', true);
```

**Allowed characters**: alphanumeric, dots, hyphens, underscores.
**Rejected**: empty keys, keys starting with `_` (reserved).

### Examples

```php
// Save settings
klytos_set_option('my-plugin.api_key', 'sk-xxxxx');
klytos_set_option('my-plugin.max_items', 50);
klytos_set_option('my-plugin.features', [
    'export' => true,
    'import' => false,
    'notifications' => true,
]);

// Read settings
$apiKey   = klytos_get_option('my-plugin.api_key', '');
$maxItems = klytos_get_option('my-plugin.max_items', 25);
$features = klytos_get_option('my-plugin.features', []);

// Check existence
if (klytos_option_exists('my-plugin.api_key')) {
    // API key is configured
}

// Delete
klytos_delete_option('my-plugin.deprecated_setting');
```

### Bulk Operations (OptionsManager Methods)

```php
$options = klytos_app()->getOptionsManager();

// Get ALL options for a plugin
$allSettings = $options->getForPlugin('my-plugin');
// ['my-plugin.api_key' => 'sk-xxx', 'my-plugin.max_items' => 50, ...]

// Delete ALL options for a plugin (use in uninstall.php)
$count = $options->deleteForPlugin('my-plugin');
// Returns number of options deleted
```

### Text Domain Tracking

Every option is tagged with a `text_domain` field identifying its owner. This happens automatically when a plugin calls `klytos_set_option()` — the PluginLoader injects the active text domain.

```php
// Automatic: text_domain is inferred from the active plugin context
klytos_set_option('my-gallery.columns', 3);
// Record: { key: "my-gallery.columns", value: 3, text_domain: "my-gallery", ... }

// Explicit: set with a specific text domain
klytos_set_option_for('_core', 'site.maintenance', false);

// Query by text domain
$allGalleryOptions = klytos_get_options_by_domain('my-gallery');

// Delete all options for a text domain
$deleted = klytos_delete_options_by_domain('old-plugin');
```

#### Text Domain Methods (OptionsManager)

```php
$options = klytos_app()->getOptionsManager();

// Get/delete by text domain
$records = $options->getByTextDomain('my-plugin');
$count   = $options->deleteByTextDomain('my-plugin');
$count   = $options->countByTextDomain('my-plugin');

// List grouped by text domain
$grouped = $options->listGroupedByTextDomain();
// ['_core' => [...], 'my-plugin' => [...], '_unknown' => [...]]

// Classify by plugin status
$domains    = $app->getPluginLoader()->getTextDomainsByStatus();
$classified = $options->classifyOptions($domains['active'], $domains['inactive']);
// ['core' => [...], 'active' => [...], 'inactive' => [...], 'orphan' => [...], 'unknown' => [...]]

// Migrate legacy options without text_domain
$migrated = $options->migrateTextDomains();
```

### Hooks

| Hook | Type | Arguments |
|---|---|---|
| `option.before_set` | action | `string $key, mixed $value, mixed $oldValue` |
| `option.after_set` | action | `string $key, mixed $value, mixed $oldValue` |
| `option.before_delete` | action | `string $key` |
| `option.after_delete` | action | `string $key` |
| `option.get` | filter | `mixed $value, string $key` |

```php
// Example: Log when options change
klytos_add_action('option.after_set', function (string $key, mixed $value): void {
    klytos_log('info', "Option updated: {$key}");
});

// Example: Transform option on retrieval
klytos_add_filter('option.get', function (mixed $value, string $key): mixed {
    if ($key === 'my-plugin.mode' && $value === null) {
        return 'default'; // Override null with default
    }
    return $value;
});
```

---

## 2. Site Configuration (Global Settings)

For global site-level settings. Do NOT use this for plugin settings — use the Options API instead.

### Reading Config

```php
klytos_config(string $key, mixed $default = null): mixed
```

Supports dot-notation for nested values:

```php
$siteName = klytos_config('site_name');                      // 'My Site'
$language = klytos_config('default_language', 'en');          // 'es'
$twitter  = klytos_config('social.twitter');                  // '@handle'
$gaId     = klytos_config('analytics.google_analytics_id');   // 'G-XXXXXXX'
$ogImage  = klytos_config('seo.default_og_image');            // '/assets/images/og.jpg'
```

### Writing Config

```php
klytos_set_config(string $key, mixed $value): void
```

**Note**: Top-level keys only (no dot-notation for writing). Use sparingly.

### Full Config Structure

```php
[
    'site_name'        => 'My Klytos Site',
    'tagline'          => 'Built with AI',
    'default_language' => 'es',
    'description'      => 'Site description',
    'favicon_url'      => '/assets/images/favicon.ico',
    'logo_url'         => '/assets/images/logo.svg',
    'indexing_enabled' => true,
    'editor'           => 'gutenberg',
    'admin_theme'      => 'dark',        // 'light' or 'dark'

    'social' => [
        'twitter'   => '',
        'github'    => '',
        'linkedin'  => '',
        'instagram' => '',
        'youtube'   => '',
        'mastodon'  => '',
    ],

    'analytics' => [
        'google_analytics_id' => '',
        'custom_head_scripts' => '',
        'custom_body_scripts' => '',
    ],

    'seo' => [
        'default_og_image' => '',
        'robots_txt_extra' => '',
    ],

    'email' => [
        'transport'     => 'mail',       // 'mail' or 'smtp'
        'from_name'     => '',           // Falls back to site_name
        'from_email'    => '',           // Falls back to noreply@domain
        'reply_to'      => '',
        'smtp_host'     => '',
        'smtp_port'     => 587,
        'smtp_user'     => '',
        'smtp_pass'     => '',
        'smtp_security' => 'tls',        // 'tls', 'ssl', ''
    ],

    'languages'  => ['es', 'en'],
    'last_build' => '2026-03-31T10:00:00+02:00',
]
```

### Via MCP

- **`klytos_get_site_config`** — Returns full config
- **`klytos_set_site_config`** — Partial update (only provided fields change)
- **`klytos_options_list_by_domain`** — List options for a text domain
- **`klytos_options_classify`** — Classify options by plugin status
- **`klytos_options_delete_domain`** — Delete all options for a text domain
- **`klytos_options_migrate`** — Migrate legacy options without text_domain

---

## 3. Storage API (Complex Data)

For complex plugin data that doesn't fit key-value. Uses the abstracted storage layer (FileStorage or DatabaseStorage).

```php
$storage = klytos_storage();

// Write a data record
$storage->write('my-plugin-data', 'record-1', [
    'name'    => 'Campaign A',
    'status'  => 'active',
    'clicks'  => 1543,
    'created' => date('c'),
]);

// Read it back
$record = $storage->read('my-plugin-data', 'record-1');

// List all records (with optional filters)
$all = $storage->list('my-plugin-data', ['status' => 'active']);

// Count records
$count = $storage->count('my-plugin-data', ['status' => 'active']);

// Search
$results = $storage->search('my-plugin-data', 'Campaign', ['name']);

// Delete
$storage->delete('my-plugin-data', 'record-1');

// Check existence
$exists = $storage->exists('my-plugin-data', 'record-1');

// Transaction (atomic operations)
$storage->transaction(function ($storage) {
    $storage->write('my-data', 'a', ['count' => 1]);
    $storage->write('my-data', 'b', ['count' => 2]);
});
```

### Collection Naming Convention

Use your plugin ID as the collection prefix:
- `my-plugin-data` — Main data
- `my-plugin-cache` — Cached data
- `my-plugin-logs` — Plugin-specific logs

---

## When to Use Each System

| Scenario | System | Why |
|---|---|---|
| Plugin toggle (on/off) | Options API | Simple key-value |
| Plugin API key | Options API | Single value, namespaced |
| List of 5 color presets | Options API | Small JSON array |
| 1000+ product records | Storage API | Complex, queryable data |
| Site name, language | Site Config | Global, not plugin-specific |
| Analytics settings | Site Config | Site-wide configuration |
| Cache of external API data | Storage API | Large, structured data |
| User preferences per user | Meta API | Per-entity metadata |

### Rule of Thumb

- **Options API**: Simple settings (strings, numbers, booleans, small arrays)
- **Site Config**: Global site settings (read mostly, write rarely)
- **Storage API**: Complex data with CRUD operations, lists, searches
- **Meta API**: Data attached to existing entities (pages, users)

---

## Cleanup on Uninstall

When your plugin is uninstalled, clean up all stored data:

```php
// plugins/my-plugin/uninstall.php
<?php
declare(strict_types=1);

$app = \Klytos\Core\App::getInstance();

// Delete all options
$app->getOptionsManager()->deleteForPlugin('my-plugin');

// Delete storage collections
$storage = $app->getStorage();
$records = $storage->list('my-plugin-data');
foreach ($records as $record) {
    $storage->delete('my-plugin-data', $record['id'] ?? $record['slug'] ?? '');
}

// Delete meta on all pages
// (Only if your plugin stored meta on pages)
```

---

## Source Files

- Options manager: `core/options-manager.php`
- Site config: `core/site-config.php`
- Storage interface: `core/storage-interface.php`
- File storage: `core/file-storage.php`
- Database storage: `core/database-storage.php`
- Global option functions: `core/helpers-global.php`
- MCP option tools: `core/mcp/tools/option-tools.php`
- Admin panel: `admin/system-options.php`
- Admin API: `admin/api/options-management.php`
