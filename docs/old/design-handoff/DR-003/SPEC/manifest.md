# Screen manifest — Klytos admin

**44 entries**: **42 product screens**, plus the *States* catalogue (a specimen sheet, not a
screen) and the *Plugin page* example (one plugin's own screen, shipped as the pattern
third-party plugins follow). Entries 1–43 are drawn in the six prototype files; entry 44
(Dashboard, added by DR-002) is specified here and in its template, and is not drawn.

Each entry records its template, its `<h1>`, its PHP entry point, its data, and only the
**deltas** from the template. The template files in `SPEC/screens/` carry the states and the
responsive behaviour; nothing is repeated here.

Templates: [shell](template-shell.md) · [list-table](template-list-table.md) ·
[record-form](template-record-form.md) · [overview-stats](template-overview-stats.md) ·
[editor-split](template-editor-split.md) · [gallery-grid](template-gallery-grid.md) ·
[conversation](template-conversation.md) · [auth-centered](template-auth-centered.md) ·
[wizard](template-wizard.md) · [console-stream](template-console-stream.md) ·
[preview-matrix](template-preview-matrix.md)

Every authenticated screen also uses **shell**. Not repeated per row.

---

## `Klytos Admin - Screens.dc.html` — core content and configuration

### 1. Pages
`pages.php` · **list-table** · H1 **Pages**
- Columns: checkbox · **Title** (row header) · Status · Template · Locale · Last edit (num) · actions
- `grid-template-columns: 28px 1fr 116px 132px 96px 132px 44px`
- Filters: All · Published · Draft · Scheduled · Private — links, `aria-current`
- Bulk: publish, unpublish, delete, change template
- Empty: "No pages yet. A page is a URL with blocks on it — **Create the first page**."
- Delta: Status badge tones — Published `exito`, Draft `offline`, Scheduled `sync`, Private `offline`. Delete on the site home is `disabled` with the reason in its name.

### 2. Page editor
`page-editor.php` · **editor-split** · H1 = the page's title
- Rail: block list · Canvas: URL line + blocks · Inspector: document / block properties
- Delta: publish blockers are hard — no `<h1>`, an image with no alt decision. See accessibility §10.1–2.
- Delta: every block has an "Edit as form" fallback page; the editor is not the only path.

### 3. Design (theme)
`design.php` · **record-form** · H1 **Design**
- Cards: Palette (colour pairs with **measured ratio shown next to each pair**) · Type scale · Radii and spacing · Preview
- Delta: the theme editor **refuses to save a text/background pair below 4.5:1** without a recorded override (accessibility §10.7). The ratio is computed with the same method as `SPEC/color-contrast-audit.md`.
- Controls: colour inputs are `<input type="color">` **plus** a mono hex field with a visible label — the picker alone is not keyboard-friendly enough.

### 4. Assets
`assets.php` · **gallery-grid** · H1 **Assets**
- Tile: thumbnail 96px · filename · size · usage count · alt-text state
- Filters: All · Images · Video · Documents · Unused
- Delta: a tile with no alt text shows an "No alt text" chip in `--tinte-aviso` / `--sobre-tinte-aviso`; that chip is a link to the asset's alt field.
- Delta: delete is `disabled` for assets in use, reason in the name, usage count links to the pages.

### 5. Users
`users.php` · **list-table** · H1 **Users**
- Columns: checkbox · **Name + email** (row header, email in mono) · Role · 2FA · Status · Last login (num) · actions
- Filters: All · Owners · Editors · Authors · Invited · Suspended
- Empty (filtered): "No users match *Invited*. — **Clear filters**"
- Delta: "2FA" cell is a badge with a word (`Enabled` / `Not set`), never a tick alone. Suspending yourself is `disabled` with the reason.

### 6. Security
`security.php` · **record-form** · H1 **Security**
- Cards: Two-factor · Passkeys · Content-Security-Policy · Integrity score · Recovery codes
- Delta: 2FA and passkey controls are **switches** (immediate effect, each confirmed by a re-auth step). CSP directives are a textarea with a mono `--type-code` font and a validate action; they are **checkbox + Save**.
- Delta: the recovery-codes card is `--tinte-aviso` with a 1px `color-mix` border — the one bordered card in the admin, because it is a one-time secret.

