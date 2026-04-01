# Klytos — The AI-First CMS

Klytos is a content management system designed from the ground up to be controlled by artificial intelligence through the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/).

Build, manage and publish websites entirely through conversation with any AI assistant that supports MCP — Claude, GPT, Gemini, or any other.

**Current version:** 0.17.0-beta.2 | **License:** [Elastic License 2.0 (ELv2)](LICENSE)

## Key Features

### AI-Native CMS
- **Full MCP Server** — 22 tool modules with 122+ tools for complete site management via AI conversation.
- **Streamable HTTP** — JSON-RPC 2.0 protocol with rate limiting and dual authentication (Application Passwords + OAuth 2.0/2.1 with PKCE).
- **AI Chat** — Built-in AI chat interface supporting multiple providers (Anthropic Claude, OpenAI GPT, Google Gemini, OpenRouter).
- **AI Image Generation** — Generate images via Gemini/Imagen directly from the admin panel or MCP.
- **AI Indexing** — Generates `llms.txt` and `llms-full.txt` for AI crawler discovery.
- **8 Built-in AI Guides** — Gutenberg blocks, SEO, accessibility, plugin development, security architecture, and more.

### Content Management
- **Dual Editors** — Gutenberg (block editor) or TinyMCE 7 (classic editor), switchable anytime from Settings.
- **Custom Post Types** — Define unlimited content types with custom slugs, multi-language support, and associated taxonomies.
- **27 Custom Field Types** — Text, richtext, number, date, image, gallery, repeater, relationship, JSON, and more.
- **Page Templates** — 4 built-in HTML templates (Default, Landing, Blog Post, Blank) plus reusable template recipes.
- **Reusable Blocks** — Modular HTML blocks with categories, scopes (global/template/page), and configurable slots.
- **Version History** — Automatic snapshots on every save with diff comparison and one-click rollback (up to 50 versions per page).
- **Inline Front-end Editing** — Edit content directly from the public site when authenticated.

### Static Output
- **Pure HTML/CSS Generation** — No database queries on the frontend. Perfect Lighthouse scores.
- **Automatic SEO** — Sitemap.xml, robots.txt, Open Graph, Twitter Cards, JSON-LD structured data, canonical URLs, breadcrumbs.
- **Multi-Language** — Hierarchical URLs, hreflang tags, and per-page language settings.

### Multi-User & Security
- **4 Roles** — Owner, Admin, Editor, Viewer with granular permissions.
- **Three 2FA Methods** — TOTP (Google Authenticator), Magic Link (email), Passkeys (WebAuthn/FIDO2).
- **Recovery Codes** — 8 single-use bcrypt-hashed backup codes per user.
- **Emergency Email Recovery** — Fallback link when authenticator app is unavailable.
- **AES-256-GCM Encryption** — All sensitive data encrypted at rest.
- **Brute-Force Protection** — Account lockout after 5 failed attempts.
- **CSRF Protection** — Token validation on all forms.
- **CSP Headers** — Nonce-based Content Security Policy for inline scripts.
- **Audit Logging** — Full security event tracking with 90-day retention.

### Privacy & GDPR Compliance
- **Privacy Manager** — GDPR data export (Article 15) and erasure (Article 17) tools.
- **Consent Manager** — Cookie banner generation, consent declarations, opt-in/opt-out tracking.
- **Privacy-First Analytics** — Built-in analytics without cookies or fingerprinting.
- **Daily Hashed IPs** — SHA-256 with rotating salt. Impossible to track visitors across days.
- **Dashboard** — Visual analytics directly in the admin panel.

### Infrastructure
- **Dual Storage** — Flat-file JSON (zero dependencies) or MySQL/MariaDB. Choose during installation.
- **Plugin System** — Discovery via manifest, lifecycle hooks, WordPress-style actions and filters, per-plugin encrypted storage.
- **Action Scheduler** — One-time and recurring scheduled tasks with batch processing, retry logic, and stale timeout detection.
- **Webhook System** — HMAC-SHA256 signed event notifications with retry logic and delivery logs.
- **Email System** — PHP mail() or built-in SMTP (STARTTLS/SSL) with HTML templates.
- **Self-Updating** — One-click updates from GitHub Releases with automatic backup and rollback.
- **CLI** — Command-line interface for build, pages, users, analytics, plugins, cron, and system status.
- **Developer Tools** — Dev bar, profiling storage, and debug logging for development mode.

## Requirements

- PHP 8.1 or higher
- Apache with `mod_rewrite` (or Nginx with equivalent rules)
- PHP extensions: `openssl`, `json`, `mbstring`, `session`, `curl`, `zip`
- MySQL 5.7+ / MariaDB 10.3+ (optional, for database storage)
- HTTPS recommended for production

## Quick Start

1. Upload the contents to your web server.
2. Navigate to `https://yourdomain.com/installer/install.php`.
3. Follow the 3-step installation wizard.
4. Connect your AI assistant using the MCP endpoint shown after installation.

See [INSTALL.md](INSTALL.md) for detailed instructions.

## MCP Connection

Klytos supports two authentication methods for MCP:

**Application Passwords** (recommended):
```
https://username:password@yourdomain.com/admin-folder/mcp
```

**OAuth 2.0 / 2.1** (for advanced integrations):
PKCE (S256) is required for all clients. Create OAuth clients from the admin panel.

