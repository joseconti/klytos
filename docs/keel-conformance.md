# Keel conformance sweep — Klytos CMS

> Required by Keel v5.0.0 ("Applying Keel completely", UNBREAKABLE). One row per applicable
> `MANIFEST.md` Table 1 requirement, plus every Table 3 action newer than the project's baseline.
> **Derived from the manifest, never from recollection.** A row with no state is an unfinished sweep.
>
> States: `present` (and where) · `missing` · `declined` (with the `docs/decisions.md` entry that
> records the refusal) · `n/a` (with the condition that excludes it).
>
> Applying a `missing` row remains the user's choice. *Not proposing* it is not a choice anyone makes.

- **Swept:** 2026-07-28
- **Baseline before this sweep:** v3.5.0 (itself reconciled the same day, D-066)
- **Running Keel:** v5.0.0
- **Project position:** Phase 4 IN PROGRESS (handoff gate open, DR-001 re-delivery pending audit) +
  Phase 5 IN PROGRESS (Sprint 6 close). Adopted project (D-001), released, with installs.
- **Conditions from the project card:** UI project = yes (since D-065) · runnable = yes ·
  `Assistant config: full` · `Client budget: no` · `User guide:` n/a until Phase 6 ·
  website intent = yes (Phase 8 deferred) · adopted = yes · Gemini CLI = yes (`.gemini/settings.json`
  `context.fileName`, no `GEMINI.md` mirror, D-011).

## Table 1 — required content

