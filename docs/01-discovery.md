# 01 — Discovery — Klytos CMS

> **Adopted project — reconstructed as-built.** This project predates Keel. This file records
> retroactively what Phase 1 would have produced, from the code inventory (2026-07-18) plus the
> user's answers to the adoption step-3 questions. Anything derived from reading code rather than
> confirmed by the user or a test is labelled `as-built, unverified`.

## 1. Problem and outcome

**Problem.** Managing a website still means a human operating a UI. Even where AI assistants can
write content, they cannot *run* the site: they have no first-class, authenticated, structured
control surface over pages, templates, media, navigation, users or the build. Bolted-on AI in
existing CMSs is a text-generation feature inside a human-first product, not a control plane.

**Outcome.** A CMS whose primary interface is an AI agent speaking MCP. A person describes what
they want in natural language and the AI creates the post types, fields, templates, blocks, menus,
pages, media and SEO artifacts directly — then builds the site to static HTML. The human admin
panel exists for supervision and for the things a human must do, not as the main road.

**Constraints that shaped it** (`as-built, unverified` — inferred from the code):
- Must run on ordinary shared hosting: PHP 8.1+, Apache + mod_rewrite, no Node build step, no
  Composer required at runtime, no daemon.
- Must work without a database (flat-file JSON) *and* with MySQL/MariaDB — storage is pluggable.
- Output is static HTML, so a compromised or offline CMS does not take the public site down.
- Self-hosted and open source (GPL-3.0-or-later): the operator owns the data and the keys.

## 2. Project type

**Web app (self-hosted PHP application) + MCP server.** Both security profiles apply
(`references/security/web-app.md`, `references/security/mcp-server.md`); the stricter rule wins on
conflict. Recorded as D-003.

This matters concretely: the MCP surface adds a threat class the web-app profile does not cover —
tool descriptions are prompts (poisoning / rug-pull), tool results carrying third-party content are
data and never instructions (injection), and destructive tools need confirmation or dry-run.
Klytos's importer (which fetches arbitrary external sites) and its oEmbed resolver are exactly the
channels that class describes.

## 3. The product as built (the de facto v1 — and beyond)

Klytos is well past a v1. The feature set below is the **as-built reality**, not a plan.

| Area | What exists | Why it is in the product |
|------|-------------|--------------------------|
| MCP control plane | **172** core tools + **8** x402 + **26** from the two bundled MCP plugins (206 on disk; a default install serves 180 — neither plugin is active); JSON-RPC 2.0 at `/mcp`; OAuth 2.0 (authorize/token/metadata discovery); bearer-token auth; per-token rate limiting; **per-tool authorization since Sprint 2** | The thesis of the product |
| Content model | Pages with hierarchy, trash/restore, locks, scheduling, versions/diff/restore; custom post types, taxonomies, terms, custom post statuses; 27 typed custom field types; free-form meta | Table stakes for a CMS |
| Templates & rendering | Template resolver, page templates with approval flow, reusable blocks with slots, template parts, Gutenberg-compatible block markup | Needed for AI to compose layouts deterministically |
| Build | Static site generation, per-page build, sitemap.xml, robots.txt, **llms.txt / llms-full.txt**, JSON-LD, hreflang, search index | Static output + AI-discoverability differentiator |
| Media | Asset library with categories, usage tracking, unused-asset cleanup, image editing, AI image generation | Table stakes + AI added value |
| Design | Token-based theming (`--klytos-*`), colors/fonts/layout via MCP, generated frontend CSS | Lets AI restyle a site without writing CSS |
| Users & auth | Roles (owner/admin/editor/viewer), bcrypt cost 12, sessions with regeneration, login lockout, TOTP + WebAuthn 2FA, audit log | Table stakes |
| Privacy & compliance | GDPR export/erasure requests, cookie consent manager with declarations and consent audit, privacy policy tooling | EU market requirement |
| Extensibility | ~305 actions + ~110 filters, plugin loader with install/activate/uninstall/backup, plugin routes, admin pages, dashboard widgets, shortcodes, terminal commands; 5 bundled plugins | Third parties extend without forking |
| Operations | Action scheduler with retries, cron, webhooks, analytics, site health, logging, backups, self-updater, web terminal + `cli.php` | Table stakes for self-hosted |
| Integrity | SHA-256 manifests with RSA signature verification, per-plugin trust levels, developer key registration | Differentiator for a self-hosted, AI-operated CMS |
| Monetization surface | x402 micropayment gating for AI bots (Coinbase CDP + Stripe providers), premium plugin licensing | Differentiator / revenue path |
| i18n | 20 locales, 639 keys each, perfectly in sync | Table stakes for the EU market |

