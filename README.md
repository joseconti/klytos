# Klytos — The AI-First CMS

Klytos is a content management system designed from the ground up to be controlled by artificial intelligence through the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/).

Build, manage and publish websites entirely through conversation with any AI assistant that supports MCP — Claude, GPT, Gemini, or any other.

**Current version:** 0.28.5 | **License:** [GPL-3.0-or-later](LICENSE)

## Key Features

### AI-Native CMS
- **Full MCP Server** — 33 tool modules with 160+ tools for complete site management via AI conversation.
- **Streamable HTTP** — JSON-RPC 2.0 protocol with rate limiting and dual authentication (Application Passwords + OAuth 2.0/2.1 with PKCE).
- **AI Chat** — Built-in AI chat interface supporting multiple providers (Anthropic Claude, OpenAI GPT, Google Gemini, OpenRouter).
- **AI Image Generation** — Generate images via Gemini/Imagen directly from the admin panel or MCP.
- **AI Indexing** — Generates `llms.txt` and `llms-full.txt` for AI crawler discovery, plus per-page `.html.md` Markdown exports.
- **AI Translation** — Auto-translate content via AI providers with `klytos_translate_with_ai`.
- **16 Built-in AI Guides** — Gutenberg blocks, SEO, accessibility, plugin development, security architecture, design patterns, site builder, custom post types, forms, and more.
- **Site Builder** — 9-phase guided website builder via MCP (discovery, design, config, theme, structure, templates, content, features, launch).
- **33 Claude Code Skills** — Comprehensive AI development skills for every subsystem.

### Content Management
- **Dual Editors** — Gutenberg (block editor) or TinyMCE 7 (classic editor), switchable anytime from Settings.
- **Custom Post Types** — Define unlimited content types with custom slugs, multi-language support, and associated taxonomies.
- **Custom Post Statuses** — Workflow states beyond draft/published (In Review, Approved, Archived, etc.) with custom badges and colors.
- **27 Custom Field Types** — Text, richtext, number, date, image, gallery, repeater, relationship, JSON, code, color, toggle, and more.
- **Page Templates** — 4 built-in HTML templates (Default, Landing, Blog Post, Blank) plus reusable template recipes.
- **Custom Templates** — File-based, database-stored, or plugin-registered template overrides with 4-level hierarchy.
- **Template Parts** — Reusable header, footer, head, and scripts components with 3-level override hierarchy.
- **Reusable Blocks** — Modular HTML blocks with categories, scopes (global/template/page), configurable slots, and sample data.
- **Version History** — Automatic snapshots on every save with diff comparison and one-click rollback (up to 50 versions per page).
- **Inline Front-end Editing** — Edit content directly from the public site when authenticated.
- **Post Lock** — Prevent concurrent editing of the same page.
- **Autosave** — Automatic draft saving to prevent content loss.
- **Comment System** — Threaded comments (3 levels deep), moderation workflow (pending/approved/spam/trash), honeypot anti-spam, and static rendering at build time.
- **Shortcode System** — Extensible shortcodes for embedding dynamic content (e.g., `{{form:form-id}}`).
- **oEmbed Resolver** — Embed external content from supported providers.
- **Export System** — Export site content in WXR (WordPress XML), JSON, or CSV formats.

### Static Output
- **Pure HTML/CSS Generation** — No database queries on the frontend. Perfect Lighthouse scores.
- **Automatic SEO** — Sitemap.xml, robots.txt, Open Graph, Twitter Cards, JSON-LD structured data, canonical URLs, breadcrumbs.
- **Multi-Language** — Hierarchical URLs (`/en/`, `/es/`, `/ca/`), hreflang tags, per-page language settings, AI-powered translation.
- **HTML to Markdown** — Per-page `.html.md` files for LLM discoverability and AI crawler consumption.

### Multi-User & Access Control
- **4 Roles** — Owner, Admin, Editor, Viewer with granular capability-based permissions.
- **Session Security** — HTTP-only cookies, SameSite=Strict, Secure flag on HTTPS, 30-minute lifetime.
- **Session Regeneration** — Session ID regenerated on login and 2FA completion (prevents session fixation).
- **Brute-Force Protection** — Account lockout after 5 failed attempts (15-minute cooldown).
- **Password Requirements** — Minimum 12 characters, bcrypt hashing (cost 12).
- **Secret Admin URL** — Admin directory uses a random, non-discoverable path (not `/admin/` or `/wp-admin/`).
- **Audit Logging** — Full security event tracking with 90-day retention.
- **Site Health Manager** — System health checks with 0-100 scoring and remediation advice.

