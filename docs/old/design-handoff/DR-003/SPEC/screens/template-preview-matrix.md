# Template — Preview matrix

**Used by**: Template preview (`template-preview.php`).

One template × one sample record, rendered at four real widths, with a column of automated
checks beside it. It is the screen that keeps §10 of the accessibility spec honest.

---

## 1. Anatomy

```
main (padding 20)
├─ h1                    "Marketing template"
├─ control row           template <select> · sample content <select> · theme toggle · Open in a new tab
├─ viewport strip        4 frames, horizontal scroll, gap 14
│  └─ frame              label above (name + px), iframe below, 1px --borde-control, radius 10
└─ checks card           list of automated checks with state + fix action
```

Widths: **360** (phone) · **768** (tablet) · **1024** (laptop) · **1440** (desktop). Real
widths, real renders, in `<iframe>`s — not screenshots and not CSS scale tricks, because a
scaled screenshot hides exactly the bugs this screen exists to find. Each frame is 420px tall
and scrolls internally.

---

## 2. States

**Default** — the current template with the first sample record, all four frames rendered,
checks run.

**Hover** — a frame's label row reveals "Open at this width" — a link that opens the same
URL in a new tab with the width as a query parameter.

**Focus** — each `<iframe>` is focusable and has a `title` ("Marketing template at 360px").
Tab enters the frame's content; `Esc` is not needed because the frame is not a trap.

**Loading** — each frame shows its label and a 420px placeholder with the text "Rendering at
360px…"; frames load independently and one slow frame does not block the others. The checks
card waits for all four and shows "Running 9 checks…" with an indeterminate progressbar.

**Check — passed** — `check_circle` glyph in `--color-exito`, the check's name, and the
evidence in mono: "One `h1` — *Pricing that scales*".

**Check — failed** — `error` glyph, `--sobre-tinte-peligro` text, the name, what was found,
and the fix as a link into the template or the record:
> "Heading levels — `h2` is followed by `h4` in the *Features* part. **Open the template
> part**"
> "Image alt — 2 images have no alt decision. **Open the record**"

**Check — not applicable** — `--texto-sutil`, the name, and why: "Skip link — not applicable,
this template has no navigation."

**Blocking** — a template that fails a **hard** check (no `h1`, an image with no alt
decision, a contrast pair below 4.5:1) cannot be set as the site default. The "Set as
default" action is `disabled` with the count in its name ("Set as default — 2 hard checks
failing") and the failing checks are listed first.

**Empty — no sample content** — a template with no matching record cannot be previewed
honestly:
> "No published record uses this template. Previewing with placeholder content shows the
> layout, not the reality. — **Preview with placeholder content** · **Create a record**"

**Error — the template cannot render** — the frame shows the PHP error in a `<pre>` with the
file and line, not a blank iframe:
> "`marketing.php` failed to render — undefined variable `$hero` on line 24. **Open the
> source**"

**Success** — "Set as the default template for Pages." in the page status region.

---

## 3. Responsive

The subject of this screen is width, so the screen's own width behaves carefully:

| Width | Behaviour |
|---|---|
| **≥ 1440** | Reference: all four frames in a horizontal strip that scrolls; 1440 and 1024 partly visible, which is honest — the strip is meant to be scrolled. |
| **1200–1439** | Same, more scrolling. |
| **900–1199** | Sidebar → icon rail. Frames stay at their real widths; only the strip scrolls. **Frames are never scaled down** — a "1440px" frame drawn at 900px is a lie. |
| **< 900** | Sidebar → drawer. The strip becomes one frame at a time with a width chip row above it (`<nav aria-label="Preview width">`, `aria-current`), because a horizontal strip of 1440px frames on a 380px screen is unusable. The checks card moves above the frame. |

---

## 4. Accessibility

- Every `<iframe>` has a `title` naming the template and the width. An untitled iframe is a
  defect.
- The frame strip is a labelled, focusable scroll container
  (`role="group" aria-label="Template preview at four widths, scrollable"`).
- Checks are a `<ul>`; each item's state is in its **text**, not only in its glyph and
  colour, and failed checks come first in DOM order as well as visually.
- The checks card's heading states the summary: `<h2>` "7 of 9 checks passed".
- Running the checks announces start and finish once through the page status region.
- The two `<select>`s have visible labels and sit in a `<form>` with a submit that JS
  suppresses — the screen works without JS, rendering the four frames server-side.
- The theme toggle here previews the **site's** theme, not the admin's; its label says so
  ("Preview the site in dark mode") so it is not confused with the admin's own toggle.
- Headings: `<h1>` names the template; `<h2>` for the frame strip ("Widths") and for the
  checks card.
