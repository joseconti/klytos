# Template — Wizard

**Used by**: Setup wizard (seven steps, first run).

No shell: the person setting Klytos up has no site to navigate yet. The wizard owns the whole
window and carries its own step rail.

---

## 1. Anatomy

```
body                    --fondo-ventana
└─ layout
   ├─ step rail   280px, --glass-fallback-sidebar, right border
   │  ├─ brand row      lockup 18px + version
   │  └─ step list      7 items, 40px each: number/state glyph + label + sub-label
   └─ panel       flex 1, --fondo-contenido
      ├─ header   h1 + lede, padding 28 28 0
      ├─ body     one card or one form, max 640px
      └─ footer   sticky, 64px, top border: Back (secondary) · step count · Continue (primary)
```

Steps: Welcome · Database · Site identity · Administrator · Content model · Intelligence ·
Finish.

---

## 2. States

**Step — upcoming** — number in a 20px circle with `--borde-control`, label in
`--texto-sutil`. Not a link: you cannot skip ahead into a step whose prerequisites are
unmet.

**Step — current** — `--fila-seleccion` background, accent number, label `500`, and it is the
item carrying `aria-current="step"`.

**Step — complete** — `check` glyph in `--color-exito`, label in `--texto-primario`, **and it
is a link**: going back is always allowed, and going back never discards what was entered.

**Step — blocked** — `error` glyph, label in `--sobre-tinte-peligro`, sub-label naming the
blocker ("Database — cannot connect"). Still a link.

**Default panel** — one decision per step. A wizard step that asks four unrelated questions
is two steps.

**Hover / focus / active** — as the shared field and button rules; the footer's Continue is
the form's submit, so `Enter` advances.

**Disabled** — Back is absent (not disabled) on step 1. Continue is **never** disabled:
pressing it with an incomplete form returns the errors, which is how a person finds out what
is missing.

**Loading** — steps that do work (testing the database, writing the schema, installing the
starter content) show a determinate `<progress>` with the current operation as text
("Creating tables — 12 of 34"), the footer's Continue goes `aria-busy`, and Back becomes
unavailable **with a reason**: "Back — unavailable while the database is being written".

**Error — validation** — the step's own error summary at the top of the panel,
`role="alert"`, focus moved to it, each failed field a link.

**Error — the step's work failed** — the panel keeps the form, filled in as the person left
it, and the summary names the cause and the remedy:
> "The database could not be reached — connection refused on `127.0.0.1:3306`. Check the host
> and port, or **use SQLite instead**."
An installer that loses the form on failure is the single most hated screen in any CMS; this
one does not.

**Success — step** — advance. No interstitial, no confetti.

**Success — finish** — the last panel states what exists now and gives exactly one primary
action ("Open the admin") plus the credentials reminder. It also states, in one line, what
was **not** done, so nothing is a surprise: "Search indexing has not run yet — it starts on
the first page publish."

**Empty** — not applicable; every step has content.

**Resumable** — closing the browser mid-wizard and returning re-enters at the first
incomplete step, with everything already entered still there. Progress is server-side, not
`localStorage`.

---

## 3. Responsive

| Width | Behaviour |
|---|---|
| **≥ 1440** | Reference: rail 280 + panel. Body column max 640px, left-aligned in the panel with 28px padding. |
| **1200–1439** | Identical. |
| **900–1199** | Rail 220px, sub-labels hidden (they move into the item's `aria-label`). |
| **< 900** | Rail becomes a **horizontal progress strip** at the top: "Step 3 of 7 — Site identity" plus a 4px `<progress>`. The full step list moves behind a "Steps" disclosure button. The footer stays sticky and full width; Continue is full width, Back is a text link to its left. |

At 400 % zoom the strip and the single-column panel still fit 320 CSS px.

---

## 4. Accessibility

- Landmarks: `<nav aria-label="Setup steps">` for the rail, `<main>` for the panel,
  `<footer>` is **not** `contentinfo` here (it holds the form's actions) — it is inside the
  `<form>`, so it is part of `main`.
- One `<h1>` per step, and it is the step's name. The wizard's own name appears once, in the
  rail's brand row, not as a competing heading.
- The step list is an `<ol>`; each item's accessible name includes its state: "Step 2 of 7,
  Database, complete".
- `aria-current="step"` on the current item — `step`, not `page`.
- The progress strip below 900px is a real `<progress>` with a text label; the text is the
  message, the bar is the illustration.
- Each step is a separate page load with a real `<form action method="post">`. The wizard
  works with JavaScript disabled, end to end. That is not a nicety: the wizard runs before
  anything else is known to work.
- Long operations announce start and completion once through a single
  `aria-live="polite"` region, never per unit of work.
- Focus on each new step lands on the `<h1>` (`tabindex="-1"`), not on the first field —
  otherwise the step's explanation is skipped.
