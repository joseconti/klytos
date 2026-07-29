# Threat model — Klytos CMS

> Keel Phase 2 §4c, produced 2026-07-28 in the v5.0.0 reconciliation (**D-067**). Profiles in force:
> `references/security/web-app.md` **and** `references/security/mcp-server.md` (D-003) — the stricter
> rule wins on any conflict.
>
> **The rule that governs every word below: only `IN PLACE` may be written in the present tense.**
> A control that is not built and evidenced is `TO BUILD`, `MANUAL` or `VERIFY`, and saying so is the
> point of the artifact. "Input is sanitized" is false until the sanitization is in the code and a
> test proves it.
>
> Delivery states: `IN PLACE` (built and verified, evidence named) · `TO BUILD` (a named slice or
> recorded finding will build it) · `MANUAL` (a human configures it outside the code) · `VERIFY`
> (only a real-environment run can confirm it).
>
> This is a **released** product with production installs holding encrypted data. Nothing here is
> hypothetical.

## 1. Assumptions

**Public by construction — obscurity is never a control.** Klytos is GPL-3.0-or-later on a public
GitHub repository. The entire source ships to every install: every endpoint shape, every capability
name, every hook, every MCP tool schema, the whole admin. An attacker reads the same code the
maintainer does. The **only** thing that is genuinely secret is the per-install randomized admin
directory name and the encryption key under `installer/config/` — and the first is a
traffic-analysis speed bump, not an authorization control. If publishing a design weakens it, the
design was already broken.

**Who the adversaries are, and what they want:**

| Adversary | Wants | Reaches the system through |
|---|---|---|
| An anonymous internet scanner | RCE, a shell, a mailer, a crypto miner | The generated public site, `index.php`, the MCP endpoint, the login form |
| An authenticated low-privilege user (`viewer`, `editor`) | Vertical escalation to `owner` | Every admin page and JSON endpoint |
| A model driving the MCP server | Whatever its instructions say — **including instructions it read from content it was given** | `tools/call`, over a bearer token or OAuth |
| An operator of a hostile MCP client | Everything its token permits | The token it holds |
| A malicious or careless plugin | Core internals, other plugins' data | The plugin loader — plugin code is PHP that Klytos executes |
| A network position between user and site | Session theft, content injection | HTTP, if TLS is not configured by the host |

**A structural property worth stating up front, because it removes a whole class of threat:** Klytos
generates a **static** site. The public-facing output has no database, no PHP request handling of its
own beyond the x402 gate, and no session. The large majority of what a CMS is normally attacked
through is not reachable from the public surface at all — the attack surface is the admin panel and
the MCP server, not the website.

**And a property that adds one:** Klytos is AI-first by design. The MCP server exists so a model can
change the site. That makes the model an actor with real authority, and it makes anything the model
*reads* a potential instruction source. §3 says what is and is not done about that.

## 2. Defended

