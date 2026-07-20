# keel-verify — the project's own release linter

> Sprint 1 slice 9 (**D-045**). Created in slice 4 (**D-032**) with the gate check; extended here.
> Lives at `docs/` root rather than `docs/reference/` on purpose: like
> [playground.md](playground.md) this is **project tooling**, not a product surface, and
> `docs/api/INDEX.md`'s own scope is `installer/` only.

```
php scripts/keel-verify        # exit 0 = every check passed
```

Its job is mechanical: verify that what the docs and the architecture PROMISE is actually true, so
no session re-checks it by eye and no release ships with a promise quietly broken. It runs at every
sprint close, at the Phase 7 gate, and in CI. **Wherever it runs, its output is pasted as evidence —
"keel-verify passed" without the output is an empty cell.**

It has no dependencies beyond PHP and git, and works on a bare checkout: the gate check stubs
`klytos_apply_filters()` so it reads the SHIPPED map rather than whatever a locally-installed plugin
filters it into, which is the right subject for a release gate.

## The checks

| # | Check | Fails when |
|---|---|---|
| 1 | authorization gate covers every admin surface | a file under `admin/` or `admin/api/` has no gate-map entry, the map names a file that does not exist, or `bootstrap.php` becomes mapped |
| 2 | the central gate is invoked | `admin/bootstrap.php` stops calling `klytos_enforce_admin_gate()` |
| 3 | `docs/api/INDEX.md` summary counts match its rows | a Summary count disagrees with the rows in its section, or the total disagrees with the sum |
| 4 | `docs/api/INDEX.md` parity | a row points at a doc that does not exist, or a doc in `docs/api/`/`docs/reference/` has no row |
| 5 | locale catalogues agree on their key set | any catalogue in a set is missing a key, or carries one its siblings do not |
| 6 | no placeholder copy in distributable surfaces | a `TODO:`, `TODO(`, `@todo`, `FIXME`, `XXX:` or `lorem ipsum` appears in a file that actually ships |
| 7 | changelog order oldest → newest | an entry is dated before the entry above it |
| 8 | version touchpoints in sync — **WARN** | the touchpoints disagree (they do today: audit **H-01**) |
| 9 | runtime assets survive the release archive — **WARN** | `installer/core/guides/` is export-ignored (it is today: audit **NEW-27**) |

## PASS, FAIL and WARN

A check **WARNs** only when the property is genuinely broken *and* a recorded decision assigns the
fix to another phase. It prints its full evidence and does **not** change the exit code. Anything
else must FAIL.

This is not a softer FAIL, it is a different statement: *"this is broken, it is known, and someone
else owns it."* `docs/sprints/sprint-1.md` scopes H-01 to Phase 7 and slice 9 to making it
detectable — a hard FAIL would have turned keel-verify red for every run of the whole sprint, and a
permanently red gate is one people learn to ignore, which is precisely how a check goes inert.

**Only two checks may warn, and both name their owning phase in the output.** A new check defaults
to FAIL; downgrading one to WARN needs a decision entry saying who owns the fix.

## What is deliberately NOT checked

Stated here so the absences read as decisions rather than oversights:

- **Minified-asset sync** — N/A per **D-038**. All 68 tracked `*.min.*` are third-party vendor
  distributions; Klytos ships no minified first-party asset, so no source↔minified drift can exist.
  Trigger: Phase 7.
- **WordPress i18n sniffs** — N/A per **D-006**. This is not a WordPress project. The equivalent
  real invariant here is catalogue key parity, which **is** checked (#5).

## Adding a check

Each check is independent and self-reporting: **add one, do not restructure.** Append a block that
builds a `$problems` array and ends in `keel_report( 'name', $problems )`, then:

1. **Prove it fails.** Inject a violation, observe the FAIL, revert. A check that has never fired is
   indistinguishable from one that cannot — the failure mode **L-010** records, where a broken check
   goes quiet and lends its credibility to everything downstream. If it is a WARN, prove **both**
   directions: that it fires, and that it goes quiet when the condition is fixed.
2. **Add its name to `EXPECTED_CHECKS`** in `tests/Unit/KeelVerifyTest.php`. That test asserts every
   check name appears in the output and that the reported count matches — it is what stops a check
   silently disappearing while the script goes on printing OK.
3. **Make it precise enough to be trusted.** Check #6 deliberately does not match bare `TODO` or
   `PLACEHOLDER`, because `install.php` ships Spanish copy where "TODO" means *all* and a CSS section
   header reads "CHART PLACEHOLDER". A check that cries wolf on correct content gets ignored, which
   defeats the purpose of having it.

"Distributable" is never a hand-written list: check #6 asks `git check-attr export-ignore`, the same
authority that builds the release archive. That choice is what surfaced **NEW-27** and **NEW-28**.

## CI

`.github/workflows/ci.yml` runs `composer install`, seeds the playground, runs the suite, `phpcs`
and this script — the **same commands the test points run**, never a diverging set. CI seeds because
91 of 138 tests are the integration tier, which *skips* rather than fails without a playground; an
un-seeded run would report green having executed 34% of the suite. A skip is therefore promoted to a
hard CI failure. PHP 8.2 and 8.3 run the suite; 8.1 — the declared floor — gets a syntax-only job,
because PHPUnit 11 requires 8.2+ (**D-027**).
