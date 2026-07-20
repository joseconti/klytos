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

Stated plainly so the closures are not read as more than they are: **the admin surface is gated and
the product's primary interface is not.** All 172 MCP tools still have zero permission checks
(**NEW-02**, Sprint 2 per D-020), and only `config['admin_user']` can actually log in (**NEW-11**).

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

### NEW-02 — MCP tools have zero authorization: 172 tools, 0 permission checks — **CRITICAL** *(found 2026-07-18, Sprint 1 kickoff re-validation)*
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

### NEW-03 — By-reference action listeners are silently broken; every page create warns — **HIGH** *(found 2026-07-18, Sprint 1 slice 0, by booting the playground)*
- **Where:** `installer/core/hooks.php:124` (`doAction( string $hook, mixed ...$args )`) and `:145`
  (`call_user_func_array`); listener at `installer/core/x402-bootstrap.php:194`
  (`function ( array &$data, string $action )`); fired from `installer/core/page-manager.php:86`
  (create) and `:148` (update).
- **What:** `doAction()` collects its arguments variadically, which **copies** them, so a listener
  declaring a by-reference parameter can never bind. PHP emits
  `Argument #1 ($data) must be passed by reference, value given` and the listener's mutations are
  **silently discarded**.
- **Reproduced, not inferred:** creating a page through `PageManager::create()` in the playground
  emits the warning three times out of three. Command and output recorded in
  `docs/05-test-points.md` (slice 0).
- **Why it matters beyond the noise:**
  1. `core/x402-bootstrap.php` is loaded **unconditionally** at boot (`core/app.php:486`), so this
     fires on **every page create in every production install**, not only when x402 is in use.
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

### NEW-05 — Five CVEs in the vendored HTTP stack — **MEDIUM** *(found 2026-07-19, Sprint 1 slice 2, by the first `composer audit` this project has ever been able to run)*
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
    (`installer/core/app.php:1009`). A site that never opens the AI chat never loads Guzzle at all.
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
- **Trigger:** Sprint 1 close.

### NEW-06 — The vendored AI stack requires PHP 8.3, but Klytos declares 8.1+ — **MEDIUM** *(found 2026-07-19, Sprint 1 slice 2)*
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
  verification of the support matrix (D-027's trigger).

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

### NEW-08 — There is no supported way to recreate a missing owner — **MEDIUM** *(found 2026-07-19, Sprint 1 slice 3)*
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

### Positive findings (recorded so they are not re-litigated)
- **No tracked secrets.** `git ls-files` over secret-shaped patterns returns zero; only
  `installer/core/keys/klytos-integrity.pub` (a public key — correct) is tracked.
- No XSS confirmed: every `$_GET`/`$_POST` echo found in the admin is wrapped.
- No SQL injection confirmed: PDO prepared statements throughout `database-storage.php`.
- Terminal executor explicitly avoids `exec`/`shell_exec`/`proc_open`/`passthru`/`system`.
- bcrypt cost 12, `session_regenerate_id(true)` on login and privilege change, login lockout, MCP
  rate limiting, AES-256-GCM at rest, RSA-signed integrity manifests.

---

### NEW-09 — Passkey second-factor login is broken, and the obvious fix opens an account-takeover path — **HIGH** *(found 2026-07-19, Sprint 1 slice 4)* — **NOT FIXED, deliberately**
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

### NEW-26 — The password-reset form has no CSRF protection — **LOW** *(found 2026-07-20, slice 8, by the `security-auditor` pass)*
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
**Recorded rule (adoption):** progressive backfill — each surface gets its complete doc in
`docs/api/` or `docs/reference/` the first time a slice touches it, unless the user wants a
documentation sprint now.

### D-05 — README undersells the product with stale counts — **LOW (but free to fix)**
`README.md` advertises "160+ tools / 75+ hooks". The real figures are **206 MCP tools** and
**411 hooks/filters** (`docs/api/INDEX.md`). On the one axis where the scan says Klytos is genuinely
strong — extensibility — the project undersells itself by more than five times on hooks. Same root
cause as H-01/H-03: nothing reconciles the docs with reality at release.

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

---

## Next step

Triage every finding above with the user into **fix now** / **fix when touched** / **accepted**,
record the buckets in the summary table, mirror them in `docs/PROGRESS.md`, and plan the fix-now
set as Phase 5 Sprint 1.