### Encryption & Cryptography

#### AES-256-GCM (Data at Rest)
- **Cipher** — `aes-256-gcm` via OpenSSL (authenticated encryption with integrity verification).
- **Key** — 256-bit (32 bytes) generated via `random_bytes(32)` CSPRNG.
- **IV** — 96-bit (12 bytes) randomly generated per encryption operation.
- **Auth Tag** — 128-bit (16 bytes) provides tamper detection on every read.
- **Format** — `Base64(IV[12] + TAG[16] + CIPHERTEXT[n])` — each encryption is atomic with unique IV.
- **Key Storage** — `config/.encryption_key` with 0600 file permissions (owner-only).
- **Key Rotation** — `rotateKey()` re-encrypts all data and config files with a new key atomically.

#### Three Encryption Levels

| Level | Name | What is encrypted |
|-------|------|-------------------|
| 0 | **Basic** | System config only: tokens, app passwords, OAuth clients |
| 1 | **Medium** (recommended) | + Users, audit log, sessions, AI chats, 2FA secrets |
| 2 | **Professional** | + Pages, blocks, templates, forms, analytics, assets, options, webhooks, logs — everything |

- Levels can be changed at any time from the admin panel; data is automatically encrypted/decrypted during transition.
- **Warning:** At Professional level, loss of encryption key = irreversible loss of ALL content.

#### Password Hashing
- **Algorithm** — bcrypt via `password_hash()` with cost factor 12.
- **Verification** — Constant-time comparison via `password_verify()` (prevents timing attacks).
- **Used for** — Admin passwords, user passwords, Application Passwords, 2FA recovery codes.

#### RSA Identity Keys (2048-bit)
- **Purpose** — Admin identity verification and signed integrity manifests.
- **Generation** — `openssl_pkey_new()` with 2048-bit RSA.
- **Storage** — Public and private keys stored as AES-256-GCM encrypted files (`admin-identity.pub.enc`, `admin-identity.priv.enc`).
- **Challenge-Response** — 32-byte random challenge signed with SHA-256 for identity verification.
- **Download Protection** — Rate-limited (1/24h), owner-only, audit-logged, email notification.

#### Webhook Signatures (HMAC-SHA256)
- **Secret** — 64 hex characters per webhook subscription.
- **Header** — `X-Klytos-Signature: sha256={hmac_hex}` on every delivery.

#### CSPRNG (Cryptographically Secure Random)
- All random values use PHP `random_bytes()` — encryption keys, IVs, CSRF tokens, user IDs, TOTP secrets, webhook secrets, recovery codes, challenges.

### Two-Factor Authentication (3 Methods)

| Method | Details |
|--------|---------|
| **TOTP** (RFC 6238) | 160-bit secret (Base32), 6-digit codes, 30s period, ±1 window for clock drift. Compatible with Google Authenticator, 1Password, Authy. |
| **Magic Link** (email) | 64-hex token, 10-minute expiry, single-use, delivered via email. |
| **Passkeys** (WebAuthn/FIDO2) | 256-bit challenge, 5-minute expiry. Supports biometrics, security keys, platform authenticators. Passwordless. |
| **Recovery Codes** | 8 single-use codes, bcrypt-hashed before storage. Auto-generated when first 2FA method enabled. |
| **Emergency Recovery** | Email fallback link when authenticator app is unavailable. |

### Network & Application Security
- **CSRF Protection** — 256-bit random token (64 hex chars), constant-time verification via `hash_equals()`.
- **CSP Headers** — Nonce-based Content Security Policy for inline scripts. No inline event handlers (`onclick`, etc.).
- **Security Headers** — X-Frame-Options (clickjacking), X-Content-Type-Options (MIME sniffing), Strict-Transport-Security (HSTS).
- **Input Sanitization** — `kses_post()` whitelist-based HTML, `sanitizeSlug()` with transliteration, type-checked query parameters.
- **SQL Injection Prevention** — PDO prepared statements with `ATTR_EMULATE_PREPARES = false` and `ERRMODE_EXCEPTION`.
- **Path Traversal Prevention** — Collection/ID names sanitized, no `.` or `/` allowed.
- **File Integrity Checker** — SHA-256 hash verification with RSA-signed manifests, 100 files per cron cycle, 24h cache.
- **Atomic File Writes** — `LOCK_EX` file locking prevents race conditions and partial writes.

