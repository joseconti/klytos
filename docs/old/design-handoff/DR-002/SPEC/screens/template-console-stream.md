# Template — Console / stream

**Used by**: Terminal · Logs · Health (log stream panel) · Webhooks (delivery payload) ·
Block data (stored JSON).

Text that a machine emitted, shown without dressing it up.

---

## 1. Anatomy

```
main (padding 20)
├─ h1
├─ control row       level chips · file/source picker · search · Follow toggle · Download
├─ stream            card, --fondo-ventana panel inside, mono --type-code (12px/19px)
│                    white-space: pre; one line per record; max-height 60vh; scrolls
└─ [Logs] detail     right panel 340px — context + stack for the selected line
```

Syntax colour is by **role, not by language**: keys and structure `--texto-sutil`, values
`--texto-secundario`, the one line that matters `--color-acento`. Levels are a mono label at
the start of the line (`ERROR`, `WARN`, `INFO`, `DEBUG`) in `--sobre-tinte-*`, with the tint
as the line's background at 11 %/19 % for `ERROR` and `WARN` only.

The terminal's prompt is a real form: `<label class="k-sr">Command</label>` + input +
submit. It posts. Output is appended above the prompt.

---

## 2. States

**Default** — tail of the file, newest last, scrolled to the bottom.

**Hover** — a line takes `--fila-hover` and reveals its copy affordance at the right. The
affordance is in the DOM always.

**Focus** — the stream container is `tabindex="0"` with `role="group"` and an
`aria-label` ("Application log, 412 lines, scrollable"), so keyboard users can scroll it.
Individual lines are focusable **only** where selecting a line does something (Logs: opens
the detail panel); there they are `<button>`s spanning the line, with the line's text as
their name.

**Selected line** (Logs) — `--fila-seleccion`, `aria-pressed="true"`, detail panel populated
with `<h2>` naming the event.

**Following** — the "Follow" toggle is `role="switch" aria-checked` (it takes effect
immediately). While following, new lines are appended and the view sticks to the bottom.
Scrolling up turns Follow off automatically and says so once in the status region: "Follow
paused — you scrolled up." Turning it back on is one click.

**Polling** — the log tail polls; it does not open a socket. New lines are appended to a
container that is **not** `aria-live` — a live log would read continuously and make the page
unusable. Instead, a polite status line announces counts on a 10-second floor: "12 new lines,
2 errors." A person who wants the content reads the region.

**Running** (Terminal) — the prompt is disabled for the duration of the command, with the
command echoed above it and an indeterminate `role="progressbar"` plus the elapsed seconds
as text. `Ctrl+C` (and a visible "Stop" button) cancels.

**Empty — no output yet** (Terminal)
> "Klytos shell. Type `help` for the command list, or press ⌘K for the palette."

**Empty — the log file is empty**
> "`error.log` is empty. Nothing has been written since it was rotated on 24 July."

**Empty — filtered to nothing**
> "No `ERROR` lines in the last 24 hours. — **Show all levels**"
This is a good-news empty state and reads like one.

**Error — the file cannot be read**
> "`error.log` cannot be read — permission denied on `/var/log/klytos/`. **Open Health** ·
> **Choose another file**"

**Error — the command failed** (Terminal) — the exit code is shown *with* its meaning, never
alone: "Exited 127 — command not found: `klytos:migrat`. Did you mean `klytos:migrate`?"

**Success** — a command that finishes cleanly prints its own output and a final line
"Done in 1.2 s". No green banner.

**Disabled** — Download is disabled when the file is empty, with the reason in its name.

**Truncation** — a stream longer than 5,000 lines shows the last 5,000 and says so at the
top of the stream, with a link to download the whole file. It never silently truncates.

---

## 3. Responsive

| Width | Behaviour |
|---|---|
| **≥ 1440** | Reference: stream + 340px detail panel side by side. |
| **1200–1439** | Detail panel 300px. |
| **900–1199** | Sidebar → icon rail. Detail panel becomes a disclosure below the selected line, in the flow — not a sheet, because the context belongs next to the line. |
| **< 900** | Sidebar → drawer. Control row wraps to two rows; the level chips scroll horizontally in a labelled scroll container. The stream keeps `white-space: pre` and scrolls horizontally: **wrapping a log line is worse than scrolling it**, and this is the one place in the admin where horizontal scroll of content is correct (1.4.10's exception for content requiring two-dimensional layout). |

The terminal's font size never scales below 12px; at 400 % zoom that is 48px effective and
lines scroll horizontally.

---

## 4. Accessibility

- The stream is `<pre>` inside a labelled, focusable scroll container. `<pre>` keeps the
  meaning of the whitespace, which is content here.
- Level is text first (`ERROR`), tint second. A monochrome print of a log screen is fully
  readable — that is the test.
- Timestamps are `<time datetime>`.
- **No `aria-live` on the stream itself.** Counts are announced politely, throttled, in the
  page's status region. This is a deliberate exception to "announce changes" and it is the
  right one.
- The Follow control is a switch (immediate effect); the level filter chips are links
  (they change the URL); the file picker is a `<select>` inside a form with a visible label
  and a submit that is not needed when JS is on but exists when it is off.
- Terminal: the prompt has a real label, `autocomplete="off"`, `spellcheck="false"`, and
  autocomplete suggestions are a `role="listbox"` with `aria-activedescendant`, exactly as
  the command palette (§5.11 of the accessibility spec).
- Copy buttons name what they copy: "Copy this line", "Copy the whole payload".
- Headings: `<h1>` the screen; the detail panel's `<h2>` names the selected event.
