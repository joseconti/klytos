# 02 — Functional Spec — Klytos CMS

> **Adopted project — reconstructed as-built.** Depth rule (adoption): enough detail to work safely
> now; each area is deepened the first time a slice touches it (**progressive backfill**), rather
> than halting the project for weeks of retro-documentation. Inferences not confirmed by the user or
> a test are labelled `as-built, unverified`.

## 1. Actors and interfaces

| Actor | Interface | Auth |
|-------|-----------|------|
| AI agent (primary) | MCP — JSON-RPC 2.0 at `/mcp` | OAuth 2.0 (`/oauth/authorize`, `/oauth/token`, `/.well-known/oauth-authorization-server`) or bearer token; per-token rate limiting |
| Human operator | Admin panel — 42 pages under `/admin` | Session + CSRF; bcrypt cost 12; optional TOTP / WebAuthn 2FA; login lockout |
| Human operator (shell) | `php cli.php <command>` — 26 commands | OS-level (filesystem access) |
| Human operator (web shell) | Admin web terminal | Session + CSRF + per-command permission |
| Site visitor | Generated static HTML in `installer/public/` | None (public); optional page password; optional x402 payment gate for AI bots |
| External system | Outgoing webhooks; `/cron` trigger; plugin-registered routes (`page` / `api` / `webhook`) | Per-route |

**Role model:** `owner` > `admin` > `editor` > `viewer`, expressed as a capability matrix
(`site.configure`, `users.manage`, `plugins.manage`, `updates.manage`, `assets.manage`,
`webhooks.manage`, `theme.manage`, `mcp.manage`, `tasks.manage`, …). **Enforcement is the product's
largest as-built defect — see `docs/04-adoption-audit.md` S-01 through S-07.**

## 2. Main flows

### F-01 — Build a site with AI (the product's reason to exist)
1. Operator installs Klytos, completes the install wizard and the 5-screen setup wizard (2FA,
   connections, AI keys, MCP endpoint).
2. Operator connects an MCP client to `/mcp` and authorizes it via OAuth.
3. AI calls `klytos_start_site_builder` / `klytos_get_guide` to load the in-product guides, then
   composes the site: `klytos_set_site_config`, `klytos_set_theme` / `set_colors` / `set_fonts` /
   `set_layout`, `klytos_create_post_type` + `add_taxonomy` + `add_custom_field`,
   `klytos_create_page_template` + `add_block_to_template`, `klytos_create_page`,
   `klytos_set_menu`, `klytos_upload_asset` / `klytos_generate_image`.
4. AI calls `klytos_build_site`; the build engine renders static HTML plus `sitemap.xml`,
   `robots.txt`, `llms.txt`, `llms-full.txt`, JSON-LD, hreflang and the search index.
5. Operator reviews in the admin panel; changes there are equivalent operations on the same
   managers (the "Desktop vs MCP" equivalence contract).

### F-02 — Human content editing
Admin → Pages → editor (Gutenberg-compatible block markup via the bundled isolated block editor) →
autosave → post lock to prevent concurrent edits → version saved → publish (or schedule) →
`page.after_save` fires → page rebuilt.

### F-03 — Plugin lifecycle
Discovery (`plugins/{id}/{id}.php` with a PHP header; legacy `klytos-plugin.json` + `init.php`) →
install (`install.php`, `migrations/`) → activate → registers routes, admin pages, MCP tools,
terminal commands, shortcodes, dashboard widgets, translations → deactivate → uninstall
(`uninstall.php`) with backup. Integrity checker assigns per-plugin trust levels.
**Gap:** the install path accepts an arbitrary uploaded ZIP with no `plugins.manage` gate (S-02).

### F-04 — Privacy and consent
Data subject request → `PrivacyManager` builds the export (filterable via `privacy.export_data`) or
runs erasure (`privacy.erase_plugin_data` lets plugins participate) → audit recorded. Cookie
consent: declarations registered per plugin (`consent.declarations`), banner config, consent audit
trail, JS library gating third-party scripts.

### F-05 — Monetized AI access (x402)
Bot detector identifies an AI crawler → gate checks the page's x402 status → payment required
response via a provider (Coinbase CDP or Stripe) → transaction logged → access granted.