### Cryptographic Summary

| Component | Algorithm | Key/Size |
|-----------|-----------|----------|
| Data encryption | AES-256-GCM | 256-bit key, 96-bit IV, 128-bit tag |
| Password hashing | bcrypt | cost 12 |
| Identity keys | RSA | 2048-bit |
| TOTP secrets | HMAC-SHA1 | 160-bit (RFC 6238) |
| Webhook signatures | HMAC-SHA256 | 256-bit secret |
| File integrity | SHA-256 | 256-bit digest |
| Manifest signing | RSA-SHA256 | 2048-bit |
| CSRF tokens | CSPRNG | 256-bit (64 hex) |
| Analytics IP hash | SHA-256 + daily salt | 256-bit rotating |
| Session IDs | PHP native | ~128-bit |

### Privacy & GDPR Compliance
- **Privacy Manager** — GDPR data export (Article 15) and erasure (Article 17) tools with plugin hooks for custom data.
- **Consent Manager** — Cookie banner generation, consent declarations with vendor info, opt-in/opt-out tracking per category (Necessary, Functional, Analytics, Marketing + custom), and full consent audit trail.
- **Privacy-First Analytics** — Built-in analytics without cookies or fingerprinting. Tracks only page path, referrer domain, device category, and anonymized visitor hash.
- **Daily Hashed IPs** — SHA-256 with rotating daily salt generated via `random_bytes(32)`. Hash changes every 24 hours — impossible to track visitors across days. Raw IPs never stored.
- **Configurable Retention** — Analytics data auto-deleted after configurable period (default 90 days).
- **Data Portability** — Machine-readable export format (GDPR Article 20).
- **Audit Log Anonymization** — On user erasure, audit logs are anonymized (not deleted) to preserve compliance records.
- **Dashboard** — Visual analytics directly in the admin panel.

### x402 Micropayment Protocol
- **AI Bot Monetization** — Charge AI bots for content access while humans browse free.
- **HTTP 402 Payment Required** — Standards-based payment protocol for AI agents.
- **Per-Page Pricing** — Configure pricing and license type (inference, training, full) per page.
- **Pluggable Providers** — Coinbase CDP (blockchain) and Stripe (credit card) payment providers.
- **Transaction Logging** — Full payment history with amounts, timestamps, and payer details.
- **Revenue Dashboard** — Stats and analytics by provider and date range.
- **Bot Detection** — Automatic AI bot identification via user-agent analysis.
- **8 MCP Tools** — Full x402 management via AI conversation.

### Bundled Plugins
- **hello-ai** — Getting started demo plugin showing translation and topbar integration.
- **klytos-forms** — Full form builder with 18 field types, conditional logic, multi-step forms, anti-spam (honeypot + reCAPTCHA), email notifications, webhook integration, CSV export, and submission storage.
- **klytos-importer** — AI-powered content migration from any website. Supports WordPress XML (WXR), sitemap crawling, direct URL discovery, content analysis, style detection, media download, and batch import with 10 MCP tools.
- **klytos-x402-coinbase** — x402 payment provider for Coinbase CDP (blockchain micropayments).
- **klytos-x402-stripe** — x402 payment provider for Stripe (credit card micropayments).

### Infrastructure
- **Dual Storage** — Flat-file JSON (zero dependencies) or MySQL/MariaDB. Choose during installation.
- **Cache System** — Multi-backend caching: File (default), Redis, Memcached, APCu, or Null (disabled).
- **Plugin System** — Discovery via manifest, lifecycle hooks (install/activate/deactivate/uninstall), WordPress-style actions and filters, per-plugin encrypted storage, admin pages, MCP tool registration, and custom routes.
- **75+ Hooks & Filters** — Comprehensive action/filter system for extensibility (page lifecycle, build process, auth, theme, assets, comments, plugins, webhooks, AI, cache, MCP, privacy, and more).
- **Action Scheduler** — One-time and recurring scheduled tasks with batch processing, retry logic, stale timeout detection (300s), and lock-file protection.
- **Webhook System** — HMAC-SHA256 signed event notifications with retry logic (5 attempts, exponential backoff) and delivery logs.
- **Email System** — PHP mail() or built-in SMTP (STARTTLS/SSL) with HTML templates.
- **Self-Updating** — One-click updates from GitHub Releases with automatic backup and rollback.
- **Backup System** — Automatic backup archives before updates with restore capability.
- **Dynamic Routing** — RouteManager for plugin-registered custom URL routes (GET, POST, PUT, DELETE, PATCH).
- **Maintenance Mode** — Toggle site on/off with custom message via admin panel or MCP.
- **CLI** — Command-line interface for build, pages, users, analytics, plugins, cron, backups, and system status.
- **Developer Tools** — Dev bar, profiling storage, and debug logging for development mode.
- **Image Editing** — Crop, resize, rotate, and flip images via GD library.
- **HTTP Client** — Built-in HTTP client with retry logic for external requests.
- **Logging** — Rotating log files with severity levels and configurable max file size.
- **Mailer** — Filterable email system with customizable headers and HTML templates.

