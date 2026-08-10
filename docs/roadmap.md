# Roadmap to v1 — Klytos CMS

> Recorded 2026-07-27 by **D-062**, on the user's explicit approval of this order.
>
> This document orders work that already exists elsewhere. It invents no scope: every item points at
> `docs/04-adoption-audit.md`, `docs/PROGRESS.md` or a recorded decision. It carries **no dates** —
> this project has no delivery commitment, and inventing one would be fiction.
>
> **It exists because the inventory was always recoverable and the ORDER never was.** Every session
> re-derived the sequence from the audit and proposed it again, which is a real recurring cost in a
> project whose state re-read is already its dominant per-session expense.
>
> The user changes this order by saying so; a change is a new decision entry, not an edit in passing.

## The order

| # | Step | Why here | Closes / delivers |
|---|------|----------|-------------------|
| 0 | **Phase 4 — the admin redesign build** | In progress; the gate passed and the contract is written (`docs/BUILD-SPEC.md` §5). Runs ahead of everything below because it rewrites the surfaces the later steps would otherwise harden twice | 40 of the 44 manifest entries |
| 0b | **The four deferred entries** (§0b below) | Split out of Phase 4 by **D-072** because each is product scope, not fidelity | entries **11 Verify** (+ NEW-38), **14 Comments**, **17 Setup wizard**, **22 Health** |
| 1 | **The `.gitattributes` review** | One line of work that unblocks a shipped feature and a promise already made to a user | **NEW-27**, **NEW-28**, **H-02** |
| 2 | **Finish the hardening** | The audit's remaining live findings, most already scoped with triggers | NEW-17, NEW-32, NEW-35, NEW-38, NEW-42, NEW-13 and the LOW tail |
| 3 | **Accessibility** | The largest measured gap against a target the project has already committed to (D-007), with legal exposure that lands on Klytos's users | **A-01…A-07** |
| 4 | **The theme package** | The mechanism that makes the generated output's accessibility fixable at the system level rather than site by site | **D-023**, **D-024**, F-01, D-06, NEW-04, NEW-23, the comment form |
| 5 | **Phase 6 — documentation** | Consolidation, once the surfaces have stopped moving | D-01…D-06, the end-user guide, the bilingual in-product guides |
| 6 | **Phase 7 — release** | The full release gate, which closes the hygiene bucket by construction | H-01, H-03, H-05, H-07, T-03, T-04, the D-038 asset contract, D-027's PHP floor |
| 7 | **Phase 8 — the site** | Deferred by **D-012** until requested | klytos.io |

## 0b. The four entries Phase 4 does not build (D-072)

Deferred with reasons, not dropped. They remain manifest entries and remain in `BUILD-SPEC.md` §5.1;
the redesign is **not reportable as complete** while they are outstanding.

| Entry | Slice shape | Why it is not Phase 4 work |
|---|---|---|
| **17 Setup wizard** | Restructure `installer/install.php` from three steps to the manifest's seven | Product scope, not fidelity. **NEW-04 makes `install.php` destructive in a checkout**, so the slice must design a non-destructive way to drive it (a disposable copy) before it writes a line. `admin/setup-wizard.php` is a different screen the design says nothing about and keeps its current UI. Its deferral also means **`template-wizard.md` is not built in Phase 4** — entry 17 is that template's only consumer |
| **11 Verify** | Split the pending-2FA branch out of `login.php` into its own screen — **paired with NEW-38** | Touches the authentication flow Sprint 5 closed (D-056…D-058). Pairing it with the OAuth consent screen's 2FA gap stops one slice hardening a path another is rebuilding |
| **14 Comments** | A new admin screen over the existing `core/comment-manager.php` | No admin screen exists at all. **L-014** is this project's recorded history of what "the comment feature" actually costs — the manager working is not the same as the feature working end to end |
| **22 Health** | A data source first, then the screen | No backing surface exists in the tree. A screen built before its source would be a design with nothing behind it |