### F-06 — Content import / migration
`klytos-importer`: discover site → analyze (sitemap / WordPress XML / style) → fetch pages →
convert content → download media → execute batch → session status.
**Security-relevant:** this flow fetches arbitrary external URLs. It is the model-facing injection
channel per the MCP profile, and shares the SSRF exposure recorded as S-08.

### F-07 — Operations
Scheduled/recurring actions with retries (`ActionScheduler`), cron trigger at `/cron`, outgoing
webhooks with an event catalog, analytics + top pages, site health checks, logging, backups,
self-update with rollback, file integrity verification against RSA-signed manifests.

## 3. Data model (`as-built, unverified` — inferred from managers, not from a schema doc)

Entities behind `StorageInterface`: pages, post types, taxonomies, terms, post statuses, custom
field definitions and values, meta, blocks, template parts, page templates, menus, assets + asset
categories, users, comments, tasks, options (domain-scoped, sensitivity-classified), site config,
theme, versions, scheduled actions, webhooks, analytics events, audit log entries, privacy
requests, consent records, translations, x402 transactions.

Two backends implement it: flat-file JSON (`file-storage.php`) and SQL via PDO with prepared
statements throughout (`database-storage.php`). Sensitive values are encrypted at rest with
AES-256-GCM (`encryption.php`, `encryption-level-trait.php`).

**Migrations/backward compatibility are mandatory** — there is a live installed base with stored,
encrypted data and a self-updater that pulls new versions automatically.

## 4. Permissions matrix (defined vs enforced)

| Capability | Defined for | Enforced? |
|-----------|-------------|-----------|
| `users.manage` | owner | **No** — `admin/users.php` has zero checks (S-01) |
| `plugins.manage` | owner | **No** — `admin/plugins.php`, `admin/api/plugins.php` (S-02) |
| `updates.manage` | owner | **No** — `admin/api/update-install.php` (S-03) |
| `assets.manage` | owner/admin/editor | **No** — `admin/api/media-upload.php` (S-05) |
| `tasks.manage` | owner/admin | **No** — `admin/api/tasks.php` (S-06) |
| `site.configure`, `mcp.manage`, `webhooks.manage`, `theme.manage` | owner/admin | **No** — the corresponding pages have no gate (S-07) |
| terminal, logs, translations, AI chat | per matrix | **Yes** — these ~30% of surfaces do check |

## 5. Extensibility contract

~305 actions and ~110 filters (full list in `docs/api/INDEX.md`). Third parties can:
- **Alter responses** — strongest axis: `mcp.tools_list`, `mcp.handle_tool`, `mcp.tool_response`,
  `build.page.output`, `page.content`, `block.rendered_html`, `part.rendered_html`,
  `shortcode.output`, `x402.response_payload`.
- **Alter strings** — per-plugin catalogues via `translations.sources`; output-level rewriting via
  the render filters. **Gap:** no filter on `__()` itself, so a plugin cannot override a *core*
  translation without editing the catalogue (audit E-01).
- **Alter queries** — weakest axis: no generic query-args filter; a plugin can swap the storage
  backend but cannot cheaply rewrite an individual query (audit E-02).
- **Register** routes, admin pages, MCP tools, terminal commands, dashboard widgets, shortcodes,
  scheduled actions, options, template parts, capabilities (`auth.capabilities`).
- **Implement** `StorageInterface`, `CacheInterface`, `PaymentProviderInterface`.

## 6. Non-functional requirements (as-built)

| Requirement | Status |
|-------------|--------|
| Runs on shared hosting, PHP 8.1+, no Node, no daemon | Met |
| Works with and without a database | Met |
| Public site survives CMS downtime (static output) | Met |
| Multi-language, 20 locales | Met for the admin chrome (~85%); **not met** for generated output and system/error strings |
| WCAG 2.2 AA + EAA | **Not met** — ~20–25% admin, ~15% generated output |
| Automated test coverage | **Not met** — zero |
| Reproducible, auditable dependencies | **Not met** — no manifest for vendored packages |
| Release version consistency | **Not met** — four-way drift |

Every "not met" row has an entry in `docs/04-adoption-audit.md` and is triaged there — not silently
carried.