### 7. Analytics
`analytics.php` · **overview-stats** · H1 **Analytics**
- Stats: Views · Visitors · Avg. time · Bounce · Agent hits
- Panel: 30-day line chart + **its `<details>` data table** (mandatory, accessibility of charts)
- Detail cards: Top pages · Referrers
- Period chips: 7d · 30d · 90d — links
- Empty: "No traffic yet. Analytics starts counting once the site is public. — **Open Settings**"

### 8. MCP
`mcp.php` · **record-form** · H1 **MCP**
- Cards: Application passwords (a small list-table inside a card) · Tool exposure (checkbox set in a `<fieldset>`) · Client configuration (read-only mono block with copy)
- Delta: an application password is shown **once**; the card says so before it is generated, not after.
- Delta: tool exposure toggles are **checkboxes + Save**, not switches — they change what an agent may do and must be reviewed as a set.

### 9. Settings
`settings.php` · **record-form** · H1 **Settings**
- Section nav: Site · URLs · Locale · Media · Intelligence · Email · Advanced
- Delta: section nav is `<nav aria-label="Settings sections">`; each section is its own page load, so each has its own `<h1>`? **No** — H1 stays "Settings" and the section is `<h2>`; the breadcrumb carries the section.
- Delta (DR-002): **search-engine and AI-crawler indexing lives here**, in *Advanced*, as a checkbox + Save with the consequence stated next to it. It is gated at `site.configure`. It used to be a bare button on the Dashboard; the Dashboard now only warns while the site is blocked and links here. This is the one existing entry DR-002 changes.

---

## `Klytos Admin - Screens 2.dc.html` — auth, copilot, moderation

### 10. Log in
`login.php` · **auth-centered** · H1 **Sign in to Klytos**
- Fields: email (`autocomplete="username email"`) · password (`current-password`) · "Remember this device" checkbox
- Reassurance: "Klytos never emails you a password."
- Delta: the credentials error names neither field (auth template §Error — credentials).

### 11. Verify
`verify.php` · **auth-centered** · H1 **Two-factor authentication**
- Fields: one code input (`one-time-code`, `inputmode="numeric"`, **not** six boxes)
- Links: use a recovery code · sign in as someone else
- Delta: lock-out after 5 attempts states the wait in words; the field goes `readonly`, not `disabled`.

### 12. AI chat
`ai.php` · **conversation** (full screen) · H1 **Klytos AI**
- Delta: transcript max width 760px; starters are drawn from the last screen visited.
- Delta: this screen and the dock share one markup; the dock's heading is `<h2>`, this one's is `<h1>`.

### 13. Tasks
`tasks.php` · **overview-stats** · H1 **Tasks**
- Stats: Open · Due this week · Overdue · Done (30d)
- Body: grouped task list — each task is a `<li>` with a title, a source ("raised by System integrity"), and its action
- Empty: "Nothing needs your attention. The last check ran 3 minutes ago." — a good-news empty state, and it reads like one.
- Delta: task state is a word plus a glyph; "Overdue" is never red alone.

### 14. Comments
`comments.php` · **list-table** · H1 **Comments**
- Columns: checkbox · **Author + excerpt** (row header) · In reply to · Status · Received (num) · actions
- Filters: Pending · Approved · Spam · Trash
- Bulk: approve, spam, delete
- Delta: the excerpt is the row header's second line and is truncated; the full text is on the comment's own page. Approving is a form post, and the confirmation names the comment's author.

### 15. Plugins
`plugins.php` · **list-table** · H1 **Plugins**
- Columns: checkbox · **Name + description** (row header) · Version (num) · Author · Status · actions
- Filters: All · Active · Inactive · Update available
- Delta: "Activate" is a switch **only** where activation is immediate; here it is a form button, because activation runs a migration. Recorded here so the build does not guess.
- Delta: a plugin with an update shows a badge with the target version in mono.

