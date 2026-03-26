---
name: klytos-security-architecture
description: Security architecture and best practices for Klytos CMS. Use when dealing with authentication, encryption, access control, or security hardening.
trigger: When the user asks about security, authentication, encryption, CSRF, rate limiting, or access control in Klytos.
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

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: [with nonce support]
Permissions-Policy: camera=(), microphone=(), geolocation=()
```

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