## Requirements

- PHP 8.1 or higher
- Apache with `mod_rewrite` (or Nginx with equivalent rules)
- PHP extensions: `openssl`, `json`, `mbstring`, `session`, `curl`, `zip`
- MySQL 5.7+ / MariaDB 10.3+ (optional, for database storage)
- HTTPS recommended for production

## Quick Start

1. Upload the contents to your web server.
2. Navigate to `https://yourdomain.com/installer/install.php`.
3. Follow the 3-step installation wizard (requirements check → configuration → completion).
4. Complete the 5-screen setup wizard on first login (2FA, connection type, AI keys, MCP config).
5. Connect your AI assistant using the MCP endpoint shown after installation.

See [INSTALL.md](INSTALL.md) for detailed instructions.

## MCP Connection

Klytos supports two authentication methods for MCP:

**Application Passwords** (recommended):
```
https://username:password@yourdomain.com/admin-folder/mcp
```

**OAuth 2.0 / 2.1** (for advanced integrations):
PKCE (S256) is required for all clients. Create OAuth clients from the admin panel. Endpoints:
- Authorization: `/oauth/authorize`
- Token: `/oauth/token`
- Metadata: `/.well-known/oauth-authorization-server`

### MCP Tool Modules

| Module | Tools | Description |
|--------|-------|-------------|
| Page Tools | 11 | Create, read, update, delete, lock, trash, restore pages |
| Template Tools | 16 | HTML templates, custom templates, template parts, plugin assets |
| Page Template Tools | 9 | Reusable template recipes, blocks, schema, preview |
| Block Tools | 8 | Reusable content blocks with slots and global data |
| Custom Field Tools | 11 | Dynamic field definitions (27 types), values, bulk operations |
| Post Type Tools | 14 | Custom content types, taxonomies, terms |
| Post Status Tools | 4 | Custom workflow statuses |
| Site Tools | 2 | Global site configuration |
| Menu Tools | 4 | Navigation management |
| Theme Tools | 5 | Colors, fonts, layout customization |
| User Tools | 5 | Multi-user management and permissions |
| Asset Tools | 13 | Files, images, categories, usage tracking, cleanup, image editing |
| Build Tools | 6 | Static site generation, preview, CSS rebuild |
| Bulk Tools | 1 | Batch page updates |
| Plugin Tools | 3 | Plugin lifecycle management |
| Task Tools | 5 | Review tasks and annotations |
| Webhook Tools | 5 | Event notifications with delivery logs |
| Version Tools | 4 | Page history, diff, and rollback |
| Analytics Tools | 2 | Privacy-first analytics queries |
| AI Tools | 3 | AI provider management and usage stats |
| AI Image Tools | 2 | Image generation via Gemini/Imagen |
| Comment Tools | 6 | Comments, moderation, bulk actions, settings |
| Consent Tools | 6 | GDPR consent declarations, banner config, audit |
| Export Tools | 1 | Site export (WXR, JSON, CSV) |
| Guide Tools | 2 | AI development guides |
| Integrity Tools | 3 | File integrity verification |
| Maintenance Tools | 2 | Maintenance mode management |
| Option Tools | 4 | Options storage by domain, classify, migrate |
| Scheduler Tools | 5 | One-time and recurring scheduled actions |
| Shortcode Tools | 1 | List registered shortcodes |
| Site Builder Tools | 1 | 9-phase guided website builder |
| Site Health Tools | 1 | System health checks (0-100 score) |
| Translation Tools | 4 | Multi-language content, AI translation |
| x402 Tools | 8 | Micropayment config, page status, transactions, stats |

**Total: 33 modules, 160+ tools**

## Admin Panel

The admin panel is accessible via a secret URL defined during installation. It includes:

