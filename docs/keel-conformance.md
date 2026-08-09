# Keel conformance sweep — Klytos CMS

> Required by Keel v5.0.0 ("Applying Keel completely", UNBREAKABLE). One row per applicable
> `MANIFEST.md` Table 1 requirement, plus every Table 3 action newer than the project's baseline.
>
> **Derived from the manifest and from the disk, NEVER from the previous sweep.** That rule is the
> reason this file is rewritten rather than amended: a sweep that cites the last sweep is
> self-confirming — a row that was wrong reproduces itself wearing the clothes of evidence, and the
> artifact that decides whether Keel is applied gets more *confident* with every run instead of more
> correct. The three authorised sources are the disk, `MANIFEST.md`, and `docs/decisions.md`.
>
> States: `present` (and where) · **missing** · `declined` (with the `docs/decisions.md` entry that
> records the refusal) · `n/a` (with the condition that excludes it). A row with no state is an
> unfinished sweep.
>
> Applying a `missing` row remains the user's choice. *Not proposing* it is not a choice anyone makes.

- **Swept:** 2026-08-09 (**D-087**) — the first sweep run against Keel **v5.13.0**, and the second
  half of the two items D-081 and D-082 both deferred.
- **Against:** `MANIFEST.md` v5.13.0, Table 1 walked row by row in its own order.
- **Project position:** Phase 4 Step 4 (stage 4 of 6) with Phase 5 in progress — so **every row
  required at or before Phase 5 is in scope**, and Phase 6/7/8 rows are ahead of the project rather
  than absent from it. That distinction is the difference between a backlog and a gap, and it is
  written per row rather than left to the reader.
- **Method, stated so it can be checked:** every `present` was verified by looking at the path on
  disk in this session, not by recalling that it was created; every `n/a` names the condition in
  `MANIFEST.md` that excludes it; every `declined` names its decision entry.

## What this sweep found that the last one did not

Four rows changed state, and **three of them were stale records rather than absent work** — which is
exactly the failure mode a sweep derived from the previous sweep would have reproduced:

1. **`Keel portability:` said v5.12.0 while both embedded trees are v5.13.0.** The trees were
   correct; the card line was three sessions behind. Found by reading `metadata.version` out of
   `.claude/skills/keel/SKILL.md` and `.agents/skills/keel/SKILL.md` instead of reading the line that
   claims to describe them. **Fixed in this change.**
2. **`docs/issues.md` had no `Last inbound sweep:` header** (Keel v5.8.0). Without it, "no news" and
   "nobody looked" are the same picture. **Fixed in this change**, with a real `gh issue list` run
   behind it: the inbox is genuinely empty.
3. **`docs/01-discovery.md` §9 had neither the environment-incapacity line nor the `claude`-on-PATH
   verdict** — the second being required precisely because the card says `Chaining: start`.
   **Fixed in this change**, both from live measurements (`scripts/keel-chain-check` row B1).
4. **`Red first` landed in `docs/05-test-points.md`** with its three checks (D-086, the item
   immediately before this one).

## Table 1 — required content

