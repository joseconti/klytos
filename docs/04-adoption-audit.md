# 04 — Adoption Audit — Klytos CMS

> Gaps between the as-built reality and Keel's standards, recorded honestly on 2026-07-18.
> This file exists only in adopted projects. **Nothing here was fixed during adoption** — adoption
> changes no code. Each gap carries what / where / severity / the standard it fails, and a triage
> bucket agreed with the user: **fix now** / **fix when touched** / **accepted**.
>
> Evidence is `file:line` from the read-only inventory. Findings marked `unverified` were inferred
> from reading code and have not been reproduced against a running instance.

## Triage summary

**Triaged with the user on 2026-07-18** (D-018).

| Bucket | Findings | Meaning |
|--------|----------|---------|
| **Fix now** — Sprint 1 | S-01, S-02, S-03, S-04, S-05, S-06, S-07, S-08, S-09, T-01 — **plus, added at Sprint 1 planning:** NEW-01, T-02, H-04, S-11, S-12, the CSP fail-open | The entire authorization axis + SSRF + the broken public comment handler, plus a minimal test harness. NEW-01 is a prerequisite (it defeats every gate); T-02 is required *by* this sprint's verification; H-04 enters via D-022 |
| **Fix when touched** | everything else (23 findings, listed below with their triggers) | Bound to a named trigger — not to "someday" |
| **Accepted** | *none* | Nothing was accepted as permanent. Every finding has an owner and a trigger. |

**Why T-01 is in Sprint 1 and not later:** without at least a minimal harness, the S-0x remediation
can be *asserted* but not *demonstrated*. Keel's test-point rule — "a result without its command and
output is an empty cell" — cannot be satisfied on an authorization fix by reading the diff.

### Fix-now bucket — status as of 2026-07-19 (end of slice 5)

Each finding's own section carries its closure note and the test that pins it; this is the index.

| Finding | Status | Closed by |
|---|---|---|
| **S-01** privilege escalation via `users.php` | **CLOSED** | slice 4 (gate) + slice 5 (named test, asserts the role did not change) |
| **S-02** arbitrary plugin ZIP → RCE | **CLOSED** | slice 4 + slice 5 |
| **S-03** unauthorized core update install | **CLOSED** | slice 4 + slice 5 |
| **S-04** dead + duplicated capability matrix | **CLOSED** | slice 3 |
| **S-05** unauthorized file upload | **CLOSED** | slice 4 + slice 5 |
| **S-06** ungated write endpoints | **CLOSED** | slice 4, **plus a live residue in `api/tasks.php` closed in slice 5** |
| **S-07** ~30% gate coverage (systemic) | **CLOSED** | slice 4 (central default-deny gate, 65/66 mapped) |
| **S-12** identity export: GET + no CSRF | **CLOSED** | slice 5 |
| **T-01** no test harness | **CLOSED** | slice 1 |
| **T-02** no playground | **CLOSED** | slice 0 |
| **H-04** unauditable vendored tree | **CLOSED** | slice 2 |
| **NEW-01** `klytos_current_user()` promotes to owner | **CLOSED** | slice 3 |
| **S-08** SSRF in the oEmbed resolver | **CLOSED** | slice 6 (`SafeHttp`, applied at 5 call sites; DNS rebinding remains open as **NEW-15**) |
| **S-09** public comment submission | **CLOSED** | slice 7 (handler moved OUT of `admin/` to the web root; the per-session rate limit replaced with the persistent IP-keyed one; **NEW-16** found and fixed in path, **NEW-17**/**NEW-18**/**NEW-19** recorded) |
| **S-11** no HSTS + the CSP fail-open | **CLOSED** | slice 8 (HSTS added, HTTPS-only and without `includeSubDomains` per D-044; the CSP now fails closed to `script-src 'self'`) |
| **NEW-14** no admin API endpoint sends security headers | **CLOSED** | slice 8 (ONE enforcement point in `admin/bootstrap.php`; the gap was **25** surfaces, not 24 — see the corrected entry below; **NEW-21**/**NEW-22**/**NEW-23** recorded) |

| **NEW-02** MCP tools have zero authorization | **CLOSED** | Sprint 2 (slices 1–4): actor from the credential, ONE default-deny gate in `ToolRegistry::call()`, central capability map, `tools/list` filtered, keel-verify check 10, the loader fails loudly, both shipped MCP plugins + x402 + integrity gated, **NEW-30** resolved, the refusal translated in 20 locales |
| **NEW-30** filter-injected tools uncallable over HTTP | **CLOSED** | Sprint 2 slice 3 (D-050) |
| **NEW-03** by-reference hook listeners silently discarded | **CLOSED** | Sprint 4 slice 1 (D-054): refused at registration; `page.save_data` filter carries the one real mutation. Its "301 registered actions" figure was wrong in number and in kind — see the entry |
| **NEW-36** the post-type allow-list drops what its own filter adds | **CLOSED** | Sprint 4 slice 1 (D-054), found by driving the NEW-03 feature end to end. The x402 post-type checkbox had **never** persisted |

Stated plainly so the closures are not read as more than they are. **The admin surface and the
product's primary interface are now both gated** — that sentence used to end "and the primary
interface is not", and Sprint 2 is what changed it. What is still true: only `config['admin_user']`
can actually log in (**NEW-11**), so the multi-role system the two gates enforce has, in production,
exactly one role that can reach the admin panel interactively. The MCP surface does not share that
limit — a bearer token can be minted at any role today, which is why the sprint's end-to-end proof
is a `role=viewer` bearer token refused a destructive tool over real HTTP.

### Re-validation, 2026-07-18 (Phase 5 Sprint 1 kickoff) — three claims corrected, two findings added

Every fix-now finding was re-verified against source before planning on top of it. The corrections
below are **amendments, not rewrites** — the original text of each finding is kept and annotated, so
what was believed and what was measured both stay on the record.

| Finding | Correction |
|---------|------------|
| **S-04** | The claimed **divergence is refuted** — the two matrices are byte-for-byte identical. Dead duplicate and drift hazard; not a live inconsistency. Severity effectively MEDIUM, not HIGH. |
| **S-07** | Sharper than stated: 15/66 ≈ 23% overall, but **lopsided** — admin pages 12% gated (5 real gates in 42 files), API endpoints 42%. |
| **S-12** | **Partly refuted** — an owner-only gate does exist. The real defect is a state-changing **GET** with no method check and no CSRF, plus a docblock asserting protections that are not implemented. |

Two findings were added that did not exist at adoption: **NEW-01** and **NEW-02** (below). NEW-01 is
a prerequisite for Sprint 1 — it defeats every gate the sprint adds. NEW-02 is Sprint 2 (D-020).

### Fix-when-touched register (trigger, not a wish)

| Finding | Trigger |
|---------|---------|
| S-11 no HSTS, S-12 identity export, CSP fail-open | **Pulled into Sprint 1** (slices 5 and 8) — they were cheap, as the "Sprint 1 if cheap" clause anticipated |
| S-10 CSP `style-src 'unsafe-inline'` | **Its own sprint, deliberately.** Measured cost: **349 inline `style=` attributes across 40 files** plus 12 `<style>` blocks. An attribute cannot carry a nonce, so all 349 must become CSS classes. Sprint 1 slice 8 nonces the 12 blocks and leaves `unsafe-inline` in place. Trigger: the CSS-consolidation sprint, or any slice that already refactors an admin page's markup |
| H-04 vendored dependency manifest | **Pulled into Sprint 1** (slice 2) by D-022 — no longer "next release" |
| S-13 MCP model-facing threats | First slice touching `mcp/tool-registry.php` or the importer's fetch path — **verify the `destructiveHint` annotation state first** |
| A-01…A-07 accessibility | A dedicated accessibility sprint after Sprint 1. A-05 (zero ARIA in generated output) is the highest-stakes item in the whole audit after the authorization axis — Klytos's users inherit it, and under the EAA the liability is theirs |
| I-01, I-02, I-03, I-04 i18n | I-04 (17 hardcoded unaccented Spanish strings) with the next `terminal-executor.php` touch; the rest per area |
| E-01, E-02 extensibility | The next slice touching `i18n.php` (E-01) or a manager's read path (E-02) |
| T-02 playground, T-03 phpstan, T-04 phpcs not a dependency | The Phase 5 scaffold — T-02 is required *by* Sprint 1's verification, so it effectively lands with it |
| H-01…H-04, H-07 release hygiene | The next release runs the FULL Phase 7, which is where these close by construction |
| H-05 missing license headers, H-06 stray `docs/` artifacts | Opportunistic; H-06 needs the user to classify `docs/docs` and `docs/consent-manager.js` |
| D-01 per-surface docs | Progressive backfill — each surface the first time a slice touches it |
| D-02 docs asserting unimplemented properties, D-05 stale README counts | With the repositioning work (D-017) and Phase 6 |
| D-04 INDEX not regenerable | Phase 5 scaffold, wired into `scripts/keel-verify` |
| D-06 skills teach the superseded parts API | The theme-package sprint (D-023) — or earlier, any slice touching `klytos-custom-templates` or a sibling skill referencing global blocks |
| F-01 no propagation path for parts | **The theme-package sprint (D-023)**, where it is a required deliverable, not an optional one: it is the mechanism the user's single-source-of-truth invariant depends on (`docs/theme-package-model.md` §3b) |

---

## S — Security (profiles: `security/web-app.md` + `security/mcp-server.md`)

### S-01 — Privilege escalation: any authenticated user can become `owner` — **CRITICAL**
- **Where:** `installer/admin/users.php:42`, `:51`, `:76`
- **What:** The page handles `create_user` and `update_role` POSTs with **zero** permission checks,
  although `users.manage` is defined as `owner`-only. A `viewer` can POST `update_role` and promote
  itself to `owner`, then do anything.
- **Fails:** web-app profile — authorization on every state-changing endpoint; principle of least
  privilege.
- **Note:** CSRF *is* checked, which means the attack requires an authenticated session — not an
  anonymous request. It is still full vertical privilege escalation.
- **CLOSED 2026-07-19, Sprint 1 slice 4 (access) + slice 5 (proof).** `users.php` is mapped
  `users.manage` in the gate map (owner-only), so a viewer never reaches the POST handler. Pinned by
  `NamedEscalationsTest::testS01ViewerCannotPromoteItselfToOwner`, which asserts more than the 403:
  it reads the record back through `UserManager` afterwards and fails if the role changed. A gate
  that returned 403 *after* the handler had run would satisfy a status-only assertion, so the
  role-unchanged check is the one that actually closes this finding.

### S-02 — Arbitrary plugin ZIP install → remote code execution — **CRITICAL**
- **Where:** `installer/admin/api/plugins.php:44`, `:72`, `:167-253`; UI page `installer/admin/plugins.php` (no gate)
- **What:** Only CSRF is verified. No `plugins.manage` gate (matrix: `owner` only). Any
  authenticated user can `install` an uploaded plugin ZIP — which is PHP that Klytos then executes —
  or activate / deactivate / delete / uninstall / restore any plugin.
- **Fails:** web-app profile — authorization; safe handling of uploaded executable content.
- **CLOSED 2026-07-19, Sprint 1 slice 4 (access) + slice 5 (proof).** `api/plugins.php` is mapped
  `plugins.manage` (owner-only) and `plugins.php` likewise, so no non-owner reaches the install path.
  Pinned by `NamedEscalationsTest::testS02NonOwnersCannotReachThePluginInstallEndpoint`, which
  asserts all three non-owner roles — **admin included**, which is the interesting one, since an
  admin holds nearly everything else but not this — and asserts the refusal arrives as parseable
  JSON, because this endpoint is called by XHR.

### S-03 — Unauthorized core update install — **HIGH**
- **Where:** `installer/admin/api/update-install.php:42`
- **What:** CSRF only, no `updates.manage` gate (`owner` only). Any authenticated user can trigger a
  core update install (which downloads and unpacks code).
- **Fails:** web-app profile — authorization; supply-chain integrity of the update path.
- **CLOSED 2026-07-19, Sprint 1 slice 4 (access) + slice 5 (proof).** `api/update-install.php` is
  mapped `updates.manage` (owner-only). Pinned by
  `NamedEscalationsTest::testS03NonOwnersCannotTriggerACoreUpdateInstall`, with the owner's
  allow-path asserted alongside so the test cannot pass by the endpoint simply being unreachable.

### S-04 — The capability matrix is dead code, and duplicated divergently — **HIGH**
- **Where:** `installer/core/user-manager.php:592` (`hasPermission()`, never called from anywhere)
  and `:601-624` (matrix copy A) vs `installer/core/helpers-global.php:430-444` (matrix copy B, the
  live one used by `klytos_has_permission`)
- **What:** Two independent definitions of the same security matrix, guaranteed to drift; the more
  authoritative-looking one is never executed. A maintainer reading `user-manager.php` reasonably
  concludes roles are enforced.
- **Fails:** web-app profile — a single source of truth for authorization; Keel's reuse rule
  (duplication is a defect).
- **CORRECTED 2026-07-18 (re-validation).** The phrase "guaranteed to drift" describes a future
  risk, not the present: both matrices were parsed and compared programmatically and are
  **byte-for-byte equivalent** — 22 permissions each, zero keys unique to either side, zero role-list
  divergences; the only difference is whitespace alignment. So this is a **dead duplicate**, not a
  live authorization inconsistency. Effective severity **MEDIUM**. The remediation is unchanged
  (delete the dead copy), but the urgency claim in the original text was overstated.
- Line numbers as measured on 2026-07-18: `klytos_has_permission()` starts at `helpers-global.php:408`
  with its matrix at **422-445** (the cited `:430` is mid-matrix, and is stale in
  `.claude/rules/security.md` too). `UserManager::hasPermission()` at `user-manager.php:592`, matrix
  **602-625**. "Never called" **confirmed** — one repo-wide grep hit, the definition itself.
- **CLOSED 2026-07-19, Sprint 1 slice 3.** Resolved the opposite way round from the original
  remediation note, and deliberately: the dead copy was **kept** and the live one deleted.
  `UserManager::hasPermission()` now holds the single matrix and `klytos_has_permission()` delegates
  to it, because `UserManager` is the lower layer — it decides for an explicitly supplied user, while
  the helper's job is resolving *which* user is current. Deleting `UserManager`'s copy instead would
  have left the sprint's later consumers (slice 4's `klytos_require_permission()`, Sprint 2's MCP
  gating, which both hold a user object rather than a session) with nothing to call, and "never
  called" stops being true the moment slice 4 lands. Guarded two ways in
  `tests/Integration/PermissionMatrixTest.php`: behaviourally, both entry points are asserted to
  agree across the full 4-role × 23-permission cross-product; and structurally, a test fails if any
  second definition of the matrix reappears anywhere in `installer/core/`. The structural guard was
  demonstrated to FAIL against the unfixed code before being trusted.

### S-05 — Unauthorized file upload — **HIGH**
- **Where:** `installer/admin/api/media-upload.php:26`
- **What:** CSRF only, no `assets.manage` gate (matrix: owner/admin/editor). A `viewer` can upload
  files.
- **Fails:** web-app profile — authorization on upload endpoints.
- **CLOSED 2026-07-19, Sprint 1 slice 4 (access) + slice 5 (proof).** `api/media-upload.php` is
  mapped `assets.manage` (owner/admin/editor). Pinned by
  `NamedEscalationsTest::testS05ViewerCannotUploadMediaButEditorCan`. The positive half is unusually
  load-bearing here, and the test name says so deliberately: the boundary is between **editor and
  viewer**, not around the owner, so a gate that refused everyone would satisfy the refusal
  assertion while silently breaking uploads for the three roles that are supposed to have them.

### S-06 — Ungated write endpoints — **MEDIUM**
- **Where:** `installer/admin/api/autosave.php`, `notices.php`, `sidebar-order.php`, `tasks.php`
- **What:** CSRF present, capability checks absent (`tasks.manage` is owner/admin-only).
- **Fails:** web-app profile — authorization.
- **EXTENDED 2026-07-18 (re-validation).** Two further ungated, state-changing endpoints were found
  and are folded into the same remediation: `installer/admin/api/inline-edit.php` (inline field edit,
  CSRF at `:55`) and `installer/admin/api/terminal-revalidate.php` (terminal 2FA revalidation, CSRF
  at `:42`, checks 2FA at `:48` but **never** `terminal.access`). Also noted: `post-lock.php:67`
  gates only the lock-*takeover* branch, leaving the file's other actions ungated, and `ai-chat.php`
  has no top-level gate — `site.configure` is checked per-action at `:248`, `:272`, `:282`, `:298`.
- **CLOSED 2026-07-19, Sprint 1 slice 4 (access) + slice 5 (the residue the gate could not reach).**
  All six named endpoints are mapped: `autosave`/`inline-edit`/`post-lock` → `pages.edit`, `tasks` →
  `tasks.create`, `terminal-revalidate` → `terminal.access`. `notices` and `sidebar-order` are mapped
  `ui.preferences`, which **every** role holds, and that is deliberate rather than an omission: they
  carry per-user interface state, and gating them at a content tier would stop a viewer dismissing
  its own notice. Both are asserted as *reachable*, so a later "tightening" that breaks them fails a
  test instead of a user.
- **A live residue survived slice 4 and was closed in slice 5, and it is the part worth remembering:**
  `admin/api/tasks.php` is mapped at the same `tasks.create` floor as its page twin, but — unlike the
  page (`admin/tasks.php:38`) — it never re-gated `update` and `complete` at `tasks.manage`. So an
  editor was **refused task completion through the interface and allowed it through the endpoint that
  interface calls.** The gate map cannot detect this, because both files legitimately sit at the same
  floor; only reading the two surfaces side by side does. Fixed by adding
  `klytos_require_permission( 'tasks.manage' )` to both branches, before any state change. Pinned by
  `NamedEscalationsTest::testS06TaskApiRegatesManageActionsLikeItsPage`, **proven to fail first** —
  it returned **500**, not 403, which is itself the proof: the handler had executed and thrown on a
  non-existent task id, so the authorization branch was never consulted.
- **General rule this produced,** now recorded in `docs/reference/authorization.md`: when a page and
  an API expose the same operation, they express the same capability model or the model is enforced
  in only one of them.

### S-07 — Permission-gate coverage is ~30% — **HIGH (systemic)**
- **Where:** `klytos_has_permission` appears in 13 of 42 admin pages and 11 of 24 API endpoints.
  Ungated privileged pages include `admin/security.php`, `admin/mcp.php`, `admin/updates.php`,
  `admin/webhooks.php`, `admin/theme.php`, `admin/privacy.php` — all defined as owner/admin-only.
- **What:** S-01…S-06 are instances; this is the systemic finding. The role system is largely
  decorative outside the terminal, logs, translations and AI-chat surfaces.
- **Fails:** web-app profile — authorization; MCP profile — confused deputy (an MCP-driven agent
  inherits whatever the ungated surface allows).
- **Recommended remediation shape:** a single enforced gate helper called at the top of every admin
  page and API endpoint, driven by one matrix, with a mechanical check (a test, or `keel-verify`)
  that fails the build when a file under `admin/` lacks a gate.