### 16. States (catalogue — not a screen)
`—` · specimen sheet
- Purpose: the empty, loading, error and offline treatments, side by side, so the build can see them together.
- **Deliverable status:** this is a design specimen. Its content is normative only insofar as it matches the per-template `States` sections; where it differs, the template file wins.

---

## `Klytos Admin - Screens 3.dc.html` — setup, money, content model, machine room

### 17. Setup wizard
`setup.php` · **wizard** · H1 = the step name
- Steps: Welcome · Database · Site identity · Administrator · Content model · Intelligence · Finish
- Delta: no shell, own step rail. Works entirely without JavaScript. Resumable server-side.

### 18. x402 dashboard
`x402.php` · **overview-stats** · H1 **Agent payments**
- Stats: Revenue (30d) · Paid requests · Unique agents · Avg. price · Settlement lag
- Panel: revenue bar chart + data table
- Detail cards: Top paid pages · Agents by spend
- Empty: "No agent payments yet. x402 is enabled and no agent has paid for a page. — **Review pricing**"
- Delta: all money is `--type-numeric`, right-aligned, with the currency as text.

### 19. Content model
`content-model.php` · **record-form** · H1 **Content model**
- Cards: Post types (list) · Taxonomies (list) · Statuses (editable set)
- Delta: each list row links to its own screen (Post type, Taxonomies). This screen creates and orders; it does not edit.

### 20. Translations
`translations.php` · **editor-split** · H1 **Translations**
- Rail: locales with a `<progress>` each · Canvas: string pairs · Inspector: AI suggestion, context, history
- Delta: every target field carries `lang` set to its locale, and every source string carries the source `lang` (3.1.2).
- Delta: an AI suggestion is never applied silently — it fills a "Suggested" row that the author accepts.

### 21. Blocks
`blocks.php` · **gallery-grid** · H1 **Blocks**
- Tile: wireframe preview 120px · block name · category · usage count
- Grouped by category, each group an `<h2>` + its own labelled `<ul>`
- Empty: "No blocks are registered. Blocks come from the core set and from plugins. — **Open Plugins**"

### 22. Health
`health.php` · **overview-stats** + a `console-stream` panel · H1 **Health**
- Stats: Checks passed · Warnings · Failures · PHP · Disk
- Panel: the diagnostics list (failures first) · Environment facts (mono definition list) · Log stream
- Delta: "Run diagnostics" is the on-demand check state described in overview-stats §Loading.
- Delta: the status bar's "Rendered in 21 ms" links here.

### 23. Terminal
`terminal.php` · **console-stream** · H1 **Terminal**
- Delta: the prompt is a real form that posts; output appends above it. `Ctrl+C` and a visible Stop both cancel.
- Delta: autocomplete comes from `api/terminal-autocomplete.php` and uses combobox semantics.

---

## `Klytos Admin - Screens 4.dc.html` — integrations, the law, the owner's account

### 24. Webhooks
`webhooks.php` · **record-form** + **list-table** · H1 **Webhooks**
- Cards: Endpoints (form) · Event subscriptions (checkbox set in a `<fieldset>`) · HMAC secret (read-only mono + rotate) · Delivery log (list-table)
- Delivery columns: **Event + endpoint** (row header) · Code (num) · Attempt · Duration (num) · Sent (num) · actions
- Delta: HTTP codes are text (`502`), tinted second. Retry is a form post per delivery.
- Delta: rotating the secret is a two-step inline confirm and states the consequence: "Existing endpoints will reject deliveries until they are updated."

### 25. Consent
`consent.php` · **record-form** + acceptance stats · H1 **Consent**
- Cards: Banner configuration · Cookie audit (list-table) · Acceptance stats (stat row)
- Delta — **the strictest rule in the bundle**: the banner preview and the shipped banner must have "Reject all" at the same prominence, size and component as "Accept all". The configuration screen offers **no** option to make reject less prominent; that option does not exist. Accessibility §10.4.
- Delta: the banner is `role="dialog" aria-modal="true"`, focus trapped, `Esc` = reject non-essential.