### Dashboard & System
- **Dashboard** — System info, quick actions, indexing status, version check.
- **Settings** — Site configuration, email (PHP mail/SMTP), editor selection, language.
- **System Options** — Advanced system-level options editor with domain grouping.
- **System Integrity** — File hash verification status with remediation options.
- **Site Health** — System health checks with scoring and recommendations.
- **Logs** — System logs viewer with severity filtering.
- **Updates** — One-click updates with automatic backup and rollback.
- **License** — Plugin license verification.
- **Setup Wizard** — Post-install 5-screen guided setup (2FA, connections, AI keys, MCP).

### Content Management
- **Pages** — Full page management with status filtering, bulk actions, and SEO metadata.
- **Page Editor** — Gutenberg or TinyMCE with autosave and post lock.
- **Post Types** — Custom content type list with field and taxonomy counts.
- **Post Type Editor** — Custom post type editor with fields, taxonomies, and statuses.
- **Taxonomy** — Taxonomy and term management with hierarchical/flat views.
- **Templates** — Page template management with live preview.
- **Template Preview** — Live template rendering preview.
- **Blocks** — Reusable blocks management with categories and scopes.
- **Block Data** — Global block data editor.
- **Translations** — Multi-language translation editor with coverage tracking.

### Design & Media
- **Theme** — Colors (11 semantic), fonts (Google Fonts), layout, custom CSS.
- **Assets** — File and image library with metadata, categories, and usage tracking.

### Users & Security
- **Users** — Role-based user management (Owner, Admin, Editor, Viewer).
- **Profile** — User profile and personal settings.
- **Security** — 2FA setup (TOTP/Magic Link/Passkeys), recovery codes, audit log viewer.

### Analytics & Tasks
- **Analytics** — Privacy-first analytics dashboard with page views, referrers, and device data.
- **Tasks** — Review tasks, to-dos, and content annotations.
- **Comments** — Comment moderation interface (approve/spam/trash).

### Plugins & Integrations
- **Plugins** — Install, activate, configure plugins with settings pages.
- **Plugin Page** — Plugin-specific admin pages (registered by plugins).
- **Webhooks** — Event notification setup, delivery logs, and testing.
- **Scheduled Actions** — View and manage scheduled/recurring tasks.

### Privacy & Consent
- **Consent** — GDPR consent banner configuration and declaration management.
- **Privacy** — Data export (Art. 15) and erasure (Art. 17) tools.

### AI Features
- **AI Chat** — Integrated AI assistant with multi-provider support (Claude, GPT, Gemini, OpenRouter).
- **AI Images** — Image generation via Gemini/Imagen API.

### MCP & Connectivity
- **MCP** — Connection setup, Application Passwords, OAuth 2.0 client management.

### x402 Payments
- **x402 Dashboard** — Payment overview, revenue stats, provider status.
- **x402 Settings** — Payment provider configuration, wallet, pricing.
- **x402 Transactions** — Transaction history, filtering, and logs.

### Developer Tools
- **Terminal** — Web-based command execution interface with autocomplete.
- **Dev Bar** — Profiling, debug info, and development tools (dev mode only).

### Admin API Endpoints (24)
- `api/autosave.php` — Draft autosave
- `api/inline-edit.php` — Front-end inline editing
- `api/media-upload.php` — File upload handler
- `api/image-edit.php` — Image crop, resize, rotate, flip
- `api/assets-management.php` — Asset CRUD operations
- `api/comment-submit.php` — Comment submission
- `api/tasks.php` — Task CRUD operations
- `api/plugins.php` — Plugin activation/deactivation
- `api/notices.php` — Admin notice management
- `api/logs.php` — Log queries
- `api/terminal.php` — Terminal command execution
- `api/terminal-autocomplete.php` — CLI command completion
- `api/terminal-revalidate.php` — Terminal state validation
- `api/translations.php` — Translation management
- `api/translations-ai.php` — AI-powered translation
- `api/options-management.php` — Options CRUD
- `api/integrity.php` — Integrity check operations
- `api/sidebar-order.php` — Admin sidebar ordering
- `api/webauthn-challenge.php` — WebAuthn challenge generation
- `api/post-lock.php` — Page lock management
- `api/update-install.php` — Update installation
- `api/download-identity.php` — Identity key download
- `api/ai-chat.php` — AI chat streaming
- `api/oembed.php` — oEmbed resolution

## CLI

