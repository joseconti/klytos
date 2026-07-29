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

> **This table was stale and is corrected 2026-07-28.** It described the adoption-time state, in
> which none of these was enforced. **Every one of S-01…S-13 has been CLOSED since Sprint 1**
> (slices 3–5), and the correction is made here rather than noted for later because a spec that
> understates the product's own security is the same defect as one that overstates it — it is simply
> the harmless-looking direction, which is why it survived six sprints. Verified by reading the
> closure entries in `docs/04-adoption-audit.md`, not the summary lines that quote them.

| Capability | Defined for | Enforced? |
|-----------|-------------|-----------|
| `users.manage` | owner | **Yes** — S-01 closed. `users.php` is mapped owner-only in the gate map; `NamedEscalationsTest` reads the record back after the refusal, so a gate that ran the handler and then returned 403 would still fail |
| `plugins.manage` | owner | **Yes** — S-02 closed. `plugins.php` and `api/plugins.php` both mapped; the test asserts all three non-owner roles, admin included |
| `updates.manage` | owner | **Yes** — S-03 closed |
| `assets.manage` | owner/admin/editor | **Yes** — S-05 closed (`api/media-upload.php`) |
| `tasks.manage` | owner/admin | **Yes** — S-06 closed |
| `site.configure`, `mcp.manage`, `webhooks.manage`, `theme.manage` | owner/admin | **Yes** — S-07 closed **by inversion**, not by adding a check to each file: `admin/bootstrap.php` refuses a surface unless the gate map grants it, so a new ungated page fails closed instead of failing open |
| terminal, logs, translations, AI chat | per matrix | **Yes** — these were the ~30% that already checked at adoption time |
| Every MCP tool | per the central capability map | **Yes** — default-deny in `ToolRegistry::call()`, `tools/list` filtered per actor (Sprint 2, D-046…D-051), with `scripts/keel-verify` check 10 failing the build on an ungated tool |

**What is still NOT enforced**, stated in the same table's spirit: an MCP tool's own `inputSchema` is
advertised and never validated (**NEW-35**), and refusals fire an audit action that **nothing
subscribes to** (**NEW-32**). Both are `TO BUILD` in `docs/threat-model.md` (D12, D13).

## 4b. Acceptance criteria — stable IDs (Keel v5.0.0)

> **Why IDs exist:** they are what makes coverage checkable by a script instead of by anyone's
> account of themselves. Each ID travels to exactly three places — this list, the **name of the test**
> that covers it (`AC-07 …`), and the `Criterion` column of `docs/05-test-points.md`. A grep across
> the three is a real check; matching prose against prose is not.
>
> **Rules.** Assigned here, **never reused, never renumbered** — renumbering breaks the link between
> a shipped test and the requirement it proves. Criteria are appended; one that dies is marked
> *withdrawn* and its number is retired, not recycled. A criterion with no ID cannot be verified as
> covered, which in practice means it will not be.
>
> **This project is adopted and released, so the set below is deliberately NOT retroactive.** It seeds
> the IDs derivable from the flows this spec already records, at the fidelity this spec already states
> them. Every other area gets its IDs **in the slice that next touches it** — inventing eighty
> criteria today for code shipped a year ago would produce a document nobody wrote and nobody tests
> against. Accessibility conditions are part of the criterion for every UI-bearing row, per D-007.

| ID | Flow | Criterion | Notes |
|---|---|---|---|
| `AC-01` | F-01 | A fresh install completes the wizard and the 5-screen setup, and the resulting site boots | Drivable against a disposable copy only — `install.php` is destructive in a checkout (NEW-04) |
| `AC-02` | F-01 | An MCP client authorizes over OAuth and receives a working credential | The 2FA branch of the consent screen is **known broken** (NEW-38) — that is `AC-02`'s open failure path, not a separate concern |
| `AC-03` | F-01 | `tools/list` returns exactly the tools the caller's role may use, and never names one it may not | Sprint 2, D-046…D-051 |
| `AC-04` | F-01 | `tools/call` on a tool the caller may not use is refused, and the refusal names the tool and the fix but never the role or the capability | Pinned by identifier *shape*, not a word blocklist (L-018) |
| `AC-05` | F-01 | `klytos_build_site` renders static HTML plus `sitemap.xml`, `robots.txt`, `llms.txt`, `llms-full.txt`, JSON-LD, hreflang and the search index | |
| `AC-06` | F-02 | A page saved in the admin fires `page.after_save` and is rebuilt | |
| `AC-07` | F-02 | A second editor opening a locked post is told it is locked rather than silently overwriting | |
| `AC-08` | F-02 | Admin and MCP paths produce the same result on the same manager (the Desktop-vs-MCP equivalence contract) | |
| `AC-09` | F-03 | Only an `owner` can install, activate, deactivate, delete or uninstall a plugin; every other role is refused **and the plugin state is unchanged afterwards** | S-02 closed; the state-unchanged half is what makes it a real assertion |
| `AC-10` | F-03 | Uninstall removes every option the plugin created, with a backup taken first | |
| `AC-11` | F-04 | A data-subject export contains the subject's data from core **and** from every plugin that participates through `privacy.export_data` | |
| `AC-12` | F-04 | A third-party script does not execute before consent is recorded | |
| `AC-13` | F-05 | An identified AI crawler hitting a gated page receives the payment-required response, and the transaction is logged | Settlement leg is `CREDENTIAL` + `PRODUCTION-RISK` |
| `AC-14` | F-06 | An import refuses a URL resolving to a private or reserved address, in **every** notation — including IPv4-mapped IPv6 — and re-validates after each redirect | The bypass this pins was found by running the encodings, not by reading the flags (L-013) |
| `AC-15` | F-07 | A scheduled action that fails is retried per its policy and its outcome is recorded | |
| `AC-16` | F-07 | A self-update that fails rolls back to the previous version and the site still serves | `VERIFY` — needs a published release (`EXTERNAL-APPROVAL`) |
| `AC-17` | F-07 | File integrity verification fails loudly on a tampered manifest | |
| `AC-18` | all | Every protected admin surface is refused on a direct server request with JavaScript disabled | **Driven and passing** 2026-07-28 — see the self-audit Q20 row in `docs/keel-conformance.md` |
| `AC-19` | all | Every admin screen meets WCAG 2.2 AA: operable by keyboard, name/role/state exposed, contrast met, visible focus, error identification never colour-only, target size ≥ 24×24 | D-007. The automated half is drivable per screen and per state; the screen-reader half is `ASSISTIVE-TECH` |
| `AC-20` | all | Every user-facing string resolves in all 20 locales, with no key missing from any catalogue | Already enforced by the `keel-verify` catalogue parity check |

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
