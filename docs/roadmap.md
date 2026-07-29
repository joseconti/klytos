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