## 0c. The stage-5 cards Phase 4 does not build (D-088)

Same rule as §0b and the same reason: these are **new product surfaces the design draws inside an
existing screen**, not redesigns of anything shipped. They stay in `BUILD-SPEC.md` §5.1 and the
redesign is **not reportable as complete** while they stand.

| Entry | Card | Why it is not Phase 4 work |
|---|---|---|
| **3 Design** | **Preview** | The manifest names the card and no file says what it previews or in what form. Specifying it is Design's, building it is product |
| **6 Security** | **Content-Security-Policy** | Klytos sends a CSP; it has no editor for one. A textarea with a validate action over a live security header is a slice with its own failure modes |
| **6 Security** | **Integrity score** | The data lives on entry 34 (System integrity). A score summarised onto Security needs a source that summarises, which does not exist |
| **37 x402 settings** | **Pricing rules** (repeatable) · **the 402 response body** | The product has one default price and no response-body editor. Both are features |
| **39 Post type** | **Exposure** (REST · MCP · sitemap · feeds) | Per-post-type exposure switches do not exist. They change what the outside world can read, so they are a slice with an authorization review, not a card |
| **9 Settings** | **URLs** · **Media** sections | Two of the seven designed sections have no shipped settings at all. They are omitted from the nav until they have content — one line each to restore (D-088 answer 3) |
| **19 Content model** | **Statuses** (editable set) | There is no global, editable status set. The four system statuses are class CONSTANTS (`PostTypeManager::SYSTEM_STATUS_DEFS`) and every custom status belongs to ONE post type (`addStatus( $postTypeId, … )`). Entry 39 names the same card at the level where it IS backed and `post-type-edit.php` already manages it there. A global set is a new product surface (D-089) |
| **19 Content model** | the **"and orders"** delta | Nothing in this product orders post types or taxonomies: `position` exists on CUSTOM FIELDS alone, and the only reorder surfaces are `reorderCustomFields()` and `reorderStatuses()`. Ordering is a manager change with its own storage and MCP consequences, not a card (D-089) |
| **25 Consent** | **Acceptance stats** (stat row) | The product stores no acceptance data of any kind. Klytos publishes a STATIC site; the visitor's choice is written to a cookie in their own browser; there is no endpoint that receives it, no collection that stores it and nothing that aggregates it. The prototype draws "Accepted everything 62% · Essential only 31% · Ignored the banner 7%", which is visitor telemetry — a whole collection surface, and one with its own privacy question, since counting consent choices is itself processing (D-092) |

Review trigger, for all of them: **the close of the Phase 4 build**, exactly as §0b.

## 0d. Product ideas raised in conversation, parked rather than built

Not design gaps and not deferrals of drawn cards: things the user asked for that are **new product**,
recorded at the moment they were raised so they are not lost and not smuggled into a fidelity stage.

### A third editor: raw HTML (raised 2026-08-10)

Today a post type chooses between exactly two editors, and the choice is real rather than decorative:
`PostTypeManager` stores `editor` (default `gutenberg`), both entry 19 and entry 39 validate against
`[ 'gutenberg', 'tinymce' ]`, and `page-editor.php:657` branches on it to mount TinyMCE from
`assets/vendor/tinymce/tinymce.min.js`. Adding `html` is therefore a small change in four places —
the manager's allow-list, the two screens' validation, the catalogues, and the editor's branch.

**What makes it a slice and not a one-liner is the one question it cannot answer for itself: what
happens to `klytos_kses_post()`.** Page content is filtered through the KSES tag map on the way out,
and that map exists precisely to stop stored content becoming script on the public site. An editor
advertised as "100% HTML" is one of two different products depending on the answer:

- **Filtered** — the author writes raw HTML and KSES still governs what survives. Safe, consistent
  with every other content path, and it is *not* 100% HTML: `<script>`, `<style>`, `<iframe>` and
  event-handler attributes are dropped, which is exactly the freedom the idea is asking for.
