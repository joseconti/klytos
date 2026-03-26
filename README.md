# Klytos — The AI-First CMS

Klytos is a content management system designed from the ground up to be controlled by artificial intelligence through the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/).

Build, manage and publish websites entirely through conversation with any AI assistant that supports MCP — Claude, GPT, Gemini, or any other.

## Key Features

- **AI-Native** — Full site management via MCP: pages, design, SEO, media, analytics, users.
- **Visual Editor** — Gutenberg block editor integrated for manual editing when needed.
- **Static Output** — Generates pure HTML/CSS. No database queries on the frontend. Perfect Lighthouse scores.
- **Privacy-First Analytics** — Built-in analytics without cookies. GDPR compliant. IP addresses are hashed.
- **Dual Storage** — Flat-file (zero dependencies) or MySQL/MariaDB. Choose during installation.
- **Plugin System** — Hooks, filters and a plugin marketplace. Free and premium plugins.
- **Multi-User** — Four roles (Owner, Admin, Editor, Viewer) with granular permissions.
- **Multi-Language** — Hierarchical URLs, hreflang tags, and per-page language settings.
- **SEO Built-In** — Sitemap, robots.txt, Open Graph, Twitter Cards, JSON-LD structured data, canonical URLs, breadcrumbs.
- **AI Indexing** — Generates `llms.txt` and `llms-full.txt` for AI crawler discovery.
- **Secure** — AES-256-GCM encryption, OAuth 2.0/2.1 with PKCE, Application Passwords, CSRF protection, CSP headers, rate limiting.
- **Self-Updating** — One-click updates from GitHub Releases with automatic backup and rollback.

## Requirements

- PHP 8.1 or higher
- Apache with `mod_rewrite` (or nginx with equivalent rules)
- OpenSSL extension
- JSON extension
- cURL extension (for updates and oEmbed)
- ZipArchive extension (for updates)
- MySQL 5.7+ / MariaDB 10.3+ (optional, for database storage)

## Installation

1. Upload the contents to your web server.
2. Open `https://yourdomain.com/your-admin-folder/` in your browser.
3. Follow the installation wizard.
4. Connect your AI assistant using the MCP endpoint shown after installation.

## MCP Connection

Klytos supports two authentication methods for MCP:

**Application Passwords** (recommended for most users):
```
https://username:password@yourdomain.com/admin-folder/mcp
```

**OAuth 2.0 / 2.1** (for advanced integrations):
PKCE (S256) is required for all clients. Create OAuth clients from the admin panel.

## Documentation

Documentation is available in the `docs/` directory:

- [Architecture](docs/KLYTOS-ARCHITECTURE-V2.md) — System design, storage, security.
- [Hooks API](docs/KLYTOS-HOOKS-API.md) — Actions, filters, plugin development.
- [Template System](docs/KLYTOS-TEMPLATE-SYSTEM.md) — Page templates, blocks, build engine.

## Plugin Development

Klytos includes Claude Code skills in `.claude/skills/` that teach AI assistants how to:

- Develop plugins (`klytos-plugin-development.md`)
- Create SEO-optimized content (`klytos-seo-content.md`)
- Use Gutenberg block markup (`klytos-gutenberg-blocks.md`)
- Follow accessibility standards (`klytos-accessibility.md`)

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

**José Conti** — [joseconti.com](https://joseconti.com)

---

Built with purpose. Powered by AI.