### 26. Privacy
`privacy.php` · **record-form** + **list-table** · H1 **Privacy**
- Cards: Export requests · Erasure requests · Per-section method and status (list-table)
- Columns: **Section** (row header) · Method · Status · Last run (num) · actions
- Delta: status words are `Automatic`, `Manual`, `Not covered` — never a tick or a colour alone. A section that is "Not covered" is a task, and it appears on Tasks.

### 27. Profile
`profile.php` · **record-form** + **list-table** · H1 **Your profile**
- Cards: Identity · Sessions (list-table, including MCP clients) · Security · Preferences
- Sessions columns: **Device / client** (row header) · IP (mono) · Location · Started (num) · actions
- Delta: preferences here own the client state the README names — theme, dock mode, sidebar collapse, table density, last filter. Each is a **switch or a select that takes effect immediately**, because they are personal and reversible.
- Delta: revoking a session is confirmed inline and names the device.

### 28. Licence
`licence.php` · **record-form** + **overview-stats** · H1 **Licence**
- Cards: Plan · Key (read-only mono + copy) · Activated domains (list-table) · Entitlements (stat row)
- Delta: the key is `readonly`, not `disabled`, and is selectable.
- Delta: an expired licence degrades this screen only — the admin keeps working, and the status bar carries one fact.

### 29. AI images
`ai-images.php` · **editor-split** · H1 **AI images**
- Rail: generation history · Canvas: prompt + result · Inspector: model, size, count, style
- Delta: a generated image **cannot be saved to the library until its alt text is written**; the alt field is next to the Save action, pre-filled with an AI draft the author must confirm. Accessibility §10.2.
- Delta: generation shows an indeterminate progressbar with an estimate and a cancel.

### 30. Options
`options.php` · **list-table** · H1 **Options**
- Columns: **Key** (row header, mono) · Value (mono, truncated) · Type · Autoload · Updated (num) · actions
- Filters: by domain — All · Core · Theme · Plugin · x402 · Intelligence
- Delta: this is the rawest screen in the admin. Values are `--type-body-mono`; editing opens a record-form page, never an inline field, because a bad edit here breaks the site.
- Delta: a value longer than the cell is truncated and the full value is on the option's page — never only in a `title`.

---

## `Klytos Admin - Screens 5.dc.html` — theme files, terms, and the machinery

### 31. Templates
`templates.php` · **gallery-grid** · H1 **Templates**
- Tile: wireframe preview 120px · template name · what it serves · parts used
- Cards below: Shared parts (list) · Source (a `console-stream` panel, read-only)
- Delta: each tile links to **Template preview**, not to an editor — you look before you change.

### 32. Taxonomies
`taxonomies.php` · **list-table** + **record-form** · H1 **Taxonomies**
- Terms columns: **Name** (row header, indented by depth) · Slug (mono) · Count (num) · Description · actions
- Delta: hierarchy is expressed with a real nested `<ul>` inside the row header cell **and** `aria-level` on the row — indentation alone is not structure.
- Add-term form sits in a card beside the table; it is a `record-form` with four fields.

### 33. Scheduled actions
`scheduled.php` · **list-table** + queue stats · H1 **Scheduled actions**
- Stats: Pending · Running · Failed · Completed (24h)
- Columns: **Hook** (row header, mono) · Status · Scheduled (num) · Attempts (num) · Duration (num) · actions
- Filters: All · Pending · Running · Failed · Completed
- Delta: "Run now" is a form post per row; the cron trigger card states the last run and the next due time in mono, with `<time datetime>`.
- Delta: a failed action shows its last error inline, one line, with a link to the log entry.

### 34. System integrity
`integrity.php` · **overview-stats** + **list-table** + diff · H1 **System integrity**
- Stats: Files checked · Signed · Modified · Unsigned · Trust level
- Columns: **Path** (row header, mono) · Expected hash (mono, truncated) · Found · Changed (num) · actions
- Delta: the diff view uses `+` / `−` prefixes with `<ins>` / `<del>`, so a monochrome print is readable. Colour is the second channel.
- Delta: "Verify signatures" is the on-demand check state; it announces start and finish once, never per file.

