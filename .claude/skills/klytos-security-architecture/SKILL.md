---
name: klytos-security-architecture
description: Security architecture and best practices for Klytos CMS. Use when dealing with authentication, encryption, access control, CSRF protection, rate limiting, security headers, HTTPS, or security hardening. Essential for secure development and understanding Klytos security model.
---

# Klytos Security Architecture

## Secret Admin URL

**CRITICAL**: The admin panel URL is SECRET. It must NEVER be discoverable from the public-facing site.

### Directory Structure
```
/                           ← Web root (public-facing)
├── index.html              ← Redirect or landing page
├── assets/                 ← Public assets (CSS, JS, images, fonts)
│   ├── css/
│   ├── js/
│   ├── images/
│   └── fonts/
├── sitemap.xml             ← Search engine sitemap
├── robots.txt              ← Crawler directives
├── llms.txt                ← AI indexing summary
├── llms-full.txt           ← AI indexing full content
│
└── {random-admin-name}/    ← SECRET admin directory (e.g. "x7k9m2-panel")
    ├── .htaccess           ← Routes all requests, blocks sensitive dirs
    ├── index.php           ← Front controller
    ├── install.php         ← Installer (renamed after use)
    ├── t.php               ← Analytics pixel
    ├── config/             ← BLOCKED by .htaccess
    ├── core/               ← BLOCKED by .htaccess
    ├── data/               ← BLOCKED by .htaccess
    ├── backups/            ← BLOCKED by .htaccess
    ├── plugins/            ← PHP blocked, assets allowed
    ├── admin/              ← Admin panel (requires auth)
    ├── public/             ← Generated static site (served via .htaccess)
    └── templates/          ← HTML templates (BLOCKED)
```

### Security Rules

1. **No admin URL leaks**: Generated HTML pages NEVER contain references to the admin URL.
   - No admin links in HTML source.
   - No admin paths in CSS/JS URLs.
   - No admin references in meta tags.
   - The `<meta name="generator">` says "Klytos" but NOT the admin path.

2. **Public assets are separate**: CSS, JS, images, and fonts for the public site
   live in `/assets/` at the web root, NOT inside the admin directory.

3. **Build output goes to root**: The build engine writes HTML pages to the web root
   and assets to `/assets/`. The admin directory is never exposed.

4. **Admin URL is configured during installation**: The directory name is chosen by
   the user or auto-generated. It should be random and non-guessable.

## Encryption

- **Algorithm**: AES-256-GCM (authenticated encryption with associated data).
- **Key**: 256-bit (32 bytes) generated with `random_bytes(32)` (CSPRNG).
- **IV**: 12 bytes, random per encryption (never reused).
- **Authentication tag**: 16 bytes (GCM built-in — prevents tampering).
- **Key storage**: `config/.encryption_key` with chmod 0600.
- **Key rotation**: Supported via `Encryption::rotateKey()`.

### Encryption Levels

The site admin chooses an encryption level during installation. It determines which data is encrypted at rest:

| Level | What is encrypted |
|---|---|
| **Basic** | System config only (config.json.enc, license, AI keys, MCP tokens) |
| **Medium** | + Users, audit logs, sessions, chats, 2FA (GDPR-relevant data) |
| **Professional** | + ALL data (pages, blocks, templates, theme, menus, forms, logs, etc.) |

The level can be changed bidirectionally from Settings > Security (requires re-auth).

### Option-Level Sensitivity

Plugins declare the sensitivity of their options via `klytos_register_option()`. This provides per-option encryption control independent of the site-wide encryption level:

| Sensitivity | Encrypted at | Example |
|---|---|---|
| `true` | **Always** (all levels) | API keys, tokens, webhook secrets |
| `'user_data'` | Medium + Professional | Emails, IPs, personal data (GDPR) |
| `false` (default) | Professional only | Colors, toggles, non-sensitive settings |

```php
klytos_register_option('my-plugin.stripe_key', true);       // Always encrypted
klytos_register_option('my-plugin.user_email', 'user_data'); // Encrypted from medium
klytos_register_option('my-plugin.color', false);            // Only at professional
```

### Identity Keys (RSA-2048)

- Admin identity is proven via an RSA-2048 key pair generated during installation.
- **Public key**: stored in `config/admin-identity.pub.enc` (encrypted with AES).
- **Private key**: stored in `config/admin-identity.priv.enc` (encrypted with AES).
- **Recovery file**: `klytos-identity.pem` — downloaded during installation, used with `klytos-encryption.key` for emergency access recovery via the unified installer.
- **Challenge-response**: The installer verifies identity by signing 32 random bytes with the private key and verifying with the public key.

## Authorization — the central admin gate

Every file under `installer/admin/` requires `admin/bootstrap.php`, which calls
`klytos_enforce_admin_gate()` once. The gate looks the running file up in the **gate map**
(`installer/core/admin-gate.php`) and requires the mapped capability.

**A surface absent from the map is refused.** Before this (audit S-07), each of the 66 admin files
was individually responsible for remembering its own check and 51 did not — so a new admin page
defaulted to *open*. It now defaults to *closed*, and `scripts/keel-verify` fails the build when a
file has no entry.

- One decision point: `UserManager::hasPermission()`. `klytos_has_permission()` answers,
  `klytos_require_permission()` enforces, both delegate there. **Never** hand-roll
  `in_array( $role, [ 'owner', 'admin' ] )` — that is a second decision point and gets removed.