| # | Requirement (MANIFEST Table 1) | Required from | State | Where / why |
|---|---|---|---|---|
| 1 | `docs/PROGRESS.md` | Phase 1 step 0a | `present` | Project card, phase table, current position, open items, deferred items |
| 2 | `docs/decisions.md` | Phase 1 step 0a | `present` | D-001…D-087, append-only |
| 3 | `docs/lessons-learned.md` | Phase 1 step 0a | `present` | L-001…L-036 |
| 4 | Off-machine durability (remote or replicating tree) | Phase 1 step 0a | `present` | `origin` = `https://github.com/joseconti/klytos.git`, `develop` and `main` both tracked; card line `Durability: covered`. Re-checked with `git remote -v` this session, as the rule requires every session to do |
| 5 | A clean working tree at every block close | Phase 1 step 0a | `present` | Every block in this session committed to `develop` and pushed. The project's one lapse is on the record rather than hidden: stages 3 and 4A sat uncommitted in one working tree through two whole sessions and were committed in **D-083** after re-measuring every claim first |
| 6 | `CLAUDE.md` + `AGENTS.md` portability lock, stamped | Phase 1 step 0a | `present` | Both carry `KEEL:BEGIN — v5.13.0`, byte-identical to each other; verified by grep this session, which is the stamp-only check the maintenance block prescribes |
| 7 | Gemini lock mirror | Phase 1 step 0a | `present` | `.gemini/settings.json` → `context.fileName: ["AGENTS.md"]`, the recorded pick (D-010). **No `GEMINI.md` mirror exists, deliberately** — pointing Gemini at `AGENTS.md` is one lock, not two that can drift |
| 8 | `.claude/skills/keel/` + `.agents/skills/keel/`, version-synced | Phase 1 step 0a | `present` | Both at **v5.13.0**, read from each tree's own frontmatter. **The card line said v5.12.0 and was wrong** — see finding 1 above |
| 9 | `docs/00-competitive-landscape.md` | Phase 1 step 0 | `present` | |
| 10 | `docs/01-discovery.md`, including the §5a environment preflight | Phase 1 | `present` | §9 carries the machine, the can-this-session-run-commands verdict, the screen-stealing verdict, the per-tag list of what cannot be driven — and, **since this sweep**, the environment-incapacity line and the `claude`-on-PATH verdict (finding 3) |
| 11 | `docs/estimate.md` | Phase 1 close | `present` | v3 |
| 12 | `docs/token-ledger.md` | Phase 1 close | `present` | One row per session, 36 rows |
| 13 | `docs/02-functional-spec.md` with stable `AC-nn` IDs | Phase 2 | `present` | |
| 14 | `docs/03-technical-plan.md` incl. `## Environment requirements` | Phase 2 | `present` | §4b is that section — the source `scripts/keel-doctor` compiles from; §4a is the driver-per-surface table |
| 15 | `docs/threat-model.md` | Phase 2 | `present` | Controls with delivery states + the "Not defended" table |
| 16 | `docs/flows/` | Phase 2 | `present` | Three flows (D-045's slice); a full retroactive set was rejected on the record, not forgotten |
| 17 | `docs/budget.md` | Phase 2 close | `n/a` | `Client budget: no` (D-013) — own project, nothing to quote |
| 18 | `docs/spec-references/` | Phase 2 | `n/a` | The spec records no `## Reference artifacts` (grepped this session, not recalled) |
| 19 | `docs/rubrics/` | Phase 2 | `present` | `api-ergonomics.md`, applied by `code-reviewer` check 8 (D-066) |
| 20 | `docs/design/references/` | Phase 2 | `n/a` | The user holds no separate visual references; the prototypes are inside Design's own delivery, where the contract puts them |
| 21 | Assistant rules — one container per accepted tool | Phase 2 close | `present` | `.claude/rules/`, `.cursor/rules/`, `.github/instructions/`, `.windsurf/rules/`, `.codex/rules/` (D-010, `Assistant config: full`) |
| 22 | Assistant subagents — per capable tool | Phase 2 close | `present` | `.claude/agents/`, `.github/agents/`, `.gemini/agents/`. **`.cursor/agents/` is `n/a`, not missing** — D-011 records that Cursor reads `.claude/agents/` natively |
| 23 | `docs/design/DESIGN-BRIEF.md` | Phase 3 | `present` | |
| 24 | `docs/design/design-handoff/` | Phase 4 start | `present` | 124 files, the DR-003 re-delivery, installed as a wholesale swap per contract rule 10 |
| 25 | `docs/BUILD-SPEC.md` | Phase 4 | `present` | §1 is the evidence table; §5 is the build contract |
| 26 | `docs/design/design-requests/` | first DR | `present` | DR-001…DR-007 |
| 27 | `.gitignore` + `.gitattributes` with the unconditional entries | Phase 5 scaffold | `present` | All seven verified by grep this session: `.keel-update-check`, `CLAUDE.local.md`, `AGENTS.override.md`, `.claude/settings.local.json`, `.gemini/.env`, `.gemini/tmp/`, `docs/continuation-prompt.md` |
| 28 | `docs/sprints/` | Phase 5 | `present` | Sprints 1–6; 1–5 archived to `docs/old/` |
| 29 | `docs/05-test-points.md` — the `Red first` column | Phase 5 | `present` | **Landed 2026-08-09 (D-086)**, in both test-point tables, with the three v5.11.0 checks enforcing it in `scripts/keel-verify` |
| 30 | `docs/05-test-points.md` — the `Criterion` and `Coverage` columns | Phase 5 | **missing** | **No table carries either.** The file's own legend defines both and states in writing that they are in force "from the next sprint table on", because back-filling a `Coverage` value nobody recorded would be inventing evidence. That is a deliberate, written deferral — but it is a deferral, and calling it `present` would be the exact defect this sweep exists to catch. **The user's call: leave deferred, or apply it to the next sprint table when one is written.** |
| 31 | `docs/api/INDEX.md` | Phase 5 first slice | `present` | 986 rows, Summary and data rows agreeing (checked by `keel-verify` checks 3 and 4) |
| 32 | `docs/keel-conformance.md` | Phase 1 step 0a + every reconciliation | `present` | This file |
| 33 | `docs/playground.md` | Phase 5 scaffold | `present` | Access, try-it, seed/reset, `last verified:` stamp |
| 34 | `scripts/keel-verify` | Phase 5 scaffold | `present` | **20 checks** as of D-086 |
| 35 | `scripts/keel-doctor` | Phase 5 scaffold | `present` | 14 rows, `--check` green |
| 36 | Build/minify script (source-first assets) | Phase 5 scaffold | `declined` | **D-038.** Measured before deciding: all 68 tracked `*.min.*` are third-party vendored, and Klytos ships **no** first-party minified asset — so the drift the contract prevents cannot occur here. Review trigger: Phase 7 |
| 37 | `scripts/keel-handoff-verify` + its allow-list entry | Phase 5 scaffold | `present` | Script present and executable; `Bash(./scripts/keel-handoff-verify:*)` in `.claude/settings.json` and in `.claude/settings.local.json`. Run at this session's start: `VERDICT: CONTINUE` |
| 38 | Single-lane lock outside the repository | Phase 5 scaffold | `present` | `~/.keel/state`, keyed by the real toplevel path; taken by this session (PID recorded), released by the close-out's `--release` |
| 39 | `scripts/keel-continue` | Phase 5 scaffold | `present` | Generated per the full contract at D-081; the osascript fire itself is still unexercised — see row 42 |
| 40 | `scripts/keel-chain-check` | Phase 5 scaffold | `present` | Twelve rows + `--json` + `--smoke`; run this session, **11 of 12 OK** |
| 41 | `Chaining model:` card line | Phase 1 step 0a | `present` | `claude-opus-5` (D-082); row B4 confirms the launcher actually passes `--model` |
| 42 | `Chain verified:` — the line, and the `--smoke` run behind it | Phase 5 scaffold | **missing** | The **line** exists and honestly reads `not yet proven`; what is missing is the proof. `--smoke` has never run, so row **B5** is the single FAIL in the chain check. **D-082 records why and does not dress it up:** this project's `--smoke` is a lighter proxy than v5.13.0's literal contract — it exercises the Terminal.app launch mechanism with an `echo` rather than firing a real, billed `claude`. **The user's call: run the real `--smoke` (it costs one billed invocation) or leave B5 red on the record.** |
| 43 | `.githooks/pre-commit` confidential-data gate | Phase 5 scaffold | `present` | With `core.hooksPath` set. Collaborator setup is a known open item (D-015) |
| 44 | Permission allow-lists per capable tool | Phase 5 scaffold | `present` | `.claude/settings.json`, `.codex/rules/`, `.cursor/cli.json`, `tools.allowed` in `.gemini/settings.json` (slice 9) |
| 45 | CI workflow | Phase 5 scaffold | `present` | `.github/workflows/ci.yml` — the plan's verified commands, seeding the playground and promoting a skip to a hard failure |
| 46 | MCP registration per capable tool | Phase 5 scaffold | `n/a` | The technical plan defines **no development MCP servers**. Klytos's own MCP server is a *product surface*, not a dev server to register — a distinction worth keeping, because the two look identical from the row title |
| 47 | `docs/architecture.md` | Phase 6 | `n/a` | Phase 6 not reached — ahead of the project, not absent from it |
| 48 | `docs/api/`, `docs/usage/`, `docs/reference/` | Phase 6 | `present` (partial by design) | `docs/api/` and `docs/reference/` exist and grow per slice, as the progressive-backfill rule requires. `docs/usage/` is Phase 6's and does not exist yet |
| 49 | `docs/security.md` | Phase 6 | `n/a` | Phase 6 not reached |
| 50 | `docs/accessibility.md` | Phase 6 | `n/a` | Phase 6 not reached. The per-slice accessibility evidence lives in `docs/05-test-points.md` and the decision entries meanwhile |
| 51 | `README.md` | Phase 6 | `present` | Exists; its per-module tool table is stale and owned by D-017's Phase 6 editorial pass, which `keel-verify` warns about rather than hides |
| 52 | `guide/` | Phase 6 | `n/a` | `User guide: n/a until Phase 6` |
| 53 | `guide/_theme/` + `guide/brand/` + the version marker | Phase 6 | `n/a` | Same condition; `Docs theme: n/a until Phase 6` |
| 54 | `docs/07-release.md` | Phase 7 | `n/a` | Phase 7 not reached |
| 55 | Phase 8 site set (`docs/site/`, brief, discovery, spec, design) | Phase 8 | `n/a` | `Website intent: yes` (klytos.io) but Phase 8 is **deferred until requested** (D-012) |
| 56 | `SPEC/art-direction.md` for the site | Phase 8 | `n/a` | Same condition |
| 57 | `~/.keel/art-ledger.md` | Phase 8 | `n/a` | Same condition. Machine-local and never committed in any case |
| 58 | `<site-docs>/launch-report.md` | Phase 8 | `n/a` | Same condition |
| 59 | `<site-docs>/operations.md` | Phase 8 | `n/a` | Same condition |
| 60 | `docs/.keel/slices/<n>.json` | Phase 5 fan-out kickoff | `n/a` | This project has never fanned work out over git worktrees |
| 61 | `docs/issues.md`, incl. the `Last inbound sweep:` header | first forge contact | `present` | The log existed; **the header line did not until this sweep** (finding 2) |
| 62 | `docs/old/` | first sprint close | `present` | Sprints 1–5, and three archived design deliveries (49 / 123 / 123 files) |
| 63 | `docs/04-adoption-audit.md` | adoption step 5 | `present` | Klytos was adopted, not started, under Keel (D-001) |

### Project-card lines (MANIFEST Table 1, closing paragraph)

Every line the manifest names exists on the card, checked by grep this session rather than by
recollection: `Keel portability:` · `Assistant config:` · `Keel baseline:` · `Client budget:` ·
`User guide:` · `Docs theme:` · `Models:` · `Chaining:` · `Chaining model:` · `Chain verified:` ·
`Issue sweep interval:` · `Test-first policy:` — plus the v5.10.0 additions `Autonomy:`,
`Durability:`, `Branches:`, `Notify:` and `Issue capture:`. **State: `present`**, with the one
correction recorded above (`Keel portability:` was stale, not absent).

## Table 3 — actions newer than the baseline

**None.** The card's `Keel baseline:` is **v5.13.0** and the running Keel is **v5.13.0**, so the
delta is empty by construction. The two v5.11.0/v5.8.0 actions this sweep applied were not new
actions: they were rows the v5.12.0 reconciliation **carried forward and named** (D-081) precisely so
that a later session could not skip them silently — which is what the naming was for, and it worked.

## Result

**63 rows. 41 `present` · 2 missing · 1 `declined` · 19 `n/a`.**

The two `missing` rows are both **decisions waiting on the user**, not work waiting on a session, and
neither blocks anything:

- **Row 30** — the `Criterion` / `Coverage` columns, deferred in writing to the next sprint table.
- **Row 42** — the `--smoke` proof behind `Chain verified:`, which costs one real billed `claude`
  invocation and is the only thing standing between this project and `VERDICT: READY`.

`scripts/keel-verify`'s conformance check counts the literal string `**missing**` and warns; it will
now report **2** where it reported 6, and the drop is real rather than editorial — three of the four
resolved rows were fixed in this change and the fourth (`Red first`) was built in the one before it.

## Standing rule

This file is **rewritten**, never amended, at every reconciliation and at the Phase 7 gate. Its
authorised inputs are the disk, `MANIFEST.md` and `docs/decisions.md` — never its own previous
contents. The 2026-08-09 sweep is the first to be run under that rule as written, and it is the
reason three stale rows were caught: each of them looked correct in the record and was wrong on disk.

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