- **Unfiltered** — genuinely raw. That is stored XSS by design, so it cannot be available to every
  role that can currently edit a page: it needs its own capability (owner, plausibly owner-only),
  and it interacts with the CSP Klytos already sends per request with a nonce — inline script pasted
  by an author has no nonce and would be blocked anyway, which is worth knowing before promising it.

There is a third shape worth putting on the table when this is planned: filtered by default with an
explicit, per-page, capability-gated "allow unfiltered HTML" that is **recorded** like §10.7's
contrast override — who allowed it and when — rather than a global switch nobody can audit later.

Not started. It is product scope, it was raised during a Phase 4 fidelity stage, and the three
preceding slices each refused exactly this kind of widening (D-088, D-090, D-091). Review trigger:
**the close of the Phase 4 build**, or sooner on the user's word — the answer to the KSES question is
theirs and nothing else in the slice can be decided without it.

## 1. The `.gitattributes` review — first, and it is one line

`.gitattributes` carries a blanket `*.md export-ignore`. Consequences, all measured:

- **NEW-27 (HIGH).** All **16** in-product guides under `installer/core/guides/` are stripped from
  every release archive, so the directory ships **empty**. `klytos_list_guides` and
  `klytos_get_guide` read it at runtime, and five of those guides are declared **REQUIRED** by the
  MCP tools' own descriptions before creating page content. Verified to reach real installs, not just
  `git archive`: v0.30.1 has no attached assets, so the updater falls back to GitHub's zipball, which
  honours `export-ignore`.
- **H-02.** `README.md` and `INSTALL.md` are stripped too, although `INSTALL.md` instructs users to
  upload them.
- **NEW-28.** `scripts/` is not export-ignored, so dev scripts ship to the web root. The SAPI guards
  are already in place; the packaging half is not.

`scripts/keel-verify` already reports NEW-27 as a standing WARN with full evidence on every run, so
the cost of leaving it is visible every time anyone runs the linter.

**What it unblocks:** the user's 2026-07-25 instruction that the 16 guides ship in **English and
Spanish**. Translating before this lands would duplicate content nobody receives. Three things must
be settled by whoever plans that work and are recorded in `docs/PROGRESS.md`: the loader takes no
locale (so this is new behaviour on an MCP surface, not a translation chore), NEW-27 comes first, and
content parity has to be mechanically checkable or the two sets drift — the locale-catalogue parity
check in `keel-verify` is the obvious model.

## 2. Finish the hardening

What remains open in the audit after Sprint 6, with the triggers each entry already carries:

- **MEDIUM:** **NEW-17** (trusted-proxy configuration — `getClientIp()` both collapses every visitor
  behind a proxy into one bucket AND takes the first client-supplied `X-Forwarded-For` entry with no
  trusted-hop allow-list; it also owns **NEW-46** and **NEW-49**), **NEW-32** (the authorization audit
  hooks reach no sink, so refusals write nothing), **NEW-38** (the OAuth consent screen cannot
  complete a 2FA login), **NEW-18** (the global `__()` exists only under `admin/`), **NEW-13** (the
  identity export has no re-auth, no 2FA and no owner notification), **NEW-35** (MCP tool input
  schemas are published and never enforced), **NEW-24** (the two public entry points send no security
  headers), **S-13** (MCP model-facing threats, still `unverified`).
- **LOW:** NEW-04, NEW-07, NEW-15, NEW-19, NEW-21, NEW-22, NEW-23, NEW-25, NEW-29, NEW-33, NEW-34,
  NEW-43, NEW-45, NEW-48, and **S-10** (349 inline `style=` attributes — its own sprint).

**Sequencing note that is not obvious:** NEW-17 should come early in this step, because three later
findings (NEW-46, NEW-49 and the availability half of NEW-17 itself) cannot be reasoned about
honestly until the client address is trustworthy.

