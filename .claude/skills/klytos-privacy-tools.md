# Klytos Privacy Tools (GDPR)

## Overview

Privacy Tools provide GDPR compliance for data subject requests:
- **Data Export** (Right of Access, Art. 15) — export all personal data for a user
- **Data Erasure** (Right to Erasure, Art. 17) — erase or anonymize personal data

## Architecture

### Core Class: `PrivacyManager` (`installer/core/privacy-manager.php`)

**Namespace:** `Klytos\Core`
**Registered in:** `App::boot()` → `$app->getPrivacyManager()`

### Key Methods

| Method | Purpose |
|---|---|
| `findUser( string $query ): ?array` | Search by username or email |
| `collectUserData( string $userId ): array` | Gather all personal data (core + plugins) |
| `exportAsJson( string $userId ): string` | JSON export |
| `exportAsHtml( string $userId ): string` | Self-contained HTML report |
| `collectErasableData( string $userId ): array` | Sections with erasable/retained flags |
| `eraseUserData( string $userId, array $sections, string $currentUserId ): array` | Erase selected sections |

### Core Data Sources

| Collection | Export | Erasure Strategy |
|---|---|---|
| `users` | Profile (no pass_hash) | Anonymize (random username/email, "Deleted User") |
| `audit-log` | Activity entries | Anonymize (username → [anonymized], IP → 0.0.0.0) |
| `form-submissions` | Matched by email | Delete |
| `tasks` | Matched by assigned_to/created_by | Anonymize (null references) |
| `analytics` | **Excluded** | N/A (daily-salted hashes, not personal data) |

### Protection Rules

- Owner account cannot be erased
- Logged-in user cannot erase themselves
- Plugin sections with `erasable => false` are skipped

## Admin Page: `installer/admin/privacy.php`

Two-tab interface (same pattern as MCP page):
- Tab 1: **Data Export** — search → preview → download JSON/HTML or email
- Tab 2: **Data Erasure** — search → select sections → erase with confirmation

Sidebar position: 67 (between Consent Manager at 66 and Users at 70)
Capability: `users.manage`
Icon: `fa-solid fa-user-shield`

## Plugin Hook API

### Filters (plugins participate in export/erasure)

```php
// Append data sections to export
klytos_add_filter( 'privacy.export_data', function ( array $data, string $userId, array $user ): array {
    $data['sections'][] = [
        'source' => 'my-plugin',
        'label'  => 'My Data',
        'count'  => 5,
        'data'   => [...],
    ];
    return $data;
} );

// Declare erasable/retained sections
klytos_add_filter( 'privacy.erasable_data', function ( array $sections, string $userId, array $user ): array {
    $sections[] = [
        'id'               => 'my-plugin:orders',
        'source'           => 'my-plugin',
        'label'            => 'Orders',
        'erasable'         => false,
        'retention_reason' => 'Tax compliance (7 years)',
        'item_count'       => 12,
    ];
    return $sections;
} );

// Perform plugin-specific erasure
klytos_add_filter( 'privacy.erase_plugin_data', function ( array $results, string $userId, array $selectedSections ): array {
    if ( in_array( 'my-plugin:wishlists', $selectedSections, true ) ) {
        // delete wishlists...
        $results[] = ['section' => 'my-plugin:wishlists', 'status' => 'deleted', 'count' => 3];
    }
    return $results;
} );
```

### Actions

| Hook | Arguments | When |
|---|---|---|
| `privacy.export_generated` | `$userId, $format` | After export generated |
| `privacy.export_sent` | `$userId` | After email sent |
| `privacy.before_erasure` | `$userId, $selectedSections` | Before erasure |
| `privacy.erasure_complete` | `$userId, $results` | After erasure done |
| `privacy.export_html_sections` | `$html, $data` (filter) | Customize HTML export |

## i18n Keys

All keys under `privacy.*` in `en.json` / `es.json`. Key examples:
- `privacy.title`, `privacy.tab_export`, `privacy.tab_erasure`
- `privacy.export_title`, `privacy.erasure_title`
- `privacy.section_profile`, `privacy.section_audit_log`, etc.
- `privacy.owner_cannot_erase`, `privacy.self_cannot_erase`
- `privacy.legally_retained`, `privacy.deleted_user`

## Files

| Purpose | Path |
|---|---|
| Core class | `installer/core/privacy-manager.php` |
| Admin page | `installer/admin/privacy.php` |
| Sidebar entry | `installer/admin/templates/sidebar.php` (position 67) |
| App registration | `installer/core/app.php` (property + boot + getter) |
| English i18n | `installer/core/lang/en.json` (`privacy.*`) |
| Spanish i18n | `installer/core/lang/es.json` (`privacy.*`) |
