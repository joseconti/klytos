---
name: Klytos Integrity Check
description: File integrity verification system for Klytos CMS. Use when working with integrity checking, file hash verification, manifest generation, RSA signature verification, plugin trust levels, or the integrity admin page.
---

# Klytos Integrity Verification System

## Architecture Overview

The integrity system verifies that core and plugin files have not been modified, deleted, or injected. It compares local SHA-256 hashes against signed manifests from trusted sources.

### Three Trust Levels

| Level | Source | Manifest | Signature Key |
|-------|--------|----------|---------------|
| **Verified (Klytos)** | Core + Marketplace plugins | `api.klytos.io` | Klytos RSA key (`core/keys/klytos-integrity.pub`) |
| **Verified (Developer)** | External plugins with `Integrity URL` | Developer's server | Developer's RSA key (stored in `integrity-keys` collection) |
| **Unverified** | External plugins without `Integrity URL` | None | None |

---

## Core Files

| File | Purpose |
|------|---------|
| `core/integrity-checker.php` | Main `IntegrityChecker` class |
| `core/keys/klytos-integrity.pub` | RSA 4096 public key for verifying Klytos-signed manifests |
| `admin/system-integrity.php` | Admin page: System > Integrity |
| `admin/api/integrity.php` | JSON API endpoint for AJAX operations |
| `core/mcp/tools/integrity-tools.php` | MCP tools: `klytos_integrity_check`, `klytos_integrity_status`, `klytos_integrity_check_plugin` |

---

## IntegrityChecker Class

**Namespace:** `Klytos\Core\IntegrityChecker`
**Collection:** `integrity` (reports, manifest caches, batch state)
**Key Collection:** `integrity-keys` (developer public keys)

### Public Methods

```php
verify(bool $forceRefresh = false): array          // Full check (core + all plugins)
verifyBatch(): array                                // Batch mode for cron
verifyOnePlugin(string $pluginId, bool $force): array // Single plugin check
getLastReport(): ?array                             // Last stored report
registerDeveloperKey(string $pluginId, string $keyUrl): bool // Store dev PEM key
```

### Report Structure

```php
[
    'status'     => 'ok|warning|error',
    'checked_at' => '2026-04-02T10:00:00Z',
    'core'       => [
        'status'  => 'ok|warning|error',
        'checked' => 150,           // files verified
        'modified' => ['file.php'], // changed files
        'added'    => [],           // unauthorized files
        'missing'  => [],           // deleted files
        'version'  => '2.1.0',
    ],
    'plugins' => [
        'my-plugin' => [
            'status'  => 'ok|warning|error|unverified',
            'checked' => 23,
            // ... same modified/added/missing structure
        ],
    ],
    'summary' => [
        'total_plugins'  => 5,
        'plugins_ok'     => 3,
        'plugins_warning' => 1,
        'plugins_error'  => 0,
        'unverified'     => 1,
    ],
]
```

---

## Manifest Structure (JSON)

All manifests (core, marketplace, external) share the same format:

```json
{
    "type": "core|plugin",
    "id": "core|plugin-id",
    "version": "1.2.0",
    "generated_at": "2026-04-01T10:00:00Z",
    "algorithm": "sha256",
    "files": {
        "relative/path.php": "sha256-hash..."
    },
    "exclude": ["cache/*", "logs/*"],
    "signature": "base64-rsa-signature..."
}
```

### Signature Verification

1. Remove the `signature` field from the manifest.
2. JSON-encode the remaining object with `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
3. Verify with `openssl_verify($json, base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA256)`.

---

## Plugin Header Fields

Three new fields added to `PluginLoader::HEADER_MAP`:

```php
'Source'            => 'source',           // 'marketplace' | 'external'
'Integrity URL'     => 'integrity_url',    // URL template with {version} placeholder
'Integrity Key URL' => 'integrity_key_url', // URL to developer's PEM public key
```

Example plugin header:

```php
/**
 * Plugin Name: Premium SEO Tool
 * Version: 2.1.0
 * Source: external
 * Integrity URL: https://api.premiumdev.com/klytos/integrity/{version}.json
 * Integrity Key URL: https://api.premiumdev.com/klytos/integrity/public-key.pem
 */
```

---

## API Endpoints

### Admin API (`admin/api/integrity.php`)

| Method | Action | Description |
|--------|--------|-------------|
| GET | `?action=status` | Last integrity report |
| GET | `?action=report` | Detailed report |
| POST | `{"action":"verify"}` | Run full verification |
| POST | `{"action":"verify_force"}` | Force refresh manifests |
| POST | `{"action":"check_plugin","plugin_id":"x"}` | Check one plugin |

Requires authentication + `site.configure` permission + CSRF token.

### Remote API (`api.klytos.io`)

| Endpoint | Description |
|----------|-------------|
| `GET /integrity/core/{version}.json` | Core manifest |
| `GET /integrity/plugins/{id}/{version}.json` | Plugin manifest |
| `GET /integrity/public-key` | Klytos public key (PEM) |
| `GET /integrity/core/versions.json` | Available core versions |
| `GET /integrity/plugins/{id}/versions.json` | Available plugin versions |

---

## MCP Tools

```
klytos_integrity_check(force_refresh: bool)     — Full verification
klytos_integrity_status()                        — Last report (no new check)
klytos_integrity_check_plugin(plugin_id, force)  — Single plugin check
```

---

## Global Helper Functions

```php
klytos_integrity_check(bool $forceRefresh = false): array
klytos_integrity_status(): ?array
```

---

## Cron Integration

Task ID: `integrity_check`, interval: `daily`.
Uses `verifyBatch()` which processes files in batches of 100 (configurable).
If a batch doesn't complete, it schedules a continuation 5 minutes later.

---

## Hooks

| Hook | Type | When |
|------|------|------|
| `integrity.before_verify` | Action | Before full verification starts |
| `integrity.after_verify` | Action | After full verification completes (receives report) |

---

## Database Collections

- `integrity` — Reports, manifest caches, batch state, alerts
- `integrity-keys` — Developer public keys (stored at plugin install time)

---

## Key Security Notes

- The private key for Klytos signatures exists ONLY on the build server / api.klytos.io. NEVER distributed.
- `core/keys/klytos-integrity.pub` is included in the core manifest and verified (NOT excluded).
- Manifests are immutable — once published for a version, they never change.
- If a remote manifest changes without a version bump, an alert is generated (tampering detection).
- Developer keys are downloaded once at plugin install and stored locally. They do NOT auto-update (prevents key substitution attacks).