### MCP Tool Modules

| Module | Description |
|--------|-------------|
| Page Tools | Create, read, update, delete pages |
| Template Tools | HTML template management |
| Page Template Tools | Reusable template recipes |
| Block Tools | Reusable content blocks |
| Site Tools | Global site configuration |
| Menu Tools | Navigation management |
| Theme Tools | Visual theme customization |
| User Tools | Multi-user management |
| Asset Tools | File and image management |
| Build Tools | Static site generation |
| Plugin Tools | Plugin lifecycle management |
| Task Tools | Review tasks and annotations |
| Webhook Tools | Event notifications |
| Version Tools | Page history and rollback |
| Analytics Tools | Privacy-first analytics queries |
| AI Tools | AI provider management and stats |
| AI Image Tools | Image generation via Gemini |
| Custom Field Tools | Dynamic field definitions |
| Post Type Tools | Custom content types and taxonomies |
| Scheduler Tools | One-time and recurring scheduled actions |
| Consent Tools | GDPR consent declarations and banners |
| Guide Tools | AI development guides |

## Admin Panel

The admin panel is accessible via a secret URL defined during installation. It includes:

- **Dashboard** — System info, quick actions, indexing status.
- **Pages** — Full page management with drafts and SEO metadata.
- **Page Editor** — Gutenberg or TinyMCE with auto-save.
- **Post Types** — Custom content types with fields and taxonomies.
- **Taxonomy** — Taxonomy and term management.
- **Templates** — Page template management with live preview.
- **Blocks** — Reusable blocks management.
- **Theme** — Colors, fonts (Google Fonts), layout, custom CSS.
- **Assets** — File and image library.
- **Analytics** — Privacy-first analytics dashboard.
- **Users** — Role-based user management.
- **Profile** — User profile settings.
- **Plugins** — Install, activate, configure plugins.
- **Webhooks** — Event notification setup and delivery logs.
- **Scheduled Actions** — View and manage scheduled tasks.
- **Tasks** — Review tasks and to-dos.
- **Security** — 2FA setup, recovery codes, audit log.
- **Consent** — GDPR consent management.
- **Privacy** — Data export and erasure tools.
- **Logs** — System logs and debugging.
- **Terminal** — Command execution interface.
- **AI Chat** — Integrated AI assistant with multi-provider support.
- **AI Images** — Image generation via Gemini API.
- **MCP** — Connection setup, Application Passwords, OAuth clients.
- **Settings** — Site configuration, email, editor selection.
- **Updates** — One-click updates with backup and rollback.
- **License** — Plugin license verification.

## CLI

```bash
php cli.php build              # Build entire static site
php cli.php build:page <slug>  # Build single page
php cli.php pages              # List all pages
php cli.php pages:count        # Count pages by status
php cli.php tasks              # List open tasks
php cli.php tasks:count        # Count tasks by status
php cli.php users              # List all users
php cli.php analytics          # Show analytics (--period=7d|30d|90d)
php cli.php plugins            # List installed plugins
php cli.php status             # System status report
php cli.php version            # Show Klytos version
php cli.php cache:clear        # Clear caches
php cli.php cron:run           # Run scheduled actions (--token=<token>)
php cli.php help               # Show help message
```

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

### AI Development Guides (`.claude/skills/`)

Claude Code skills that teach AI assistants how to work with Klytos:

- `klytos-gutenberg-blocks.md` — Block markup reference.
- `klytos-seo-content.md` — SEO content writing guide.
- `klytos-accessibility.md` — WCAG 2.1 AA compliance.
- `klytos-plugin-development.md` — Plugin architecture and hooks.
- `klytos-core-development.md` — System architecture guide.
- `klytos-security-architecture.md` — Encryption, auth, CSP.
- `klytos-seo-and-indexing.md` — Sitemap, llms.txt, robots.txt.

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
  docs/                # 9 architecture documents
  installer/
    VERSION
    index.php          # Front controller
    install.php        # Installation wizard
    cli.php            # Command-line interface (14 commands)
    admin/             # Admin panel (35 PHP files)
      api/             # AJAX/REST endpoints
      assets/          # CSS, JS, vendor libraries
      includes/        # Shared components
      partials/        # Reusable UI fragments
      templates/       # Layout templates
    core/              # Core engine (18 manager classes)
      ai/              # AI chat engine and 4 providers
      lang/            # i18n translations (en, es — 492 keys each)
      mcp/             # MCP server
        tools/         # 22 tool modules (122+ tools)
    config/            # Encrypted configuration
    data/              # Flat-file storage (JSON, 16+ collections)
    plugins/           # Plugin directory
    public/            # Generated static site
    templates/         # HTML page templates
    vendor-ai/         # Bundled AI libraries
```

## License

Klytos is released under the [Elastic License 2.0 (ELv2)](LICENSE).

**You CAN:**
- Use Klytos for free, for any purpose (personal, commercial, enterprise).
- View, study and modify the source code.
- Share the original, unmodified code.

**You CANNOT:**
- Provide Klytos as a hosted or managed service (SaaS).
- Remove or circumvent plugin license key functionality.
- Sell modified versions of Klytos as your own product.

## Author

**José Conti** — [plugins.joseconti.com](https://plugins.joseconti.com) — [klytos.io](https://klytos.io)

---

Built with purpose. Powered by AI.