| # | Requirement | Required from | State | Where / why |
|---|---|---|---|---|
| 1 | `docs/PROGRESS.md` | Ph1 0a | **present** | repo root `docs/` |
| 2 | `docs/decisions.md` | Ph1 0a | **present** | D-001…D-066 |
| 3 | `docs/lessons-learned.md` | Ph1 0a | **present** | L-001…L-028 |
| 4 | `CLAUDE.md` + `AGENTS.md` lock | Ph1 0a | **present** | both refreshed to the v5.0.0 canonical block this session |
| 5 | Gemini lock mirror | Ph1 0a | **present** | `.gemini/settings.json` → `context.fileName: AGENTS.md` (D-011) |
| 6 | Embedded skill, both trees | Ph1 0a | **present** | `.claude/skills/keel/` + `.agents/skills/keel/`, both v5.0.0, `diff -rq` clean |
| 7 | `docs/00-competitive-landscape.md` | Ph1 0 | **present** | |
| 8 | `docs/01-discovery.md` | Ph1 | **present** | §9 `## Environment & test drivers` added 2026-07-28 (A2) |
| 9 | `docs/estimate.md` | Ph1 close | **present** | v3 |
| 10 | `docs/token-ledger.md` | Ph1 close | **present** | 26 sessions |
| 11 | `docs/02-functional-spec.md` | Ph2 | **present** | §4b acceptance criteria with `AC-01`…`AC-20` added (A10); the stale permissions matrix corrected |
| 12 | `docs/03-technical-plan.md` | Ph2 | **present** | code map marked `[E]`/`[A]`/`[G]` (A3), change map §2b (A4), driver table §4a and `## Environment requirements` §4b (A5) |
| 13 | `docs/threat-model.md` | Ph2 | **present** | created 2026-07-28 (A6) — 11 `IN PLACE`, 5 `TO BUILD`, 2 `MANUAL`, 2 `VERIFY`, 13 deliberate omissions |
| 14 | `docs/flows/` | Ph2 | **present** | 3 flows (slice 9) |
| 15 | `docs/budget.md` | Ph2 close | **n/a** | `Client budget: no` (D-013) |
| 16 | `docs/spec-references/` | Ph2 | **n/a** | the spec records no `## Reference artifacts` |
| 17 | `docs/rubrics/` | Ph2 | **present** | `api-ergonomics.md` (D-066) |
| 18 | `docs/design/references/` | Ph2 | **n/a** | no rich visual reference is held |
| 19 | Assistant rules, per tool | Ph2 close | **present** | `.claude/rules/`, `.cursor/rules/`, `.github/instructions/`, `.windsurf/rules/`, **plus Codex's three nested `AGENTS.md`** under `installer/core|admin|plugins` — the D-066 note claiming Codex had none was wrong and is corrected there |
| 20 | Assistant subagents, per tool | Ph2 close | **present** | `.claude/`, `.github/`, `.gemini/` (Cursor reads `.claude/agents/`, D-011). `test-driver` generated in all three (A11) |
| 21 | `docs/design/DESIGN-BRIEF.md` | Ph3 | **present** | reconstructed as-built 2026-07-28 and labelled as such — it is not the contract the delivery was measured against (that is `docs/BUILD-SPEC.md` §1). Written because more design work is coming, so the next handoff starts from a real brief |
| 22 | `docs/design/design-handoff/` | Ph4 start | **present** | holds the OLD delivery; the DR-001 re-delivery is not yet installed |
| 23 | `docs/BUILD-SPEC.md` | Ph4 | **present** | §1/§1b evidence tables filled; gate FAILED, awaiting re-audit |
| 24 | `docs/design/design-requests/` | Ph4 | **present** | DR-001 |
| 25 | `.gitignore` + `.gitattributes` | Ph5 scaffold | **present** | `.keel-update-check` ignored; `.gitattributes` under review (NEW-27/28/H-02) |
| 26 | `docs/sprints/` | Ph5 | **present** | sprint-1…sprint-6 |
| 27 | `docs/05-test-points.md` | Ph5 | **present** | `Criterion`/`Coverage` columns defined and in force from Sprint 7 (A10); sprints 1–6 deliberately not retrofitted |
| 28 | `docs/api/INDEX.md` | Ph5 first slice | **present** | 969 rows |
| 29 | `docs/keel-conformance.md` | always | **present** | this file, created by this sweep |
| 30 | `docs/playground.md` | Ph5 scaffold | **present** | |
| 31 | `scripts/keel-verify` | Ph5 scaffold | **present** | **17 checks** — 5 added 2026-07-28 (A7): `[E]` markers vs disk, internal links, cited commands, conformance gap, first-party suppression count; **1 added 2026-07-29 (D-074, L-030): every `#ks-*` the admin references resolves to a sprite `<symbol>`** |
| 32 | `scripts/keel-doctor` | Ph5 scaffold | **present** | written 2026-07-28 (A5), POSIX sh so it can report a missing PHP; `--check` green, 14 rows |
| 33 | build/minify script | Ph5 scaffold | **n/a** | Klytos minifies none of its own assets — the 90 `*.min.*` are third-party under `installer/admin/assets/vendor/` (measured) |
| 34 | `.githooks/pre-commit` | Ph5 scaffold | **present** | confidential-data gate (D-015) |
| 35 | Permission allow-lists, per tool | Ph5 scaffold | **present** | all four containers, extended 2026-07-28 with the doctor and driver commands (A11). `keel-doctor --fix` deliberately excluded |
| 36 | CI workflow | Ph5 scaffold | **present** | `.github/workflows/ci.yml` — never executed (L-022) |
| 37 | MCP registration | Ph5 scaffold | **n/a** | the plan defines no dev MCP servers; Klytos's own server is a product surface |
| 38 | `docs/architecture.md` | Ph6 | **missing** | Phase 6 not started — not yet due |
| 39 | `docs/api/`, `docs/usage/`, `docs/reference/` | Ph6 | **partial** | `api/` and `reference/` exist and grow per slice; `usage/` is Phase 6 — not yet due |
| 40 | `docs/security.md` | Ph6 | **missing** | Phase 6 — not yet due |
| 41 | `docs/accessibility.md` | Ph6 | **missing** | Phase 6 — not yet due |
| 42 | `README.md` | Ph6 | **present** | its per-module tool table is stale (recorded, D-017) |
| 43 | `guide/` + theme unit | Ph6 | **n/a for now** | `User guide:` is undecided until Phase 6 |
| 44 | `docs/07-release.md` | Ph7 | **missing** | Phase 7 — not yet due |
| 45 | `<site-docs>/` set | Ph8 | **missing** | website intent yes, Phase 8 deferred (D-012) — not yet due |
| 46 | `docs/issues.md` | first forge contact | **present** | |
| 47 | `docs/old/` | first sprint close | **present** | sprints 1–5 archived 2026-07-28 (moved, never deleted); sprint 6 is still open |
| 48 | `docs/04-adoption-audit.md` | adoption 5 | **present** | |

**Card lines:** all present — `Keel portability:`, `Assistant config:`, `Keel baseline:`,
`Client budget:`, `User guide:`, `Docs theme:`, `Models:`, plus the `Domain rubric:` line added by
D-066.