### 35. Updates
`updates.php` · **overview-stats** + **list-table** · H1 **Updates**
- Panel: the core release, its version in mono, and the changelog as real prose with headings
- Cards: Plugin updates (list-table with a batch action) · History (list-table with rollback)
- Delta: rollback is a two-step inline confirm naming the version it returns to.
- Delta: an update in progress disables **only** the update actions, with the reason in their names; the rest of the admin stays usable.

### 36. Transactions
`transactions.php` · **list-table** · H1 **Transactions**
- Columns: **Id** (row header, mono) · Page · Agent · Amount (num) · Network · Provider · Settled (num) · actions
- Filters: All · Settled · Pending · Failed · Refunded
- Delta: every amount is `--type-numeric` with the currency as text next to it; the network and provider are text, not logos.
- Empty: "No transactions yet. — **Review x402 pricing**"

### 37. x402 settings
`x402-settings.php` · **record-form** · H1 **Agent payment settings**
- Cards: Provider · Wallet (read-only mono + copy) · Pricing rules (repeatable rows) · Exempt agents (repeatable rows) · The 402 response body (`--type-code` textarea with a preview)
- Delta: the enable/disable control is a **switch** (immediate, and the consequence is stated next to it). Everything else is checkbox + Save.
- Delta: pricing rows are a repeatable group; add/remove are buttons that post, and each row has a visible label set.

### 38. Plugin page (example — not a product screen)
`plugins/<slug>/admin.php` · **record-form** · H1 = the plugin's name
- Purpose: the pattern a third-party plugin follows to live inside the shell.
- Delta: a plugin may add **one** sidebar entry, in the group its capability implies. It may not add a group, may not change the toolbar, and may not write to the shell's live regions except through the documented helper.
- Delta: the capabilities card lists what the plugin may do, in words, with the capability name in mono next to each.

---

## `Klytos Admin - Screens 6.dc.html` — the deep screens

### 39. Post type
`post-type.php` · **record-form** · H1 = the post type's name
- Cards: Identity · Editor choice · Custom fields (repeatable) · Statuses (editable set) · Per-locale slugs (one field per locale, each `lang`-tagged) · Exposure (REST, MCP, sitemap, feeds)
- Delta: exposure controls are **checkboxes + Save** — they change what the outside world can read and are reviewed as a set.
- Delta: the per-locale slug fields are in a `<fieldset>` whose `<legend>` is "Slugs by locale"; each field's label is the locale's name, and the field carries `lang`.
- Empty (custom fields): "No custom fields. A custom field adds a value to every record of this type. — **Add a field**"

### 40. Block data
`block-data.php` · **editor-split** · H1 = the global block's name
- Rail: global blocks · Canvas: slot editors · Inspector: stored JSON (`console-stream` panel, read-only) + placements (list)
- Delta: the JSON panel is read-only and is the record of truth; edits happen in the slot editors above it. The panel is `<pre tabindex="0">` with a label.
- Delta: "Placements" lists every page using this block; each is a link, and the count is in the card's `<h2>`.

### 41. Logs
`logs.php` · **console-stream** · H1 **Logs**
- Controls: level chips (links) · file `<select>` · search · Follow switch · Download
- Detail panel: context and stack for the selected line
- Delta: the stream is **not** `aria-live`; counts are announced politely on a 10-second floor. Rationale in console-stream §Polling.
- Delta: levels are mono text first (`ERROR`, `WARN`, `INFO`, `DEBUG`), tint second.

### 42. Template preview
`template-preview.php` · **preview-matrix** · H1 = the template's name
- Widths: 360 · 768 · 1024 · 1440, real `<iframe>`s, never scaled
- Checks: one `h1` · no skipped heading levels · a skip link · one `main` · every image has an alt decision · theme contrast pairs ≥ 4.5:1 · `lang` set · form labels · target sizes
- Delta: a template failing a **hard** check cannot be set as the site default. Hard = no `h1`, an image with no alt decision, a contrast pair below 4.5:1.

### 43. Reset password
`reset-password.php` · **auth-centered** · H1 **Choose a new password**
- Fields: new password (`new-password`) · confirm (`new-password`) · a visible, always-present rules list · a `<progress>` strength meter with a text label
- Delta: an expired token replaces the card entirely — there is nothing to fill in.
- Delta: success states what happened to the other sessions, in words.