## 3. Accessibility

The target is recorded in **D-007**: WCAG 2.2 AA as the floor, AAA where feasible, plus EN 301 549 and
the European Accessibility Act. The measured baseline at adoption was **~20–25 %** for the admin panel
and **~15 %** for the HTML Klytos generates.

The findings are A-01 (no skip links), A-02 (no `prefers-reduced-motion`), A-03 (focus indication
effectively absent), A-04 (landmarks unlabelled, token ARIA), **A-05** (zero `aria-`/`role=` in the
generated output), A-06 (hardcoded `lang="en"` in generated documents) and A-07 (the shipped
accessibility skill asserts compliance the code does not have — the L-002 defect, in a document users
read as a guarantee).

**A-05 is the highest-stakes item in the whole audit after the authorization axis**, because Klytos's
users inherit the markup and under the EAA the liability is theirs. Its system-level fix belongs to
step 4, which is why these two steps are adjacent and in this order: the admin half does not wait for
the theme package, and the generated-output half is completed by it.

Verification is split honestly, per Keel: automated passes are run and recorded; the real
assistive-technology pass is a guided user loop, one instruction at a time, and is never declared
done by the assistant alone.

## 4. The theme package

**D-023** and **D-024**, designed in full (`docs/theme-package-model.md`) and **not implemented**. It
is the largest un-estimated item in the project and it runs through Keel Phases 3–4 — the first
redesign in this project to do so, which is also the trigger for the deferred
`design-fidelity-auditor`.

It carries, by construction rather than by choice: **F-01** (the canonical parts API has no
propagation path while the superseded one does), **D-06** (every shipped skill still teaches the
superseded API), **NEW-04** (`build` writes into the repository root), **NEW-23** (the public site's
CSP cannot use nonces while its HTML is pre-generated), the **comment form** that no template has ever
emitted (L-014), and the accessibility contract that makes **A-05** fixable once rather than per site.

## 5. Phase 6 — documentation

Per-surface docs have been backfilled progressively since Sprint 1, so this phase **consolidates**
rather than writes from zero. What is genuinely outstanding: **D-03** (no end-user guide — `guide/`,
created bilingual when this phase runs, plus the docs theme), **D-05** (the README's per-module tool
table is stale: 34 rows summing to 177 against the measured 206 served), **D-02** (docs describing
intent the code does not implement — each instance is tracked), **D-01**'s tail, and **D-06** with
step 4.

## 6. Phase 7 — release

The full Phase 7 gate, which closes the hygiene bucket by construction: **H-01** (four-way version
drift — `keel-verify` reports it as a standing WARN today), **H-03** (`changelog.txt` abandoned since
0.4.0), **H-05** (33 first-party files with no licence header), **H-07** (no release automation),
**T-03**/**T-04**, the **D-038** build-assets contract at its recorded trigger, and **D-027**'s
question about whether the PHP floor rises to 8.2.

**One thing that must be treated as an unverified test point, not a formality (L-022): CI has never
run.** The workflow was written in Sprint 1 slice 9 and every commit since is unpushed by standing
instruction, so its first execution is a check nobody has performed.

## 7. Phase 8 — the project website

Deferred by **D-012** with website intent recorded as **yes** (klytos.io). It runs on request,
normally after a release.

## Standing items that are not steps

- **The state-file split.** `docs/decisions.md` is ~65k tokens and is re-read in full every session;
  it is the project's dominant per-session cost and it grows every time. The Sprint 5 close recorded
  that the next close must treat splitting the state files as a real decision rather than a fifth
  "nothing qualified". It is owed at the **Sprint 6 close**.
- **The bus factor.** `docs/00-competitive-landscape.md` records it plainly: the biggest risk to this
  project is not competition but distribution and a bus factor of 1. Nothing in this roadmap addresses
  it, and saying so is more useful than pretending otherwise.
