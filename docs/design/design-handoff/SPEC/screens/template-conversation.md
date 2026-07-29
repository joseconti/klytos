# Template — Conversation

**Used by**: AI chat (full screen) · Copilot dock (docked / collapsed / floating / full).

The copilot is a first-class citizen and never the only path. Everything it can do is also
reachable as a normal form somewhere else in the admin. If a task is only possible by asking
the copilot, that is a defect.

---

## 1. Anatomy

```
region (full screen: main · dock: aside)
├─ header            50px — title, model chip, dock-mode controls
├─ transcript        flex 1, scrolls, padding 16–20, gap 14
│  ├─ user turn      right-aligned bubble, --fila-seleccion, radius 10
│  ├─ agent turn     plain text on the surface, no bubble, --type-body
│  └─ tool call      row: --fondo-ventana panel, radius 8, mono, status glyph + text
├─ context chips     row above the composer — what the copilot can currently see
└─ composer          textarea (auto-grows 34 → 120px) + 28px round send button
```

The agent's turns have no bubble on purpose: they are the page's content, and long answers
in a bubble are unreadable.

---

## 2. States

**Default / idle** — composer focused in the full-screen chat, **not** focused in the dock
(the dock must never steal focus from the page).

**Hover** — a turn's actions (copy, retry, insert into page) appear in its top-right; they
are in the DOM at all times and focusable.

**Focus** — the composer takes the accent ring. Each turn is a focusable
`role="article"` with a name ("You, 14:02" / "Klytos AI, 14:02") so a keyboard user can move
answer by answer.

**Sending** — the send button goes `aria-busy="true"`; the composer stays enabled so the
next message can be typed. `Enter` sends, `Shift+Enter` newlines, and the field's hint says
so.

**Streaming** — the agent turn appears progressively. The transcript is
`role="log" aria-live="polite" aria-relevant="additions"`, so **only the finished turn is
announced, not each token**: the streaming text lives in an `aria-hidden` node and is
swapped for the announced node on completion. Auto-scroll follows the bottom only while the
user is already at the bottom; the moment they scroll up it stops, and a "Jump to latest"
link appears.

**Tool call — running** — the row reads `search_pages` in mono with an indeterminate
`role="progressbar"` and the text "Running". Under reduced motion the spinner is a static
glyph and the word carries the state.

**Tool call — done** — `check` glyph in `--color-exito`, the tool name, and the outcome in
words: "Ran `search_pages` — 3 results". The result is expandable
(`<details>`), collapsed by default.

**Tool call — failed** — `error` glyph in `--sobre-tinte-peligro`, the tool name, the reason,
and the retry:
> "`publish_page` failed — the page has no `<h1>`. **Open the page** · **Retry**"

**Tool call — needs permission** — the copilot never writes without consent. The row becomes
a two-button inline confirm inside the transcript, with the exact effect spelled out:
> "Klytos AI wants to delete 4 draft pages. **Show them** · **Allow once** · **Deny**"
Focus moves to the confirm row; `Esc` denies.

**Stopped** — the user pressed Stop: the partial turn stays, marked "Stopped" in
`--texto-sutil`, and the retry action is offered. Nothing is silently discarded.

**Loading history** — older turns load by a "Load earlier messages" link at the top of the
transcript, which is a normal page load with a query parameter. No scroll-triggered loading.

**Empty — new conversation** — not a blank panel. Three concrete starters drawn from the
current screen's context, as links, plus the sentence:
> "Ask about this site. Klytos AI can read your pages, options and logs, and can change
> things with your permission.
> · Which pages have no meta description?
> · Summarise yesterday's errors
> · Draft a pricing page from the current one"

**Empty — no context available** — the context chip row says so rather than disappearing:
"No page in context — open a page to give Klytos AI something to look at."

**Error — the model is unreachable** — a `role="alert"` row in the transcript, and the
composer stays usable:
> "Klytos AI is unreachable — the provider returned 503. Your message was not sent.
> **Retry** · **Open Settings**"

**Error — no API key / no entitlement** — the composer is replaced by a single line and an
action; a disabled composer with no explanation is not acceptable:
> "Klytos AI is not configured. Add a provider key in Settings → Intelligence. —
> **Open Settings**"

**Success — an action was applied** — the tool row states the change and links to it, and the
change also appears in the normal place (the page, the option, the log). The transcript is
never the only record.

---

## 3. Dock modes (dock only)

| Mode | Geometry | Notes |
|---|---|---|
| **Docked** | 360px column at the right of the shell, full height below the toolbar | Content area shrinks; nothing overlaps |
| **Collapsed** | 44 × 44 launcher, bottom-right, 20px inset | `aria-expanded="false"` on the launcher |
| **Floating** | 380 × 520 card, bottom-right, card shadow, draggable | Drag has a keyboard equivalent: mode → docked |
| **Full** | Takes over the content area, sidebar retained | Same markup as the AI chat screen |

Mode is remembered per user. Opening moves focus to the dock's heading; closing returns focus
to the launcher. The dock never covers focused content: in docked and full modes it is in the
flow; in floating mode the content area reserves its footprint.

---

## 4. Responsive

| Width | Behaviour |
|---|---|
| **≥ 1440** | Reference. Dock 360px; full-screen chat centres the transcript at max 760px. |
| **1200–1439** | Dock 320px. |
| **900–1199** | Sidebar → icon rail. Docked mode is unavailable; the dock falls back to floating, then to full when opened. |
| **< 900** | Sidebar → drawer. The dock is **full only** — a 380px floating card on a 380px screen is a modal pretending not to be one. Full mode is `role="dialog" aria-modal="true"` at this width, with focus trapped and `Esc` closing. |

---

## 5. Accessibility

- Full-screen chat: the transcript is inside `<main>`. Dock: `<aside role="complementary"
  aria-label="Klytos AI">`, **after** `main` in the DOM.
- Transcript: `role="log" aria-live="polite" aria-relevant="additions"`. Never
  `aria-live="assertive"` — the copilot does not interrupt.
- Tool calls: `<ol aria-label="Tool calls">` of `<li>`, status as text.
- Composer: real `<label>` (visually hidden is acceptable here, the placeholder is not the
  label), `aria-describedby` the Enter/Shift+Enter hint.
- Stop is a real button, reachable by keyboard while streaming, and it is the first
  focusable node after the streaming turn.
- The model chip is text, not a coloured dot.
- Nothing in the conversation auto-focuses, auto-scrolls the page, or moves focus during
  streaming.
- Headings: the full-screen chat's `<h1>` is "Klytos AI"; the dock's heading is an `<h2>`
  because the dock lives on another screen that already owns the `<h1>`.
