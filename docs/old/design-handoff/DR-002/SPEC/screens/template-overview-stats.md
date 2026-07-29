# Template — Overview / stats

**Used by**: Analytics · x402 dashboard · Health · Updates · System integrity · Tasks ·
Licence (entitlements) · Consent (acceptance stats).

A screen whose job is to answer "is this fine?" in under two seconds, then let you drill in.

---

## 1. Anatomy

```
main (padding 20)
├─ h1  +  [optional] period control (chip row of links: 7d · 30d · 90d)
├─ stat row            grid, 3–5 columns, gap 14
├─ primary panel       card, padding 20 — the chart, the score, or the release notes
└─ detail cards        1–2 columns, gap 14 — top pages, referrers, failing checks
```

**Stat card** — 32px rounded icon tile in a semantic tint, `--type-numeric` scaled up
(`600 20px` mono) for the value, `--type-caption` in `--texto-sutil` for the label. A delta,
when shown, is a word plus a direction glyph plus the number — never a bare coloured
arrow: "▲ 12 % vs previous 30 days".

**Chart** — the only graphics in the admin. Line or bar, one series unless the screen says
otherwise, gridlines in `--separador`, axis labels in `--texto-sutil` at
`--type-caption`. No gradient fills, no drop shadows, no 3D, no donut.

---

## 2. States

**Default** — as drawn.

**Hover** — stat cards that link raise nothing; they take `--fila-hover`. Chart hover shows
a value readout **in a fixed position under the chart**, not a floating tooltip, so it is
reachable and does not depend on pointer position.

**Focus** — a linked stat card is a single `<a>` and takes the ring around the whole card.
The chart's data points are not individually focusable; the chart's table equivalent is
(below).

**Loading** — the page is server-rendered, so the numbers arrive with the HTML. The one
exception is a check that runs on demand (Health's "Run diagnostics", System integrity's
"Verify signatures"): its card shows an indeterminate `role="progressbar"` plus the text
"Checking 412 files…", the trigger goes `aria-busy`, and the result replaces the text. The
rest of the page stays live.

**Empty — no data yet** — a new site has no traffic and no transactions. The stat row still
renders with `—` as the value (not `0`, which is a claim) and the label unchanged; the
primary panel carries the sentence and the action.
> "No traffic yet. Analytics starts counting once the site is public. — **Open Settings**"
> "No agent payments yet. x402 is enabled and no agent has paid for a page. — **Review
> pricing**"

**Empty — nothing is wrong** — the good empty state, and it must feel like an answer, not a
gap. Health with zero failing checks says so in words, with the `task_alt` glyph in
`--color-exito` and the count: "All 24 checks passed. Last run 3 minutes ago."

**Error — a source is unavailable** — the affected card alone degrades; the page does not.
The card keeps its heading and replaces its body:
> "Traffic could not be read — the analytics table is missing. **Run the installer** ·
> **Open Health**"

**Error — the subject of the screen is unhealthy** — this is content, not an error state.
Failing checks are rows with `ERROR` / `WARN` as a mono label, the sentence, and the fix
action. The stat card for that group turns to the `--tinte-peligro` tile with
`--sobre-tinte-peligro` text, and the number is the count of failures.

**Success** — after a run completes: `role="status"`, "Signature check finished — 3 files
modified.", and the detail table below repopulates.

**Disabled** — a run trigger is disabled while a run is in flight, with the reason in its
name ("Verify signatures — a check is already running").

---

## 3. Responsive

| Width | Behaviour |
|---|---|
| **≥ 1440** | Reference. Stat row 4–5 across, detail cards 2 across. |
| **1200–1439** | Stat row 4 across, detail cards 2 across; chart height unchanged (240px). |
| **900–1199** | Sidebar → icon rail. Stat row 2 across, detail cards 1 across. Chart keeps its height and gains horizontal scroll only if the period is 90d. |
| **< 900** | Sidebar → drawer. Stat row 1 across, cards full width. **The chart is replaced by its data table**, not shrunk — a 320px-wide line chart is decoration, a table is information. The table is the same `<table>` that backs the chart at every width (below). |

---

## 4. Accessibility

- **Every chart has a table.** The chart is `role="img"` with an `aria-label` giving the
  headline ("Page views, 30 days: 12,480 total, peak 812 on 14 July"), and immediately after
  it, in the DOM, a real `<table>` with the same numbers inside a `<details>` whose summary
  is "View as table". Below 900px the `<details>` is open and the chart is hidden. This is
  the only accessible chart pattern the admin uses; do not invent another.
- Stat cards: `aria-labelledby` binds the value to its label so it reads "1,284 — page
  views, 30 days". The icon tile is `aria-hidden="true"`.
- A linked stat card is one `<a>` wrapping the whole card, not a chevron in the corner.
- Period chips are links in `<nav aria-label="Period">`, current one `aria-current="true"`.
- The on-demand check reports through the page's single `aria-live="polite"` region at start
  and at finish, never per file.
- Colour never carries health on its own: every state has a word (`Passed`, `Failed`,
  `Modified`, `Unsigned`) and the failing rows are also grouped first.
- Headings: `<h1>` the screen; each card `<h2>`; the stat row is not a heading and has no
  wrapper heading — the cards are self-labelling.