| # | Threat | Control | State | Evidence |
|---|---|---|---|---|
| D1 | Vertical privilege escalation through an ungated admin page or endpoint | **Default-deny by inversion at `admin/bootstrap.php`** — a surface is refused unless the gate map grants it, rather than each file remembering to check | `IN PLACE` | S-01…S-07 all closed, Sprint 1 slices 3–5. `NamedEscalationsTest` asserts the *record is unchanged* after a refusal, not merely the 403 — a gate that ran the handler and then returned 403 would satisfy a status-only assertion (**L-008**). `AdminGateHttpTest` asserts a 200 for the allowed role too, so the test cannot pass by arriving anonymous |
| D2 | An MCP caller invoking a tool it has no right to | **Default-deny gate in `ToolRegistry::call()`** + one central capability map + `tools/list` filtered per actor, so a caller is not even told what it cannot use | `IN PLACE` | Sprint 2, D-046…D-051. `scripts/keel-verify` check 10 fails the build on an ungated tool. Refusal message names the tool and the fix, never the role or the capability (**L-018** pinned it by identifier *shape*, not by a word blocklist) |
| D3 | Cross-site request forgery on a state-changing admin action | `klytos_csrf_field()` on every form, `klytos_verify_csrf()` on every mutating endpoint; the primitive itself was fixed in path in Sprint 6 slice 4 | `IN PLACE` | NEW-47, NEW-26, D-061 |
| D4 | XSS through injected inline script/style | **CSP with a per-request nonce** — `nonce="$cspNonce"` on every inline `<script>`/`<style>`, no inline `onclick`; plus escaping at print time through `klytos_esc_*` / `klytos_kses` | `IN PLACE` | Slice 8, D-044. Two `<style>` occurrences inside `srcdoc` attributes **cannot** carry a nonce and are documented as such rather than counted as covered (**L-015**) |
| D5 | Transport downgrade / session theft on the wire | `Strict-Transport-Security` and the rest of the security-header set, emitted from both public entry points | `IN PLACE` | S-11 closed, slice 8, `docs/reference/security-headers.md` |
| D6 | SSRF from any user-influenced URL | `SafeHttp` — private/reserved-range blocking with **IPv4-mapped IPv6 normalized before classification** and **AAAA resolved alongside A**, re-validated after redirects, typed `REASON_*` refusals | `IN PLACE` | S-08 closed; the mapped-IPv6 bypass was found by *running the encodings*, not by reading the flags — a reviewer had reasoned past it citing the documentation (**L-013**) |
| D7 | Account takeover at the login boundary | Password + second factor; passkeys usable as a second factor; owner recovery path | `IN PLACE` | Sprint 5 (NEW-11, NEW-37, NEW-39, NEW-09) with a recorded **user verdict PASS** 2026-07-26; Sprint 6 slice 3 (NEW-42) for the assertion path; Sprint 4 for recovery |
| D8 | Known vulnerabilities in vendored dependencies | `installer/composer.json` + `.lock` pin the vendored AI stack; `composer audit -d installer` is the gate | `IN PLACE` | Sprint 3 slice 1, D-052 — measured **11 → 0**, both sides. Stated honestly: none of the 11 had a demonstrated exploitation path here; the bump closed an obligation, not a live hole |
| D9 | A fatal or an information leak when the AI stack meets an unsupported PHP | Typed `UnsupportedRuntimeException` raised **above** the vendored `require_once`, so Composer's platform check never emits third-party text into the response | `IN PLACE` | D-053. The *ordering* is the load-bearing part and is pinned by a test that reads the source, because on a supported host no runtime test can reach that branch |
| D10 | A secret reaching the public repository | `.githooks/pre-commit` confidential-data gate, plus the scan before every commit | `IN PLACE` for this machine · `MANUAL` for collaborators | D-015. **The gap is recorded, not hidden:** `git config core.hooksPath .githooks` is not documented in the repo's development notes, so a contributor's clone has no gate |
| D11 | A tampered core update or plugin archive | RSA signature verification of signed manifests against `installer/core/keys/klytos-integrity.pub`; update path with backup and rollback | `IN PLACE` (mechanism) · `VERIFY` (end to end from a real published release) | The local half is driven by `scripts/dev/upgrade-test.sh`; a real GitHub-release upgrade needs a published release (`EXTERNAL-APPROVAL`) |
| D12 | An MCP tool call whose arguments violate the schema the tool publishes | Validation of `$params` against `inputSchema` in `ToolRegistry::call()` | **`TO BUILD`** | **NEW-35.** Today the registry runs the authorization gate, the plugin filter and the handler, and never validates. A second URL interpolation rides on it (`ai-image-generator.php:62`, whose declared `enum` looks like it prevents exactly this). Enforcing schemas is a behaviour change for every existing MCP client and needs a recorded decision plus a release note |
| D13 | An operator being unable to see why access was refused | A listener on `mcp.access_denied` / `auth.access_denied` writing to the audit log | **`TO BUILD`** | **NEW-32.** The actions fire with role, capability and tool. **Nothing subscribes.** So on a default install every refusal writes nothing, Developer Mode on or off. This is stated in exactly those words because the opposite was once written and believed: *a seam is not a sink* (**L-019**). D-057 records the user's decision that "log every refusal by default" carries volume and content questions belonging to its own slice |
| D14 | A 2FA-enabled account being unable to authorize an MCP client | A second-factor branch in the OAuth consent screen | **`TO BUILD`** | **NEW-38.** `core/mcp/oauth-authorize-view.php:91` has no second-factor branch; on the 2FA path the screen silently re-renders the login form with no error |
| D15 | Type and null-safety defects reaching production | Static analysis | **`TO BUILD`** | **T-03.** `phpstan.neon` is export-ignored in `.gitattributes` and **does not exist on disk** — a config referenced by another config, which reads as configured. This is exactly the trap the `[A]` marker in the code map exists to make visible |
| D16 | A release shipping without the documentation it instructs users to read | `.gitattributes` boundaries | **`TO BUILD`** | **NEW-27 / NEW-28 / H-02.** The blanket `*.md export-ignore` strips all 16 in-product guides — a live MCP surface — plus `README.md` and `INSTALL.md`, while `scripts/` is *not* export-ignored and ships dev scripts to the web root. Both are currently only **detectable** (keel-verify WARN), not fixed |
| D17 | Transport encryption for the admin session | TLS at the host | **`MANUAL`** | Shared-hosting product: Klytos emits HSTS but cannot obtain a certificate. Documented for the operator |
| D18 | Provider API keys (OpenAI / Anthropic / Gemini / x402) | Held by the operator, encrypted at rest by `AiKeyManager` | **`MANUAL`** (the operator supplies and rotates them) | Never in the repo, never in the handoff, never in `docs/` |
| D19 | The debug log leaking in production | A single user-facing switch, **OFF by default at release** | **`VERIFY`** | `docs/03-technical-plan.md` §8 records this as `as-built, unverified` — the mechanism exists (`Logger`, `klytos_log_*`, the `logs.php` viewer); the *default at release* has never been confirmed. Phase 7 gate closes it |
| D20 | A regression shipping because the suite never ran on the declared floor | CI on PHP 8.2 and 8.3 | **`VERIFY`** | **L-022:** the workflow has existed since Sprint 1 slice 9 and **has never executed** — every commit is unpushed. A workflow with no run checks nothing, the same way a hook with no listener writes nothing (D13). `/opt/local/bin/php82` is installed, so the 8.2 leg can now be rehearsed locally before it ever reaches CI |