## Table 3 — actions newer than the baseline

Baseline before this sweep was v3.5.0, so only **v4.0.0** and **v5.0.0** apply.

| ID | Version | Action | State |
|---|---|---|---|
| A1 | v4.0.0 | Load `references/anti-patterns.md` for this project's type and run its self-audit once against the current tree, each answer evidenced by a command or an artifact | **present** |
| A2 | v5.0.0 | `docs/01-discovery.md` gains `## Environment & test drivers` (the §5a preflight) | **present** |
| A3 | v4.0.0 | Mark every code-map row in `docs/03-technical-plan.md` with `[E]`/`[A]`/`[G]` | **present** |
| A4 | v4.0.0 | Build the change map (§4b): one row per recurring change type, naming real paths and real commands | **present** |
| A5 | v5.0.0 | `## Environment requirements` in the technical plan, and `scripts/keel-doctor` generated from it | **present** |
| A6 | v4.0.0 | Create `docs/threat-model.md` with every control at its HONEST delivery state and a "Not defended" table | **present** |
| A7 | v4.0.0 + v5.0.0 | Extend `scripts/keel-verify`: cited commands exist, internal doc links resolve, no orphan documents, `[E]` markers match disk, code fences balanced, suppression count reported — plus the v5.0.0 check that fails on a `missing` conformance row with no decision | **present** |
| A8 | v4.0.0 | Offer the Phase 1 competitive confrontation (§3a) once | **declined 2026-07-28 — D-068** | Offered and refused on the merits: comparing Klytos feature-by-feature against the CMS field is the wrong exercise for a product whose differentiator is an axis most of that field does not have. The action Table 3 names is *offer*, and the offer was made and answered — so this row is closed, not pending. Re-offered only if the user asks or the condition changes |
| A9 | v4.0.0 | Every stated duration is AI development time, named as such | **present in behaviour** — governs from this session; `docs/estimate.md` is already AI-time based |
| A10 | v5.0.0 | `AC-nn` IDs on acceptance criteria + `Criterion`/`Coverage` columns in `docs/05-test-points.md` | **present** |
| A11 | v5.0.0 | `test-driver` subagent in every capable container; permission allow-lists extended to the playground, drivers, sniffers, `keel-verify`, `keel-doctor --check`/`--plan` (never `--fix`) | **present** |
| A12 | v5.0.0 | Phase 4 stops being the manual phase: `SPEC/external-setup.md` triaged into drivable vs guided, fonts fetched by script, drivers stood up at the first rendered screen, fidelity verified from rendered output at the declared breakpoints | **missing** — lands with the DR-001 re-audit |
| A13 | v5.0.0 | Convert "the user will check it" into driven tests; sprint closes hand back verified evidence plus a short tagged list; the keyboard/focus-order pass moves to the driven list; assistive-tech batches per flow | **present as a standing rule** — recorded in `docs/05-test-points.md` (the coverage columns and the rule above them), the `test-driver` agent, and `docs/03-technical-plan.md` §4a. No retroactive conversion: it binds from the next slice |
| A14 | v4.0.0 | `docs/lessons-learned.md` template gains symptom/cause/fix and a "Check added" field (new entries only, no rewriting) | **present** |
| A15 | v5.0.0 | Maintenance and adoption join the contract: every maintenance session opens with `keel-doctor --check`; the adoption audit gains a Testability row | **present** — `docs/playground.md` Step 0 (with the PATH trap written out), and audit row **T-05** |
| A16 | v5.0.0 | Product screenshots for the guide captured by the assistant from the playground | **n/a for now** — Phase 6, `guide/` undecided |

## Result — after applying the batch the user approved on 2026-07-28

- **present:** 40 · **n/a:** 9 · **missing:** 6 · **declined:** 1
- The seven remaining `missing` rows are **six that are legitimately ahead of the project's position**
  — `docs/architecture.md`, `docs/security.md`, `docs/accessibility.md` (Phase 6), `docs/07-release.md`
  (Phase 7), and the `<site-docs>/` set (Phase 8, deferred by D-012). **A8 is declined, not missing**
  (D-068) — the distinction is the whole point of this file: a declined row is a decision someone
  made, a missing row is a gap nobody has looked at.
- **Not yet due is deliberately recorded as `missing`, not `n/a`.** The phase has not been reached,
  so the row is not excluded by a condition — it is simply ahead. Marking it `n/a` would quietly
  convert "we have not got there" into "it does not apply to us", which is the exact substitution
  this sweep exists to prevent.