- **SHARPENED 2026-07-18 (re-validation).** The overall figure holds (15 of 66 files ≈ 23%), but the
  split is **lopsided** and the pages are far worse than the average suggests:
  - **Admin pages: 5 real gates in 42 files (12%)** — `logs.php:31`, `system-options.php:27`,
    `translations.php:29`, `terminal.php:29` (all four redirect to `admin/`), and
    `plugin-page.php:91` (the only one that returns a correct 403 and is i18n'd). Two further files
    reference `klytos_has_permission` but are **conditional UI, not gates**: `index.php:156` filters
    dashboard widgets, `settings.php:492,547` hides sections — in both cases the page and its POST
    handlers stay open.
  - **API endpoints: 10 top-level gates in 24 files (42%)**, plus 2 partial (`post-lock.php`,
    `ai-chat.php`).
  - **24 ungated admin pages are state-changing**, including `users.php`, `plugins.php`,
    `updates.php`, `security.php`, `mcp.php`, `theme.php`, `x402-settings.php`.
- **A verified model for the fix already exists in core:** `installer/core/router.php:438-447` gates
  plugin-registered dynamic routes and already branches denial by surface type (`Helpers::jsonResponse`
  403 for api/webhook, an HTML 403 otherwise). The static admin pages never pass through that router,
  which is why they are ungated. Promote that shape into `klytos_require_permission()` rather than
  inventing one.
- **Adjacent defect found in the same pass:** `admin/bootstrap.php:242-251` **302-redirects API
  endpoints** to an HTML login page instead of returning 401 JSON, because every `admin/api/*.php`
  requires that bootstrap. Consequence: the `isAuthenticated()` re-checks in 21 of 24 endpoints are
  **dead code** — they can never be reached unauthenticated — and XHR callers receive an opaque
  redirect rather than a parseable error.

- **CLOSED 2026-07-19, Sprint 1 slice 4.** Resolved by inversion rather than by addition. The
  remediation note above asked for "a single enforced gate helper called at the top of every admin
  page and API endpoint"; that shape was **rejected** because it preserves the defect — file 67 can
  still forget. Instead the decision moved to the one place all 66 files provably pass through
  (`admin/bootstrap.php`, verified mechanically: zero exceptions), driven by a **gate map** in
  `installer/core/admin-gate.php` in which an ABSENT entry is a REFUSAL. A new admin file is denied
  until someone maps it deliberately. Coverage went from 15/66 to **65/66 mapped surfaces**, the
  66th being `bootstrap.php` itself, which is deliberately unmapped so a direct request for it hits
  default-deny. `klytos_require_permission()` and `klytos_deny()` were added as the reusable
  enforcing counterparts to `klytos_has_permission()`; Sprint 2 consumes the same pair at the MCP
  enforcement point. The denial shape from `core/router.php:438-447` was **promoted**, not
  reinvented, exactly as this finding recommended. Mechanically guarded by `scripts/keel-verify`,
  which was proven to FAIL on a removed gate entry and then restored. Full design and the rejected
  alternatives: **D-032**.
- **The adjacent defect is also CLOSED (same slice).** The auth guard now answers **401 JSON** for
  `admin/api/*` instead of 302-redirecting to an HTML login page, so the 401 contract those
  endpoints advertise is observable for the first time. The dead `isAuthenticated()` re-checks
  inside them are left in place as defence in depth — removing them is cosmetic and would have
  hidden NEW-09, which was found precisely by asking why one of those "dead" checks existed.

### S-08 — SSRF in the oEmbed resolver — **MEDIUM** — **CLOSED 2026-07-19 (Sprint 1 slice 6)**

> **Closed by** `Klytos\Core\SafeHttp` (`installer/core/safe-http.php`), which promoted
> `KlytosImporter\ImportValidator::validateUrl()` — the product's only working SSRF control — into
> core and applied it at five call sites. The audit entry below described the pre-flight half only;
> the survey done for the fix found the finding was **wider than recorded** in two ways:
> (a) the *discovered* oEmbed endpoint is attacker-controlled too, and unlike the page fetch its
> response **is** echoed to the caller, so the file had a second and fully arbitrary fetch; and
> (b) every fetch followed redirects with no per-hop validation, so pre-flight validation alone would
> not have closed it. Both are fixed. Tests: `tests/Unit/SafeHttpTest.php`,
> `tests/Integration/SafeHttpRedirectTest.php`, `tests/Integration/OembedSsrfTest.php` — six of the
> eight endpoint tests proven to FAIL against the unfixed code. Residual gap: **NEW-15** (DNS
> rebinding). Reference: `docs/reference/safe-http.md`. Decision: **D-041**.

- **Where:** `installer/admin/api/oembed.php:131` (`discoverOembed($url)`), validation at `:19`
- **What:** Fetches an arbitrary user-supplied URL when no known provider matches.
  `filter_var(FILTER_VALIDATE_URL)` is the only check — no blocking of private ranges, `127.0.0.0/8`,
  `localhost`, `169.254.169.254` (cloud metadata) or non-HTTP schemes. Authenticated-only, which
  limits severity.
- **Fails:** web-app profile — SSRF on user-influenced outbound requests. The importer's fetch paths
  (`klytos_import_fetch_page`, media downloader) should be reviewed against the same rule.

### S-09 — Public comment submission is broken *and* mislabelled — **MEDIUM (functional + security-relevant)**
- **Where:** `installer/admin/api/comment-submit.php:36`; guard at `installer/admin/bootstrap.php:245`
- **What:** The file header states it "Does NOT require authentication — this is the public comment
  form handler", but it `require_once`s `bootstrap.php`, whose auth guard exempts only `login.php`,
  `logout.php` and `reset-password.php`. Anonymous visitors get a 302 to the login page. Public
  comments do not work, and the honeypot / rate-limit anti-spam below is unreachable.
- **Fails:** Correctness; and the comment misleads a future reader about the file's trust boundary.
  When it is fixed, it becomes a genuinely unauthenticated endpoint and the anti-spam and rate
  limiting must be verified, not assumed.
- **CORRECTED 2026-07-20 (slice 7).** The recorded symptom is stale: anonymous callers no longer get
  a 302 to login, they get **`401 {"code":"authentication_required"}`** — slice 4 changed API
  surfaces to answer JSON. Same defect, different shape. Verified live before any change was made.
- **CLOSED 2026-07-20, Sprint 1 slice 7 — and the recorded remediation was NOT the one applied.**
  The audit's fix ("add it to `$preAuthScripts`") was rejected against source for two reasons, and
  the second is the one that matters:
  - **It could not satisfy this slice's own test point.** The endpoint's documented address is
    `<admin>/api/comment-submit.php`, and the admin directory is renamed to a randomized
    `<hex>-admin` at install (`install.php:811-824`) precisely so that it never appears in a public
    URL — `Helpers::getBasePath()` says so in those words (`core/helpers.php:192-197`), as does the
    root `.htaccess` header. A comment form on a generated page posting there would have published
    the secret directory name on every page of every site.
  - **It would have handed anonymous callers more than a comment box.** `admin/bootstrap.php` runs
    the cron manager and the action scheduler on **every** request (`bootstrap.php:184-196`). An
    endpoint exempted from its auth guard is a scheduler trigger for any passer-by.
    `installer/index.php` does neither (`index.php:62`). This is exactly the D-036 question — what
    does the file PERMIT once the only authentication check in front of it is removed — and it is
    why the handler moved instead of being exempted.
  - **Resolution:** the handler is now `installer/public/comment-submit.php`, copied to the site's
    **web root** by `BuildEngine::syncCommentEndpoint()` (the placement x402 already uses for its
    own public gate), reachable at `/comment-submit.php`. The old admin file is **deleted** and its
    gate-map entry removed, so no admin surface answers anonymous callers and `keel-verify` still
    passes at 64 files.
  - **The rate limit was not weak — it did not exist.** `$_SESSION['last_comment_at']` could never
    be read from the generated site: `Auth::startSession()` scopes the cookie to
    `path=<base>/admin/` with `SameSite=Strict` (`core/auth.php:52-62`), so every anonymous
    submission arrives with a brand-new session. Confirmed live — each request received a fresh
    `klytos_session`. It now uses the product's existing persistent, IP-keyed
    `MCP\RateLimiter`, 2 per 60s, filterable via `comment.rate_limit`. Residual gap recorded as
    **NEW-17**.
  - **Input bounds added**, because anonymous reach makes an unbounded field a one-request disk
    write: `author_name` 100, `author_email` 254, `page_slug` 200 (truncated *before* sanitizing),
    and `parent_id` must match this collection's own ID shape or it is dropped rather than stored
    verbatim.
  - **A second, deeper defect was uncovered and fixed in path: NEW-16** — comments could never be
    enabled at all, because `SiteConfig::setValue()` did not exist although the MCP tool called it
    four times. The L-009 shape again: the first fault was hiding the second.
  - Pinned by `tests/Integration/PublicCommentTest.php` — 7 tests, each of the four test-point
    criteria asserted, all **proven to FAIL** against the unfixed code by probe (endpoint removed →
    404; rate limit disabled → six submissions with six different session cookies all accepted;
    honeypot disabled → the bot's comment stored; `parent_id` check removed → the forged value
    stored verbatim). Walked for real in the playground as well as in the suite.
  - Still absent by decision: **there is no comment form in the generated output**, and there never
    was one. Form emission belongs to the theme-package sprint (D-023). Said plainly here rather
    than left implied — the endpoint works and nothing calls it yet.

### S-10 — CSP allows `style-src 'unsafe-inline'` — **LOW**
- **Where:** `installer/core/auth.php:793`
- **What:** Weakens an otherwise well-implemented nonce-based CSP.

### S-11 — No `Strict-Transport-Security` header — **LOW** — **CLOSED 2026-07-20 (slice 8)**
- **Where:** `installer/core/auth.php:781-796` (sets `X-Content-Type-Options`, `X-Frame-Options`,
  `Referrer-Policy`, `Permissions-Policy`)
- **Fails:** web-app profile — transport security headers.
- **Resolution (D-044):** `Strict-Transport-Security: max-age=31536000`, sent **only over TLS** and
  deliberately **without `includeSubDomains`** — a browser honours the directive for the full
  max-age after the header stops being sent, so forcing HTTPS onto an operator's sibling subdomains
  by default is close to irreversible and is not Klytos's call to make. Filterable via
  `security.hsts` for operators who want preload or subdomains.
- **Verification:** the HTTPS branch is pinned by `tests/Unit/SecurityHeadersTest.php` (the
  playground speaks plain HTTP and `php -S` cannot terminate TLS, so there is no real response to
  observe it on); the cleartext branch is asserted on a real playground response
  (`testHstsIsNotSentOverPlainHttp`) and confirmed absent by `curl -D -`. Proven to fail against the
  unfixed code by removing the emission line.

### The CSP fail-open — **CLOSED 2026-07-20 (slice 8)**
- **Where:** `installer/core/auth.php` — the `$nonce ? … : "'self' 'unsafe-inline'"` fallback.
- **What:** a caller that supplied no nonce silently received the **weakest policy in the product**,
  with nothing to signal it. The docblock even documented the fallback as intended behaviour.
- **Resolution (D-044):** a missing nonce now yields `script-src 'self'` — it fails closed. The
  failure mode becomes a visibly broken widget instead of a silently disabled defence.
- **Why it was not cosmetic:** `login.php` had **no CSP at all** and carries an inline `<script>`
  (the 2FA method switcher). Extending header coverage to it without nonce-ing that block would have
  broken two-factor login — a regression shipped *by* a security fix. Nonce-ing it was in scope by
  necessity, the NEW-16 shape from slice 7.
- **The one deliberate exception, stated rather than hidden:** `installer/index.php` passes an
  explicit policy that keeps `script-src 'unsafe-inline'`, because the front controller `readfile()`s
  pre-generated static HTML carrying inline scripts that cannot hold a per-request nonce. Recorded
  as **NEW-23**.

### S-12 — Identity export: no CSRF, and a state-changing GET — **MEDIUM**
- **Where:** `installer/admin/api/download-identity.php`
- **What (original text, partly wrong):** "Authenticated, but neither CSRF nor a capability check on
  an identity/key export path."
- **CORRECTED 2026-07-18 (re-validation).** The **capability half is refuted**: an owner-only gate
  does exist at `:34-39` (`isLoggedIn()`) and `:41-48` (string-compares `getUsername()` against
  `config['admin_user']`). It is ad-hoc rather than `klytos_has_permission()`, so it is a convention
  violation — but the authorization is substantively there. The **CSRF half is confirmed**: no
  `klytos_verify_csrf()` anywhere in the file.
- **The real defect, missed originally:** there is **no `REQUEST_METHOD` check**, so a
  secret-exporting operation answers **GET** — and it *writes state* at `:88-90`
  (`identity_last_downloaded_at`, `identity_download_count`, persisted to config). That breaks the
  project's own "don't change state on a GET" rule.
- **Exploitability, stated honestly:** an attacker can force the owner's browser to issue the request
  (`<img src>`, a link), burning the 24 h rate limit and writing config — a DoS / audit-noise vector.
  They **cannot read the key material**, because the response is `application/octet-stream` with
  `Content-Disposition: attachment` (`:102-103`). CSRF: yes. CSRF-to-key-exfiltration: no.
- **What it exports (`:72-85`):** the admin identity **RSA private key**, fingerprint and
  `admin_user`, decrypted from `config/admin-identity.priv.enc`. The highest-value secret in the
  system.
- **CLOSED 2026-07-19, Sprint 1 slice 5** (the capability half was already refuted at re-validation
  and re-closed properly by slice 4's gate map, which maps this endpoint to owner-only
  `users.manage`). Both remaining halves are fixed:
  - **The state-changing GET.** `api/download-identity.php` now requires **POST** (405 with
    `Allow: POST` otherwise). The fix had to be structural rather than local: `admin/security.php`
    gated on `users.manage` and then **302-redirected** to the endpoint, and a browser follows a 302
    with a GET — so the redirect *was* the defect, and it was the normal path, not an edge case. A
    redirect cannot carry a POST, so the form was retargeted to POST straight at the endpoint and the
    `request_identity_download` branch was removed (per L-007, with its remaining exit condition
    stated: a stale cached page would now fall through to a harmless re-render instead of being
    redirected to a guaranteed 405).
  - **The missing CSRF.** `klytos_verify_csrf()` is now called, after the method check and before any
    secret is read or any state written.
  - Pinned by `NamedEscalationsTest::testS12IdentityExportRefusesAStateChangingGet` and
    `::testS12IdentityExportRequiresCsrf`, both **proven to fail** against the unfixed code (200
    where 405 and 403 were required), plus `::testS12IdentityExportIsOwnerOnlyAndDoesNotFatal`
    carried over from slice 4, still asserting on the response **BODY** per L-009.
  - **Independent confirmation nobody had to write:** the repaired config-mutation guard (D-039)
    fired on both new tests against the unfixed code — because the GET genuinely did write
    `identity_last_downloaded_at` and `identity_download_count` — and went quiet once the fix landed.
  - Decision: **D-040**. The three protections the docblock had falsely claimed are now **NEW-13**.
- **Documentation defect in the same file (L-002 class):** the header block at `:9-13` claims
  re-authentication with the current password, 2FA verification, and an email notification to the
  admin. **None of the three is implemented.** A reviewer trusting the docblock concludes the
  endpoint is far better protected than it is. Corrected in the same slice as the code.

### S-13 — MCP model-facing threats not addressed explicitly — **MEDIUM** `unverified`
- **What:** The MCP profile's specific class is not visibly handled: (a) tool results carrying
  third-party content (the importer fetches arbitrary sites) treated as data, never instructions;
  (b) destructive tools (`klytos_delete_page`, `klytos_permanent_delete_page`, `klytos_empty_trash`,
  `klytos_deactivate_plugin`, `klytos_options_delete_domain`) gated behind confirmation or dry-run;
  (c) tool descriptions treated as release-controlled prompts (poisoning / rug-pull).
- **Fails:** `security/mcp-server.md` — "Model-facing threats".
- **Note:** MCP annotations exist in `ToolRegistry::register(...)`; whether `destructiveHint` is set
  and honored was not verified. Verify before triaging.

### NEW-01 — `klytos_current_user()` silently promotes to `owner` — **CRITICAL** *(found 2026-07-18, Sprint 1 kickoff re-validation)*
- **Where:** `installer/core/helpers-global.php:390-397`
- **What:** A v1.x compatibility fallback returns a hardcoded `'role' => 'owner'` whenever
  `$auth->getUserId()` is empty **or** `UserManager` throws. Any authenticated session lacking
  `klytos_user_id` is silently escalated to owner.
- **Why it outranks most of the S-0x set:** it **defeats every gate Sprint 1 adds**. A gate that
  calls `klytos_has_permission()` is only as good as the identity that function resolves, and this
  fallback makes that identity `owner` on a failure path an attacker or a stale session can reach.
  Fixing the gates without fixing this would produce the *appearance* of enforcement.
- **Fails:** web-app profile — fail-closed authorization; least privilege.
- **Triage:** **fix now**, Sprint 1 slice 3, as a prerequisite. Remediation and its backward-compat
  handling are recorded in **D-021** (fail closed + idempotent v1.x migration, upgrade tested from
  the real previous version).
- **Related, same function:** `klytos_has_permission()` is otherwise correctly fail-closed — an
  unknown permission resolves to an empty role list and is denied (`helpers-global.php:450-452`) —
  and the matrix is filterable via `auth.capabilities` (`:448`). The defect is the identity, not the
  matrix.
- **CLOSED 2026-07-19, Sprint 1 slice 3.** The fallback is removed: an authenticated session with no
  `klytos_user_id`, and a session naming a user that no longer resolves, both return `null` and are
  logged. Asserted by `tests/Integration/CurrentUserFailClosedTest.php` (6 tests), which was
  demonstrated to FAIL against the unfixed code before being trusted — 3 of its tests fail when the
  fix is reverted. Verified on a **real upgraded install**, not only a fixture:
  `scripts/dev/upgrade-test.sh` installs v0.30.1 with its own installer in a temp directory, upgrades
  it to the working tree, and asserts the denial there. Compensating migration verified idempotent
  (`tests/Integration/V1MigrationTest.php`). Side effect exposed by the fix and recorded rather than
  absorbed: **NEW-08**.

### NEW-02 — MCP tools have zero authorization: 172 tools, 0 permission checks — **CRITICAL** — **CLOSED 2026-07-24 (Sprint 2, slices 1–4)** *(found 2026-07-18, Sprint 1 kickoff re-validation)*
- **Where:** `installer/core/mcp/tools/` — all 34 files, 172 registered tools.
  `klytos_has_permission` appears **zero** times across the entire directory.
- **What:** MCP authentication (`token-auth.php` — Bearer, OAuth access token, or HTTP Basic app
  password) establishes *who* the caller is. Nothing then establishes *what* they may do. Any holder
  of an application password therefore operates the CMS with effectively owner-level power —
  including destructive tools (`klytos_delete_page`, `klytos_permanent_delete_page`,
  `klytos_empty_trash`, `klytos_deactivate_plugin`, `klytos_options_delete_domain`).
- **Why this is the most serious finding in the audit for *this* product:** Klytos's premise is
  handing control to an autonomous agent over MCP. S-01…S-07 describe an ungated admin panel; this
  describes an ungated *primary interface*. It also realizes the MCP profile's **confused deputy**
  class directly — the model acts with the full authority of the endpoint, not of the role.
- **Related:** `installer/core/terminal-executor.php` — the CLI path `dispatch()` bypasses the
  permission checks that the web path `execute()` applies. Convenient for seeding, but it means the
  CLI is **not** a valid surface for verifying authorization.
- **Fails:** `security/mcp-server.md` — authorization and confused deputy; web-app profile — least
  privilege.
- **Triage:** **Sprint 2**, dedicated, per **D-020**. Sprint 1 builds the
  `klytos_require_permission()` helper that Sprint 2's `ToolRegistry` enforcement point reuses.
  Recorded plainly: when Sprint 1 closes, the admin is gated and this is not.
- **Sharpened 2026-07-19** (Sprint 1 slice 3 `security-auditor` pass, on adjacent code):
  `installer/core/ai/chat-engine.php:401-421` — `getAvailableTools()` is the *only* restriction on
  which tools AI chat can reach, precisely because no per-call gate exists anywhere (the finding
  above). It is written as an allow-list by exception: it filters the tool set **only** when the role
  is exactly `'viewer'` (read-only tools) or exactly `'editor'` (non-destructive tools). **Every other
  role value falls through unfiltered** — `owner` and `admin` by intent, but equally any unrecognized
  string: a plugin-defined role, a renamed role, or a corrupted record. That is fail-OPEN on the
  product's primary interface, and it is a distinct defect from "no gate at tool-call time" — it
  would survive a naive fix that only added checks to the tools themselves.
  Slice 3 improved the null case rather than worsening it: with the fallback removed,
  `klytos_current_user()` returns `null`, so `$user['role'] ?? 'viewer'` now resolves an unidentified
  session to the **least**-privileged role. The unrecognized-role hole is untouched and belongs to
  Sprint 2's enforcement point, which must default-deny on any role it does not know rather than
  filter by exception.
- **CLOSED 2026-07-24 — Sprint 2, four slices.** What actually closes it, in the order it was built:
  1. **Identity (slice 1, D-047).** There is no session on the MCP path, so the recorded one-line
     remediation ("reuse `klytos_require_permission()` at the ToolRegistry enforcement point") would
     have **denied 100% of MCP traffic**. `TokenAuth::validate()` now resolves an actor
     `{user_id, role}` from the credential itself; an idempotent boot migration stamps the installed
     base's role-less bearer tokens `owner`, proven on a real v0.30.1 upgrade.
  2. **The gate (slice 2, D-046/D-048).** ONE default-deny decision (`denialReason()`) in
     `ToolRegistry::call()`, **above** the `mcp.handle_tool` filter so plugin-handled tools are
     covered, asking `UserManager::hasPermission()` — the ONE matrix (S-04), not a second one. A
     central `tool-capabilities.php` map where an **absent entry denies**; `tools/list` filtered by
     the same decision; a JSON-RPC error object with an explicit **HTTP 403**; a `mcp.access_denied`
     audit action; **keel-verify check 10** failing the build when a registered core tool has no
     entry.
  3. **Coverage (slice 3, D-049/D-050).** The loader fails loudly instead of skipping a file that
     registers nothing; the 3 dead integrity tools wired in and gated; x402 and both shipped MCP
     plugins declare their capabilities through `mcp.tool_capabilities`; filter-injected tools made
     callable over HTTP so they are gated rather than merely unreachable (**NEW-30**); the AI-chat
     advisory list default-denies an unknown role — the fail-open sharpened above.
  4. **Reconciliation (slice 4, D-051).** The count truth (172 core + 8 x402 = **180** on a default
     install, **206** with both MCP plugins active, **0** dead), the refusal message translated into
     all 20 locales, the "adding a new MCP tool" checklist, and `ai.use` widened to `editor` —
     because *this* finding is what kept the editor out of the AI chat (D-035).
  - **The evidence, not the claim:** a `role=viewer` bearer token refused `klytos_delete_page` with a
    JSON-RPC error object and HTTP 403 on the wire, an owner allowed the same tool, an editor refused
    a `forms.manage` plugin tool and allowed an `x402.view` read, `tools/list` = 206/197/56/19 for
    owner/admin/editor/viewer. Every refusal test was proven to FAIL against the ungated code before
    being trusted. Full reference: `docs/reference/mcp-authorization.md`.
  - **The honest residue, recorded rather than implied away:** the gate's power-*reducing* effect is
    latent for credentials that already exist, because every one of them resolves to `owner` (an app
    password is pinned to the admin user, and pre-Sprint-2 bearer tokens migrate to owner — which
    records what was already true). Reducing power requires minting a credential at a lower role, and
    today that means a bearer token; per-role application passwords wait on **NEW-11**. A bearer
    token also names no user, so an owner bearer is an **unattributed** owner credential in the audit
    log.

### NEW-03 — By-reference action listeners are silently broken; every page create warns — **HIGH** — **CLOSED 2026-07-25 (Sprint 4 slice 1, D-054)** *(found 2026-07-18, Sprint 1 slice 0, by booting the playground)*
- **Where:** `installer/core/hooks.php:124` (`doAction( string $hook, mixed ...$args )`) and `:145`
  (`call_user_func_array`); listener at `installer/core/x402-bootstrap.php:194`
  (`function ( array &$data, string $action )`); fired from `installer/core/page-manager.php:86`
  (create) and `:148` (update). *(Adoption-day line numbers. Sprint 4 inserted the `page.save_data`
  filter above both, moving the action to `:92` and `:157` — read `PageManager::create()` and
  `update()` rather than these lines.)*
- **What:** `doAction()` collects its arguments variadically, which **copies** them, so a listener
  declaring a by-reference parameter can never bind. PHP emits
  `Argument #1 ($data) must be passed by reference, value given` and the listener's mutations are
  **silently discarded**.
- **Reproduced, not inferred:** creating a page through `PageManager::create()` in the playground
  emits the warning three times out of three. Command and output recorded in
  `docs/05-test-points.md` (slice 0).
- **Why it matters beyond the noise:**
  1. `core/x402-bootstrap.php` is loaded **unconditionally** at boot (by `App::boot()` — cited here
     as `core/app.php:486` at adoption; the real line is now `:546`, so the method is named instead
     of a line that rots on the next insertion), so this fires on **every page create in every
     production install**, not only when x402 is in use.
  2. The listener's actual purpose — injecting the post type's `x402_default_enabled` into new pages
     — **never takes effect**. A feature that appears implemented does nothing.
  3. Systemically, the documented contract that an action can mutate `$data` by reference is broken
     for **every** by-reference listener, core or third-party. That is Keel's extensibility rule
     failing at the mechanism level, and `core/guides/plugin-development.md:171` documents the hook
     without noting that mutation does not work.
- **Fails:** correctness; Keel's extensibility rule; L-002's rule that documentation may only assert
  what the code does.
- **Why it is NOT fixed in Sprint 1:** the correct fix changes the signature of `Hooks::doAction()`,
  which backs **301 registered actions**. That is a deliberate, separately-tested change, not a
  slice-0 side effect. Widening Sprint 1 to absorb it would repeat exactly the mistake D-018
  rejected.
- **Trigger:** its own slice, before or alongside Sprint 2 (which adds MCP enforcement hooks and
  would inherit the same broken contract). Whoever takes it must also decide the fate of the
  `filter` path, which does not have this defect, and reconcile the guide's documentation.

- **RESOLUTION 2026-07-25 (Sprint 4 slice 1, D-054).** Actions are now fire-and-forget **by
  enforcement**: `Hooks::addAction()` and `addFilter()` refuse a callback declaring a by-reference
  parameter with a typed `HookContractException` naming the hook, the parameter and the callback's
  `file:line`. The one real listener became a filter on the new **`page.save_data`**, applied by
  `PageManager` above the `page.before_save` action, which stays as an observer. The filter path was
  closed in the same move (it has the identical defect; zero code relied on it).
- **The "301 registered actions" figure above is wrong in number AND in kind, and it mattered.** It
  is a stale copy of `docs/api/INDEX.md`'s `| Actions |` row at commit `622d54c`, traced through that
  file's history (301 → 302 → 304 → 306 → 307 → 308 at HEAD) — not a measurement, and **nothing
  "registers" 301 anything**. Re-measured three ways, the last comment-stripped and multi-line-aware:
  **308** distinct action names, **363** action fire sites, **23** shipped action registrations,
  **120** filter names, **128** filter fire sites, **32** shipped filter registrations, max **4**
  payload args. The real blast radius was **one listener**. The inflated framing is part of why this
  was deferred twice (L-015: a number copied from another document is not a measurement — which
  applied to the first two of those three passes, see D-054).
- **The signature change this entry proposed was refuted by measurement, not by preference.**
  `mixed &...$args` binds correctly and then makes PHP reject every non-variable argument with a
  fatal `Error` — literals, class constants, concatenations, ternaries, array literals and `??`
  expressions, which **36+ call sites** pass, including `PageManager::create()` itself. An undefined
  array key passed to a by-reference parameter is also silently *created* in the caller's array.
- **A second, independent layer was found underneath it — see NEW-36.** Closing the hook alone would
  have left the feature just as dead.

### NEW-36 — The post-type update allow-list silently drops what its own extension filter adds — **MEDIUM** — **CLOSED 2026-07-25 (Sprint 4 slice 1, D-054)** *(found 2026-07-25 by driving the NEW-03 feature end to end rather than the defect it names)*
- **Where:** `installer/core/post-type-manager.php` `update()` (the hardcoded `$updatable` list);
  `installer/admin/post-type-edit.php:73`; `installer/core/x402-bootstrap.php:167` and `:184`.
- **What:** `admin/post-type-edit.php` applies the `admin.post_type_edit.update_data` filter — whose
  entire purpose is letting a plugin add data to a post type — and passes the result to
  `PostTypeManager::update()`, which persists only a **hardcoded 7-field allow-list**
  (`name`, `slug`, `slug_i18n`, `editor`, `taxonomies`, `custom_fields`, `statuses`). Anything the
  filter added is dropped without a word. **An extension point whose output went nowhere.**
- **The live consequence:** x402 renders a checkbox and a price field on the post-type edit form and
  reads `x402_default_enabled` back in **six** places, but its only writer is that filter — so the
  setting has **never** persisted. The operator ticks the box, saves, and it comes back unticked.
- **Reproduced, not inferred:** `HookMutationTest::testANewPageInheritsItsPostTypeX402Default`
  carries an explicit precondition assertion that fired against the unfixed tree — *"the post type
  does not carry x402_default_enabled, so no page could ever inherit it"* — separating this defect
  from NEW-03's before either was fixed.
- **Why it was invisible:** NEW-03 was standing in front of it. The by-reference warning was the loud
  symptom, so the diagnosis stopped there; nobody asked whether the value being read had ever been
  written. This is **L-009's shape** (a fault masking a fault) and **L-014's rule** (drive the
  feature, not the defect) arriving together.
- **Fixed in path** because acceptance criterion 1 of Sprint 4 is unreachable without it — the NEW-16
  precedent from Sprint 1 slice 7, in scope by necessity rather than opportunism. `$updatable` now
  passes through a new **`post_type.updatable_fields`** filter and x402 declares its own two keys;
  **absent still means not persisted**, so the fix cannot become mass assignment (the
  `admin.gate_map` / `mcp.tool_capabilities` shape).
- **Not fixed, and named so it is not mistaken for oversight:** `PageManager::buildPageData()` and
  several sibling managers have the same fixed-key-set shape. None of them currently has a plugin
  filter feeding it, so none has the live defect this entry records. Trigger: the first plugin that
  needs to persist its own key on one of those records.
- **Process lesson recorded as L-005:** this was invisible to a thorough read of the codebase and
  appeared on the playground's first boot. It is the concrete argument for T-01/T-02.

### NEW-04 — `build` writes into the repository root and overwrites tracked files — **MEDIUM (HIGH for contributors)** *(found 2026-07-18, Sprint 1 slice 0)*
- **Where:** `installer/core/build-engine.php:57` — `$this->outputPath = dirname( $app->getRootPath() )`
- **What:** The build output path is the **parent of the Klytos directory**. In production that is
  correct (site at the web root, admin in a subdirectory). In a **checkout**, `dirname( installer/ )`
  is the repository root, so `php installer/cli.php build` writes the generated site over the repo.
- **Reproduced, with evidence:** restored the tracked root `.htaccess` (md5 `2f2703b6…`), ran
  `php installer/cli.php build`, re-hashed it (md5 `ab176f73…`), `git status` reported it modified.
  The same run created untracked `about/`, `contact/` and `.well-known/x402.json` at the repo root.
  All were reverted; nothing was committed.
- **Collision with tracked content, which is what makes this more than untidy:**
  - **`.htaccess`** (tracked) is **overwritten**. The committed version carries a GPL header and the
    documented rule that it must never reference the secret admin directory; the generated version
    replaces all of it.
  - **`index.html`** (tracked — the klytos.io landing page, D-012/D-017) sits exactly where the build
    writes the generated home page. It survived only because this seed's home page has the slug
    `home`, not the site's front page. A front-page build would clobber it.
- **Full inventory of what one `build` scattered over the repository root**, all untracked, all
  reverted: `about/`, `contact/`, `home/`, `search/`, `assets/` (including a vendored FontAwesome
  copy), `.well-known/x402.json`, `llms.txt`, `llms-full.txt`, `robots.txt`, `sitemap.xml`,
  `search-index.json`, `x402-gate.php` — plus the overwritten tracked `.htaccess`.
- **Second-order finding — `.gitignore` guards the wrong directory.** Lines 46-57 exclude
  `installer/public/index.html`, `installer/public/**/*.html`, `installer/public/css/style.css`,
  `sitemap.xml`, `robots.txt`, `llms.txt` — but the build never writes there. `installer/public/`
  holds only `x402-gate.php` and `js/`. So the generated-site exclusions have **never** matched real
  build output, and a contributor who runs a build gets **twelve** untracked generated paths at the
  repo root with nothing stopping them from being committed.
- **Third finding, same class — runtime directories with randomised names escape `.gitignore`.**
  The logs directory name comes from config `logs_dir_name` and is randomised per install (observed:
  `installer/data/logs-d8950d4907c6`), so the committed `installer/data/logs/` pattern never matched
  a real install; `installer/data/_cache/` was not excluded at all. Both were fixed in slice 0
  alongside the identity-key and plain-`.json` gaps.
- **Fails:** Keel's rule that a verification environment must not mutate the repository; the
  confidential/no-junk-in-git discipline by omission.
- **Consequence recorded in `docs/playground.md`:** `php installer/cli.php build` is documented as
  **unsafe in a checkout**, alongside the web installer, and is NOT part of the try-it flow. The
  playground therefore verifies the admin, the MCP endpoint and the CLI, but **not** the generated
  static site — an honest gap, not a silent one.
- **Remediation shape (not done here):** make the output path injectable (constructor argument or a
  `build.output_path` filter) so the playground can build into a scratch directory, then restore the
  static-site flow to `docs/playground.md`. Also correct the stale `.gitignore` block.
- **Trigger:** the first slice touching `build-engine.php`, or the theme-package sprint (D-023),
  which rebuilds the frontend and will need a safe build target anyway.

- **New trigger observed 2026-07-19 (Sprint 1 slice 4):** it is not only `cli.php build`.
  **Activating a plugin** rebuilds the frontend asset bundle and writes
  `assets/js/klytos-hooks.js` into the repository root. Found because slice 4's
  `testPluginPageDeclaringNoCapabilityIsRefused` activates a fixture plugin through the product's
  own API, and `git add -A` then staged the generated file — caught by the pre-commit
  confidential-data review, one step before it entered history. The test now records whether the
  directory existed beforehand and removes what it created, asserting the repository root is left
  clean; the underlying defect is still NEW-04's and still deferred by D-026. Widens the standing
  warning in `docs/playground.md`: any operation that triggers a build writes into the checkout, not
  just the obvious one.

### NEW-05 — CVEs in the vendored HTTP stack — **MEDIUM** — **CLOSED 2026-07-25 (Sprint 3 slice 1, D-029 → D-052)** *(found 2026-07-19, Sprint 1 slice 2, by the first `composer audit` this project has ever been able to run)*
- **CLOSED.** `composer audit -d installer` reports **`No security vulnerability advisories found.`** The
  tree was re-vendored to **guzzle 7.15.1**, **psr7 2.13.0**, **promises 2.5.1**, plus a new
  **`symfony/polyfill-php80` v1.37.0** required by both (16 → **17** packages, 482 → **509** tracked
  files). Measured both sides: **11 advisories before, 0 after.**
  - **D-029's recorded floors were stale and would not have closed this.** It fixed the target at
    "guzzle ≥ 7.12.1, psr7 ≥ 2.12.1" **and** at "re-audit to zero"; by 2026-07-25 those two halves
    disagreed — 7.12.1/2.12.1 leaves **6 of 11 open**. The criterion won over the derivation (L-014),
    a user decision recorded as **D-052**. D-029's remediation *shape* was not re-opened.
  - **The change is 95 files, not 482.** D-029 called this "a change across 482 tracked files"; that is
    the size of the whole tree, never the size of the change. Measured: **95 files across `installer/`**,
    of which **93 sit under `vendor-ai/` (27 added, 66 modified, 0 deleted)**; the remaining two are
    `installer/composer.json` and `installer/composer.lock`. The two scopes are named because 27+66+0 = 93
    and the mismatch would otherwise read as an error to anyone re-deriving it (L-015). Recorded because
    the 482 figure was the main argument for treating this as a scope change.
  - **The reachability assessment was redone from scratch, and it retires the NEW-15 worry.**
    `installer/core/safe-http.php` and `installer/core/http-client.php` contain **zero** references to
    `GuzzleHttp` or `Psr\Http`, so the two HTTP stacks never share a URL and the differential-parsing
    precondition for "host confusion via authority reinterpretation" does not exist in this codebase.
    CVE-2026-59882 and CVE-2026-48998 have **no bearing on the SSRF control**. Cookies (4 advisories):
    no jar anywhere, Guzzle's default is `'cookies' => false` and is now asserted by a test. Proxy
    (2): nothing sets it; the env read is SAPI-guarded for `HTTP_PROXY` and `HTTPS_PROXY` cannot come
    from a request header under CGI. Referer/fragment: `'referer' => false` by default and stripped.
    Full reasoning in **D-052**.
  - **Stated plainly: none of the 11 had a demonstrated exploitation path in Klytos.** The bump closed
    a standing obligation on a released product with an installed base; it did not close a live hole.
  - **Verified, not assumed:** the two new files `guzzle/src/Handler/ProxyEnvironment.php` and
    `psr7/src/Rfc3986.php` — the proxy and URI-host-validation fixes — are physically present in the
    tree. `tests/Unit/VendorAiManifestTest.php` was observed **failing on all three methods** before the
    four records were reconciled, and green after. A new
    `tests/Integration/VendorAiCompatibilityTest.php` proves the vendored API surface the AI stack
    imports still resolves, proven to fail against a wrong symbol and against a non-round-tripping URL.
  - **The honest limit (L-014):** no automated test proves "AI chat still works" — that needs a live
    provider key. The suite proves the stack loads and its API surface resolves; the real round-trip is
    handed to the operator.
- **Advisory count re-measured 2026-07-24 (Sprint 2 close): it had grown from 5 to 11.** `composer audit -d installer`
  now reports **11 advisories across the same 2 packages** — 7 in `guzzlehttp/guzzle` 7.10.0 and 4 in
  `guzzlehttp/psr7` 2.9.0, all medium, same pinned versions. New since the 2026-07-19 triage:
  **CVE-2026-59882** (psr7, *Host Confusion via Weak URI Host Validation* — worth a second look
  against **NEW-15**/`SafeHttp`, since host confusion is the family SSRF validation depends on),
  **CVE-2026-59883** (guzzle, cookie disclosure/injection via IP-address domains), plus guzzle
  advisories for Proxy-Authorization headers reaching origin servers, unbounded response cookies,
  host-only cookie scope, and URI fragments leaking in `Referer`. **D-029's scope is NOT re-opened
  here** — the remediation is still "bump guzzle ≥ 7.12.1 and psr7 ≥ 2.12.1, re-audit to zero", which
  these additions do not change. What changes is the reachability assessment that slice must redo:
  the original one turned on "no cookie jar, no user-controllable URL in the AI module", and two of
  the new advisories are about URI/host parsing rather than cookies. Recorded so the queued slice
  starts from today's list, not from the 5 it was triaged with.
- **Where:** `installer/vendor-ai/guzzlehttp/guzzle` 7.10.0 and `installer/vendor-ai/guzzlehttp/psr7` 2.9.0
- **What:** `composer audit` against the reconstructed manifest (D-028) reports **5 advisories across
  2 packages**, all severity *medium*. Full output in `docs/05-test-points.md` (slice 2 evidence).

  | CVE | Package | Title | Fixed in |
  |---|---|---|---|
  | CVE-2026-55767 | guzzle | Dot-only cookie domains match all hosts | 7.12.1 |
  | CVE-2026-55568 | guzzle | Silent HTTPS proxy downgrade to cleartext | 7.12.1 |
  | CVE-2026-55766 | psr7 | CRLF injection in HTTP start-line serialization | 2.12.1 |
  | CVE-2026-49214 | psr7 | CRLF injection via URI host component | 2.10.2 |
  | CVE-2026-48998 | psr7 | Host confusion via authority reinterpretation | 2.10.2 |

- **Reachability, checked rather than assumed** (this is what makes it MEDIUM and not HIGH):
  - `vendor-ai/` is loaded **lazily and from exactly one place** — `App::getChatEngine()`
    (`installer/core/app.php`). A site that never opens the AI chat never loads Guzzle at all.
  - **No cookie jar.** `CookieJar` and the `cookies` request option appear nowhere in
    `installer/core/ai/` or in `soukicz/llm`. CVE-2026-55767 has no path.
  - **No user-controllable URLs.** The five provider endpoints are hardcoded literals in
    `chat-engine.php:242-247`; no `base_url`/`custom_endpoint` setting exists anywhere in the AI
    module or the MCP tools. The three PSR-7 URI/host CVEs need an attacker-influenced URI, which
    this code path does not offer.
  - **The one plausible path is CVE-2026-55568.** Guzzle honours `HTTP_PROXY`/`HTTPS_PROXY` from the
    environment without the application asking, so on a shared host that sets them, an LLM API key
    could leave over cleartext. Klytos never configures a proxy, but it cannot prevent this one.
- **Fix constraint (verified, so the cost is known):** `soukicz/llm` 0.5.0 requires `guzzlehttp/guzzle: ^7.9`,
  so 7.12.1 and psr7 2.12.1 are **constraint-compatible** — the upgrade needs no dependency-tree
  surgery, only a re-vendor.
- **NOT patched here, deliberately.** D-022's standing rule: CVE findings are reported and triaged
  with the user, never silently patched. Re-vendoring is a change across 482 tracked files → a scope
  change (Estimate v2), not a slice detail.
- **Fails:** web-app profile — dependency audit; `references/maintenance.md` — CVE duty.
- **Triaged 2026-07-19 (D-029):** dedicated remediation slice **after** Sprint 1 closes, with Estimate v2. Scope fixed in that decision. Rejected at triage: folding it into Sprint 1, a guzzle-only minimal fix, and permanent acceptance.
- **Trigger:** Sprint 1 close — **FIRED, and the slice ran 2026-07-25 (Sprint 3 slice 1). Estimate v3
  written. See the CLOSED block at the head of this entry.**

### NEW-06 — The vendored AI stack requires PHP 8.3, but Klytos declares 8.1+ — **MEDIUM** — **CLOSED 2026-07-25 (Sprint 3 slice 2, D-053)** *(found 2026-07-19, Sprint 1 slice 2)*
- **CLOSED.** `App::getChatEngine()` now decides **before** requiring the vendored autoloader and
  throws a typed `Klytos\Core\Ai\UnsupportedRuntimeException` carrying a translated message, rather
  than letting Composer's generated `platform_check.php` send HTTP 500, echo third-party text into the
  response body and throw from inside vendored code. All three callers already wrap the call in
  `try/catch (\Throwable)`, so they degrade with no change.
  - **The ordering is the load-bearing part and it is pinned by a test that reads the source**
    (`AiRuntimeGuardTest::testTheGuardRunsBeforeTheVendoredAutoloaderIsRequired`), because once the
    `require_once` has run the guard is unreachable — dead code that still reviews clean. Proven by
    relocating the guard below the require and watching the test fail, then reverting.
  - **The policy is a pure static** (`App::aiRuntimeUnsupportedReason( int )`) taking the version id as
    a parameter, since PHP cannot be downgraded inside the suite — the D-044 `buildSecurityHeaders()`
    split, for the same reason. Every branch is reachable: 80100/80200/80299 refused, 80300/80400
    allowed, both directions asserted (L-008).
  - **The floor is written once** (`App::AI_MIN_PHP_VERSION_ID = 80300`) and a test asserts it matches
    what Composer generated into `vendor-ai/composer/platform_check.php`, so a future re-vendor that
    moves the floor fails the suite instead of reaching a user as a 500.
  - **The `ai.runtime_unsupported` action is an audit seam with no core listener**, stated in those
    words in `docs/reference/ai-runtime.md` — L-019's rule applied at the moment of writing rather than
    discovered later by a fresh-context pass.
  - **What this does NOT do, deliberately:** it does not raise the product's declared PHP support, and
    it does not reconcile the four different floors this codebase asserts (8.0 in `installer/index.php`,
    8.1 in README/`install.php`/`updater.php`, 8.2 for the suite per D-027, 8.3 for vendor-ai). That is
    a support-matrix decision with installed-base consequences and stays with **D-027's trigger**.
  - **Unchanged by the D-052 re-vendor:** the 8.3 floor comes from `soukicz/llm`, which was not touched,
    so `config.platform.php` and `platform_check.php` regenerated identically.
- **Where:** `installer/vendor-ai/soukicz/llm` (`php: >=8.3`), `brick/math` (`php: ^8.2`),
  `ramsey/collection` (`php: ^8.1`) vs the product's declared PHP 8.1+ (D-004).
- **What:** On PHP 8.1 or 8.2, the AI chat module loads code whose own manifest says it will not run
  there. Nothing declares or checks this: `App::getChatEngine()` requires the autoloader
  unconditionally once the feature is used, so the failure mode is a runtime error inside a vendored
  library, not a graceful "unsupported" message.
- **Why it went unnoticed:** with no manifest there was nothing that could state a platform
  requirement — which is precisely the H-04 defect.
- **Relation to D-027:** the same shape as the PHPUnit-11-needs-8.2 gap, but this one is in the
  **product**, not the toolchain. D-027's gap was verification coverage; this one can reach a user.
- **Fails:** Keel's "external dependencies fail safe" rule — an absent or version-incompatible
  dependency must degrade gracefully (feature disabled, notice shown), never fatal.
- **Remediation shape (not done here):** a `PHP_VERSION_ID` guard at the `getChatEngine()` load
  point that disables AI chat with an explicit message below 8.3 — or raising the product floor, a
  user decision with installed-base consequences.
- **Trigger:** the same triage as NEW-05 (both are "what do we do about vendor-ai"), or the next
  verification of the support matrix (D-027's trigger). — **FIRED: closed 2026-07-25 in the NEW-05
  slice's own sprint, as this line anticipated. See the CLOSED block at the head of this entry.**

### NEW-07 — Two BSD packages ship with no licence text — **LOW (licence compliance)** *(found 2026-07-19, Sprint 1 slice 2)*
- **Where:** `installer/vendor-ai/soukicz/llm` (BSD-3-Clause) and `installer/vendor-ai/phplang/scope-exit` (BSD)
- **What:** Every other vendored package ships its own `LICENSE` file next to its source and those
  files survive `git archive` (verified with `git check-attr export-ignore`). These two do not have
  one — upstream omits it — so the only record was `vendor-ai/LICENSE-THIRD-PARTY.md`, which
  (a) listed 14 of the 16 packages, omitting `phplang/scope-exit` and `ralouphie/getallheaders`
  entirely, (b) attributed `soukicz/llm` to "Ondrej Soukup" when `composer.json` names **Petr Soukup**,
  and (c) is itself stripped from every release archive by the blanket `*.md export-ignore`
  (`.gitattributes:8`) — the same defect already recorded as H-02 for `README.md`/`INSTALL.md`.
- **Fixed in this slice** (the parts that are the notice itself): the notice now lists all 16
  packages at their vendored versions, corrects the attribution, and reproduces the BSD-3-Clause
  text in full for the two packages that lack it. `tests/Unit/VendorAiManifestTest.php` fails the
  suite if the list ever drifts from `composer/installed.php` again.
- **NOT fixed here:** the `*.md export-ignore` that keeps the notice out of the distributable. That
  is `.gitattributes` packaging policy, owned by **H-02**, and changing what ships is a Phase 7
  decision, not a slice-2 side effect.
- **Trigger:** H-02, at the next full Phase 7.

### NEW-08 — There is no supported way to recreate a missing owner — **MEDIUM** — **CLOSED 2026-07-25 (Sprint 4 slice 2, D-055)** *(found 2026-07-19, Sprint 1 slice 3)*
- **Where:** `installer/cli.php` (26 commands; `users` **lists** only), `installer/core/app.php`
  Step 10b, `installer/core/user-manager.php` (`migrateFromV1Config()` throws on a missing or
  invalid `admin_email`)
- **What:** If an install ends up with no owner record, there is no supported recovery path on any
  interface. The CLI has no user-create, no password reset and no owner repair; the admin panel
  requires a login that cannot succeed without a user; and the web installer refuses to run on an
  install it considers already installed. The reachable route into that state is a v1.x-shaped
  install whose config lacks a usable `admin_email`: the boot migration cannot construct the owner
  and gives up.
- **Why it surfaced now:** slice 3 removed the fallback that used to paper over exactly this — a
  session with no `klytos_user_id` was silently promoted to `owner` (NEW-01), which meant a missing
  owner record was invisible because *everyone* was the owner. Closing the escalation exposes the
  gap that was underneath it. That is the correct trade: an unrecoverable install is a support
  incident, a silent privilege escalation is a vulnerability.
- **Partly mitigated in slice 3, and only partly — stated plainly rather than implied:** Step 10b is
  now wrapped in `try`/`catch`, so a failed migration logs and degrades to "no owner" instead of
  throwing out of `App::boot()` and white-screening every request. That converts an undiagnosable
  fatal into a diagnosable, fail-closed denial. **It does not restore access.** With no owner, login
  still cannot succeed.
- **Scope note:** the fix is new functionality (an owner-repair path — most naturally a CLI command,
  since it must work without a session), with its own design and test point. It was deliberately not
  folded into slice 3, whose subject is the escalation itself.
- **Trigger:** with the NEW-03 slice, after Sprint 1 closes. Raise to HIGH if any real install is
  ever reported in this state.

- **RESOLUTION 2026-07-25 (Sprint 4 slice 2, D-055).** `owner:repair --email=<address>` — a terminal
  command declaring `users.manage`, reachable from `installer/cli.php` with **no session**, which is
  what this state requires. It writes the missing `admin_email` into config and then runs the
  product's **own** `UserManager::migrateFromV1Config()`, which rebuilds the owner record from
  `config['admin_user']` and `config['admin_pass_hash']`. **The operator's existing password still
  applies; the command sets no credentials.**
- **Why it takes no username or password, which is the whole design.** `Auth::login()` validates the
  username against `config['admin_user']` and the password against `config['admin_pass_hash']`, never
  against the user record (**NEW-11**). An owner minted with its own credentials would be a record
  **nobody can log in as** — and `findOwner()` returning non-null would then make the command refuse
  forever, leaving the install permanently unrecoverable. **The first implementation did exactly
  that**; it was caught by the slice's own `code-reviewer` before it shipped, and the design changed.
  Recorded as **L-024**.
- **What the broken state actually retains, measured:** `upgrade-assert.php:131` removes **only**
  `admin_email`. `admin_user` and `admin_pass_hash` survive. So the missing piece is the email, not
  the identity — and repairing the cause is what restores access.
- `dispatch()` runs no permission check and `cli.php` calls it directly; that asymmetry is deliberate
  (CLI access already implies filesystem access) and the declared permission gates the **web**
  terminal. Consequence worth naming: being logged in presupposes an owner, so the web terminal can
  only ever reach this command's refusal branches.
- **Refusals THROW**, so they exit non-zero — a returned refusal would have exited 0 and told an
  automated recovery script that a repair which changed nothing had worked. Six tests; two of them
  initially passed against the unfixed tree **for the wrong reason** (an unknown command also reports
  failure) and were tightened to assert the refusal's REASON (L-012). The recovery is proven through
  **`Auth::login()`**, the real gate, not through the user manager.
- **An install that has ALSO lost `admin_user`/`admin_pass_hash` cannot be recovered by this command**
  — it refuses and says so, rather than creating an account nobody could use.
- **Deliberately NOT built, named so it does not read as an oversight:** resetting a password. Making
  the supplied password real would mean writing `config['admin_pass_hash']`, i.e. an unauthenticated
  CLI primitive that resets the owner password on any install. If wanted, that belongs in a
  separately-named command. This also does not touch **NEW-11** — non-owner accounts still cannot log
  in.

### Positive findings (recorded so they are not re-litigated)
- **No tracked secrets.** `git ls-files` over secret-shaped patterns returns zero; only
  `installer/core/keys/klytos-integrity.pub` (a public key — correct) is tracked.
- No XSS confirmed: every `$_GET`/`$_POST` echo found in the admin is wrapped.
- No SQL injection confirmed: PDO prepared statements throughout `database-storage.php`.
- Terminal executor explicitly avoids `exec`/`shell_exec`/`proc_open`/`passthru`/`system`.
- bcrypt cost 12, `session_regenerate_id(true)` on login and privilege change, login lockout, MCP
  rate limiting, AES-256-GCM at rest, RSA-signed integrity manifests.

---

### NEW-09 — Passkey second-factor login is broken, and the obvious fix opens an account-takeover path — **HIGH** *(found 2026-07-19, Sprint 1 slice 4)* — **CLOSED 2026-07-25 (Sprint 5 slice 2, D-058)**
- **Closed in the order D-036 demanded, and the order is the whole fix.** (1) `register_challenge`
  and `register_complete` now require `isAuthenticated()`, leaving only `auth_challenge` reachable
  while 2FA is pending; (2) `login.php`'s dispatcher gained the `passkey` branch wired to
  `TwoFactor::verifyPasskeyAssertion()`; (3) the account holder is emailed on enrolment and a
  `user.passkey_enrolled` action fires; (4) **only then** was
  `api/webauthn-challenge.php` added to `$preAuthScripts`. Proven end to end without a browser: a
  P-256 key, a hand-encoded COSE key and CBOR attestation object, and a real ES256 signature over
  `authData || SHA-256(clientDataJSON)` — enrolled through the product's own
  `completePasskeyRegistration()` and completed through the real login form over HTTP
  (`tests/Integration/PasskeyLoginTest.php`). The takeover proof is a permanent test: registration is
  **refused** in the 2FA-pending state, and all four tests were observed failing before the exemption
  existed.
- **Correction of record: this entry said "all FOUR of its actions". There are THREE**
  (`register_challenge`, `register_complete`, `auth_challenge`) plus an unknown-action branch.
  Re-counted against source while writing the fix (L-015: a number inherited from another document is
  not a measurement). The finding's substance is unaffected — two of the three were the dangerous
  pair, and they are the two now restricted.
- **A second-order defect was found and closed in the same change:** `$preAuthScripts` was matched
  against `basename( SCRIPT_NAME )`, and **six** filenames exist in both `admin/` and `admin/api/`
  (`ai-chat`, `logs`, `plugins`, `tasks`, `terminal`, `translations`) — the exact collision D-032 keyed
  the gate map by path to avoid. Adding the list's first `api/` entry is what made it matter, so the
  list now matches `klytos_admin_gate_key()`, which resolves from `SCRIPT_FILENAME` and returns null
  (i.e. requires authentication) for anything outside `admin/`. `webauthn-challenge.php` happens to be
  unique repo-wide, so nothing was exploitable; the mechanism was one same-named file away from being.
- Original entry follows, unchanged.
- **Where:** `installer/admin/bootstrap.php` (the auth-guard exemption list),
  `installer/admin/api/webauthn-challenge.php:20`, `installer/admin/login.php:54-99` and `:311`,
  `installer/core/two-factor.php:507-530` and `:586`
- **What (two independent defects, and the second is why the first must not be fixed alone):**
  1. **The endpoint is unreachable in the state it was written for.**
     `webauthn-challenge.php` guards itself with
     `if ( !$auth->isAuthenticated() && !$auth->is2faPending() )` — the `is2faPending()` half exists
     to serve a user who has submitted a password but not cleared the second factor. It was not in
     bootstrap's exemption list, so bootstrap 302-redirected that request to the HTML login page
     before the endpoint's own check ran, and `login.php:311`'s passkey fetch got HTML.
  2. **Even reachable, passkey login still cannot complete.**
     `TwoFactor::verifyPasskeyAssertion()` (`two-factor.php:586`) has **zero call sites** anywhere in
     the repository, and `login.php:54-99`'s 2FA dispatcher branches on `totp` / `recovery` /
     `email` / `emergency_email` only — there is no `passkey` branch. The assertion the browser
     posts is never verified.
- **Why the exemption was added and then REMOVED in the same slice.** Slice 4 first added
  `webauthn-challenge.php` to the exemption list, on the strength of defect 1 alone. The
  `security-auditor` pass showed that is a **full account-takeover primitive**, and the finding was
  verified against source before being accepted: `is2faPending()` becomes true as soon as a caller
  supplies a correct **password** (`auth.php:112-118`); the endpoint gates **all four** of its
  actions on the same weak condition, including `register_challenge` and `register_complete`; and
  `TwoFactor::completePasskeyRegistration()` (`two-factor.php:507-530`) appends the new credential,
  adds `'passkey'` to the enabled methods and sets `enabled = true` **without checking that the
  caller ever passed an existing second factor**, with no notification to the account owner. So
  anyone holding a stolen or phished password alone — no possession of the victim's TOTP device or
  existing passkey — could enrol their own authenticator and hold the account permanently, defeating
  2FA entirely. The redirect was the only thing preventing it.
- **The exemption bought nothing anyway**, which is what makes the reversal unambiguous rather than
  a trade-off: because of defect 2, the legitimate flow could not complete even with the endpoint
  reachable. The change was therefore all risk and no function.
- **Fix shape when this is done properly (its own slice, with its own tests):** restrict
  `register_challenge` / `register_complete` to fully authenticated callers, leave only
  `auth_challenge` reachable in the 2FA-pending state, add the missing `passkey` branch to
  `login.php`'s dispatcher wired to `verifyPasskeyAssertion()`, and notify the account owner when a
  new authenticator is enrolled. **Trigger:** the same slice that closes NEW-11, since both are
  authentication rather than authorization.
- **Standing warning for slice 7:** `api/comment-submit.php` is the next file scheduled to be added
  to this same exemption list (S-09). It must get exactly the scrutiny this one did — an entry in
  `$preAuthScripts` removes the *only* authentication check standing in front of whatever the file
  does internally.

### NEW-10 — Any authenticated user could complete the setup wizard on a fresh install — **HIGH (privilege escalation)** *(found 2026-07-19, Sprint 1 slice 4)* — **CLOSED in the same slice**
- **Where:** `installer/admin/setup-wizard.php` (POST `wizard_action=*`), reachable because
  `admin/bootstrap.php` required authentication but checked no role
- **What:** The wizard writes 2FA settings, stores AI provider keys, mints **MCP application
  passwords**, and sets `setup_completed`. It had no role check of any kind. On a fresh install —
  the exact window in which it is reachable — any authenticated account could complete it and issue
  itself an MCP application password, which is a durable credential against the product's primary
  interface.
- **Interaction with NEW-11, which is why this was not exploitable in practice yet:** since
  `Auth::login()` only ever authenticated `config['admin_user']`, the only account that could reach
  it was the owner's. The escalation was latent, not live — but it was latent behind an
  authentication defect, not behind a control, and fixing NEW-11 without fixing this would have
  opened it.
- **Fixed 2026-07-19 (D-033):** gated on the new owner-only `setup.run` capability. Safe on a fresh
  install because the owner is the only account that exists at that point. Verified in the per-role
  walk: owner 302 (setup already complete), admin/editor/viewer 403.

### NEW-11 — Only one account can log in: `Auth::login()` never consults `UserManager` — **HIGH** *(found 2026-07-19, Sprint 1 slice 4)*
- **Where:** `installer/core/auth.php:99-102` versus `installer/core/user-manager.php:384`
- **What:** `Auth::login()` validates credentials **exclusively** against `config['admin_user']` and
  `config['admin_pass_hash']` — the single v1 admin credential. It never calls
  `UserManager::authenticate()`, which is fully implemented one layer below: it verifies the
  per-user bcrypt `pass_hash`, refuses suspended accounts, and updates `last_login`. That method is
  used only for **re-authentication** inside `admin/profile.php:45` and
  `admin/partials/ai-panel-profile.php:33`. Consequence: accounts with role `admin`, `editor` or
  `viewer` **cannot log into the admin panel at all**, no matter that they exist, are `active`, and
  carry a valid password hash.
- **Verified live, not inferred:** all four seeded playground users were driven against the real
  login form with their correct passwords. `owner` → 302 (success); `admin`, `editor`, `viewer` →
  200 with "Incorrect username or password".
- **Why it matters beyond the obvious:** it is very likely *why* S-07 survived — with one account in
  practice, an ungated admin surface never misbehaves, so nothing ever pointed at the missing gates.
  It also means the multi-user role system, the capability matrix, and now the gate, have never been
  exercised by a real non-owner session in production.
- **NOT fixed in slice 4, deliberately.** This is authentication, not authorization — an adjacent
  subsystem in D-031's sense, and a real piece of work: wiring `Auth::login()` to
  `UserManager::authenticate()` means deciding precedence against the v1 config credential, routing
  per-user 2FA, per-user lockout, and the app-password path. It has its own design and test point.
  Slice 4's gate is correct regardless of it.
- **Trigger:** Sprint 2 planning — it belongs with the MCP authorization work, since both concern
  who a caller actually is before deciding what they may do. Until then, stated plainly: **Sprint 1
  gates a multi-role system that, in production, only one role can currently enter.**

### NEW-12 — `api/download-identity.php` carried three defects, and each masked the next — **HIGH** *(found 2026-07-19, Sprint 1 slice 4)* — **CLOSED in the same slice**
- **Where:** `installer/admin/api/download-identity.php:35` (was), `:102` (was), and the owner check
- **What:**
  1. `$auth->isLoggedIn()` — **no such method** on `Auth` (it defines `isAuthenticated()` and
     `is2faPending()`; there is no `__call`). Every request to the endpoint died here.
  2. `$app->getLogger()->log( ... )` — **no such method** on `Logger` (its API is
     `write()` / `writeAlways( $level, $message, $context, $source )`). The guard on the line above,
     `method_exists( $app, 'getLogger' )`, does not catch this: it interrogates **App**, which does
     have `getLogger()`, rather than the `Logger` the call is made against — so it passed and the
     next line fataled.
  3. Owner-ness was decided by `$username !== $config['admin_user']`, a hand-rolled check outside
     the capability matrix (S-04).
- **How each masked the next:** defect 1 killed the request at line 35, so defect 2 at line 102 was
  never reached and never observed. Fixing 1 alone would have "restored" an endpoint that still
  fataled — which is exactly what happened, and was caught only because the test asserts on the
  response BODY rather than the status.
- **Why a status-only test could not have caught either fatal — worth recording, because it is the
  general lesson:** verified live, the PHP fatal returns **HTTP 200** with the error rendered into
  the body, because output has already begun by then. An assertion on the status code alone would
  have passed against completely broken code.
- **Fixed 2026-07-19:** authentication comes from bootstrap (401 JSON for API surfaces),
  authorization from the gate map's owner-only `users.manage`, and the audit entry uses
  `writeAlways( 'warning', …, 'security' )` — `writeAlways` rather than `write` because `write()`
  discards everything unless Developer Mode is on (`logger.php:116`), and an audit trail for a
  private-key export that only exists in debug mode is not an audit trail.
- **Not fixed here, still open:** the file's docblock claims re-authentication with the current
  password, 2FA verification and an email notification, none of which exist. That is the S-12 class
  of defect (a docblock asserting protections the code does not implement) and belongs to slice 5.
  **Resolved in slice 5 by correcting the docblock; the missing protections themselves are now
  tracked as NEW-13 below.**

### NEW-13 — The identity export has no re-authentication, no 2FA check and no owner notification — **MEDIUM** *(found 2026-07-19, Sprint 1 slice 5)*
- **Where:** `installer/admin/api/download-identity.php`
- **What:** the endpoint exports the site's RSA **private key**, and the only things standing in
  front of it are the session (authentication, from `admin/bootstrap.php`), the gate map's owner-only
  `users.manage`, a POST + CSRF check (both added in slice 5), and a 1-per-24-hours rate limit.
  Its docblock claimed three further protections — re-authentication with the current password, 2FA
  verification, and an email notification to the owner. **None of the three is implemented.**
- **Fails:** web-app profile — step-up authentication for a high-value secret export; absence of an
  out-of-band signal on a security-critical action.
- **What this actually means, stated plainly:** a stolen or hijacked owner **session** is sufficient
  to exfiltrate the site's private key. The password is never re-checked, so an attacker who obtains
  a session (XSS, a borrowed unlocked machine, a stolen session cookie) does not need the password
  at all — and nothing notifies the owner that it happened. The audit-log entry exists
  (`writeAlways`, so it survives Developer Mode being off), but a log nobody is told to read is a
  forensic record, not a control.
- **Why it is NOT fixed in slice 5 (user decision, D-040):** re-authentication and 2FA verification
  are the authentication subsystem, not the authorization one this sprint is about, and an email
  notification adds a mail dependency with its own failure modes. That is a second subsystem with
  its own test point inside a slice already carrying six findings — the tangling refused by D-025,
  D-026, D-029 and narrowed by D-031.
- **Trigger:** the authentication slice that also owns **NEW-09** (passkey second factor) and
  **NEW-11** (`Auth::login()` never consults `UserManager`). That slice is already opening the
  password-verification and 2FA plumbing all three need, so the marginal cost there is small and the
  marginal cost here would be the whole of it.
- **Guard against silent regression:** the docblock now states what the code actually does and names
  what it does not, so the L-002 failure mode (a doc asserting a property the code lacks) cannot
  quietly return. Re-adding the claims without the code is a review-blocking change.

### NEW-14 — No admin API endpoint sends security headers — **MEDIUM (systemic)** *(found 2026-07-19, Sprint 1 slice 5, by the `security-auditor` pass)*
- **Where:** all **24** files in `installer/admin/api/`
- **What:** `Auth::sendSecurityHeaders()` (`installer/core/auth.php:779`) has exactly **six** call sites
  repo-wide — `installer/index.php:91`, `core/mcp/oauth-authorize-view.php:142`,
  `admin/reset-password.php:27`, `admin/setup-wizard.php:48`, `admin/templates/header.php:20`, and
  the definition itself. Every admin PAGE gets them, because they all include `templates/header.php`.
  **No admin API endpoint does** — verified by counting, not by sampling: `grep -l` over
  `installer/admin/api/*.php` returns **0 of 24**.
- **Fails:** web-app profile — security headers on every response, not only on HTML pages.
- **Severity reasoning, stated honestly rather than inflated:** these endpoints return JSON to
  same-origin XHR, so the headers that matter most for them are a narrower set than for a page —
  `X-Content-Type-Options: nosniff` above all, plus `Referrer-Policy` and, once S-11 lands,
  `Strict-Transport-Security`. CSP matters less on a JSON response that is never rendered as a
  document. It is still a real gap: `nosniff` is precisely what stops a browser being talked into
  treating a JSON body as something executable, and several of these endpoints reflect
  caller-supplied values into their responses.
- **Relationship to existing findings:** distinct from **S-11**, which is about a missing HSTS
  directive *inside* `sendSecurityHeaders()`. This is about the function never being **called** on 24
  surfaces. Fixing S-11 alone would improve headers on pages and change nothing here — which is
  exactly why it is recorded separately instead of folded in.
- **Not fixed in slice 5:** unrelated to the six escalations this slice owns, and it is a 24-file
  change that wants one shared entry point rather than 24 remembered calls — the same shape as S-07,
  and the same answer (a single enforcement point, most likely in `admin/bootstrap.php` beside the
  gate, so a new endpoint cannot forget).
- **Trigger:** **slice 8** (`HSTS + CSP fail-open + hardening`), which is already opening
  `sendSecurityHeaders()` for S-11 and is the natural home.
- **CLOSED 2026-07-20 (slice 8, D-044) — and this finding's own numbers were wrong in two ways,
  both found by enumerating rather than re-reading the sentence:**
  1. **23 files, not 24.** Slice 7 deleted `admin/api/comment-submit.php` (D-043). The count was
     accurate when written and stale by the time it was actioned.
  2. **The gap was 25 surfaces, not 24, and the framing "every admin PAGE gets them, because they
     all include `templates/header.php`" is false.** Five admin pages do **not** include it —
     `bootstrap.php`, `login.php`, `logout.php`, `reset-password.php`, `setup-wizard.php`. Two of
     those called `sendSecurityHeaders()` themselves; **`login.php` and `logout.php` called
     nothing**, so the login form — the single most security-sensitive page in the product — was
     served with no CSP, no `nosniff` and no `X-Frame-Options` at all. The finding's own reasoning
     ("pages are fine, endpoints are not") is what hid it.
- **Resolution:** ONE call in `admin/bootstrap.php`, which all **64** admin entry points require
  (verified mechanically this slice, not inherited from slice 4's record). Placement is bounded on
  both sides and that is the load-bearing part — see D-044: it cannot go later because everything
  below it emits or exits, and it cannot go earlier because `registerAutoloader()` is Step 1 of
  `App::boot()`. The residue is recorded as **NEW-22** rather than papered over.
- **Verification:** asserted on **real responses**, never on the fact that a function was called —
  a header set after output has begun is not set at all, so only the response can answer it. The
  401 JSON refusal and the 403 gate document both carry the headers on the wire. Proven to fail
  against the unfixed code: removing the enforcement point fails 6 of 11 tests in
  `tests/Integration/SecurityHeadersHttpTest.php`.

### NEW-21 — `page-editor.php` sets its own CSP with `script-src 'unsafe-inline'` — **LOW** *(found 2026-07-20, slice 8)*
- **Where:** `installer/admin/page-editor.php:314`
- **What:** the page builds a `$customCsp` that explicitly allows inline script, and serves **7**
  inline `<script>` blocks under it. Distinct from the fail-open slice 8 closed: that was an
  *implicit* fallback nobody chose, this is an *explicit* opt-out that is visible at its call site.
- **Not fixed, by user decision:** nonce-ing the editor's seven blocks is its own change with its
  own verification pass — this is the most JS-dense surface in the admin — and landing it inside a
  headers slice repeats the tangling D-025/D-026/D-029/D-031 have each refused in turn.
- **Trigger:** the same sprint that closes **S-10** (the CSS/JS consolidation work), which is
  already rewriting this page's markup.

### NEW-22 — The boot-failure page and the two pre-boot redirects send no security headers — **LOW** *(found 2026-07-20, slice 8)*
- **Where:** `installer/admin/bootstrap.php` — the not-installed redirect (`:51-54`), the
  core-load-failure redirect (`:57-63`), and the boot-failure 500 page (`:85-94`).
- **What:** slice 8's enforcement point sits immediately after `App::boot()`, and cannot sit
  earlier: `registerAutoloader()` is **Step 1 of `boot()`** (`app.php:268`), so the `Auth` class
  does not resolve on any of these three paths. The 500 page echoes an escaped exception message,
  so `nosniff` there has real if modest value.
- **Severity reasoning, stated honestly rather than inflated:** all three are degraded paths that
  do not run on a healthy install; two are bodiless redirects. The exposure is a 500 page with a
  server-escaped message and no `nosniff`.
- **Why not fixed by an explicit `require_once core/auth.php`** (the slice-7 precedent for
  `RateLimiter`): it would make the ONE place headers are decided callable in a state where
  `klytos_apply_filters()` does not exist and `Helpers` may not be loaded — adding pre-boot
  fragility to the function every admin request now depends on, to cover a path that only runs when
  the application is already failing. The `function_exists()` guard on the filter is in place so the
  option stays open.
- **Trigger:** the next slice that touches the bootstrap's failure paths, or any slice that
  introduces `core/bootstrap-minimal.php` (whose absence D-043 already recorded).

### NEW-23 — The public site's CSP cannot use nonces, so it keeps `'unsafe-inline'` — **LOW** *(found 2026-07-20, slice 8)*
- **Where:** `installer/index.php` (the explicit policy), `installer/core/router.php:303-326`
  (serves pre-generated HTML), `installer/core/build-engine.php:444` and `:881` (write inline
  `<script>` into it).
- **What:** the front controller `readfile()`s static HTML generated at build time, and that HTML
  contains inline script — the GDPR consent banner's `ConsentManager.init(...)` and a page's
  `custom_js`. **A file generated once cannot carry a per-request nonce**, so the public policy
  keeps `script-src 'unsafe-inline'`.
- **Verified before deciding, not assumed:** failing closed on this call site would have silently
  disabled the consent banner on every generated page of every site — a compliance regression
  shipped by a security fix.
- **Why it is written as an explicit literal policy at the call site:** so the weakening appears in
  a diff and in review, which is exactly what the implicit fallback slice 8 removed did not do.
- **Possible fixes, none of them free:** CSP hashes for the emitted inline blocks (they are
  build-time-known, so this is tractable), or externalizing the init calls into generated `.js`
  files.
- **Trigger:** the **theme-package sprint (D-023)**, which owns generated output and is already
  replacing the template layer that emits these scripts.

### NEW-24 — The two standalone public entry points send no security headers — **LOW–MEDIUM** *(found 2026-07-20, slice 8, by the `security-auditor` pass)*
- **Where:** `installer/public/comment-submit.php`, `installer/public/x402-gate.php`
- **What:** neither calls `Auth::sendSecurityHeaders()` and neither sets any security header —
  verified by grep, not sampled: the only `header()` calls in both files are `Content-Type`,
  `Allow` and `Retry-After`. `comment-submit.php` answers JSON, so **`X-Content-Type-Options:
  nosniff` is the one that matters** and it is absent. `x402-gate.php` can serve `format=html`
  content through `$gate->handle()` with no CSP, no `nosniff` and no `X-Frame-Options`.
- **Why this is exactly NEW-14 again, one directory over:** both are **anonymous** and sit at
  **fixed, scannable URLs on every install** — arguably a more exposed position than the admin
  surfaces slice 8 just covered. NEW-14's lesson was that per-file remembering fails at file N+1;
  these two are file N+1, and they were outside the tree the new enforcement point governs.
- **Not fixed in slice 8, and the reason is a real constraint rather than schedule:** neither file
  requires `admin/bootstrap.php` (that is the whole point of D-043's relocation), so covering them
  means either a second enforcement point or an explicit pre-boot `require`. `comment-submit.php`
  answers its **flood ceiling before `App::boot()` by design** (D-043, the `security-auditor`
  finding that restructured slice 7), so a post-boot call would leave exactly that path uncovered —
  the NEW-22 shape a second time. Doing it properly wants its own test point.
- **Stated so the slice does not overclaim:** `docs/reference/security-headers.md` says in its own
  words that slice 8 covers the admin plus the front controller, **not** these two. Asserting
  otherwise would be the L-002 defect.
- **Trigger:** the next slice touching `installer/public/`, or the introduction of
  `core/bootstrap-minimal.php` (whose absence D-043 already records) — which would give both files
  a shared pre-boot place to call from.

### NEW-25 — Seven copies of the HTTPS check survive `Helpers::isHttps()` — **LOW** *(found 2026-07-20, slice 8, by the `code-reviewer` pass)*
- **Where:** `installer/admin/users.php:93`, `installer/admin/partials/ai-panel-users.php:60`,
  `installer/admin/api/download-identity.php:137`, `installer/core/mcp/tools/user-tools.php:118`,
  `installer/core/site-health-manager.php:135`, `installer/install.php:841`. (An eighth in
  `installer/vendor-ai/guzzlehttp/psr7/` is third-party and is never touched.)
- **What:** slice 8 added `Helpers::isHttps()` as the single TLS check and collapsed **five** call
  sites into it — the two in `Helpers` itself, the two session-cookie `secure` flags in `Auth`, and
  `admin/bootstrap.php:195`, which the reviewer correctly flagged as a miss in the very file the
  slice was editing. The six above are in files slice 8 does not otherwise open.
- **Severity reasoning, not inflated:** every copy is byte-identical to the helper's body, so this
  is a consistency and future-drift risk, not a live defect. It matters if the definition ever
  changes — the obvious future change being trusted-proxy support (**NEW-17**), at which point six
  forgotten copies would silently keep the old behaviour. Three of them build password-reset URLs,
  where getting the scheme wrong has real consequences.
- **Trigger:** the trusted-proxy slice (NEW-17), which must convert them all as part of its own
  work, or opportunistically whenever one of those files is next opened (the D-025 pattern).

### NEW-26 — The password-reset form has no CSRF protection — **LOW** *(found 2026-07-20, slice 8, by the `security-auditor` pass)* — **CLOSED 2026-07-27 (Sprint 6 slice 4, D-061)**
- **Where:** `installer/admin/reset-password.php` — the "set new password" form (`:125-140`); no
  `klytos_csrf_field()` and no `klytos_verify_csrf()` anywhere in the file.
- **Exploitability, stated honestly rather than inflated:** low. A forged cross-site POST still
  needs a valid `user_id` + `token` pair, which `validatePasswordResetToken()` checks — and that
  secret is precisely what CSRF protection would otherwise be substituting for. An attacker who
  already holds a valid reset token does not need CSRF to use it.
- **Why it is recorded rather than fixed here:** slice 8 touched this file only to reuse the
  request's CSP nonce; the defect is pre-existing and belongs to authentication, which is an
  adjacent subsystem under D-031's narrowing. It is noted because the file was in the diff and a
  reviewer looked at it — leaving it unrecorded would mean the next reader has to find it again.
- **Trigger:** the authentication slice that owns **NEW-09**, **NEW-11** and **NEW-13**.
- **CLOSED 2026-07-27 — Sprint 6 slice 4 (D-061)**, with NEW-47, because the two are one defect class
  on one flow: the form emits `klytos_csrf_field()` and the POST verifies it, answering **403** with
  the same `auth.session_expired` message. The recorded low exploitability is unchanged and was not
  used to argue it away — what closed it is that fixing it alongside NEW-47 costs one line and one
  test, while leaving it means every future reader re-deriving the "this one does not need CSRF"
  judgement. Proven by a reverted TEMP-BREAK (the check removed → the token-less POST was accepted,
  200) and by a positive control that resets a password end to end and then logs in with the new one
  through the real form.

## A — Accessibility (target: WCAG 2.2 AA + EAA, `references/accessibility.md`)

### A-01 — No skip links anywhere — **HIGH**
Zero hits for skip-link patterns in the whole repo. WCAG 2.4.1 Bypass Blocks (A).

### A-02 — No `prefers-reduced-motion` anywhere — **HIGH**
Zero hits across ~4,900 lines of admin CSS, despite animated sidebar, dialog and toast components.
WCAG 2.3.3 (AAA) / EAA expectation; also a vestibular-safety issue.

### A-03 — Focus indication is effectively absent — **HIGH**
Only 13 `:focus`/`:focus-visible` rules across all admin CSS. Custom-styled controls lose the UA
default. WCAG 2.4.7 Focus Visible (AA) and 2.4.11 Focus Appearance (2.2, AA).

### A-04 — Landmarks unlabelled, ARIA coverage token — **MEDIUM**
23 `aria-*` occurrences across 42 admin pages; only 1 `role="region"`. `<nav>` elements carry no
`aria-label`. WCAG 1.3.1, 2.4.1.

### A-05 — Generated frontend output has zero ARIA/roles — **HIGH (highest stakes)**
`installer/core/build-engine.php` contains **zero** `aria-` or `role=` attributes;
`installer/templates/` and `installer/templates/parts/` likewise. No skip link in
`templates/parts/header.html`.
**Why this is the worst one:** Klytos's users inherit this. Under the European Accessibility Act the
legal exposure lands on them, for markup they did not write.

### A-06 — Hardcoded `lang="en"` in generated documents — **MEDIUM**
`core/mailer.php:463`, `core/privacy-manager.php:202`, `core/helpers.php:1089`,
`core/mcp/oauth-authorize-view.php:147`. WCAG 3.1.1. (The page build path *does* handle `lang`
dynamically — `build-engine.php:2125`, `template-resolver.php:224` — so this is inconsistency, not
absence.)

### A-07 — The shipped accessibility skill asserts compliance the code does not have — **MEDIUM**
`.claude/skills/klytos-accessibility/` claims WCAG 2.1 AA and EAA compliance. Measured: ~20–25%
(admin), ~15% (generated). Recorded as L-002. Any AI operating Klytos reads that claim as true.

---

## I — Internationalization

### I-01 — Generated frontend output and system/error strings are not localized — **HIGH**
~85% of admin chrome is externalized (918 `__()` call sites over 639 keys), but exception and error
strings in `installer/core/*` are almost entirely English literals, and the generated site output is
not covered.

### I-02 — Newer admin pages carry hardcoded English — **MEDIUM**
`installer/admin/setup-wizard.php`, `terminal.php`, `updates.php`, `post-type-edit.php`,
`plugins.php`, `consent.php`, `system-integrity.php`; flash messages frequently hardcoded
(e.g. `installer/admin/users.php:52`). Partials drift from the catalogue:
`admin/partials/ai-panel-dashboard.php:64` hardcodes `Server` where `admin/index.php:133` uses
`__('dashboard.server')`.

### I-03 — No plural handling — **MEDIUM**
No `_n()` equivalent, so counted strings are English-shaped or concatenated — which breaks in
Slavic and Asian locales that ship (ru, pl, ja, zh).

### I-04 — Terminal command descriptions are hardcoded **Spanish**, unaccented — **HIGH**
- **Where:** `installer/core/terminal-executor.php` — 17 occurrences, lines 371, 393, 406, 427, 462,
  472, 490, 509, 532, 556, 594, 622, 678, 691, 706, 789, 874
- **What:** Command descriptions — user-facing strings shown in the web terminal and by
  `php cli.php help` — are hardcoded Spanish literals, not `__()` keys. Examples as written in
  source: `'Listar todas las paginas'`, `'Mostrar numero total de paginas por estado'`,
  `'Mostrar version de Klytos'`, `'Mostrar resumen de analiticas'`,
  `'Mostrar el valor de una opcion de configuracion'`.
- **Fails two rules at once:**
  1. **Base language.** D-006 records English as the base language of everything built. A Spanish
     literal in source is a defect, not a translation — it cannot be localized to English, because
     English is supposed to be the source.
  2. **Orthography.** Every one of them is missing its accents: `paginas`/`páginas`,
     `numero`/`número`, `version`/`versión`, `analiticas`/`analíticas`, `opcion`/`opción`,
     `configuracion`/`configuración`, `ayuda` is fine but `Limpiar caches`/`cachés`. Keel treats a
     spelling error like a code bug. Spanish users see misspelled Spanish; everyone else sees a
     language they did not choose.
- **Remediation shape:** replace each literal with `__('terminal.cmd.<name>.description')`, add the
  English source string to `installer/core/lang/en.json`, and add the correctly accented Spanish to
  `es.json` — plus the other 18 locales, per the same-slice catalogue rule.
- **Note:** found while generating `docs/api/INDEX.md`, not in the original security sweep — which
  is itself a small lesson about how much a mechanical full-surface extraction turns up.

### Positive finding
All 20 locale catalogues carry exactly 639 leaf keys with zero drift. That is unusual discipline and
should be protected by a mechanical check rather than left to habit.

---

## E — Extensibility (Keel's maximum-extensibility rule for extensible types)

### E-01 — Core translation strings cannot be overridden by a plugin — **MEDIUM**
There is no filter on `__()` itself (no `gettext`-style interception). A plugin can register its own
catalogue but cannot change a core string without editing the catalogue file.
**Fails:** "every meaningful user-facing string passes through a filter".

### E-02 — The read path is not filterable — **MEDIUM**
No generic query-args filter analogous to `pre_get_posts`. A plugin can swap the storage *backend*
but cannot cheaply alter an individual query.
**Fails:** "every query and every response is filterable" — the response half is met, the query half
is not.

---

## T — Testing and verification

### T-01 — Zero automated tests, zero CI — **CRITICAL for a project of this nature**
No `tests/`, no `phpunit.xml`, no CI. The codebase performs encryption, session auth, 2FA, OAuth
2.0, plugin installation and payments. Every release is manually verified or not verified at all.
**Fails:** Phase 5's testing contract — every acceptance criterion maps to a named automated test.
**Consequence for triage:** the S-0x remediation cannot be *proved* without at least a minimal
harness, which is why T-01 belongs with the fix-now set.

### T-02 — No playground — **HIGH**
Klytos is runnable, so Keel requires a real verification environment
(`references/playground-recipes.md`) plus `docs/playground.md` with try-it instructions and
throwaway credentials. Neither exists.

### T-03 — `phpstan.neon` is export-ignored but does not exist — **LOW**
`.gitattributes` anticipates tooling that was never added (same for `phpunit.xml`, `tests/`,
`.github/`).

### T-04 — PHPCS is not a declared dependency — **LOW**
`phpcs.xml` is real and well-tuned, but with no `composer.json` the ruleset only runs against a
globally installed `phpcs` — so "lint passes" is not reproducible for a contributor or in CI.

---

## H — Hygiene, release and packaging

### H-01 — Four-way version drift — **HIGH**
`installer/VERSION` `0.31.1-beta.1` / `README.md` `0.28.5` / `changelog.txt` `0.4.0` / newest tag
`v0.30.1`. Nothing reconciles them.
**Standard:** Phase 7 — version touchpoints in sync, verified by `scripts/keel-verify`.

### H-02 — Release archives ship without README/INSTALL — **HIGH**
`.gitattributes` blanket `*.md export-ignore` strips `README.md` and `INSTALL.md`, although
`INSTALL.md` instructs users to upload them to the server. `LICENSE` and `PRIVACY.md` are correctly
re-included; these two are not.

### H-03 — `changelog.txt` abandoned since 0.4.0 — **HIGH**
~27 releases undocumented. Users of a self-updating CMS have no way to know what changed — including
whether a release contains a security fix.

### H-04 — Vendored dependencies have no manifest — **HIGH** — **CLOSED 2026-07-19 (Sprint 1, slice 2)**
`installer/vendor-ai/` ships 482 tracked files (guzzlehttp, psr/*, ramsey/uuid, brick/math, swaggest,
symfony polyfills, soukicz/llm) with no `composer.json` recording pinned versions. They cannot be
audited against CVEs or updated reproducibly.
**Fails:** web-app profile — dependency audit (`composer audit`); maintenance reference — CVE duty.

**Resolution.** `installer/composer.json` + `installer/composer.lock` reconstruct the tree exactly
(D-028) and `composer audit -d installer` now runs. Two counts in the original finding were wrong and
are corrected on the record: the tree holds **16 packages, not 9** (the "9" came from reading the top
level of `vendor-ai/`, which has 12 vendor directories), and 482 is the **tracked** file count — 484
exist on disk, two being gitignored package-internal files. The audit's first run produced NEW-05
(5 CVEs), NEW-06 (PHP 8.3 floor) and NEW-07 (licence texts) below — the findings this manifest
existed to make visible.

### H-05 — 33 first-party PHP files carry no license header — **LOW**
195 of 228 do. Inconsistent for a GPL project that states the plugins/templates clause in its
headers.

### H-06 — `docs/` contained stray non-documentation artifacts — **LOW**
A 29 KB extension-less file literally named `docs/docs`, plus `docs/consent-manager.js` (a source
file living in the docs directory). Not touched during adoption; flagged for the user to classify.

### H-07 — No release automation — **MEDIUM**
Packaging relies entirely on `git archive` + `.gitattributes`. No build script, no release workflow,
no version bump automation — the direct cause of H-01, H-02 and H-03.

---

## D — Documentation

### D-01 — No per-surface API documentation — **MEDIUM**
`docs/api/INDEX.md` now exists — **930 public surfaces**: 138 global helper functions, 96 classes and
interfaces, 301 actions, 110 filters, 206 MCP tools, 34 HTTP routes, 26 terminal/CLI commands,
19 plugin extension contracts. Full per-surface docs do not exist.

> **These are the ADOPTION-DAY figures (2026-07-18) and are kept as the record of that day, not as
> current values.** INDEX.md has grown every sprint since; read it, never this paragraph. The `301`
> here is the specific number that leaked into D-026 and NEW-03 as *"`doAction()` backs 301
> registered actions"*, where it was wrong in kind as well as in number — it counts distinct action
> **names**, and nothing registers 301 anything. Corrected in Sprint 4 slice 1 (D-054), which
> re-measured **308** names, **363** fire sites and **23** shipped listener registrations.
**Recorded rule (adoption):** progressive backfill — each surface gets its complete doc in
`docs/api/` or `docs/reference/` the first time a slice touches it, unless the user wants a
documentation sprint now.

### D-05 — README undersells the product with stale counts — **LOW (but free to fix)**
`README.md` advertises "160+ tools / 75+ hooks". The real figures are **206 MCP tools** and
**411 hooks/filters** (`docs/api/INDEX.md`). On the one axis where the scan says Klytos is genuinely
strong — extensibility — the project undersells itself by more than five times on hooks. Same root
cause as H-01/H-03: nothing reconciles the docs with reality at release.

**Sharpened 2026-07-24 (Sprint 2 slice 4, surfaced by the sprint-close `docs-verifier`), and
deliberately only half-fixed.** "33 tool modules" was **false** (there are 34 loader files since
slice 3 wired `integrity-tools.php` in) and was corrected in the three places it appears —
a one-word factual count this sprint is directly responsible for. The rest is **not** fixed and is
recorded here rather than half-done: README's per-module table has 34 rows summing to **177**, a
figure that describes the pre-slice-3 world, and its row labels no longer map one-to-one onto the
loader's files (e.g. one "Template Tools" row covers both `template-tools.php` and `part-tools.php`).
Regenerating it is an editorial pass on the public README, which belongs with the D-017 repositioning
in Phase 6, not inside a security sprint. "160+ tools" is left alone because it is **true** (206 ≥
160) — understated marketing copy, not a false claim.

To spare Phase 6 the re-derivation, the measured per-file counts (2026-07-24, `$registry->register(`
per file, summing to exactly the 172 that keel-verify check 10 independently reports) are:
`asset` 13 · `template` 15 · `post-type` 12 · `custom-field` 11 · `page` 11 · `page-template` 9 ·
`block` 8 · `build` 6 · `comment` 6 · `consent` 6 · `part` 6 · `scheduler` 5 · `task` 5 ·
`theme` 5 · `user` 5 · `webhook` 5 · `menu` 4 · `option` 4 · `post-status` 4 · `translation` 4 ·
`version` 4 · `ai` 3 · `integrity` 3 · `plugin` 3 · `ai-image` 2 · `analytics` 2 · `guide` 2 ·
`maintenance` 2 · `site` 2 · `bulk` 1 · `export` 1 · `shortcode` 1 · `site-builder` 1 ·
`site-health` 1. Plus **8** x402 tools injected by filter, so a default install serves **180** and
a install with both shipped MCP plugins active serves **206**.

### D-02 — Docs describe intent more accurately than the code implements it — **MEDIUM**
The systemic pattern behind A-07 and S-04: 31 `klytos-*` skills assert properties (WCAG compliance,
an enforced role matrix) the code does not have. Recorded as L-002 with the rule for next time.

### D-04 — `docs/api/INDEX.md` is a snapshot, not a regenerable artifact — **MEDIUM**
The index was built by mechanical extraction, but the extraction scripts lived in a session-scoped
scratchpad and are gone. With 930 surfaces across 228 files, keeping it accurate by hand is not
realistic — it will drift the first time a surface is added without its row.
**Standard:** Keel's `scripts/keel-verify` includes an `docs/api/INDEX.md` parity check, run at every
sprint close and at the Phase 7 gate. That script does not exist for this project yet (T-01 family).
**Remediation shape:** commit the extraction as a repo script and wire its parity check into
`scripts/keel-verify` at the Phase 5 scaffold, so an INDEX row without its surface — or a surface
without its row — fails mechanically instead of being noticed by luck.
**Extraction gotcha to preserve in that script:** MCP tools register in **two** styles — core tools
via `$registry->register('name', …)`, while x402 and the forms/importer plugins inject
`['name' => …, 'description' => …]` arrays through the `mcp.tools_list` filter. Grepping only
`register(` misses 34 of the 206 tools.

### D-03 — No end-user guide — **deferred to Phase 6**
`guide/` does not exist. Keel's Phase 6 asks the four guide questions (languages, ships-in-release,
developer portal, portal ships/repo-only) and builds it on `keel-docs-theme`. Not a defect now.

### D-06 — Every shipped skill teaches the superseded parts API — **MEDIUM** *(found 2026-07-18, theme-model design work)*
`installer/core/mcp/tools/part-tools.php` is the canonical API for shared site chrome
(`klytos_set_part`, `set_part_data`, `list_parts`, `get_part`), and `part-tools.php:174` states it
explicitly: *"After migrating, edit shared elements with `klytos_set_part` / `klytos_set_part_data`
instead of `klytos_set_global_block_data`."* The in-product guide
(`installer/core/guides/site-builder.md`, Phase 6) documents it correctly.
**Not one of the 31 shipped `.claude/skills/klytos-*` skills mentions `klytos_set_part`.**
`klytos-custom-templates` still teaches the superseded model (global blocks,
`klytos_set_global_block_data`, `klytos_set_custom_template_part`, top-bar auto-injection).
**Impact:** two contradictory mental models ship side by side, and which one an AI follows depends on
whether it loaded the skill or the in-product guide — a coin flip that decides whether the site's
chrome is authored through the current or the abandoned API. This is the inverse of D-02/L-002: there
the docs claimed more than the code did; here the code does more than the docs admit.
**Remediation shape:** reconcile `klytos-custom-templates` (and any sibling skill referencing global
blocks) onto the parts model, in the same slice that touches either. Tracked in
`docs/theme-package-model.md` §2 as a defect the theme-package redesign must close, not inherit.

---

## F — Functional / architecture

### F-01 — The canonical parts API has no propagation path; only the superseded one does — **MEDIUM** *(found 2026-07-18, theme-model design work)*
Klytos generates a static site, so a change to shared chrome must propagate to every generated HTML
file. That mechanism exists — but only for the **superseded** model:
- `BlockManager::render()` wraps output in `<!--klytos:block:{id}-->` markers
  (`installer/core/block-manager.php:242`), and `BuildEngine::smartRebuildBlock()` uses them to
  replace a global block across all generated files **without a full rebuild**
  (`installer/core/build-engine.php:1322-1362`), exposed over MCP at
  `installer/core/mcp/tools/build-tools.php:100`.
- `PartManager` emits the equivalent `<!--klytos:part:{id}-->` markers
  (`installer/core/part-manager.php:340`) — but **there is no `smartRebuildPart`, and no MCP entry
  point for one.** The markers are written; nothing consumes them.

**Impact:** changing the header through the current, recommended API forces a full site rebuild,
while the abandoned API does it incrementally. On a large site this is the difference between a
targeted rewrite and regenerating every page. It also compounds D-06: the API the skills *don't*
teach is the one that is faster.
**Standing requirement it violates:** the user's binding invariant — shared chrome is authored in one
place, one edit updates the whole site, and updating a header never means touching the site's pages.
The authoring half holds; the propagation half is half-built. Recorded in full at
`docs/theme-package-model.md` §3b, with its verification step at §7.
**Remediation shape:** implement smart propagation for parts on the markers that already exist,
expose it over MCP, and make "one edit → whole site updated, no full rebuild, no per-page work" a
verified test point with a recorded files-updated count.

---

### NEW-15 — `SafeHttp` resolves twice, so DNS rebinding survives S-08's fix — **LOW** *(found 2026-07-19, Sprint 1 slice 6, while writing the fix)*

- **Where:** `installer/core/safe-http.php` — `blockReason()` resolves via `resolveHost()`, then
  `HttpClient` resolves again independently when it connects.
- **What:** the address is validated at time-of-check and resolved again at time-of-use. A hostname
  served by a nameserver the attacker controls, with a very short TTL, can answer with a public
  address for the validating lookup and a private one for the connecting lookup. The check passes and
  the connection still lands inside the network.
- **Why it was not fixed in slice 6:** the remedy is to pin the validated address for the connection
  (`CURLOPT_RESOLVE`, plus the equivalent for the stream fallback, which has none — it would have to
  refuse rather than degrade). That changes `HttpClient`'s transport contract for every caller, needs
  its own test point with a resolver it can actually control, and lands in a slice already carrying
  the redirect chain and five call-site conversions. D-031's narrowing applies: the slice fixes the
  path it is changing, not the adjacent subsystem.
- **Severity is LOW, and the reasoning is on the record rather than assumed:** it requires the
  attacker to control authoritative DNS for a name they can get the product to fetch, and the
  reachable surfaces are authenticated (oEmbed: `pages.edit`) or operator-initiated (webhook
  creation, plugin install). It is a real gap, not a theoretical one — it is simply the harder half.
- **Honest framing, because overstating the fix is the L-002 defect:** slice 6 raises the cost of
  SSRF substantially. It does not make it impossible, and `docs/reference/safe-http.md` says so in
  those words.
- **Trigger:** the authentication slice (which already reopens this area), or the first time any
  unauthenticated surface gains an outbound fetch — whichever comes first.

---

### NEW-16 — Comments could never be switched on: `SiteConfig::setValue()` did not exist — **HIGH (functional)** *(found 2026-07-20, Sprint 1 slice 7, while proving S-09 closed)* — **FIXED in slice 7**
- **Where:** `installer/core/site-config.php` (method absent); callers at
  `installer/core/mcp/tools/comment-tools.php:136,139,143,147`
- **What:** `klytos_set_comment_settings` — the **only** supported way to enable the comment
  system — called `$config->setValue( ... )` four times. `SiteConfig` exposed exactly
  `__construct`, `get`, `set`, `getValue` and `updateBuildTimestamp`, and has no `__call`. Every
  invocation therefore died with *Call to undefined method
  Klytos\Core\SiteConfig::setValue()*. **Proven live, not inferred:** booting the app and calling
  the method returns that fatal.
- **The second half, which is the reason a "just call set() instead" fix would also have failed:**
  `SiteConfig::set()` (`site-config.php:54-127`) carries a hardcoded allow-list of twelve
  top-level fields, and `comments_enabled` is not among them — so a value passed to `set()` is
  **silently dropped**. Both the method that was called and the method that exists were incapable
  of storing this setting.
- **Why this is the L-009 shape, recorded because the pattern keeps recurring:** S-09 said comments
  were broken because the endpoint was unreachable. Underneath that sat a feature that could not be
  turned on at all. Fixing the first defect is what made the second one reachable — exactly as
  `api/download-identity.php`'s first fatal had been masking its second. A slice that had only
  added `comment-submit.php` to `$preAuthScripts` would have shipped, passed its own review, and
  left comments just as non-functional, because nothing would ever have set `comments_enabled`.
- **Fixed in slice 7** (D-043): `SiteConfig::setValue()` is implemented as the dot-notation
  counterpart to the existing `getValue()`, deliberately not routed through `set()`'s allow-list.
  In scope by necessity rather than by opportunism — S-09's test point requires an anonymous
  submission to succeed, which is unreachable while the feature cannot be enabled.

### NEW-17 — Behind a non-loopback proxy, the comment rate limit becomes one shared bucket — **MEDIUM** *(found 2026-07-20, Sprint 1 slice 7)* — recorded, NOT fixed
- **Where:** `installer/core/mcp/rate-limiter.php:151-170` (`getClientIp()`)
- **What:** `getClientIp()` honours `X-Forwarded-For` **only** when `REMOTE_ADDR` is loopback, and
  otherwise returns `REMOTE_ADDR`. That is the right default against header spoofing on a
  directly-exposed host. On a site behind a CDN or reverse proxy whose address is not loopback,
  every visitor resolves to the **same** address, so the whole audience shares one rate-limit
  bucket: two comments per minute for the entire site, and one spammer denies commenting to
  everyone. The failure direction is availability, not bypass.
- **Why not fixed here:** the remedy is a configurable trusted-proxy list, which changes
  `getClientIp()` for the MCP endpoint, the OAuth token endpoint and the plugin route layer too —
  three surfaces this slice does not touch, each needing its own test point. Deferring it is the
  user's recorded decision, taken with the trade-off stated.
- **Stated in the reference doc rather than implied away:** `docs/reference/public-comments.md`
  says the limit raises the cost of spam substantially and that a proxied deployment needs this
  fix before it means what it says.
- **SECOND HALF, added 2026-07-26 by the Sprint 6 slice 1 `security-auditor` pass — this entry only
  ever covered availability, and the same function has a BYPASS half.** In the case the code *does*
  trust (`REMOTE_ADDR` is loopback, i.e. a proxy on the same host — the supported deployment), it
  takes `explode( ',', $forwarded )[0]`: the **first**, client-supplied entry, with no trusted-hop
  allow-list and no chain-depth limit. A reverse proxy that **appends** rather than overwrites
  `X-Forwarded-For` — `proxy_add_x_forwarded_for` is the nginx default — leaves that first entry
  under the caller's control. Consequences on both sides: a caller can mint a fresh bucket key per
  request (bypassing the comment limit, the MCP limit and, since D-059, the login ceiling), or fill
  a chosen victim's bucket deliberately (a targeted lockout rather than the site-wide one this entry
  already records). Whether it is live depends on a deployment's proxy configuration, which cannot
  be settled from the source — recorded as a real half of NEW-17 rather than as a separate finding,
  because it has the same one remedy: a configurable trusted-proxy list.
- **Severity raised to MEDIUM–HIGH** on that basis, and the trigger is unchanged: the trusted-proxy
  configuration slice, which owns all four consuming surfaces at once.
- **Trigger:** the first deployment behind a CDN, or the slice that adds trusted-proxy config.

### NEW-18 — The global `__()` exists only inside the admin bootstrap, so no public surface can translate — **MEDIUM (i18n)** *(found 2026-07-20, Sprint 1 slice 7)* — recorded, NOT fixed
- **Where:** `installer/admin/bootstrap.php:28` (the only global definition);
  `installer/core/app.php:775-793` (`registerI18nGlobal()`)
- **What:** `App::registerI18nGlobal()` declares `function __()` **inside a namespaced file**, so
  it becomes `Klytos\Core\__()` and is unreachable from an unnamespaced file. The global `__()`
  every surface actually uses is declared in the ADMIN bootstrap. Any entry point outside
  `admin/` — `t.php`, `public/x402-gate.php`, and now `public/comment-submit.php` — therefore has
  no translation function at all. **Verified by the failure, not by reading:** the new endpoint
  fataled with *Call to undefined function `__()`* on its first run.
- **Consequence:** this is why the other public entry points hardcode English. It is not
  sloppiness; it is structural, and it silently pushes every public surface out of the i18n system
  that D-006 makes mandatory.
- **Worked around, not fixed, in slice 7:** the comment endpoint calls
  `$app->getI18n()->get( $key )` through a local closure rather than adding a third copy of the
  shim. Its strings ARE translated, in all 20 catalogues.
- **The real fix** is to move the global declaration into a core file both bootstraps require, and
  then convert `t.php` and `x402-gate.php`. That touches the admin bootstrap and the x402 surface —
  adjacent subsystems under D-031's narrowing.
- **Trigger:** the theme-package sprint (D-023), which makes the generated frontend a first-class
  surface, or the next slice that adds a public entry point.

### NEW-19 — `RateLimiter` rewrites its whole file per request, now on an anonymous path — **LOW** *(found 2026-07-20, Sprint 1 slice 7)* — recorded, NOT fixed
- **Where:** `installer/core/mcp/rate-limiter.php` — `check()` calls `loadData()` and `saveData()`,
  each reading and rewriting the entire `data/rate_limits.json`
- **What:** every call decodes the whole file, appends, and re-encodes it. Entries are pruned only
  probabilistically (1% per call) plus an hourly cron. Until slice 7 every caller was
  authenticated, so the number of distinct identifiers was bounded by the number of credentialed
  clients. The comment endpoint is reachable by anyone, so a distributed spammer can create an
  entry per source address and drive the per-request cost up with the file size.
- **Not a bypass** — the limit still holds — and there is no lock-free corruption concern beyond
  what already exists (`saveData` uses `LOCK_EX`). It is a scaling and disk-growth characteristic
  that changed the moment an unauthenticated caller could reach the limiter.
- **Compounding detail added by the slice's `security-auditor` pass:** cleanup only removes an
  identifier once **every** timestamp under it has expired, so the set of addresses ever seen grows
  monotonically between cron runs, and an attacker rotating source addresses (cheap from an IPv6
  /64) gets an independent bucket per address with no global ceiling above the per-IP one.
- **Trigger:** the first report of `rate_limits.json` growth, or the slice that fixes NEW-17 (which
  is already opening this class).

### NEW-20 — `RateLimiter::check()` is a read-decide-write with no lock spanning it — **MEDIUM** *(found 2026-07-20, Sprint 1 slice 7 `security-auditor` pass)* — recorded, NOT fixed
- **Where:** `installer/core/mcp/rate-limiter.php:48-78` — `check()` calls `loadData()` (shared
  lock, released) and then `saveData()` (exclusive lock) as two separate operations
- **What:** the read, the decision and the write are not atomic. Two concurrent requests can both
  read the same pre-write state, both conclude they are under the limit, and the later `saveData()`
  overwrites the earlier one's counter — so more than `maxRequests` land in a window. The limit is
  not defeated arbitrarily, but it is not exact under concurrency.
- **Pre-existing, and this slice did not introduce it** — the class has backed the MCP endpoint,
  the OAuth token endpoint and auth-failure tracking since 1.1.0. What changed is how cheaply it can
  be driven: comment spam is trivially parallelised, whereas the previous callers all required a
  credential first.
- **Explicitly UNVERIFIED, recorded as such rather than asserted:** the reviewer traced this
  statically and did not run a concurrency test, and neither did this slice. Settling it takes a
  parallel burst against the playground (e.g. `ab -c 20 -n 20` against `/comment-submit.php`) and
  counting how many exceed the configured maximum. **It is recorded as plausible-and-unproven, not
  as confirmed** — the L-013 discipline applied to an accusation rather than to a reassurance.
- **Fix when taken:** hold one exclusive lock across the whole read-check-write in `check()`.
- **Trigger:** the same slice that takes NEW-17 or NEW-19 — all three are in this one class.

### NEW-27 — Every in-product guide is stripped from the release archive — **HIGH** *(found 2026-07-20, slice 9)*

- **What:** all **16** files under `installer/core/guides/` are excluded from the distributable by
  the blanket `*.md export-ignore` in `.gitattributes`. The directory ships, empty.
- **Why it matters, and why it is not merely cosmetic:** these are not documentation *about* the
  product, they are a **product surface**. `installer/core/mcp/tools/guide-tools.php:33-37` reads
  that directory at runtime for `klytos_list_guides`, and `klytos_get_guide` serves the files by
  name. The tool's own description declares `gutenberg-blocks`, `seo-content`,
  `post-types-and-fields`, `forms` and `design-patterns` **REQUIRED** reading before creating page
  content. On a released install, `klytos_list_guides` therefore returns an empty list and every
  "REQUIRED" guide is unreachable — so the AI-first CMS ships without the instructions it tells the
  AI to read first. `site-builder-tools.php:44-59` has an explicit "list available guides to help
  diagnose" branch, which on a real install lists nothing.
- **Evidence (measured, not inferred):**
  ```
  $ git archive HEAD | tar -x -C /tmp/klytos-archive-test
  $ find /tmp/klytos-archive-test -name '*.md' | wc -l
  2                     # PRIVACY.md only
  $ ls /tmp/klytos-archive-test/installer/core/guides/
                        # empty
  ```
  Verified this reaches real installs and not only `git archive`: release `v0.30.1` has **no
  attached assets**, so `Updater::resolveDownloadUrl()` falls back to `$release['zipball_url']`
  (`updater.php:751-752`) — GitHub's auto-generated zipball, which honours `export-ignore`.
- **Relation to H-02:** the same rule, reaching further than H-02 recorded. H-02 notes that the
  blanket `*.md export-ignore` strips `README.md` and `INSTALL.md`; it did not establish that it
  also strips a live runtime surface.
- **Detectable now:** `scripts/keel-verify` WARNs on every run with the file count and the reason
  (slice 9, D-045).
- **Fix when taken:** an un-ignore rule for `installer/core/guides/**` (and the H-02 files) in
  `.gitattributes`, plus a test that the guide tools resolve at least one guide.
- **Security dimension** (added 2026-07-20 by the slice's `security-auditor` pass, distinct from the
  functional HIGH above): `guide-tools.php:30` and `:74` are release-controlled MCP tool
  *descriptions* that instruct any connected AI assistant that several guides — including
  **`security-architecture`** (encryption, auth, CSP) and **`accessibility`** — are REQUIRED reading
  before it creates pages, SEO fields, post types or forms. Because every guide is stripped, a real
  install's MCP surface can never deliver that guidance: the tool tells the assistant to consult
  security and accessibility rules that provably do not exist on the running system. For a product
  whose premise is AI-driven authoring, that is a degradation of its one documented safety rail, not
  a missing help file. **Mitigating factor, checked rather than assumed:** `klytos_get_guide`
  (`guide-tools.php:89-102`) returns an explicit `error` plus an empty `available_guides` rather than
  silently returning blank content, so the assistant is not misled into believing it read real
  guidance — but nothing stops it proceeding without it.
- **Trigger:** Phase 7 — `docs/sprints/sprint-1.md` scopes the release-hygiene bucket there, and
  packaging is its subject.

### NEW-28 — Development scripts ship to the web root and execute over HTTP — **MEDIUM** *(found 2026-07-20, slice 9; the SAPI half fixed in the same slice)*

- **What:** `scripts/` carries no `export-ignore`, so `scripts/dev/router.php`,
  `scripts/dev/upgrade-assert.php`, `scripts/dev/seed-playground.php`, `scripts/dev/upgrade-test.sh`
  and `scripts/keel-verify` all ship to the site root of every install. The root `.htaccess` serves
  any existing file directly (`.htaccess:23-25`: `REQUEST_FILENAME -f` → `RewriteRule ^ - [L]`), so
  they are reachable.
- **Evidence (tested against an extracted archive, not reasoned about):**
  ```
  $ curl -D - http://.../scripts/dev/router.php
  HTTP/1.1 404 Not Found        <- its OWN 404 page, 468 bytes, disclosing the admin path,
                                   the MCP endpoint, BuildEngine internals and audit NEW-04
  $ curl http://.../scripts/dev/upgrade-assert.php
  HTTP 200, 1332 bytes          <- executed
  $ curl http://.../scripts/dev/seed-playground.php
  This script is CLI-only.      <- correctly refused (seed-playground.php:35 already had the guard)
  ```
- **Severity, stated honestly rather than inflated:** this is information disclosure and unnecessary
  attack surface, **not** a demonstrated RCE or authentication bypass. `router.php` carries traversal
  and dotfile guards, and no exploit beyond disclosure was found. What makes it worth recording is
  that it is a second, unaudited front controller with file-serving logic sitting at a fixed path on
  every install — and that `seed-playground.php`, which creates users with known credentials, was
  protected only by a guard its two siblings happened not to have.
- **Fixed in this slice (D-031's narrowing — slice 9 was already changing these files):** both files
  now refuse the wrong SAPI. `upgrade-assert.php` requires `cli`; `router.php` requires `cli-server`
  **and** that `SCRIPT_NAME` is not its own path, because `php -S` reports `cli-server` both when the
  file is the router and when it is served as a file — the first version of the guard was wrong for
  exactly that reason (**L-016**).
- **Still open — the packaging half, and the SAPI guards fix ZERO of it.** This distinction was
  raised by the slice's own `security-auditor` pass and is worth stating precisely, because "we
  added guards" reads as "handled" and it is not: a SAPI check stops **execution**. It does nothing
  about **disclosure**. `scripts/keel-verify` and `scripts/dev/upgrade-test.sh` carry **no `.php`
  extension**, so a standard Apache/php-fpm/nginx handler mapping never executes them — but
  `.htaccess:23-25` still serves any existing file, so both are streamed as **readable source**:
  - `scripts/keel-verify` contains literal comments naming this project's own tracked findings
    (`NEW-27`, `NEW-04`, `H-02`), handing an anonymous visitor an index of its known weaknesses.
  - `scripts/dev/upgrade-test.sh:89` contains the throwaway harness credential
    `admin_pass=upgrade-test-2026-Aa!`.
  Dev scripts should not ship at all. One `export-ignore` line removes the entire class — execution
  AND disclosure — including the two files that now merely fail closed.
- **Trigger:** Phase 7, with NEW-27 and H-02 — all three are the same `.gitattributes` review.

### NEW-29 — App-password `last_used` is never persisted (a `?? []`-by-reference footgun) — **LOW** *(found 2026-07-22, Sprint 2 slice 1)*
- **Where:** `installer/core/auth.php::validateAppPassword()`.
- **What:** the method iterates `foreach ( $data['passwords'] ?? [] as &$stored )`, sets
  `$stored['last_used'] = Helpers::now()` on a successful match, and writes `$data`. Iterating the
  result of `??` **by reference** binds `&$stored` to a throwaway temporary, so the update never reaches
  `$data['passwords']` and the write persists nothing. Application-password `last_used` has therefore
  never updated in production — the admin UI's "last used" column for app passwords has always shown the
  creation-time value (`null`).
- **Verified, not inferred:** a probe minted an app password, validated it successfully, and read the
  record back — `last_used` was still `null`. This is the same footgun **L-017** records in the slice's
  own migration, here in pre-existing code.
- **Impact:** cosmetic. `last_used` is a display/audit convenience; it is read by no authorization or
  authentication decision, so nothing is weakened. Bearer-token `last_used` uses a different path
  (`updateTokenLastUsed()`) and is unaffected.
- **Fails:** correctness (a silent no-op write); L-017's rule against `?? []`-by-reference.
- **NOT fixed here, on purpose:** it is adjacent to slice 1 (the app-password path) but the slice does
  not change `validateAppPassword()`'s `last_used` logic, and the sprint's subject is authorization, not
  app-password display metadata — the D-025/D-026/D-031 narrowing. The fix is one line (assign the list
  to a variable, or guard the key, then write back).
- **Trigger:** the **NEW-11** authentication slice, which already opens the credential/app-password
  code, or the next slice touching `validateAppPassword()`.

### NEW-30 — Filter-injected MCP tools are unreachable over the HTTP transport (advertised but not callable) — **RESOLVED 2026-07-23, Sprint 2 slice 3** *(found 2026-07-22, Sprint 2 slice 2 `code-reviewer` pass)*
- **RESOLUTION (slice 3, user-confirmed — D-050):** the "make callable over HTTP" option was chosen over
  narrowing the doc claim. `ToolRegistry::exists()` now returns true when a tool is registered **or**
  declared in the capability map, so `handleToolsCall()` lets a filter-injected tool reach `call()`,
  which gates it (`denialReason`) and dispatches it via `mcp.handle_tool`. `handleToolsCall()` catches a
  typed `ToolNotFoundException` from `call()` (a mapped-but-unhandled tool) and answers "Unknown tool"
  rather than a 500 — a narrow catch (added on the code-reviewer's note) that never masks an unrelated
  handler error. Proven over real HTTP (`McpGateHttpTest`: owner calls x402/`klytos_forms_list` → 200; viewer/editor
  → 403) and live in the playground (owner `klytos_forms_list` → HTTP 200, was "Unknown tool"). x402 and
  the two shipped plugins are now first-class over both transports. Original finding follows.
- **Where:** `installer/core/mcp/server.php::handleToolsCall()` → `ToolRegistry::exists()`.
- **What:** `handleToolsCall()` rejects a call with `!$this->registry->exists($toolName)` **before**
  `call()`, and `exists()` checks only the `register()`-populated `$this->tools` table. A tool that
  exists solely through the `mcp.handle_tool` filter — the 8 `klytos_x402_*` tools today, and any
  shipped-plugin tool — hits `JsonRpc::invalidParams("Unknown tool: …")` over real JSON-RPC and never
  reaches `call()` (or the gate) at all. `tools/list` **does** advertise these tools (via
  `mcp.tools_list`), so the HTTP surface advertises tools it then refuses as "unknown".
- **Pre-existing, not introduced by slice 2:** `exists()` has always been register-only. Slice 2 makes
  it visible because it is the first slice to reason about "the gate covers plugin-handled tools" — which
  is true on the **AI-chat** path (`chat-engine` calls `call()` directly, so filter-injected tools ARE
  gated and usable there), but moot on the HTTP path, which rejects them earlier.
- **Impact:** functional, fails **closed** (a refusal, never an escalation). x402/plugin tools are
  usable via AI chat but not via a direct MCP HTTP client. No security regression.
- **NOT fixed here, on purpose:** reconciling `exists()`/`handleToolsCall()` with filter-injected tools
  is the same "make filter-injected tool sets first-class" work **slice 3** already owns (wiring
  `integrity-tools.php`, the two plugins' declarations). Doing it in slice 2 would widen the slice past
  its subject.
- **Fix shape:** teach `exists()`/`handleToolsCall()` to also probe `mcp.handle_tool` (e.g. a
  dry-run/`can_handle` signal) before rejecting, OR narrow the "covers plugin tools" claim in
  `docs/reference/mcp-authorization.md` to the chat path and accept HTTP as register-only.
- **Trigger:** **slice 3** (filter-injected tool reconciliation), where it is in scope by construction.

### NEW-31 — The AI chat's panels carry no permission check of their own: `ai.use` reached user management and site settings — **HIGH** — **FIXED 2026-07-24 (Sprint 2 slice 4, in path)** *(found 2026-07-24 by the sprint-close `security-auditor` pass on D-051)*
- **Where:** `installer/admin/ai-chat.php` (panel routing), `installer/admin/partials/ai-panel-users.php`,
  `ai-panel-settings.php`, `ai-panel-dashboard.php`, `ai-panel-profile.php` (**zero**
  `klytos_has_permission` calls between them, verified by count), and
  `installer/admin/api/ai-chat.php` (`get_providers`).
- **What:** `admin/ai-chat.php` is gated at `ai.use` by the gate map, and then `require_once`s
  `partials/ai-panel-{$panel}.php` for a caller-supplied `?panel=`. The partials are privileged
  surfaces reached through a different door: `ai-panel-users.php` runs `create`, `update_user`,
  `suspend`, `activate`, `send_password_reset` and `force_logout` behind **CSRF alone** — work
  `admin/users.php` reserves to `users.manage` (**owner-only**) — and `update_user` passes
  `$_POST['password']` to `changePassword()` for an arbitrary `user_id`. `ai-panel-settings.php`
  writes site, email and SMTP settings that `admin/settings.php` reserves to `site.configure`.
  Separately, `api/ai-chat.php`'s `get_providers` returns `masked_key` (the first 6 and last 4
  characters of every configured AI provider key) with no check, while the four sibling
  key-management actions in the same file all require `site.configure`.
- **The escalation, stated precisely rather than dramatically.** Two of the auditor's routes do not
  exist: `UserManager::create()` refuses a second owner (`user-manager.php:111`) and `update()`
  refuses `role = owner` outright (`:209`), so *becoming* owner directly is blocked. What is real:
  a holder of `ai.use` could **create an `admin` account** and could **set any existing account's
  password**, including the owner's — which is account takeover by a longer path. That is a genuine
  escalation for `admin` **today**, before any of this sprint's work: admin holds `ai.use` and does
  not hold `users.manage`.
- **Why it was invisible:** `ai.use` and the capabilities these panels need were the *same set*
  (owner+admin) from the moment `ai.use` was created (D-035), so no request could observe the
  difference. **D-051 split the sets**, and the gap became reachable one tier lower. This is the
  S-07 family — a privileged surface trusting the file that included it — one door further in, and
  it is the third time in this project that a capability check was missing precisely where two
  capabilities happened to coincide.
- **Fixed in path (D-031's narrowing; the slice's own subject is authorization).** Panel routing in
  `ai-chat.php` now carries a **panel → capability map** mirroring each panel's standalone twin in
  the gate map (`users` → `users.manage`, `settings` → `site.configure`, `dashboard` →
  `pages.view`, `profile` → `profile.edit`), with an **absent entry denying**, so a fifth panel is
  refused until it is mapped. It runs **before** `templates/header.php`, so a refusal is a clean 403
  document and not a denial appended to half-rendered HTML. `get_providers` now requires
  `site.configure` like its four siblings; it has **no caller in the product**, and `ai-chat.php`
  renders the provider list server-side from the same manager without ever using `masked_key`, so
  nothing breaks.
- **Proven, both directions:** `AdminGateHttpTest::testAiChatPanelsRequireTheirOwnTierNotJustAiUse`
  and `::testAiChatApiDoesNotDiscloseProviderKeysBelowSiteConfigure`. Against the unfixed code an
  editor received **200** on `?panel=users` and **200** on `get_providers`; both now 403, while the
  owner still gets 200 and an editor still reaches the chat itself and the two unprivileged panels.
- **Not exploitable on a current install** — NEW-11 means no `admin` or `editor` account can log in
  at all — which is why it survived: the roles that could reach it have no way in. Recorded here so
  the NEW-11 slice does not open the door with this behind it.
- **Related and NOT fixed:** `klytos_ai_list_providers`'s tool description claims it "Does NOT
  expose API keys" while `listProviders()` returns `masked_key`. The tool is mapped `mcp.manage`
  (owner/admin), so the claim is wrong but the exposure is contained. L-002 shape; bound to the next
  slice touching `ai-tools.php`.

### NEW-32 — The authorization audit hooks are a seam with no sink: refusals write nothing to the log — **MEDIUM** *(found 2026-07-24 by the Sprint 2 sprint-close playground-QA pass)*
- **Where:** `installer/core/mcp/tool-registry.php:275` fires `mcp.access_denied`;
  `installer/core/helpers-global.php:507` fires `auth.access_denied`. **Neither has a single listener
  anywhere in core** (verified by grep across `installer/`, excluding vendor).
- **What:** both gates hand the full refusal reason — the role, the capability, the surface or tool —
  to an action hook, and nothing subscribes. So on a default install every admin 403 and every MCP
  permission denial leaves **no trace at all** in `installer/data/logs-*/`. Developer Mode being ON
  changes nothing: `Logger::write()` only records what some code asks it to record, and no code asks.
- **How it was found, which is the point:** the sprint-close playground-QA pass was told the debug log
  is ON and asked to paste it after a walkthrough that produced 401s, 403s, MCP denials and 429s. It
  came back with an empty log and reported the *document* as defective. The document was defective —
  it promised a log — but so was the mental model behind it: two of this project's own reference
  documents said the refusal reason "went to the audit log", which is a claim the code does not make
  good on by itself. That wording is corrected in `installer/core/mcp/server.php` and
  `docs/reference/mcp-authorization.md`; the gap itself is recorded here.
- **Why it matters beyond documentation:** an authorization system that refuses silently cannot be
  operated. Nobody can answer "is something probing us?" or "which capability is my integration
  missing?" without a record, and the sprint's own hand-to-the-user step ("copy the debug log when
  something fails") returns nothing for exactly the failures the sprint built.
- **Fix shape (a decision, not an obvious yes):** subscribe a core listener that logs both actions at
  warning level. It is ~10 lines and reuses `klytos_log_warning()`. What makes it a *decision* rather
  than a chore is volume and content: every refusal writes a line, refusals can be driven by an
  anonymous caller (rate-limited, but still), and the entry names a role and a capability — so it
  needs a deliberate answer on retention and on whether the reason belongs in a file an operator
  might share. Default-on with the reason included is the likely right answer; it is not mine to
  assume.
- **NOT fixed in slice 4** — it is a behaviour change to logging discovered at the sprint close, with
  a real design question attached, and inventing an answer at close time is how a sprint acquires
  unreviewed behaviour. **Trigger:** the next slice touching logging or the audit trail, or the
  NEW-11 authentication slice (which will add refusals of its own).

### NEW-33 — The terminal/CLI's own strings are hardcoded Spanish — **LOW** *(found 2026-07-24 by the Sprint 2 sprint-close playground-QA pass)*
- **Where:** `installer/core/terminal-executor.php` (e.g. `:656` `"Comandos disponibles:\n\n"`), and
  the same file's other operator-facing literals.
- **What:** `php installer/cli.php help` answers *"Comandos disponibles:"*, and `logs` answers *"No
  hay archivos de log."*, regardless of the site's configured language. The project's recorded base
  language is **English** with every user-facing string coming from the 20 catalogues (D-006); these
  bypass the catalogues entirely and are the wrong language into the bargain.
- **Why it survived:** the terminal is a developer surface, and the one developer who used it reads
  Spanish. It is the mirror image of **NEW-18** (the global `__()` is unavailable outside `admin/`,
  so public entry points hardcoded **English**): the same missing i18n reachability, resolved by
  whichever language the author was thinking in.
- **Severity LOW and honest about it:** nothing is insecure and nothing is broken; it is a
  correctness and consistency defect in a surface real operators do read. It also makes a
  playground-QA pass hesitate — a fresh reader matching documented English output against Spanish
  output cannot tell a translation gap from a wrong install.
- **Fix shape:** route those strings through the I18n **service** (the `installer/public/comment-submit.php`
  pattern — the global `__()` is not available in `cli.php` either) and add the keys to all 20
  catalogues. **Trigger:** the next slice touching `terminal-executor.php`, or the NEW-18 resolution,
  which owns the underlying `__()` reachability problem.

### NEW-34 — The AI chat's `model` parameter is unvalidated and reaches the provider URL — **LOW** *(found 2026-07-25 by the Sprint 3 slice 1 reachability trace, sharpened by that slice's `security-auditor` pass)*
- **Where:** `installer/admin/api/ai-chat.php:168` (`$modelId = $input['model'] ?? null;`) →
  `installer/core/ai/chat-engine.php` (`new LocalModel( $modelId )`, stored verbatim) →
  `installer/vendor-ai/soukicz/llm/src/Client/Gemini/GeminiClient.php:83`, which builds
  `"{$this->apiEndpoint}/models/{$model}:generateContent?key={$this->apiKey}"` by plain interpolation.
- **What:** `send_message` never checks `model` against `AiKeyManager::PROVIDERS[$providerId]['models']`,
  although its sibling actions (`switch_provider`, `set_key`, `validate_key`) all validate *provider*.
  The value lands in the URL's **path** segment, before the `?key=` the request authenticates with.
- **Impact, measured rather than reasoned about** (five spellings run through the vendored psr7):
  | `model` | effect |
  |---|---|
  | `gemini-2.5-pro` | normal — `key=` on the wire |
  | `evil#x` | everything after `#`, **including `key={apiKey}`**, becomes the URI **fragment**, which PSR-7 never sends → the request reaches Google **with no API key** → 401 |
  | `evil?x` | `:generateContent?key=…` is pulled into the query and the path truncates to `/models/evil` → wrong endpoint |
  | `evil%0d%0aX-Injected: 1` | percent-encoded — **no CRLF injection** |
  | any spelling | **host never moves** — the authority is fixed by the literal prefix that precedes the interpolation |
- **So: denial-of-function, not a security boundary crossing.** No header injection, no authority
  takeover, no key exfiltration to a third party — the key is *dropped*, not *sent elsewhere*. The
  caller already holds `ai.use` and is already using their own API key against that same host.
- **Population, corrected:** this is **not** admin-only. `api/ai-chat.php` is gated at `ai.use`
  (`installer/core/admin-gate.php`), which **D-051 widened to `editor`** — so editor and above. The
  first draft of this finding said "authenticated admin"; the slice-1 `security-auditor` caught it.
- **Why NOT fixed in Sprint 3:** it is an adjacent subsystem under D-031's narrowing — Sprint 3's
  slices touch the vendored tree and `App::getChatEngine()`, not `api/ai-chat.php`'s input handling —
  and it is a correctness defect rather than a CVE path, so folding it into a security slice would
  repeat the tangling D-025/D-026/D-029/D-038 have each refused.
- **Fix shape (so the next slice does not re-derive it):** validate `$modelId` against
  `AiKeyManager::PROVIDERS[$providerId]['models']` in the `send_message` branch exactly as the sibling
  actions already validate `$providerId`, and fall back to the provider's default model rather than
  erroring — an unknown model is a stale client, not an attack.
- **Trigger:** the next slice touching `installer/admin/api/ai-chat.php`, or the NEW-11 authentication
  slice (which will be reviewing what each role may reach anyway).

### NEW-35 — MCP tool input schemas are advertised but never enforced, and a second URL interpolation rides on that — **LOW/MEDIUM** *(found 2026-07-25 by the Sprint 3 slice 2 `security-auditor` pass; both halves re-verified against source before recording)*
- **Two findings, one root.**
- **(a) The systemic half — `inputSchema` is a published contract the server does not keep.**
  `ToolRegistry::call()` (`installer/core/mcp/tool-registry.php`) does exactly three things before
  dispatch: the D-046 authorization gate, the `mcp.handle_tool` plugin filter, and the handler
  invocation. **It never validates `$params` against the tool's `inputSchema`.** The schema is built at
  registration and sanitised for `tools/list` — i.e. it exists to be *advertised* — so every `enum`,
  `type` and `required` a tool declares is advisory. An MCP client that reads the schema and trusts it
  is trusting something nothing enforces, and every one of the 172 core tools' handlers receives
  whatever the caller sent.
- **(b) The concrete instance it enables — the NEW-34 pattern, a second time.**
  `installer/core/mcp/tools/ai-image-tools.php` declares `model` with a JSON-Schema `enum` and passes
  `$params['model']` straight through; `installer/core/ai-image-generator.php:57,62` then does
  `$model = $options['model'] ?? self::DEFAULT_MODEL;` and
  `$url = self::API_BASE . '/models/' . $model . ':generateContent?key=' . $apiKey;` — the identical
  unvalidated interpolation NEW-34 records for the chat path, reachable over **MCP** via
  `klytos_generate_image` (gated `assets.manage`). The declared `enum` looks like it prevents this and
  does not.
- **Impact is the same shape as NEW-34 and is bounded the same way:** the authority is fixed by the
  literal prefix, so the host cannot move; a `#` would push `key={apiKey}` into the fragment and the
  request would leave unauthenticated. Denial-of-function, not exfiltration.
- **Why (a) matters more than (b):** (b) is one file. (a) is the reason (b) was possible and the reason
  the next one will be. Fixing only the interpolation leaves every other tool's declared contract
  unenforced — the by-omission failure shape S-07 and NEW-02 both exist to close, on a third axis.
- **Deliberately NOT fixed in Sprint 3.** Neither half is in this sprint's diff: slice 1 touched the
  vendored tree, slice 2 touched `App::getChatEngine()`. Schema enforcement across 172 tools is its own
  slice with its own decision — it is a **behaviour change for every MCP client**, since calls that
  work today would start being refused, and that needs the D-034 treatment (a recorded decision plus a
  release note), not a quiet inclusion.
- **Fix shape, recorded so it is not re-derived:** validate `$params` against `inputSchema` in ONE place
  in `ToolRegistry::call()` — the D-046/D-032 inversion applied a third time — deciding first whether an
  unknown property is refused or ignored, and whether the check is advisory (log) or enforcing (refuse)
  for the first release. The interpolation in `ai-image-generator.php` should be fixed regardless, since
  it does not depend on the schema question.
- **Trigger:** the next slice touching `ToolRegistry::call()` or the MCP tool surface; or the NEW-34
  slice, which is the same defect class and should close both instances together.

### NEW-37 — No supported path can rotate the password of the only account that can log in — **HIGH** *(found 2026-07-25 at the Sprint 5 kickoff, by driving the NEW-11 feature rather than the finding — L-014)*
- **Where:** `installer/core/auth.php:99-102` (the gate reads config) versus
  `installer/core/user-manager.php:424` (`changePassword()` writes the record);
  `installer/admin/reset-password.php:68` and `:71`; `installer/admin/profile.php`;
  `installer/core/mcp/tools/user-tools.php` (`klytos_reset_user_password`)
- **What:** every password-change surface in the product writes `pass_hash` on the **user record**.
  `Auth::login()` verifies against **`config['admin_pass_hash']`**. The two have already diverged, so
  changing the owner's password changes nothing an operator can observe at the login form: the new
  password is refused and the old one keeps working, indefinitely.
- **Proven live, with a net-zero probe** (the password was changed and restored inside one run, so the
  playground was left byte-identical):
  ```
  1. UserManager::changePassword( owner, NEW )
  2. UserManager::authenticate( owner, NEW )   -> ACCEPTED   the record updated
  3. Auth::login( owner, NEW )                 -> REFUSED    the change never reached the gate
  4. Auth::login( owner, OLD )                 -> ACCEPTED   the old password still works
  5. restored
  ```
- **The L-002 defect is live in the product on top of it:** `reset-password.php:71` prints *"Your
  password has been reset successfully. You can now log in."* immediately after `changePassword()`
  returns true. The sentence is false for the owner — the only account that can log in at all — and the
  operator has no way to tell, because the write really did succeed. An owner who follows the reset
  link believing their old password is gone is wrong in the more dangerous direction.
- **Why this is a distinct finding from NEW-11 and not a restatement of it:** NEW-11 is about *who* can
  log in (one account). This is about *which credential* logs them in — it would remain true even if
  NEW-11 had been fixed by giving the config credential to every role. Both share a single cause (two
  authorities for one decision) and therefore a single fix, which is why they close together.
- **Severity is HIGH on the credential-lifecycle axis rather than the escalation axis:** it grants
  nobody anything they did not have, and it makes revocation impossible — a leaked or shared owner
  password cannot be rotated through any supported path, on a product whose primary interface hands
  control to an autonomous agent.
- **Fix:** closed by **D-056** in Sprint 5 slice 1 — the record becomes the sole login authority, so
  every existing rotation surface starts working with no change to any of them.

### NEW-38 — The OAuth consent screen cannot complete a 2FA login, so a 2FA-enabled account loops forever — **MEDIUM** *(found 2026-07-25 at the Sprint 5 kickoff, while tracing every `Auth::login()` caller)* — recorded, NOT fixed
- **Where:** `installer/core/mcp/oauth-authorize-view.php:91` (the login call), `:93-96` (the only
  branch that inspects the result), `:131-138` (the screen selector)
- **What:** the consent view is the **second** of the two `Auth::login()` callers repo-wide (the other
  is `admin/login.php:115`), and it has **no second-factor branch of any kind** — `is2faPending`,
  `complete2fa` and `requires_2fa` appear nowhere in the file. When the account has 2FA enabled,
  `login()` returns `success => true, requires_2fa => true` and sets the pending state. The view's only
  check is `if ( ! $result['success'] )`, which is therefore **not** taken; execution falls through to
  the screen selector, which asks only `isAuthenticated()` — false while 2FA is pending — and sets
  `$showLogin = true`. The user is shown the same login form again, with **no error and no second-factor
  prompt**. Submitting again repeats it.
- **Correction of record, made before this entry was written:** the kickoff plan described this as
  *"treats `requires_2fa` as a failed login and re-renders the form"*. That is wrong in mechanism and
  right in outcome — a failed login would display *"Invalid credentials."*; this displays nothing at
  all, which is worse to diagnose, because the operator sees a form that looks like it was never
  submitted. Re-derived against source rather than carried across (L-015).
- **Pre-existing, and this sprint makes it reachable by more accounts.** It is true today for any
  2FA-enabled owner authorizing an MCP client; after Sprint 5 slice 1 it is true for `admin`, `editor`
  and `viewer` as well, and slice 2 adds a fourth second-factor method that would hit the same wall.
  Stated rather than implied: **the sprint does not create this defect and does widen its population.**
- **Fix shape, recorded so it is not re-derived:** the view needs the same dispatcher `login.php` has —
  either by rendering a second-factor step of its own, or by redirecting to `admin/login.php` with a
  `redirect_to` back to the consent URL, which reuses one implementation instead of forking a second
  2FA UI (the reuse rule; `Helpers::sanitizeRedirectUrl()` already exists for exactly this).
- **Trigger:** the next slice touching the OAuth authorization flow, or the first report of an MCP
  client that cannot be authorized. **Deliberately not fixed in Sprint 5** (D-057): it is the OAuth
  consent surface, a different subsystem with its own test point, and folding it in is the tangling
  D-025/D-026/D-029/D-031 each refused in turn.

### NEW-39 — The login form was an account-status oracle by TIMING — **MEDIUM** — **CLOSED 2026-07-25 (Sprint 5 slice 1, in path)** *(found by that slice's `security-auditor` pass; both reviewers reported it and only one described it correctly)*
- **Where:** `installer/core/user-manager.php::authenticate()`
- **What:** the method returned early for an unknown username **and** for a non-active account, so
  only *"the account exists and is active"* ever reached `password_verify()`. Every other channel was
  deliberately identical — same `login_failed` error, same rendered message, same status, same
  lockout accounting — and the response **time** gave the answer anyway.
- **Measured, not reasoned about** (12 runs each, median, seeded playground):

  | case | median |
  |---|---|
  | active account + wrong password | **218.98 ms** |
  | suspended account + correct password | **0.65 ms** |
  | username that does not exist | **0.64 ms** |

  A 340× difference, readable from a single request, needing one or two probes per candidate
  username — the per-account lockout does not mitigate it, because enumeration never needs five
  tries.
- **Pre-existing code, NEW exposure — which is what made it this slice's problem.** `authenticate()`
  predates Sprint 5, but until **D-056** its only callers were re-authentication surfaces behind an
  existing session (`admin/profile.php`, `partials/ai-panel-profile.php`). Putting it behind the
  public login form is what turned a latent ordering quirk into an unauthenticated enumeration
  channel. It also made this project's own new `docs/reference/authentication.md` assert *"Nothing in
  the response distinguishes them"* — the **L-002** defect, in a document written in the same slice.
- **The two reviewers disagreed about it and one was wrong** (**L-023** again): the `code-reviewer`
  described it as "no bcrypt when the username matches no record", which omits the suspended case;
  the `security-auditor` described the real split (*active* versus *suspended-or-nonexistent*).
  Re-derived by measurement rather than by picking the more plausible account.
- **Fixed in path** (D-031's narrowing — the method the slice re-points the login gate at): every
  outcome now performs one bcrypt verify, comparing against another stored record's hash when the
  submitted username resolves to nothing. A real hash rather than a committed literal, so the cost
  matches exactly, no bcrypt string enters the repository, and there is no first-call-in-the-process
  outlier — which under `php -S`, where every request is a fresh process, would have **inverted** the
  oracle instead of closing it. Re-measured after: **217.55 / 219.13 / 218.05 ms**.
- **Residual, stated rather than hidden:** an install with no users at all skips the equalization.
  There is no account to enumerate in that state.

### NEW-40 — The login lockout's read-modify-write is not atomic, and nothing throttles the endpoint — **LOW–MEDIUM** *(found 2026-07-25 by both Sprint 5 slice 1 review passes)* — recorded, NOT fixed
- **Where:** `installer/core/auth.php` — `readLockouts()`, `recordFailedAttempt()`, `writeLockouts()`
- **What:** `LOCK_EX` covers the final `file_put_contents()` but not the read that preceded it, so two
  concurrent failed attempts against the same account can both read the pre-increment count and one
  increment is lost. A parallelized brute force therefore gets somewhat more than the nominal five
  attempts before the 15-minute lockout engages. A torn read decodes to nothing and falls through to
  `[]`, i.e. **fails open** (no lockouts) rather than closed.
- **Second half:** nothing rate-limits `admin/login.php` by IP or globally, so a burst of invented
  usernames is bounded only by the 15-minute pruning window; every request in that window pays a
  decode + encode + `LOCK_EX` write of the whole map.
- **Severity is bounded and said plainly:** it weakens a control that bounds abuse. It does not
  bypass authentication, does not disclose anything, and does not lock anyone out who should not be.
- **Same shape as NEW-20** (`MCP\RateLimiter::check()` is a read-decide-write with no lock spanning
  it), now reproduced in a second subsystem — which is the argument for fixing them together rather
  than separately.
- **Fix shape, recorded so it is not re-derived:** the codebase already has the primitive —
  `ActionScheduler::acquireLock()` is a `flock`-based critical section. Wrap read-through-write in it
  for both this and NEW-20, and decide explicitly whether a torn or unreadable file should fail
  closed. **Trigger:** the next slice touching either limiter, or the first report of credential
  stuffing against a real install.

### NEW-41 — Suspending a user does not revoke their OAuth access token — **MEDIUM** *(found 2026-07-25 by the Sprint 5 slice 1 `security-auditor` pass)* — **CLOSED 2026-07-26 (Sprint 6 slice 2, D-060)**
- **Where:** `installer/core/mcp/token-auth.php::resolveUserActor()` and
  `installer/core/mcp/oauth-server.php::validateAccessToken()` (`ACCESS_LIFETIME = 3600`)
- **What:** `resolveUserActor()` reads the user's current **role** from the record but never its
  **status**, and `validateAccessToken()` checks only expiry. So a suspended user's OAuth access
  token keeps authenticating — carrying its role into the D-046 gate — for up to an hour after
  suspension.
- **Why it matters now:** Sprint 5 made suspension mean something on every other surface — the login
  form refuses, a live admin session ends within 60 seconds, and an application password is refused
  outright (`validateAppPassword()` requires an active record). This is the one credential type left
  where "suspended" does not take effect, and the inconsistency is more dangerous than the gap: an
  operator who suspends an account will reasonably believe access is gone.
- **Not caused by this slice, and not in its diff** — D-056 rewrote `validateAppPassword()` only.
  Recorded here rather than fixed because the OAuth token lifecycle (revocation on suspension, and
  whether existing tokens are invalidated on role change too) is its own decision with its own test
  point. `docs/reference/mcp-authorization.md` does not claim otherwise, so this is a behavioural gap
  rather than an L-002 documentation defect; `docs/reference/authentication.md` now states it in its
  suspension table.
- **Trigger:** the next slice touching the OAuth server or token validation, or the NEW-40 slice.
- **CLOSED 2026-07-26 — Sprint 6 slice 2 (D-060), at the trigger this entry named** (the slice
  immediately after NEW-40's). `resolveUserActor()` now reads the record's `status` alongside its
  `role`, and the OAuth branch of `validate()` requires a non-null actor to accept — so a suspended
  user's token answers **HTTP 401 on the next request**, at **authentication**, which is the layer
  D-056's implementation note 1 put application passwords at. One resolver, one answer: the
  inconsistency this entry called more dangerous than the gap is what closed.
  - **What was NOT built, stated because the entry asked for it:** active revocation of the stored
    token, and the adjacent question of whether a **role change** should invalidate existing tokens.
    Both remain their own decision with their own test point. The status is read per request, so
    reactivating the account makes the same token work again —
    `docs/reference/mcp-authorization.md` says exactly that in a section named for it, and
    `OAuthSuspensionHttpTest::testReactivatingTheUserRestoresTheSameToken` pins it, so the document
    cannot drift into claiming the stronger property.
  - **One consequence named in D-060 rather than discovered later:** an OAuth token whose user record
    has been **deleted** also moves from 403 to 401 — the same direction D-056 chose for the same
    condition on the application-password path. `validateAccessToken()`'s expiry-only check is
    unchanged; the attribution is what refuses.
  - Proven over the real MCP HTTP surface on port 8109, with the token minted through the product's
    own OAuth flow; three of the four tests observed failing first (200 where 401 was required, and
    403 where 401 was required for the deleted-user case).

### NEW-42 — Four rough edges in the passkey assertion path, now that it is reachable — **LOW–MEDIUM** *(found 2026-07-25 by the Sprint 5 slice 2 review passes)* — **CLOSED 2026-07-27 (Sprint 6 slice 3, D-063)**
- **Where:** `installer/core/two-factor.php::verifyPasskeyAssertion()`; `installer/admin/bootstrap.php` (the setup-wizard skip-list)
- **What (four, each verified against source):**
  1. **No clone detection.** The new signature count is stored without being compared to the stored
     one, so an authenticator that has been cloned — the exact condition the WebAuthn sign counter
     exists to reveal — produces no signal. The data to detect it is written and never read.
  2. **No `origin` check on assertion**, although `completePasskeyRegistration()` validates
     `clientData['origin']` against `https://{rpId}`. Asymmetric, and the narrower path is the one
     that runs at every login rather than once per enrolment.
  3. **No length guard before reading `authData` offsets.** `completePasskeyRegistration()` checks
     `strlen( $authData ) < 37` first; the assertion path does not, so a 32-byte
     `authenticatorData` (trivially precomputable — it is `sha256( rpId )`, and the rpId is public)
     reaches `ord( $authData[32] )` on an out-of-range offset. It fails **closed** after a PHP
     warning, so this is log noise and a rough edge rather than a bypass.
  4. **The setup-wizard skip-list was not extended alongside `$preAuthScripts`.** It still matches by
     basename and does not name this endpoint, so on an install where `setup_completed` is false the
     endpoint 302s to the wizard instead of answering its JSON contract. Narrow window; the two lists
     should move together.
- **Why they are recorded rather than fixed:** items 1–3 are inside `verifyPasskeyAssertion()`, which
  this slice made reachable but did not write, and each is a behaviour decision of its own (what
  should a sign-count regression DO — refuse, or warn?). Item 4 is a second list with its own
  semantics. The slice that found them was already carrying two blocking corrections, and D-031's
  narrowing is what keeps that from becoming an argument for widening it further.
- **Trigger:** the next slice touching the WebAuthn path, or the first report of a passkey failing
  after an authenticator restore.
- **Resolution (2026-07-27, Sprint 6 slice 3, D-063).** All four are closed, and each was proven to
  fail first with its own reverted TEMP-BREAK:
  1. **Clone detection** compares the presented signature counter with the stored one and refuses
     when it does not exceed it — **only when both are non-zero**. That condition is the item, not a
     detail: synced platform passkeys report `signCount = 0` permanently, so the naive "must
     increase" rule would refuse the second login of most authenticators in use. **Measured, not
     argued** — with the naive rule installed, exactly one test failed and it was the synced-passkey
     login. A new `user.passkey_clone_detected` action fires with both counters; **nothing in core
     subscribes**, and the reference doc says so in those words (L-019).
     - **This is a deliberate divergence from WebAuthn, and the first draft of this entry cited the
       spec as its authority, which was wrong.** §7.2's guard is **OR** — *"If `authData.signCount`
       is nonzero **or** `storedSignCount` is nonzero"* — verified against the published text, not
       recalled. The two rules differ in exactly one case, **stored non-zero and presented zero**,
       which the spec calls a cloning signal and Klytos accepts. **So a cloned credential can skip
       this check by presenting a counter of zero.** The recorded scope (`sprint-6.md`, approved)
       says AND, so AND ships — and the question was then **put to the project owner and settled on
       2026-07-27: the permissive rule stays** (D-063 note 2). Deciding fact: a determined attacker
       bypasses clone detection under **either** rule, because holding the cloned credential means
       choosing the counter it presents; OR catches only a careless clone, while permanently
       refusing an authenticator that legitimately resets is a real cost to real users.
  2. **The `origin` check** now runs on assertion, through the SAME `originIsAcceptable()` the
     registration path was refactored onto — one rule rather than two copies, so the symmetry is
     structural instead of remembered. It cannot lock anyone out: every stored credential was
     enrolled through that identical check.
  3. **The length guard** refuses a short `authenticatorData` before any offset is read. The
     regression test asserts the **absence** of the PHP warning, and the TEMP-BREAK reproduced it
     exactly as described here — `Uninitialized string offset 32`, exit code 1 under
     `failOnWarning`.
  4. **The setup-wizard skip-list** moved off basename matching onto `klytos_admin_gate_key()`,
     exactly as `$preAuthScripts` did in D-058, and gained `api/webauthn-challenge.php`. The
     `$currentScript` variable that fed the old match is gone, after establishing repo-wide that
     nothing read it (bootstrap's file-scope variables leak into every admin file that requires it —
     L-007).
- **One correction of record, found by running the break rather than reading it:** this entry says
  the endpoint "302s to the wizard". It does, but to
  **`/installer/admin/api/setup-wizard.php`** — a path that does not exist — because the redirect is
  built from `dirname( $_SERVER['SCRIPT_NAME'] )` and the script lives in `api/`. So the caller
  received a redirect to a 404 rather than to the wizard. Substance unchanged; the description was
  incomplete (L-015).

### NEW-43 — `klytos_delete_page` describes an action it did not perform — **LOW** *(found 2026-07-25 by the Sprint 5 sprint-close playground-QA pass)* — recorded, NOT fixed
- **Where:** `installer/core/mcp/tools/page-tools.php` (the `klytos_delete_page` handler)
- **What:** the handler returns `'info' => 'Page moved to trash. Use klytos_restore_page to undo…'`
  **unconditionally**, alongside `'success' => $deleted`. So deleting a page that does not exist
  answers `success: false` together with a sentence stating the page was moved to trash, with
  `isError: false` and HTTP 200. A model reading the response — which is the primary consumer of this
  product's tools — has one field saying nothing happened and one saying something did.
- **How it was found:** a fresh-context pass ran `docs/playground.md`'s own example against the
  non-existent `index` page. The document quotes that sentence as the tool's answer, so the misleading
  text had already propagated into the documentation.
- **Severity is LOW and said plainly:** nothing is destroyed and nothing is escalated. It is the
  **L-002 shape on an MCP surface** — a response asserting a state change that did not occur — which
  matters more here than the same wart would in a human-facing string, because the caller is an agent
  that acts on what it is told.
- **Fix shape:** return the `info` only when `$deleted` is true, and give the false branch a reason
  (no such page / already trashed). One line plus its test.
- **Trigger:** the next slice touching the page MCP tools, or the NEW-35 slice (tool contracts are
  advisory), which is the same family — a published contract that does not describe what the tool
  does.

### NEW-44 — Every refusal by the central admin gate is silently discarded — **MEDIUM** *(found 2026-07-26 at the Sprint 6 kickoff re-validation)* — FIXED in path, Sprint 6 slice 1 (D-059)
- **Where:** `installer/core/admin-gate.php:282` and `:296`; `installer/core/logger.php:122`
- **What:** both refusal paths of `klytos_enforce_admin_gate()` — the unresolvable-path denial and
  the default-deny for an unmapped surface — call
  `klytos_log_warning( …, 'security' )`. `Logger::write()` treats **any** source that is not
  `'core'` as a **plugin ID** and returns early unless that plugin has logging enabled
  (`if ( $source !== 'core' && ! $this->isPluginLoggingEnabled( $source ) ) return;`). No plugin is
  called `security`. So the S-07 gate — the single enforcement point four sprints were spent
  building — writes **nothing** when it refuses, with Developer Mode on or off.
- **Why it matters beyond the missing lines:** this is the mechanism the project's own hand-off
  procedure depends on. Every sprint close tells the user "the debug log is ON, paste it when
  something fails"; for the failures the authorization system produces, there is nothing to paste.
- **How it was found, and it is the uncomfortable part:** the Sprint 5 close-out playground-QA pass
  found this **exact** mistake in `docs/playground.md`'s own remedy snippet — a listener passing
  `'security'` as the source, producing silence directly beneath a paragraph explaining that silence
  was expected. The document was fixed. **Nobody asked whether the product made the same mistake**,
  and it does, in the gate itself. **L-019's shape a third time**, and the first two times were in
  documents.
- **Distinct from NEW-32**, which is that `mcp.access_denied` / `auth.access_denied` have no
  subscriber. This one is not about a missing listener: the gate calls the logger **directly** and
  the logger drops the entry. Fixing NEW-32 would not have fixed this.
- **Fix applied (D-059):** the callers pass `'core'` and keep the category in the message and
  context. Deliberately NOT fixed by teaching `Logger` a reserved-core-source allow-list — that is a
  contract change belonging to the NEW-32 logging slice, and this sprint would otherwise be
  redesigning the logger while hardening the limiters.

### NEW-45 — Five log calls pass their arguments in the wrong order, so every AI error message is discarded — **LOW** *(found 2026-07-26 at the Sprint 6 kickoff re-validation)* — recorded, NOT fixed
- **Where:** `installer/core/ai/chat-engine.php:213`, `:221`, `:229`, `:331`;
  `installer/core/ai/chat-manager.php:304`
- **What:** the signature is
  `klytos_log( string $level, string $message, array $context = [], string $source = 'core' )`
  (`helpers-global.php:642`). All five call sites invoke it as `klytos_log( $message, $level )` —
  e.g. `klytos_log( "AI chat error [{$httpCode}]: " . mb_substr( $body, 0, 300 ), 'error' )`. So the
  real diagnostic text arrives as `$level`, fails `in_array( $level, self::LEVELS, true )`, is
  replaced by `'info'` (`logger.php:145-147`) and is **discarded entirely**; the line written is the
  literal word `error`, at level INFO.
- **Severity is LOW and said plainly:** nothing is escalated, nothing is disclosed, no control is
  weakened. What is lost is the diagnostic content of every AI chat failure — the provider status
  code, the message body excerpt, the exception text, the failing tool name — which is precisely what
  the "paste the debug log" hand-off exists to collect.
- **Same family as NEW-44 and NEW-32**, reached a third way: there the sink drops the entry, here the
  caller destroys the payload before the sink sees it. The pattern worth naming is that this
  project's logging surface has now failed in three independent ways and every one was found by
  following a call to its sink rather than by reading the call.
- **Why not fixed here:** the AI subsystem is not in Sprint 6's diff and no slice touches these
  files — D-031's narrowing, which this project applies rather than argues about. It is five lines
  plus a test.
- **Trigger:** the next slice touching `installer/core/ai/`, or the NEW-32 logging slice, which
  should close all three together.

### NEW-46 — The login IP ceiling is not exact under concurrency — **LOW** *(found 2026-07-26 by the Sprint 6 slice 1 `security-auditor` pass; MEASURED the same session)* — recorded, NOT fixed

- **Where:** `installer/admin/login.php` (the ceiling: `isAuthBlocked()` → `$auth->login()` →
  `recordAuthFailure()`), `installer/core/mcp/rate-limiter.php:146-158`.
- **What:** the ceiling reads the counter, authenticates, and only then records the failure — and the
  authentication in the middle is a ~218 ms bcrypt verify (the NEW-39 equalization). Concurrent
  requests can all observe "under the limit" before any of them has recorded its own failure, so a
  simultaneous burst overshoots by its own width.
- **Measured, not argued** — the discipline NEW-20 established this same sprint. Bucket pre-filled to
  9 of 10, one process per request, each performing the real `isAuthBlocked()` → 218 ms →
  `recordAuthFailure()` sequence:

  | Requests fired | Served (expected 1) | Bucket after |
  |---|---|---|
  | 6, one at a time | **1** | 10 |
  | 6, simultaneous | **6** | 15 |
  | 12, simultaneous | **12** | 21 |

  The sequential run is the control: the instrument can tell the two cases apart. The bucket landing
  on exactly 9 + N is itself worth noting — **no increment is lost**, which is precisely what D-059's
  atomicity bought; the overshoot is a check-then-act window, not the lost-update defect NEW-20 was.
- **Why it is LOW rather than a reopening of NEW-40.** The sustained rate is still bounded: after the
  burst the counter holds every failure, and subsequent requests are refused. The cost of the attack
  is one burst the width of the server's worker pool per 60-second window, against an endpoint whose
  per-account lockout (5 attempts, keyed by the submitted username) is unaffected — a plugin cannot
  widen that one and neither can this. What it defeats is the claim that the ceiling is exact.
- **The fix shape, recorded so it is not re-derived:** count the *attempt* before authenticating
  rather than the *failure* after. That also counts successful logins against the ceiling, which is a
  policy change — the current design deliberately counts failures only, so that a user logging in
  repeatedly never approaches the limit. It therefore needs its own decision, not a rider here.
- **`docs/reference/authentication.md` states this with the numbers** rather than claiming an exact
  limit, because claiming otherwise would be the L-002 defect in the document written to prevent it.
- **Trigger:** the trusted-proxy slice that owns NEW-17 (same file, same function, same test point),
  or the first report of a brute force that outpaces the ceiling.

### NEW-47 — The password-login POST has no CSRF check, so an attacker can log a victim into the attacker's account — **LOW–MEDIUM** *(found 2026-07-26 by the Sprint 6 slice 1 `security-auditor` pass)* — **CLOSED 2026-07-27 (Sprint 6 slice 4, D-061)**

- **Where:** `installer/admin/login.php` — the password-login branch runs no `klytos_verify_csrf()`
  (only the 2FA branch does), and the shipped password form emits no `klytos_csrf_field()`.
- **What:** login CSRF. An attacker holding **their own** valid credentials on the install can host a
  page that silently POSTs a victim's browser into a session authenticated as the **attacker's**
  account. `SameSite=Strict` on the session cookie does not prevent it: the victim has no
  pre-existing session cookie to withhold, a new session is created, and its `Set-Cookie` is stored
  normally because the response comes from the CMS's own origin.
- **Why it matters here specifically:** Klytos is an AI-first CMS whose admin surfaces write content
  and mint credentials. A victim who does not notice whose session they are in may author content
  into, or paste secrets into, an account the attacker controls and can read.
- **Distinct from NEW-26**, which is the password-*reset* form. That one's recorded justification —
  the reset token already is the secret CSRF would substitute for — does not apply here: the login
  POST has no equivalent secret gating it.
- **Pre-existing, and NOT introduced by Sprint 6.** It is recorded now because slice 1's
  `LoginCeilingHttpTest` deliberately **pins the absence** (its source-parity test asserts the form
  emits no CSRF field, so the test's token-less request stays faithful to the shipped page). A test
  asserting a property nobody decided is a decision made by omission — this entry is that decision
  being written down instead.
- **Consequence of fixing it, stated so the next slice does not discover it:** adding a CSRF field to
  the login form makes `LoginCeilingHttpTest`'s source-parity test fail by design, and its requests
  must be updated in the same slice. That is the test working, not breaking.
- **Trigger:** the next slice touching `admin/login.php`'s POST handling, or the NEW-26 slice — same
  defect class, and they should close together.
- **CLOSED 2026-07-27 — Sprint 6 slice 4 (D-061), pulled forward by explicit user decision** rather
  than waiting for its trigger. The shipped password form emits `klytos_csrf_field()` and the
  password branch verifies it; a refusal answers **HTTP 403** with the form re-rendered and the
  message `auth.session_expired` (all 20 catalogues), worded from the single mapping D-059
  implementation note 1 established. NEW-26 closed with it, as this entry's own trigger proposed.
  - **The predicted consequence happened exactly as written:** `LoginCeilingHttpTest`'s source-parity
    test failed on the same run that added the field, with its own message, and its requests plus
    `AuthLoginHttpTest`'s were updated in the same slice — they now fetch the session and token from
    the page itself rather than minting them (L-026).
  - **And the fix did not work until a deeper defect was fixed: NEW-50.** `hash_equals( '', '' )` is
    TRUE, so `Auth::validateCsrf()` accepted a missing token in a session that held none — the exact
    anonymous state this form lives in. Observed by execution: after the check was added, the
    token-less requests were still served. See NEW-50.
  - Proven by three reverted TEMP-BREAK cycles; against the unfixed tree the forged login answered
    **302** and established a session.

### NEW-48 — The 2FA emergency-email branch overwrites its own error message — **LOW** *(found 2026-07-26 by the Sprint 6 slice 1 `code-reviewer` pass)* — recorded, NOT fixed

- **Where:** `installer/admin/login.php` — the `email`/`emergency_email` branch of the 2FA POST
  handler, and the shared outcome block below it.
- **What:** when the pending user has no valid email on file the branch sets
  `$error = __( 'security.no_email' )`. Execution then reaches the shared outcome block, where
  `$verified` is false, `$method !== 'email'` is true for `emergency_email`, and `$info` was never
  set — so `$error` is **overwritten** with `security.2fa_invalid_code`. A user locked out of their
  own account, using emergency recovery, is told their code is invalid when the real reason is that
  the account has no email address.
- **Exactly the defect class D-059 implementation note 1 fixed one branch away**, in the same file,
  in the same session: two places writing one message, with the later one winning. That fix was
  scoped to the password-login handler and its claim ("one place that maps `$result['error']` to
  words") is accurate for that handler and not for this one.
- **Why not fixed here:** it is a different branch of the file — the 2FA handler, not the
  password-login handler this slice changed — so D-031's narrowing applies exactly as it did to
  NEW-45 at this sprint's kickoff. It is one line plus a test that drives the emergency-email path
  with an email-less account, which is real work rather than a rider.
- **Trigger:** the next slice touching the 2FA branch of `admin/login.php` — NEW-38 (the OAuth
  consent screen cannot complete a 2FA login) is the obvious one, since it must reuse this
  dispatcher.

### NEW-49 — A suspended OAuth client's retries now consume the shared per-IP auth-failure budget — **LOW** *(found 2026-07-26 by the Sprint 6 slice 2 `security-auditor` pass; the mechanism re-verified against source before recording)* — recorded, NOT fixed

- **Where:** `installer/core/mcp/server.php:87,101` (the `isAuthBlocked()` gate and the
  `recordAuthFailure()` call) with `installer/core/mcp/rate-limiter.php:105-138` (the bucket, keyed
  `'ip:' . $ip`, `MAX_AUTH_FAILURES = 10` per `WINDOW_SECONDS = 60`).
- **What:** the auth-failure bucket is **IP-keyed and shared across every credential** reaching the
  MCP endpoint, and `isAuthBlocked()` runs at the top of `handlePost()` for **every** request from
  that address. Before D-060 a suspended user's OAuth token answered 200, so it never recorded a
  failure; now each rejected request records one. A legitimate integration with a retry loop whose
  account has just been suspended therefore starts consuming the shared budget, and past ten
  failures in sixty seconds every other MCP client behind the same address answers **429** until the
  window rolls — repeatedly, for as long as the retry loop runs.
- **Not a new capability for an attacker, and that was checked rather than assumed:** any garbage
  bearer token already reaches the identical `recordAuthFailure()` at the identical cost of one
  unauthenticated HTTP request, so an attacker who wanted to exhaust that bucket could already do it
  with no credential at all. What changed is that a **legitimate, previously silent** client can now
  do it by accident, which is an operability property rather than a security boundary.
- **Severity LOW and bounded by deployment:** it only bites where several MCP clients share one
  source address — a NAT or a reverse proxy — which is the same precondition as **NEW-17**, whose
  loopback-only `X-Forwarded-For` trust is what makes the address collapse in the first place.
- **Why not fixed here:** the remedies are all somebody else's slice. Keying the bucket per
  credential rather than per address, or exempting an *authenticated-but-refused* credential from
  the anonymous-brute-force budget, is a policy change to a shipped control — the constants
  `server.php` shares are exactly what D-056's implementation note 3 and D-059 both refused to move
  for a neighbouring purpose. It also cannot be reasoned about honestly until the address itself is
  trustworthy, which is NEW-17's remedy.
- **Stated where an operator meets it** rather than only here: `docs/reference/mcp-authorization.md`
  says it in the suspension section, beside the behaviour that causes it.
- **Trigger:** the trusted-proxy slice that owns **NEW-17**, or any slice that revisits the MCP rate
  limiter's keying.

### NEW-50 — `hash_equals( '', '' )` is true, so a missing CSRF token passed in a session that held none — **MEDIUM** *(found 2026-07-27 while closing NEW-47; measured, not read)* — **FIXED in path (Sprint 6 slice 4, D-061)**

- **Where:** `installer/core/auth.php::validateCsrf()`, consumed by `Helpers::verifyCsrf()` and
  therefore by every `klytos_verify_csrf()` call site in the admin.
- **What:** `verifyCsrf()` resolves an absent field to `''`. `validateCsrf()` read
  `$_SESSION['klytos_csrf'] ?? ''` and returned `hash_equals( $expected, $token )` — and
  **`hash_equals( '', '' )` returns true**. So any POST that sent no token, arriving in a session
  that had never been issued one, was **accepted**.
- **How it was found, which is the whole point:** by execution, not by reading. `klytos_verify_csrf()`
  was added to the password-login branch (NEW-47) and the token-less requests in
  `LoginCeilingHttpTest` were **still served**. The check agreed with itself about two empty strings.
  A slice that had shipped on "the call is there" would have shipped a guard that guards nothing —
  the L-019 family, and the L-016 discipline is what caught it.
- **Reach beyond the login form:** any admin surface POSTed in a session that had not rendered a form
  first. In practice authenticated sessions receive a token as soon as any admin page renders one, so
  the realistic exposure is the anonymous surfaces — `login.php` and `reset-password.php`, i.e.
  exactly the two this slice was closing. Recorded as MEDIUM rather than LOW because the defect was in
  the **primitive**, not in a call site: nothing about it was specific to those two pages.
- **Fixed in path (D-061):** `validateCsrf()` refuses when either side is empty, placed in the one
  method that decides token validity rather than as a guard at the login call site — a local check
  would have left every other caller with the hole (the S-04 / S-07 shape). Pinned by
  `LoginCsrfHttpTest::testAnEmptyTokenIsRefusedEvenWhenTheSessionHoldsNoneEither`, proven by removing
  the guard and watching the token-less login be served again.

### NEW-51 — The OAuth consent screen's own login form had no CSRF check either — **LOW–MEDIUM** *(found 2026-07-27 by BOTH Sprint 6 slice 4 review passes, independently)* — **FIXED in path (D-061)**

- **Where:** `installer/core/mcp/oauth-authorize-view.php` — the `action === 'login'` branch called
  `$auth->login()` with no `klytos_verify_csrf()`, and its rendered form emitted no
  `klytos_csrf_field()`. Its sibling `action === 'authorize'` branch has verified CSRF all along,
  which is what made the gap visible the moment anyone compared them.
- **What:** the same attack as **NEW-47**, through the other door. `Auth::login()` has exactly **two**
  call sites in the product; slice 4 was closing one of them. Fixing one of two identical paths is
  the failure D-041's own review cycle recorded, and it would have left NEW-47 marked CLOSED while the
  forced login stayed available at a different URL.
- **Exploitability, stated honestly rather than inflated:** in practice **nil until this same slice**,
  because the page fataled before rendering anything (**NEW-52**). What made the fix necessary is
  precisely that NEW-52 was fixed here too — the gap would have become live in the same commit.
- **Fixed in path (D-061)** rather than recorded, with the message kept as a hardcoded English literal
  matching every other string in that file: the view runs through the PUBLIC front controller where
  the global `__()` does not exist (**NEW-18**) and its namespace is `Klytos\Core\MCP`, so a `__()`
  call there would fatal rather than translate. Proven by a reverted TEMP-BREAK (the check removed →
  the forced login reached the consent step) plus a positive control that logs in and reaches consent.

### NEW-52 — `/oauth/authorize` fataled on every request: the consent screen has never rendered — **HIGH (functional)** *(found 2026-07-27 by REQUESTING the URL while proving NEW-51)* — **FIXED in path (D-061)**

- **Where:** `installer/core/router.php::handleOAuthAuthorize()` called `handleOAuthAuthorizeView()`
  **unqualified**. The router is namespace `Klytos\Core`; the function is declared in
  `Klytos\Core\MCP` (`oauth-authorize-view.php:30`). PHP resolves an unqualified function call to the
  current namespace and then to the **global** one — never to a sibling sub-namespace.
- **What the user saw:** `Fatal error: Uncaught Error: Call to undefined function
  Klytos\Core\handleOAuthAuthorizeView() in .../installer/core/router.php on line 195`, on **every**
  request to `/oauth/authorize`. So the OAuth **authorization-code flow could not be completed by any
  MCP client, ever** — the consent screen is its only interactive step. Reproduced over real HTTP
  against the playground before anything was changed, and confirmed **byte-identical at HEAD**, so it
  is pre-existing and not introduced by this sprint.
- **What it reframes:** **NEW-38** says this screen cannot complete a 2FA login. That was derived from
  source and is true — of a page that could not render at all. The 2FA gap is still open and still
  needs its slice; what changes is that nobody could have hit it.
- **Why it was fixed here rather than recorded:** slice 4 adds a CSRF check to this exact page and
  must prove it over HTTP. The proof is impossible while the page fatals, so this is in scope by
  necessity — the **NEW-16** precedent (D-043), where comments could never be switched on and the
  slice's own test point was unreachable without fixing it. One token changed: `MCP\handleOAuth…`.
- **How it was found, which is the transferable part:** by requesting the URL. Two review subagents,
  a `docs-verifier` and five sprints of work had read past it, because reading a call site does not
  tell you which namespace resolves it. Recorded as **L-027**.

### NEW-53 — The WebAuthn localhost origin allowance is a prefix match, so strings that are not origins satisfy it — **LOW** *(found 2026-07-27 by the Sprint 6 slice 3 `security-auditor` pass; I had reached the same conclusion independently while proving the refactor)* — recorded, NOT fixed
- **Where:** `installer/core/two-factor.php::originIsAcceptable()`
- **What:** the development allowance is `str_starts_with( $origin, 'https://localhost:' )` — a raw
  prefix test with no check that what follows is a port and that the string ends there. So
  `https://localhost:8443@evil.example` (where `localhost:8443` is **userinfo** and the host is
  `evil.example`), `https://localhost:8443/path` and `https://localhost:notaport` are all accepted.
- **Severity is LOW and the reason is specific, not a shrug.** A browser never serialises an origin
  with userinfo or a path — an origin is `scheme://host[:port]` and nothing else — so this cannot be
  produced by the case the origin check actually defends. A non-browser caller can put any string in
  `clientDataJSON`, but that document is folded into the SHA-256 the ES256 signature covers, so
  reaching the check with a chosen origin already requires the credential's private key, at which
  point the origin test is not what is standing between the attacker and the account.
- **Pre-existing, and that was proven rather than asserted.** The rule was carried into
  `originIsAcceptable()` byte for byte from the registration path, which has had it since before
  Sprint 5. A differential run of the old inline expression against the new helper over 63
  origin/rpId combinations produced **zero** behaviour differences, so this slice neither introduced
  the looseness nor widened it — it applied an existing rule to a second call site, which is what
  NEW-42 item 2 asked for.
- **Why it is not fixed here:** tightening it (`preg_match( '#^https://localhost(?::[0-9]+)?$#', … )`)
  changes the **registration** path's behaviour, which is outside NEW-42's four items and outside the
  approved slice scope — D-031's narrowing. The boundary is instead **pinned by a test that asserts
  the current, loose behaviour on purpose** (`PasskeyLoginTest::testTheLocalhostDevelopmentAllowanceSurvivesOnBothPaths`),
  the D-044 precedent, so tightening it later appears as a deliberate inverted assertion rather than
  as a mystery failure.
- **Trigger:** the next slice touching the WebAuthn ceremonies, or the decision that settles whether
  the `http://localhost` development case should be supported at all (see `docs/reference/authentication.md`
  "Known limits") — the two are one edit.

### NEW-54 — A failed passkey assertion does not consume its challenge — **LOW** *(found 2026-07-27 by the Sprint 6 slice 3 `security-auditor` pass; verified against source before recording)* — recorded, NOT fixed
- **Where:** `installer/core/two-factor.php::verifyPasskeyAssertion()`
- **What:** `deleteWebAuthnChallenge()` is called on the **success** path only. All **12** `return
  false` branches leave the stored challenge in place until it expires on its own
  (`WEBAUTHN_CHALLENGE_LIFETIME = 300` seconds), so a captured, genuinely-valid assertion can be
  replayed against the same still-live challenge within that window.
- **Why LOW, stated precisely rather than by severity feel:** every check that can fail *before*
  signature verification rejects an assertion that is not valid in the first place, and a **valid**
  assertion consumes its challenge on success. So the replay window only exists for an assertion that
  is correctly signed and refused for some *other* reason — which, before this slice, was no case at
  all, and after it is exactly one: clone detection. A cloned authenticator retrying with the same
  counter is refused again, and retrying with a higher one would be accepted with a fresh challenge
  anyway. The remaining scenario needs an attacker who has **intercepted** a valid assertion, which is
  a materially stronger position than forging one.
- **Pre-existing and not introduced by slice 3**, but slice 3 is what created the first refusal that
  happens after a valid signature, so the entry is written now rather than left implicit.
- **Trigger:** the next slice touching `verifyPasskeyAssertion()`, or the first slice that adds
  another post-signature refusal to that method.

### NEW-55 — A page that does not exist is reported to the AI as an internal error — **LOW–MEDIUM** *(found 2026-07-27 by the Sprint 6 close playground-QA pass; reproduced and traced before recording)* — recorded, NOT fixed
- **Where:** `installer/core/mcp/tool-registry.php` (the generic `catch (\Exception)` in `call()`) as reached by `klytos_get_page` in `installer/core/mcp/tools/page-tools.php`
- **What, measured:** `klytos_get_page` with `slug=no-such-slug-xyz` answers HTTP 200 with
  `"isError": true` and the body **`Error: An internal error occurred while executing the tool.`**
  The handler is `return $app->getPages()->get( $params['slug'] ?? '' );`, which throws for a missing
  page; `ToolRegistry::call()` catches **every** `\Exception` and maps it to that one sentence, by
  design ("Internal errors — log but don't expose details"). So a **domain miss is indistinguishable
  from a real internal failure**.
- **Why it matters more here than the same wart would in a human-facing string:** the caller is an
  agent. "An internal error occurred" invites a retry, an escalation, or a bug report; the truth is
  "that page does not exist", which is ordinary and actionable. The refusal-vs-error distinction the
  MCP surface is otherwise careful about (a gate refusal is 403 with a JSON-RPC error object; a tool
  running is 200) is undone one layer in.
- **The mirror image of NEW-43, and they belong to one slice:** NEW-43 is a tool saying something
  happened when it did not; this is a tool saying something broke when it merely was not found. Both
  are the **L-002** shape on the MCP surface, and both are about the same thing — the published
  contract of a tool not matching what it returns, which is also **NEW-35**'s subject.
- **Not fixed here:** distinguishing a domain miss from an internal error means either a typed
  not-found exception or a per-tool contract, and narrowing that catch changes the response shape of
  **every** MCP tool — the D-034 treatment (recorded decision plus a release note), not a rider on a
  documentation pass.
- **Trigger:** the **NEW-43** slice or the **NEW-35** slice — whichever comes first should close all
  three together.

---

## Next step

Triage every finding above with the user into **fix now** / **fix when touched** / **accepted**,
record the buckets in the summary table, mirror them in `docs/PROGRESS.md`, and plan the fix-now
set as Phase 5 Sprint 1.