## 3. Not defended — and what to do if it matters

**An omission that is written down is a decision; an omission that is silent is a trap.** Six months
on nobody can distinguish "we decided against it" from "we forgot", and the second reading is the
dangerous one. Every row here is a decision.

| Not defended | Consequence | If you need it |
|---|---|---|
| **Indirect prompt injection through content the model reads** | This is the sharpest one for an AI-first CMS, so it is first. A model driving Klytos reads page content, comments, imported sites and tool results. Attacker-authored text in any of those can be treated as instructions, and the model holds real authority — it can publish, delete, and change site config within its capabilities. The MCP capability gate (D2) bounds the *blast radius* to what that credential may do; it does not stop the model being persuaded | Keep untrusted content out of tool results, or label provenance and treat any action derived from untrusted content as unauthorized until re-confirmed by a human. Practically: narrow the token's capabilities for any session that will read imported or user-submitted content |
| **The client's own tool-selection logic** | Klytos cannot control which tool a model picks or with what arguments. The request is a request, never an authorization | Already the design: authorize per call, server-side (D2). Nothing further is possible from this side |
| **A compromised MCP client** | It holds the credentials it was given, and can use every capability they carry | Scope tokens narrowly per client, keep them short-lived, and revoke. Klytos supports per-credential roles (Sprint 2); using them narrowly is the operator's act |
| **Plugin code, once installed** | A plugin is PHP that Klytos executes in-process. It can reach anything the process can. Installation is `owner`-only (S-02) and integrity-verified where signed (D11), but an installed plugin is trusted | Do not install plugins you do not trust. Signed manifests and trust levels reduce the *supply* risk, never the *execution* risk |
| **Cost of the calls an MCP client makes** | No server-side quota per caller. An enthusiastic or scripted client discovers the AI provider's limit through the invoice. x402 is monetization, not metering | Server-side metering per caller, enforced before the work is done, not after |
| **What the MCP surface exposes about the site** | The tools return what the CMS holds. Anything sensitive already in a page, an option or a log is returned to a caller with the capability | Filter or redact at the tool boundary and keep sensitive data out of the CMS |
| **Volumetric denial of service** | The site goes down. A static generated site is unusually resilient here — the expensive surfaces are the admin and the MCP endpoint | A WAF or CDN in front. Nothing in-application is a real answer |
| **A determined user on their own browser** | They can read every asset, extract their own session and call any endpoint directly. Client-side validation is UX | Nothing client-side. Every call is authorized server-side (D1, D2) — attempting a client-side fix is the trap |
| **Account takeover through the operator's email provider** | Password reset is only as strong as the inbox behind it | Enforce the second factor (D7 supports it); the enforcement decision is the operator's |
| **Supply-chain provenance** | Dependencies are pinned, vendored and audited to zero (D8), but nothing is attested and there is no SBOM. Release archives are not signed | SBOM generation and artifact signing at the Phase 7 release path |
| **A malicious insider holding `owner`** | An `owner` can do anything by design — install plugins, run the terminal, read every key | Separation of duties is outside a single-tenant self-hosted CMS's model. Audit logging would at least record it, which is D13, still `TO BUILD` |
| **Data at rest beyond what Klytos encrypts** | `installer/config/` is encrypted with a per-install key; the *content* — pages, users, comments, logs — is not, and on flat-file storage it is plain JSON on the host's disk | Application-level encryption of specific fields with owned key rotation, or host-level disk encryption. Note the key itself lives beside the data, which is a property of self-hosted shared hosting, not an oversight |
| **The generated public site has no authentication of its own** | Static output is public by definition. There is no member area and no per-page gating beyond the x402 payment gate | If gated content is needed, it is a new feature, not a configuration |

## 4. How this file stays true

- Moving a row from §3 to §2 happens **when the control ships**, in the same slice, with its evidence
  and a `docs/decisions.md` entry. Moving one the other way is equally a decision.
- A control's delivery state changes in the slice that changes reality — never retrospectively, and
  never to make a report look better.
- Re-verified at every sprint close and at the Phase 7 gate.
- `scripts/keel-verify` should eventually assert that no `IN PLACE` row names a file or symbol that
  does not exist. Until it does, that check is a human duty, and saying so is more honest than
  implying the file is machine-checked.