```bash
# Content
php cli.php build              # Build entire static site
php cli.php build:page <slug>  # Build single page
php cli.php pages              # List all pages
php cli.php pages:count        # Count pages by status

# Tasks
php cli.php tasks              # List open tasks
php cli.php tasks:count        # Count tasks by status

# Users
php cli.php users              # List all users

# Analytics
php cli.php analytics          # Show analytics (--period=7d|30d|90d)

# Plugins
php cli.php plugins            # List installed plugins
php cli.php plugins:activate   # Activate a plugin
php cli.php plugins:deactivate # Deactivate a plugin

# System
php cli.php status             # System status report
php cli.php version            # Show Klytos version
php cli.php cache:clear        # Clear all caches
php cli.php clear              # Alias for cache:clear
php cli.php logs               # View system logs
php cli.php cron:run           # Run scheduled actions (--token=<token>)

# Backup & Updates
php cli.php backup:create      # Create site backup (--label=weekly)
php cli.php backup:list        # List available backups
php cli.php backup:restore     # Restore from backup
php cli.php update:check       # Check for available updates
php cli.php update:run         # Install pending updates

# Configuration
php cli.php config:get         # Retrieve configuration value
php cli.php config:set         # Set configuration value

# Help
php cli.php help               # Show help message
```

## Custom Field Types (27)

| Category | Types |
|----------|-------|
| **Text** | `text`, `textarea`, `richtext`, `code`, `password` |
| **Numeric** | `number`, `range` |
| **Date & Time** | `date`, `datetime`, `time` |
| **Choices** | `select`, `multiselect`, `checkbox`, `checkbox_group`, `radio`, `toggle` |
| **Media** | `image`, `file`, `gallery` |
| **Data** | `email`, `url`, `phone`, `color`, `json` |
| **Advanced** | `repeater` (nested sub-fields), `relationship` (references to other entries) |

All field types support validation rules, default values, required/optional, and custom ordering.

## Hooks & Filters (75+)

Klytos provides a comprehensive hook system for plugin extensibility:

### Key Action Hooks
- **Page Lifecycle** — `page.before_save`, `page.after_save`, `page.after_delete`, `page.status_changed`, `page.scheduled_published`
- **Build Process** — `build.before`, `build.page.before`, `build.page.after`, `build.completed`, `build.failed`
- **Users** — `user.login`, `user.deleted`, `user.role_changed`, `auth.after_login`
- **Plugins** — `plugin.loaded`, `plugins.loaded`
- **System** — `cache.flushed`, `cron.run`, `maintenance.enabled`, `maintenance.disabled`
- **AI** — `ai.chat.error`, `ai.chat.tool_executed`, `ai.key.configured`
- **x402** — `x402.payment_received`, `x402.config.updated`
- **Scheduler** — `scheduler.action_complete`, `scheduler.action_failed`, `scheduler.batch_complete`
- **Privacy** — `privacy.erase_section`

### Key Filter Hooks
- **Build Output** — `build.page.output`, `build.head_html`, `build.body_end_html`, `build.llms_txt`, `build.sitemap_urls`
- **Content** — `page.content`, `page.password_form`, `shortcode.pre_process`
- **Templates** — `template_part.*`, `build.page_markdown`, `build.structural_block_mapping`
- **MCP** — `mcp.tools_list`, `mcp.tool_response`, `mcp.handle_tool`
- **Auth** — `auth.capabilities`
- **Privacy** — `privacy.export_data`, `privacy.erase_plugin_data`
- **Options** — `option.get`, `option.set`
- **Webhooks** — `webhooks.events`
- **Commerce** — `x402.payment_providers`, `x402.response_payload`

## Documentation

- [INSTALL.md](INSTALL.md) — Step-by-step installation guide.
- [Architecture](docs/KLYTOS-ARCHITECTURE-V2.md) — System design, storage, database schema.
- [Hooks API](docs/KLYTOS-HOOKS-API.md) — Actions, filters, plugin development.
- [Template System](docs/KLYTOS-TEMPLATE-SYSTEM.md) — Page templates, blocks, build engine.
- [Plugin Architecture](docs/PLUGINS-ARCHITECTURE.md) — Plugin development guide.
- [Plugin Admin UI](docs/PLUGINS-ADMIN-UI.md) — Plugin settings pages.
- [Template Architecture](docs/TEMPLATES-ARCHITECTURE.md) — Template system deep dive.
- [Terminal Architecture](docs/TERMINAL-ARCHITECTURE.md) — Terminal executor design.
- [Developer Mode](docs/DEVELOPER-MODE.md) — Debug and development features.
- [Consent Manager](docs/consent-manager-spec.md) — Consent system specification.
- [x402 Micropayments](docs/KLYTOS-X402.md) — x402 protocol integration guide.
- [File Integrity](docs/FEATURE-INTEGRITY-DEVELOPERS.md) — File integrity verification system.