**Not in the product** (recorded so no session assumes otherwise): no automated tests, no CI, no
dependency manifest for its own vendored dependencies, no design source files, no changelog since
0.4.0.

## 4. Competitive position

Full report: `docs/00-competitive-landscape.md` (scan run at adoption, 2026-07-18; 109 cited
sources; status **partial** — Reddit needed a mirror, the search budget was exhausted, and
Lobsters / IndieHackers / ProductHunt / WPTavern went unexamined; those are marked *uncovered*, not
*negative evidence*). Headlines:

- **The direct threat is EmDash, not WordPress.** Cloudflare shipped an MIT-licensed,
  self-hostable, MCP-native, x402-supporting, Astro-based CMS on 2026-04-01 — matching most of
  Klytos's differentiation list — and it has **11.3k GitHub stars against Klytos's 10**. Cloudflare
  acquired Astro in January 2026; this is a coordinated stack play, not a side project.
- **The defensible intersection is real and currently empty:** no PHP CMS has *both* MCP and static
  generation in core. Statamic is closest (core + two addons, $349/site, requires Laravel); Grav's
  MCP is a Node sidecar; WordPress's static story is plugin-only with WP2Static dead. **But the
  window is closing:** the official PHP MCP SDK is now maintained by Symfony / the PHP Foundation,
  so expect PHP competitors within roughly twelve months.
- **Three marketed pillars are weakly or negatively evidenced.** x402: zero demand across seven CMS
  repos, ~77% volume collapse, and Cloudflare abandoned pay-per-crawl in July 2026 — even EmDash
  shipping it drew 3 comments out of 504. GDPR consent: regulatory pressure is real but user pull
  is literally zero across six repos and HN — a sales asset, not an acquisition driver. Shared
  hosting / no-Docker: the weakest of nine themes; r/selfhosted is Docker-*positive*.
- **Two under-exploited assets Klytos already has.** **Multilingual** is the highest-volume unmet
  defect cluster in the entire category (Tina has no i18n at all; Decap's issue has been open since
  2020) — and Klytos's 20 in-sync locales appear nowhere in its positioning. **Safe agent write
  access** is severe latent demand with documented open gaps at Directus, Payload and Strapi.

Also caught by the scan, and worth fixing on its own: **the README's "160+ tools / 75+ hooks" is
stale** — the real figures are 206 MCP tools and 411 hooks/filters (`docs/api/INDEX.md`). The
project undersells itself by a factor of five on extensibility.

**Open Keel item:** the scan skipped step 0's opening question — *which competitors does the user
already know about?* That was never asked. The user's answer may add entries this scan missed.

## 5. Project decisions carried into Keel