- The key is derived from `SCRIPT_FILENAME` (the file PHP executed), never `SCRIPT_NAME`
  (URL-derived, caller-influenced). A path resolving outside `admin/` yields no key and is denied.
- `bootstrap.php` is deliberately unmapped, so a direct request for it default-denies.
- Refusals are shaped for the caller: **JSON 401/403** for `admin/api/*` and MCP, an escaped
  self-contained **HTML 403** for pages, stderr for CLI. The API half matters — an XHR that receives
  an HTML login page gets a parse error instead of a status it can act on.
- `null` in the map means "no capability required" and is the audited exception list. It does **not**
  mean unauthenticated: the auth guard runs first and separately.

Full reference, including the matrix and the "adding a new admin page" checklist:
`docs/reference/authorization.md`.

> **Known gap, stated rather than implied:** all 172 MCP tools still have **zero** permission checks
> (audit NEW-02) until Sprint 2. The admin surface is gated; the product's primary interface is not.
> Separately, `Auth::login()` validates only against `config['admin_user']`, so `admin`, `editor` and
> `viewer` accounts cannot log in through the form at all (audit NEW-11).

## Authentication Methods (MCP)

Order of authentication in `token-auth.php`:
1. **Bearer token**: `Authorization: Bearer <token>` → tokens.json.enc
2. **OAuth 2.0/2.1 access token**: `Authorization: Bearer <token>` → oauth_tokens.json.enc
3. **Application Password (Basic Auth)**: `Authorization: Basic base64(user:pass)` → app_passwords.json.enc

## OAuth 2.1 Compliance

- PKCE mandatory for ALL clients (S256 only, plain rejected).
- No Implicit Grant (response_type=token rejected).
- No Resource Owner Password Credentials (grant_type=password rejected).
- Refresh token rotation (one-time use).
- Redirect URI exact string match (no wildcards).
- Bearer tokens in Authorization header only (query params rejected).

## Password Security

- Algorithm: bcrypt with cost factor 12.
- Minimum length: 12 characters.
- Stored as hash only — NEVER in cleartext.
- Application passwords: 24 chars, format xxxx-xxxx-xxxx-xxxx-xxxx-xxxx.

## Rate Limiting

- Sliding window: 60 seconds.
- MCP requests: 60/minute per authenticated identifier.
- Auth failures: 10/minute per IP.
- Admin login: 5 attempts → 15 minute lockout.

## CSRF Protection

- Token: 32 hex characters, per-session.
- Required on ALL admin POST forms.
- Validated via `$auth->validateCsrf($token)`.

## Security Headers

Decided in ONE place — `Auth::sendSecurityHeaders()` — and sent from ONE place
per entry point. `admin/bootstrap.php` calls it once, and every admin page and
API endpoint requires that bootstrap, so no surface can forget. Full contract:
`docs/reference/security-headers.md`.

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Strict-Transport-Security: max-age=31536000     # HTTPS responses ONLY
Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-<per-request>'; …
Permissions-Policy: camera=(), microphone=(), geolocation=()
```

**Rules when writing admin code:**

- **Never add a call to `sendSecurityHeaders()` in a new admin file.** The
  bootstrap already sent them. A second call is only correct when the page
  needs its own policy, and then it passes `$customCsp`.
- **The CSP fails closed.** No nonce means `script-src 'self'` — inline script
  is refused, not waved through. Every inline `<script>` needs
  `nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>"`.
- **There is ONE nonce per request**, in `$GLOBALS['klytos_csp_nonce']`. Reuse
  it; never call `generateCspNonce()` in a page. A second nonce would not match
  the header that was already sent, and the browser would refuse the script.
- **No inline `onclick=` / `onchange=`** — a nonce-based CSP refuses them. Use
  `addEventListener` inside a nonced block.
- **HSTS is HTTPS-only and carries no `includeSubDomains`**, by decision
  (D-044): the directive is cached for a year and would force HTTPS onto
  subdomains that belong to the operator, not to Klytos. Widen it via the
  `security.hsts` filter, not by editing core.
- **`style-src` still allows `'unsafe-inline'`** while audit S-10 is open (349
  inline `style=` attributes cannot carry a nonce). Do **not** "improve" this by
  adding a nonce source to `style-src`: per CSP Level 3 that makes browsers
  IGNORE `'unsafe-inline'` and would break all 349 at once.
- **The public front controller is different.** `installer/index.php` keeps
  `script-src 'unsafe-inline'` explicitly, because it serves pre-generated
  static HTML containing inline scripts (the consent banner) that cannot hold a
  per-request nonce. Tracked as NEW-23.

## .htaccess Protection

Blocks direct access to:
- `config/` (encryption keys, credentials)
- `core/` (PHP source code)
- `data/` (encrypted data files)
- `backups/` (backup archives)
- `templates/` (HTML templates)
- `.enc` files (encrypted data)
- `.encryption_key` (master key)
- `.install.lock` (installation lock)
- `VERSION` file

## File Permissions

- Directories: 0700 (owner read/write/execute only).
- Encryption key: 0600 (owner read/write only).
- Data files: inherited from directory (0700 → files are 0600 effective).

## Audit Logging

Every significant action is logged with:
- Who (user_id, username)
- What (action type)
- On what (entity_type + entity_id)
- From where (source: admin, mcp, cli, plugin)
- IP address
- Timestamp

Retention: 90 days (configurable), auto-pruned by CronManager.