### Built-in AI Guides (`core/guides/`)

16 guides available via the `klytos_get_guide` MCP tool:

- `gutenberg-blocks` — Block markup reference for page creation.
- `seo-content` — SEO content writing best practices.
- `accessibility` — WCAG 2.1 AA compliance guide.
- `plugin-development` — Plugin architecture and hooks tutorial.
- `core-development` — System architecture guide.
- `security-architecture` — Encryption, auth, CSP patterns.
- `seo-and-indexing` — Sitemap, llms.txt, robots.txt configuration.
- `design-patterns` — Design patterns and component composition.
- `forms` — Form builder plugin usage guide.
- `post-types-and-fields` — Custom content types and field management.
- `site-builder` — 9-phase guided site building.
- `site-builder-checklist` — Site launch verification checklist.
- `site-builder-content` — Content strategy and page writing.
- `site-builder-page-trees` — Page hierarchy planning.
- `site-builder-palettes` — Color palette selection guide.
- `site-builder-types` — Business type templates and configurations.

### Claude Code Skills (`.claude/skills/`)

33 skills that teach AI assistants how to work with every Klytos subsystem:

- `klytos-core-development` — System architecture, boot sequence, manager pattern.
- `klytos-plugin-development` — Plugin lifecycle, hooks, entry points, admin pages.
- `klytos-mcp-tools` — MCP tool registration, handler pattern, schema validation.
- `klytos-page-structure` — Page data structure, creation, editing, templates.
- `klytos-custom-post-types` — Custom content types, taxonomies, custom fields.
- `klytos-custom-fields` — Metadata and structured data attachment to pages.
- `klytos-custom-templates` — Custom page templates, template parts, hook points.
- `klytos-gutenberg-blocks` — Block markup reference for visual editor.
- `klytos-site-builder` — 9-phase guided website builder.
- `klytos-seo-content` — SEO best practices, meta descriptions, Open Graph.
- `klytos-seo-and-indexing` — Sitemap, llms.txt, robots.txt, AI indexing.
- `klytos-accessibility` — WCAG 2.1 AA compliance standards.
- `klytos-design-system` — Design principles, color philosophy, component patterns.
- `klytos-css-classes` — CSS design tokens, component classes, utility classes.
- `klytos-admin-sidebar` — Admin sidebar menu items and plugin admin pages.
- `klytos-admin-notices` — Admin notices (success, error, warning, info).
- `klytos-desktop-vs-mcp` — Desktop admin vs AI (MCP) interface differences.
- `klytos-forms` — Form builder plugin guide.
- `klytos-menus-and-navigation` — Site navigation menu management.
- `klytos-media-management` — Asset metadata, categories, usage tracking.
- `klytos-consent-manager` — GDPR/CCPA-compliant cookie consent.
- `klytos-privacy-tools` — Data export and erasure for GDPR.
- `klytos-scheduled-actions` — Background tasks, cron jobs, recurring actions.
- `klytos-options-storage` — Plugin options and site configuration storage.
- `klytos-hooks-reference` — Complete hook API reference.
- `klytos-helper-functions` — Global `klytos_*()` helper functions.
- `klytos-escape-and-sanitization` — Escape/sanitization functions for security.
- `klytos-security-architecture` — Authentication, encryption, access control.
- `klytos-integrity-check` — File integrity verification system.
- `klytos-terminal` — Web terminal and CLI command system.
- `klytos-time` — Dates, times, timezones, and scheduling.
- `klytos-importer` — AI-powered content migration plugin.
- `accessibility-audit` — WCAG 2.2, EAA, ADA compliance audits.

## Project Structure