- One row is `declined` with its decision entry (**A8**, D-068). Every other row the user was offered was taken.

## Standing rule

`scripts/keel-verify` must fail on a `missing` row with no decision (v5.0.0). It does not yet — that
is A7, and until A7 lands this file is the only thing enforcing the sweep.

---

# Anti-patterns self-audit (Keel v4.0.0 — `references/anti-patterns.md`)

> Run 2026-07-28. **Every answer below comes from a command or an artifact.** An answer given from
> recollection is not an answer — it is trap 7 ("verification claimed but not run"). Questions 16–18
> are WordPress-only and do not apply; question 21 applies in its dev-dependency sense.
>
> Re-run at every sprint close, at the Phase 7 gate, and whenever a shortcut is tempting.

| # | Question | Answer | Evidence |
|---|---|---|---|
| 1 | Does every tool declared in the technical plan actually run in a test-point command, and is it blocking? | **NO** | `phpstan.neon` is declared (export-ignored in `.gitattributes`) and does **not exist on disk** — T-03, now `[A]` in the code map and `TO BUILD` as D15 in the threat model. `phpcs`, `phpunit`, `composer audit` and `keel-verify` all run and are blocking |
| 2 | Does every command cited in `CLAUDE.md`, `AGENTS.md`, the README and `docs/` exist? | **YES** | 9/9 checked present: `scripts/keel-verify`, `scripts/keel-doctor`, `scripts/dev/seed-playground.php`, `scripts/dev/router.php`, `scripts/dev/upgrade-test.sh`, `vendor/bin/phpunit`, `vendor/bin/phpcs`, `phpcs.xml`, `.github/workflows/ci.yml` |
| 3 | Does every Version touchpoint carry the same value? | **NO — four-way disagreement, unchanged** | `installer/VERSION` = `0.31.1-beta.1` · `README.md:7` and `:523` = `0.28.5` · `changelog.txt` = `0.4.0` · git tags exist up to `v0.30.1`. Already recorded as audit H-01; Phase 7 owns it |
| 4 | Does every code-map path carry its marker, and does every `[E]` exist on disk? | **YES, as of this session** | Markers added 2026-07-28 (A3). Every `[E]` confirmed by an existence check; `installer/public/`, `config/`, `data/`, `backups/` correctly demoted to `[G]`; `phpstan.neon`, `scripts/keel-doctor` (now built), `tests/E2E/` marked `[A]` |
| 5 | Does every documented extension point have a test asserting it fires with its documented arguments? | **NO** | Not measured exhaustively, and saying so is the honest answer rather than guessing a number. What IS known: `mcp.access_denied` and `auth.access_denied` fire and **nothing subscribes** (NEW-32, threat model D13) — the L-019 case. A full extension-point coverage measurement is owed |
| 6 | Does every generated artifact have a consumer, or is the generator gone? | **YES** | `installer/public/` (5 entries) is consumed by the web server; produced by `cli.php build`. `.pot`-equivalent: the 20 JSON catalogues are hand-maintained with a parity check in `keel-verify`, not generated |
| 7 | Does every row in `docs/05-test-points.md` carry its command and its output? | **PARTIAL** | 137 table rows exist with an Evidence column in the schema. Not every historical row carries a pasted command+output; from v5.0.0 on, the `Criterion`/`Coverage` columns make the gap countable instead of impressionistic |
| 8 | Has the suppression count grown since the last sprint close? | **NO — and the first measurement of it was wrong** | A naive grep over `installer/` returned **119** and was read as this project's debt. **All 119 are in `installer/vendor-ai/`** — third-party vendored code Klytos neither authors nor may edit. **Klytos's own first-party count is 0**, now the recorded baseline and `keel-verify` check 15. The two measurements disagreeing is what surfaced it (**L-023**); counting vendored suppressions would have set a baseline no Klytos change could ever move, which trains everyone to ignore the number |
| 9 | Is every deliberate omission recorded? | **YES, as of this session** | `docs/threat-model.md` §3 records 13 deliberate omissions with their consequence and their remedy. Before today they were unwritten — which is precisely the trap |
| 10 | Is every control described in the present tense actually `IN PLACE` with evidence? | **YES, as of this session** | The threat model was written under that rule: 11 controls `IN PLACE` with named evidence, 5 `TO BUILD`, 2 `MANUAL`, 2 `VERIFY`. The previously-believed claim that refusals "go to the audit log" is corrected to D13 `TO BUILD` |
| 11 | Exactly one authoritative file per artifact, and does every `*.min.*` match a fresh build of its source? | **YES / NOT APPLICABLE** | All **90** `*.min.*` files are under `installer/admin/assets/vendor/` — TinyMCE, xterm, highlight.js, Gutenberg — third-party distributions shipped as published. **Klytos minifies none of its own assets**, so the source-first contract has nothing to govern here. The technical plan and the change map both said "no minified pair exists"; that was imprecise and is corrected |
| 12 | Is every document reachable from an index, and does every internal link resolve? | **NO — 10 broken links, all in the public README** | `README.md` links to `docs/KLYTOS-ARCHITECTURE-V2.md`, `KLYTOS-HOOKS-API.md`, `KLYTOS-TEMPLATE-SYSTEM.md`, `PLUGINS-ARCHITECTURE.md`, `PLUGINS-ADMIN-UI.md`, `TEMPLATES-ARCHITECTURE.md`, `TERMINAL-ARCHITECTURE.md`, `DEVELOPER-MODE.md`, `consent-manager-spec.md`, `KLYTOS-X402.md` — **none of the ten exists.** This is the repository's front door, in a public repo. New finding; belongs with the D-017 README pass |
| 13 | Does every user-visible acceptance criterion have a driven test with evidence, or a delegation tag with steps? | **NO** | `docs/02-functional-spec.md` carries **no `AC-nn` IDs**, so the question cannot even be answered mechanically today. That is A10, applied below |
| 14 | Does `scripts/keel-doctor --check` pass, so a green suite means the suite ran? | **YES** | Built today and run: 14 rows, all blocking requirements satisfied, exit 0. PHP 8.3.12, suite **284 tests / 1385 assertions OK** |
| 15 | Does every applicable `MANIFEST.md` Table 1 row carry a state? | **YES** | The table above: 48 rows, 27 present / 9 n/a / 20 missing / 0 declined, every `n/a` quoting its condition |
| 19 | (MCP) Has every ability been called through a real client with its documented arguments this release? | **NO** | 180 tools are served; the integration tier drives `tools/list` and a subset of `tools/call`. A full per-tool exercise has never been run. Related: **NEW-35** — schemas are advertised and never enforced, so "documented arguments" is not even a checked property today |
| 20 | (Web) Is every protected surface refused on a direct server request, with JavaScript disabled? | **YES — driven, not reasoned** | Playground on port 8137 (bind verified, `X-Powered-By: PHP/8.3.12` and no `Server:` header — the L-011 tell that it is our own server). `curl` executes no JavaScript by definition. `admin/` → **302** to `login.php`, `users.php` → **302**, `plugins.php` → **302**, `security.php` → **302**, `api/plugins.php` → **401**, `config/config.json.enc` → **403** (the router replicates the `.htaccess` denies). **One probe was wrong and is reported as such rather than as a result:** `index.php?klytos_mcp=1` returned 404 because that is not the MCP endpoint's real address — the MCP surface was not exercised in this pass |
| 21 | (Library) Is every dependency in the manifest backed by a decision entry? | **YES** | Root `composer.json` is `require-dev` only (D-022, D-027); the vendored AI stack is reconstructed and pinned by D-028 and re-vendored by D-052, with `composer audit -d installer` at zero |

## What this audit produced that was not already known

Three findings are new, and each is a normal work item rather than an emergency:

1. **10 broken links in `README.md`** — the public front door points at ten documents that do not
   exist. Belongs with the D-017 README editorial pass, which already owns the stale tool table.
2. **The suppression count is 0, not 119.** Never counted before; the first count was wrong in the reassuring-sounding direction (it made the project look like it had debt it does not have, which is the harmless-feeling error that still corrupts a baseline). Now `keel-verify` check 15, baseline 0.
3. **The "no minified assets" belief was imprecise** — there are 90, all third-party. Corrected in
   the technical plan and the change map rather than left as a comfortable half-truth.

And one non-finding worth stating, because its absence would otherwise read as an oversight:
**every `S-01`…`S-13` security finding from the adoption audit is CLOSED**, verified by reading the
audit entries rather than by trusting the summary line in `docs/PROGRESS.md`, which still carries
advice about not filing them as public issues.