---

## Not drawn in a prototype file — specified here

### 44. Dashboard
`index.php` · **overview-stats** · H1 **Dashboard**

The admin's landing screen and the target of the shell's brand link (`template-shell.md` §3).
It is an **overview-stats screen** — no twelfth template. Its job is one sentence: *what state
is this install in, and what should I do next.* It is deliberately **not** a fifth set of
numbers: Analytics owns traffic, Tasks owns work, Health owns checks, x402 owns money. The
Dashboard shows the state of the **install**, and every figure on it links to the screen that
owns the detail.

- Data: `Pages::count('all'|'published'|'draft')` · `siteConfig.last_build` ·
  `Auth::listBearerTokens()` (count) · Health failing-check count · Updates pending count ·
  `app.getVersion()` + `PHP_VERSION` · registered dashboard widgets + per-user visibility meta
- Stats (5 across, the template's maximum): **Last build** · **Pages** · **MCP** ·
  **Failing checks** · **Pending updates**
- Panel: *Next steps* (working site) or *Set up the site* (new site) — see the empty state
- Detail cards: the **widget grid** — core widgets *Quick actions* and *System info*, plus
  every plugin-registered widget
- Glyphs, all from the existing sprite, **no additions**: `ks-cloud_upload` (last build) ·
  `ks-description` (pages) · `ks-key` (MCP) · `ks-rule` (checks, `ks-task_alt` when zero) ·
  `ks-system_update_alt` (updates) · `ks-block` (indexing blocked) · `ks-check_circle` /
  `ks-chevron_right` (setup steps) · `ks-widgets`, `ks-dashboard_customize` (widget grid) ·
  `ks-warning`, `ks-sync_problem` (degraded card)

**Delta — no chart.** The Dashboard carries no chart, so `template-overview-stats.md` §4's
`<details>` data-table rule does not apply to it. The 30-day traffic line lives on Analytics
and is not duplicated here. If a plugin widget draws a chart, §4 applies to that widget in
full — the pattern is mandatory, not optional, and there is no second one.

**Delta — every stat card is a link**, one `<a>` wrapping the whole card, per the template:
Last build → `updates.php`, Pages → `pages.php`, MCP → `mcp.php`, Failing checks →
`health.php`, Pending updates → `updates.php`. Values are `--type-numeric`; **Last build** is
a `<time datetime>` reading `2026-07-28 14:03 UTC` in mono with "3 hours ago" as its
`--type-caption` detail line. A zero count is `0` here, not absent — on a stat card the
number is the answer; the sidebar's suppress-zero rule is about link labels.

**Delta — Klytos and PHP versions are not stat cards.** They are facts, not figures, and they
are already in the status bar and in the *System info* widget. Putting them in the stat row
spends two of five slots on something that never changes.

**Delta — the indexing warning is a banner, and only a warning.** While
`indexing_enabled` is false the screen opens with one `--tinte-aviso` banner above the stat
row: `ks-block`, "Search engines and AI crawlers are blocked. The site is live but will not
be indexed." plus the link **Open indexing settings** → `settings.php#advanced`. The banner
carries **no toggle** — a site-wide setting is not changed in passing from the landing screen.
It is `role="status"`, not `role="alert"` (it is true on arrival, it did not just happen), and
it is not dismissible: it disappears when the condition does. When indexing is enabled there
is no banner and no confirmation — the absence is the message.

**Delta — the widget grid is a frame with a contract.** Two columns, gap 14, ordinary cards
(radius 10, no coloured border). Each widget is `<section>` with an `<h2>` as its heading and
`aria-labelledby` bound to it. A widget may render text, a list, a table or a stat row; it may
not render a second-level card, a coloured left border, or anything sticky. Core widgets:

- *Quick actions* — a vertical stack of links (never buttons; they navigate): **Create a page**
  → `pages.php`, **Open design** → `design.php`, **Create an MCP token** → `mcp.php`,
  **Generate an image** → `ai-images.php`. Each link is filtered by capability before render,
  so a widget with nothing left to show renders nothing at all rather than an empty card.
- *System info* — a real `<table>` with a caption, per `SPEC/accessibility.md` §2.1: Klytos
  version, PHP version, server software. Values in `--type-body-mono`.

**Delta — per-user widget visibility.** The grid's own heading row carries one link,
**Choose widgets** (`ks-dashboard_customize`) → `profile.php#preferences`, where visibility is
a checkbox set in a `<fieldset legend="Dashboard widgets">` with Save. It is *not* a switch
set and *not* an inline menu on the Dashboard: it is a preference, it belongs with the other
preferences (entry 27), and a hidden widget must be recoverable from somewhere findable. The
grid never renders a "widget hidden" placeholder.

**Delta — empty state, and it is the important one.** A brand-new site has no pages, no
traffic, no transactions. The stat row still renders, with `—` as the value for Last build and
`0` for Pages (0 pages is a fact, not a claim); the **widget grid is replaced entirely** by a
*Set up the site* panel: an `<ol>` of three steps, each a row with its state as a word, its
sentence, and one action.

1. **Create the first page** — "A page is a URL with blocks on it." — **Create a page**
2. **Choose a theme** — "Colours, type and spacing for the whole site." — **Open design**
3. **Publish the site** — "Nothing is public until the first build runs." — **Build now**

Step state is text first: `Done` with `ks-check_circle` in `--color-exito`, `Next` on the
current step (the only one whose action is a primary button; the rest are secondary links),
`Later` in `--texto-sutil` on steps not yet reachable. A `Later` step's action is
`disabled` with the reason in its accessible name ("Build now — create a page first").
The panel disappears the moment step 3 is done; it does not linger as a congratulation, and
it is never dismissible while incomplete. The widget grid returns in its place.

**Delta — degraded subsystem: per-card only, no page banner.** Handled exactly as
`template-overview-stats.md` §Error does it — the affected card keeps its heading and replaces
its body ("MCP could not be read — the token store is unreachable. **Open Health**"), and the
tile turns `--tinte-peligro` / `--sobre-tinte-peligro` with the count as the failure count.
No page-level banner: `template-shell.md` §1 already gives degradation one status-bar fact and
says it never becomes a banner, and on this screen a banner would restate the card beside it.
The **Failing checks** card is the exception in tone, not in pattern — with zero failures it
reads "All 24 checks passed" with `ks-task_alt` in `--color-exito` and the last run time,
because a good answer must look like an answer.

**Accessibility** — the template's §4 applies in full; what is specific to this screen:
- One `<h1>`, "Dashboard", first in `<main id="main">`. Every card is an `<h2>`; nothing
  between them is a heading. The stat row has no wrapper heading — the cards self-label.
- The setup panel is `<ol>`, not a list of divs: the steps are ordered and the order is
  information. Its `<h2>` is "Set up the site", and each step's `<li>` names its state in text
  before its action.
- A linked stat card is one `<a>`; `aria-labelledby` binds value to label so it reads
  "4 — failing checks". The icon tile is `aria-hidden="true"`.
- The indexing banner is `role="status"` and sits inside `<main>`, before the `<h1>`? **No** —
  after it, so the heading is still the first thing in the document.
- **Build now** is the only write on the screen: a form post with CSRF, confirmed through the
  shell's single `aria-live="polite"` region ("Build finished — 3 pages published"), never a
  link.
- Nothing on the screen requires JavaScript. The widget grid, the setup panel and the banner
  are all server-rendered.

**Responsive** — the template's four breakpoints, unchanged. Below 1200 the stat row is 4
across and Pending updates wraps to a second row rather than shrinking below the 5-column
minimum; at < 900 the stat row is 1 across and the setup panel's steps stack with the action
below the sentence. There is no chart to substitute.

---

## Screens kept for reference, not for build

| File | What it is |
|---|---|
| `Klytos Admin - Redesign.dc.html` | Earlier exploration — dashboard, AI chat and pages, in a native-window and a browser reading |
| `Klytos Admin - Copilot dock.dc.html` | The copilot dock in its four modes; the modes table in `template-conversation.md` §3 is the normative version |
| `Klytos Admin - Current.dc.html` | The pre-redesign admin, for diffing |