```
klytos/
  .htaccess
  index.html
  README.md
  INSTALL.md
  LICENSE
  PRIVACY.md
  changelog.txt
  docs/                    # 12 architecture & feature documents
  installer/
    VERSION                # Current version (0.28.5)
    index.php              # Front controller
    install.php            # Installation wizard (3 steps)
    cli.php                # Command-line interface (26 commands)
    t.php                  # Analytics tracking pixel
    admin/                 # Admin panel
      *.php                # 42 admin pages
      api/                 # 24 AJAX/REST endpoints
      assets/              # CSS, JS, vendor libraries
        css/               # Admin stylesheets
        js/                # Admin scripts
        vendor/            # TinyMCE 7, Gutenberg, FontAwesome
      includes/            # Shared components
      partials/            # Reusable UI fragments
      templates/           # Layout templates (header, sidebar, footer)
    core/                  # Core engine
      *.php                # 65 manager classes and helpers
      ai/                  # AI chat engine (4 providers)
      cache/               # 5 cache backends (File, Redis, Memcached, APCu, Null)
      guides/              # 16 built-in AI development guides
      lang/                # i18n translations (en, es — 492+ keys each)
      mcp/                 # MCP server (JSON-RPC 2.0, OAuth, rate limiting)
        tools/             # 33 tool modules (160+ tools)
      x402/                # x402 micropayment protocol
    config/                # Encrypted configuration (.htaccess protected)
      .encryption_key    # AES-256 master key (32 bytes, 0600 perms)
      config.json.enc    # Main site config (AES-256-GCM)
      database.json.enc  # DB credentials (AES-256-GCM, if MySQL)
      admin-identity.pub.enc   # RSA-2048 public key (encrypted)
      admin-identity.priv.enc  # RSA-2048 private key (encrypted)
      .install.lock      # Prevents re-installation
    data/                  # Flat-file storage (JSON, 25+ collections)
    backups/               # Automatic backup archives
    plugins/               # Plugin directory
      hello-ai/            # Demo plugin
      klytos-forms/        # Form builder plugin
      klytos-importer/     # Site migration plugin
      klytos-x402-coinbase/# Coinbase payment provider
      klytos-x402-stripe/  # Stripe payment provider
    public/                # Generated static site
    templates/             # HTML page templates (4 built-in)
    vendor-ai/             # Bundled AI libraries
```

### Core Engine Classes (65)

| Category | Classes |
|----------|---------|
| **Application** | App, Router, RouteManager, SiteConfig |
| **Storage** | StorageInterface, Storage, FileStorage, DatabaseStorage |
| **Content** | PageManager, PostTypeManager, BlockManager, PageTemplateManager, TemplateResolver, MetaManager, VersionManager, ShortcodeManager |
| **Build** | BuildEngine, HtmlToMarkdown |
| **Users & Auth** | UserManager, Auth, TwoFactor, AuditLog |
| **Security** | Encryption, EncryptionLevelTrait, IntegrityChecker, License |
| **Infrastructure** | CacheManager, CacheInterface, ActionScheduler, CronManager, WebhookManager, HttpClient, Logger, Mailer, NoticeManager |
| **Content Features** | CommentManager, TaskManager, AnalyticsManager, MenuManager, AssetManager, ExportManager, OembedResolver |
| **Theme & Display** | ThemeManager, DevBar, ProfilingStorage |
| **Privacy** | PrivacyManager, ConsentManager |
| **Translation** | TranslationManager, I18n, TimezoneCache |
| **AI** | ChatEngine, ChatManager, AiKeyManager, AiImageGenerator |
| **Plugins** | PluginLoader, Hooks |
| **System** | TerminalExecutor, Updater, SiteHealthManager, OptionsManager |
| **Helpers** | helpers.php, helpers-global.php, helpers-security.php, helpers-time.php, seed-data.php |
| **x402** | X402Bootstrap, X402McpTools |

## i18n & Multi-Language

- **Core Languages** — English (en), Spanish (es), with 492+ translation keys each.
- **Extensible** — Any ISO 639-1 language code supported (e.g., Catalan `ca`).
- **Hierarchical URLs** — `/en/about/`, `/es/sobre-nosotros/`, `/ca/sobre-nosaltres/`.
- **hreflang Tags** — Automatic search engine language alternates.
- **Plugin Translations** — Per-plugin translation namespaces.
- **AI Translation** — Auto-translate via `klytos_translate_with_ai` MCP tool.
- **Coverage Tracking** — Know which translations are missing per language.

## License

Klytos is released under the [GNU General Public License v3 or later](LICENSE).

**You CAN:**
- Use, study, modify, and distribute Klytos freely.
- Use Klytos for any purpose (personal, commercial, enterprise).
- Build and sell plugins/templates under any license you choose.

**Plugin Exception:**
Plugins and templates that communicate with Klytos through its public API (hooks, filters, MCP tools, REST endpoints) are **NOT** derivative works and may use any license. See the LICENSE file for details.

## Author

**José Conti** — [plugins.joseconti.com](https://plugins.joseconti.com) — [klytos.io](https://klytos.io)

---

Built with purpose. Powered by AI.
