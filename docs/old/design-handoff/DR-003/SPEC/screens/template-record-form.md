# Template — Record form

**Used by**: Settings · x402 settings · Post type · Profile · Consent · Privacy · Licence ·
Security · MCP · Content model · Taxonomies (add-term form) · Webhooks (endpoint + HMAC) ·
Design (theme) · Plugin page. Data and field lists in `SPEC/manifest.md`.

---

## 1. Anatomy

```
main (padding 20)
├─ h1
├─ [optional] section nav        left column 180px, sticky, or a chip row above
└─ card stack                    gap 14
   └─ card                       --fondo-elevado, radius 10, padding 20, gap 12–15
      ├─ h2                      --type-card-heading
      ├─ [optional] eyebrow      --type-eyebrow, uppercase, --texto-sutil
      ├─ field rows
      └─ [optional] card footer  actions, right-aligned
```

**Field** — label `600 12px` above; control 34px tall, radius 6, 1px `--borde-control`,
background `--fondo-ventana` (sunken inside a card); hint `--type-caption` in
`--texto-sutil` underneath. Two-column field grids at ≥ 1200px only where both fields are
short (city/postcode, network/chain).

Save is a form post. The primary "Save" lives in the **toolbar**, not at the foot of the
page, and it is the same button on every form screen.

---

## 2. States

**Default** — clean, no validation shown. Never validate on load.

**Hover** — control background lightens/darkens 120ms; label does not move.

**Focus** — 2px accent ring at 2px offset **and** the control's border goes
`--color-acento`. The ring is the accessible indicator; the border is decoration.

**Active / pressed** — colour change only, no scale, no bounce.

**Disabled** — control at `--texto-deshabilitado` on `--fondo-ventana`, border
`--borde-deshabilitado`, `disabled` attribute set, and a one-line reason next to the label:
"Locked — this option is managed by `wp-config`-style constant `KLYTOS_X402_WALLET`."
A disabled control is never hidden and never explained only in a tooltip.

**Read-only vs disabled** — a value the user may copy but not change (licence key, wallet
address, webhook secret) is `readonly`, mono, selectable, with a copy button. It is not
`disabled`.

**Dirty** — the toolbar's Save becomes primary-filled and enabled; a "You have unsaved
changes" line appears in the page status region. Navigating away with unsaved changes is
caught by `beforeunload` **and** by an inline confirm on the nav link — not by a browser
`confirm()`.

**Loading / saving** — Save goes `aria-busy="true"`, label "Saving…", the form's controls
stay enabled and readable (a post takes 40ms; freezing the form is worse than not).

**Success** — the page reloads with a `role="status"` line under the H1: "Settings saved."
The changed card gets no highlight animation; the sentence is the feedback.

**Error — field level** — `aria-invalid="true"`, border `--color-peligro`, an `error` icon
before the message, and the message in `--color-peligro` linked by `aria-describedby`.
Colour is never the only channel (§1.3 of the accessibility spec).
> "Enter a slug. Lowercase letters, numbers and hyphens only — `spring-sale`."

**Error — form level** — an error summary at the top of `main`, `role="alert"`, focus moved
to it on load, listing every failed field as a link to that field:
> "This post type was not saved. 2 fields need attention:
>  · Slug — enter a slug
>  · Menu position — must be a whole number between 1 and 100"

**Error — the save failed for a server reason** — same summary, but it names the cause and
the action, never a code alone:
> "This post type was not saved — the options table is read-only. **Open Health** ·
> **Try again**"

**Empty** — a form is never empty, but a *collection inside a form* can be (no custom
fields yet, no exempt agents yet). That collection renders one row: the sentence and the
add action, inside the card, keeping the card's heading.
> "No custom fields. A custom field adds a value to every record of this type. — **Add a
> field**"

**Destructive section** — always the last card, heading "Danger zone" is **not** used
(no theatre): the heading is what it does, "Delete this post type". Inline two-step confirm:
the button becomes "Confirm delete — 34 records will be deleted" on first click, with
`aria-live="polite"` on its wrapper. Never a browser `confirm()`.

---

## 3. Responsive

| Width | Behaviour |
|---|---|
| **≥ 1440** | Reference. Content column max 880px, section nav 180px sticky at left, card stack fills the rest. |
| **1200–1439** | Content column shrinks to fit; two-column field grids stay. |
| **900–1199** | Sidebar → 56px icon rail. Section nav collapses from a left column to a horizontal chip row above the cards (still `<nav aria-label="Settings sections">`). All field grids go single-column. |
| **< 900** | Sidebar → off-canvas drawer. Cards go full width, padding 20 → 16. The toolbar's Save stays in the toolbar and stays visible: the toolbar is sticky, not scrolled away. |

Text spacing at 1.4.12 values must not clip a label or a hint — no fixed-height field rows.

---

## 4. Accessibility

- Every control has a **visible `<label for>`**. No placeholder-as-label anywhere in the
  admin.
- Hints and errors are both in `aria-describedby`, hint first.
- Required: the `required` attribute plus the word "Required" in the hint. Never an asterisk
  alone.
- `autocomplete` on every field with a standard token — `email`, `current-password`,
  `new-password`, `organization`, `url`, `one-time-code`.
- Mono fields (slug, key, path, wallet) get `spellcheck="false"`, `autocapitalize="off"`,
  `inputmode` where it helps.
- Grouped controls are in `<fieldset><legend>` — every radio group, every checkbox set.
- **Switch vs checkbox** is a real decision, recorded per screen in the manifest: a control
  that takes effect immediately is `role="switch"`; a control that needs Save is a
  `<input type="checkbox">`. On this template most are checkboxes, because this template has
  a Save button.
- Section nav is `<nav aria-label="…">`; the current section is `aria-current="page"`.
- Headings: one `<h1>`; each card `<h2>`; groups inside a card `<h3>`. Eyebrows are not
  headings unless they are the only label for the region.
- Focus order is DOM order: section nav → card 1 fields → card 2 fields → … → destructive
  card. The toolbar's Save is reached before `main` (§3.2) and also exists as the form's
  implicit submit, so `Enter` in a text field saves.