| Decision | Value | Record |
|----------|-------|--------|
| Project type | web app + MCP server | D-003 |
| Stack & conventions | adopted as-is | D-004 |
| License | GPL-3.0-or-later | D-005 |
| i18n | multi-language, base English, 20 locales, custom JSON mechanism | D-006 |
| Accessibility target | **WCAG 2.2 AA + European Accessibility Act**, admin panel *and* generated output | D-007 |
| Docs language | English (already the project's language) | D-008 |
| Design system | existing, token-based, no design source files; current look is the baseline | D-009 |
| Portability | dual lock + dual embedded skill (open source: any assistant) | D-002 |
| Assistant config | full package for Claude Code, Codex, Cursor, Copilot, Gemini CLI, Windsurf | D-010 |
| Model binding | orchestrator / reviewer / mechanical default map | D-011 |
| Website intent | yes — Phase 8 later, on request | D-012 |
| Client budget | no — own project | D-013 |

## 6. Internationalization (Phase 1 §6, answered as-built)

1. Multi-language or single? **Multi-language.**
2. Which output locales ship? **20**: ca, da, de, el, en, es, eu, fi, fr, gl, it, ja, nb, nl, pl,
   pt, ru, sv, tr, zh.
3. Is English the base/principal language? **Yes.**
4. Docs language? **English** — already the case for every existing document; no translation needed.

Mechanism note: Klytos uses a custom key-based JSON catalogue (`__('domain.key')`), not gettext.
Keel's "WordPress projects are always gettext + `.pot`" rule does **not** apply — this is not a
WordPress project. The obligation that does carry over: every new user-facing string is
externalized through `__()` with its key added to all 20 catalogues, in the same slice.

## 7. Accessibility (stated up front, per Keel)

Target: **WCAG 2.2 AA as the floor, AAA where feasible, plus EN 301 549 / European Accessibility
Act**, applied to the admin panel *and* to the HTML Klytos generates for its users' sites. The
generated output is the higher-stakes surface: Klytos's users inherit its accessibility, and under
the EAA the legal exposure lands on them.

Measured reality at adoption (`docs/04-adoption-audit.md`): roughly 20–25% of a WCAG 2.1 AA
baseline in the admin, ~15% in generated output. The gap between the shipped
`klytos-accessibility` skill's compliance claim and the code is recorded as L-002.

## 8. Honest assessment

Recorded without softening, per Keel's operating principles.

**Strong.** The extension surface (~415 hooks, MCP tools as a first-class extension point) is the
most mature subsystem and is genuinely comparable in ambition to WordPress's. The i18n catalogues
being 20-for-20 in perfect sync is unusual discipline. Input handling — escaping, sanitization,
prepared statements, CSP with nonces, AES-256-GCM at rest, bcrypt cost 12, RSA-signed integrity
manifests — is above the norm for a self-hosted PHP project. `llms.txt` generation and x402 bot
gating are real, early positioning on where the web is going.

**Weak, and it is not close.** Three things undercut the above:

1. **Authorization is decorative.** A complete role/capability matrix exists, is duplicated in two
   divergent places, and the canonical implementation (`UserManager::hasPermission()`) is called
   from nowhere. Permission gates cover ~30% of admin surfaces. A `viewer` can promote itself to
   `owner` and can install a plugin ZIP — arbitrary PHP execution. For a product whose entire
   premise is *handing control to an autonomous agent*, the weakest axis being authorization is the
   worst possible place for it to be weak.
2. **Zero tests, zero CI**, on a codebase doing encryption, session auth, 2FA, OAuth 2.0, plugin
   installation and payments. Every release is manually verified or not verified at all.
3. **Release hygiene has drifted four ways** — `VERSION` 0.31.1-beta.1, README 0.28.5, changelog
   0.4.0, newest tag v0.30.1 — with nothing reconciling them, and `.gitattributes` strips
   `README.md`/`INSTALL.md` from release archives that `INSTALL.md` itself tells users to upload.

**Strategically weak, per the competitive scan.** Three of the pillars the product markets on are
weakly or negatively evidenced — x402, GDPR consent, and shared-hosting/no-Docker — while the two
assets with the strongest documented demand (20 in-sync locales; safe agent write access) are
absent from the positioning. "AI-first CMS" is no longer a claim that differentiates: WordPress core
shipped the Abilities API in 6.9, Lovable is at $500M ARR, and Cloudflare's EmDash matches most of
the differentiation list with 11.3k stars to Klytos's 10.

**Verdict — proceed, with two mandatory corrections.**

1. **Technically:** the product is real and the ambition is justified, but it cannot be recommended
   for third-party production use until the authorization gaps are closed. That is the first
   sprint, ahead of every feature. Tests, accessibility and version hygiene follow on the priority
   set in `docs/04-adoption-audit.md`.
2. **Strategically:** reposition. Lead with the empty, defensible intersection — *the PHP CMS with
   MCP and static generation in core, on hosting you already have, multilingual out of the box* —
   and drop x402 from the marketing (keep the code; it costs nothing to keep and the thesis may
   return). The window on that intersection is roughly twelve months, because the official PHP MCP
   SDK is now Symfony/PHP-Foundation maintained.

**The biggest risk is not competition.** It is **10 stars and a bus factor of 1**. The scan
documents three architecturally similar projects — LightCMS (23 stars), Seite (13), VoxelSite (4) —
that were right about the same things and went nowhere anyway. Being correct is not the constraint;
distribution and a second contributor are. This is worth saying plainly because the security and
test work above is exactly what makes a second contributor possible, and the repositioning is what
makes anyone look.

**User decision on the verdict:** [pending — to be recorded when the audit triage is agreed]
