# Sidebar navigation — Klytos admin

The normative contents of the shell's `<nav id="k-nav" aria-label="Main">`. `template-shell.md`
specifies how the sidebar *behaves* — states, the 56px rail, the drawer, the accessibility
tree. This file specifies what is *in* it: the groups, their order, the items, their glyphs,
their targets and their counts.

This is the single source of truth. Where a prototype file's `navGroups` array disagrees with
this file, **this file wins** — the prototypes each drew the slice of the sidebar their screens
needed, no one file drew all eight groups, and the union of them contradicted itself. The
contradictions are resolved in §3–§7.

Every label here is the **nav label**, which is deliberately not always the screen's `<h1>`.
The nav is a map and reads in short nouns; the `<h1>` names the screen. Where they differ the
table says so.

---

## 1. The eight groups, in fixed order

Top to bottom, always this order, captions exactly as written:

1. **Site** · 2. **Content** · 3. **Design** · 4. **Intelligence** · 5. **Monetisation** ·
6. **Compliance** · 7. **System** · 8. **Account**

The order is fixed and is not personalisable. A group is never reordered, never collapsed by
default, and never renamed by a plugin. A group whose items are all hidden by capability
(§7) renders **nothing at all** — no caption, no empty `<ul>`.

Each caption is a visually-styled `<h2>` labelling its own `<ul>`, per `template-shell.md` §3.

---

## 2. The items

**Count column** — what the number to the right of the label counts. A zero count is absent,
not `0` (`template-shell.md` §1). The count is part of the link's accessible name
("Comments, 6 pending"). Counts are `--type-numeric`, `--texto-sutil`.

### Site

| Label | Glyph | Manifest entry | Count |
|---|---|---|---|
| Overview | `ks-space_dashboard` | 44 Dashboard (`index.php`) | — |
| Analytics | `ks-monitoring` | 7 Analytics | — |
| Tasks | `ks-checklist` | 13 Tasks | open tasks |

*Overview*, not "Dashboard": it is the brand link's target and the word the rest of the admin
uses when it sends you back ("Open the overview"). The `<h1>` stays **Dashboard**.

### Content

| Label | Glyph | Manifest entry | Count |
|---|---|---|---|
| Pages | `ks-description` | 1 Pages | pages, all statuses |
| Comments | `ks-forum` | 14 Comments | comments awaiting moderation |
| Assets | `ks-perm_media` | 4 Assets | items in the library |
| Content model | `ks-category` | 19 Content model | post types defined |
| Taxonomies | `ks-sell` | 32 Taxonomies | taxonomies defined |
| Translations | `ks-translate` | 20 Translations | strings not yet translated in any locale |

*Assets*, not "Media" — the screen's `<h1>` is **Assets** and the nav now agrees with it. The
older prototypes said "Media"; that label is retired.

### Design

| Label | Glyph | Manifest entry | Count |
|---|---|---|---|
| Theme | `ks-palette` | 3 Design (theme) | — |
| Templates | `ks-dashboard_customize` | 31 Templates | templates registered |
| Blocks | `ks-widgets` | 21 Blocks | blocks registered |

*Theme* is the nav label for the screen whose `<h1>` is **Design** — the group is already
called Design and an item repeating its group caption reads as an error.

### Intelligence

| Label | Glyph | Manifest entry | Count |
|---|---|---|---|
| AI chat | `ks-auto_awesome` | 12 AI chat | — |
| AI images | `ks-imagesmode` | 29 AI images | — |
| MCP | `ks-smart_toy` | 8 MCP | connected clients |
| Webhooks | `ks-webhook` | 24 Webhooks | deliveries failed in the last 24 h |

*MCP*, not "Connections": the screen's `<h1>` is **MCP**, the protocol is the thing being
configured, and "Connections" was vague about what it connected. The prototypes' "Connections"
label is retired.

### Monetisation

| Label | Glyph | Manifest entry | Count |
|---|---|---|---|
| Agent payments | `ks-toll` | 18 x402 dashboard | — |
| Transactions | `ks-receipt_long` | 36 Transactions | — |
| Payment settings | `ks-tune` | 37 x402 settings | — |

Labels say *agent payments*, not *x402*: x402 is the protocol, and it belongs in the screens'
prose and in their `<h1>`s (**Agent payments**, **Agent payment settings**), not in a nav
label a new owner has to decode.

**Transactions carries no count.** The prototypes showed `5.1k`; a lifetime transaction total
is not something to act on, and a nav count is a call to action. Failures are surfaced as a
Task (entry 13), which does carry one.

### Compliance

| Label | Glyph | Manifest entry | Count |
|---|---|---|---|
| Consent | `ks-cookie` | 25 Consent | — |
| Privacy | `ks-policy` | 26 Privacy | open export and erasure requests |

Two items is the right size for this group. **System integrity** stays in *System* — it is
file signing and tamper detection, an operations concern, and the prototypes already placed
it there.

### System

| Label | Glyph | Manifest entry | Count |
|---|---|---|---|
| Users | `ks-group` | 5 Users | invitations not yet accepted |
| Security | `ks-shield` | 6 Security | — |
| Plugins | `ks-extension` | 15 Plugins | plugins with an update available |
| Updates | `ks-system_update_alt` | 35 Updates | updates pending, core and plugins |
| Integrity | `ks-verified_user` | 34 System integrity | files modified or unsigned |
| Health | `ks-monitor_heart` | 22 Health | failing checks |
| Scheduled actions | `ks-schedule` | 33 Scheduled actions | actions in the failed state |
| Logs | `ks-format_align_left` | 41 Logs | — |
| Terminal | `ks-terminal` | 23 Terminal | — |
| Options | `ks-data_object` | 30 Options | — |
| Settings | `ks-tune` | 9 Settings | — |

Eleven items, and *Settings* is always last — it is where you go when nothing more specific
fits, so it sits at the bottom of the group where that reading is natural.

**Logs and Options carry no count.** A log line count and an option count are magnitudes, not
work; `2.8k` next to *Logs* asks you to do something about 2 800 log lines, and there is
nothing to do. Errors reach you through Tasks and Health.

**Plugins counts updates, not installations.** How many plugins are installed is not news.

### Account

| Label | Glyph | Manifest entry | Count |
|---|---|---|---|
| Profile | `ks-account_circle` | 27 Profile | — |
| Licence | `ks-workspace_premium` | 28 Licence | — |

This group is the *screens* about you and your install's entitlement. The account **row** at
the foot of the sidebar — avatar, name, role, theme toggle, log out — is separate furniture
specified in `template-shell.md` §1, and is not part of this `<nav>`.

### Glyph uniqueness

Every glyph appears exactly once in the sidebar, with one deliberate exception:
**`ks-tune` is the settings mark** and appears twice — *Settings* (System) and *Payment
settings* (Monetisation). Group and label disambiguate them, and inventing a second settings
glyph would weaken the first. No other glyph may be reused.

---

## 3. Blocks: **Design**

One answer, and it is *Design*. Two prototypes (Screens 3, Screens 6) put Blocks under
Content; four put it under Design. Design is correct: entry 21 is a **gallery of registered
block types** with wireframe previews, categories and usage counts — an inventory of the
building set, which is what Templates and Theme also are. *Content* holds records a person
authored: pages, comments, assets, terms, strings. A block type is not a record.

The corollary: **Block data** (entry 40) — the *content* of a global block — is not a nav item
at all (§5), which is what made Blocks look like a Content screen in the two files that drew
it there.

---

## 4. Guides: **dropped**

*Guides (16)* is removed from the sidebar. It is not a manifest entry, it has no
`SPEC/screens/` file, nothing links to it, and no one specified what a guide is. Shipping a
nav item to a screen that does not exist is worse than shipping no item.

Help is already answered twice over: `⌘K` accepts `help` (`template-shell.md` §1, Command
palette), and screens carry contextual help in place. If product later wants a documentation
library, it arrives as a manifest entry with a template and a `SPEC/screens/` file, and it
joins **Site** below *Tasks*. It does not arrive as a nav item first.

The manifest stays at **44 entries**.

---

## 5. The entries with no nav home

Seven manifest entries are not in the sidebar. Six are reached only from a parent screen; one
is not shipped at all. This is deliberate: the sidebar lists **destinations you choose**, not
every URL the admin can render.

| Entry | Reached from | Why not a nav item |
|---|---|---|
| 2 Page editor | Pages (entry 1) — a row's title, or *Create a page* | It edits *one* page. There is no "the" page to open. |
| 39 Post type | Content model (entry 19) — a row in the Post types list | Edits one type. The list is the destination. |
| 40 Block data | Blocks (entry 21) — a global block's tile | Edits one global block's content. |
| 42 Template preview | Templates (entry 31) — a tile links here, never to an editor | It previews one template. |
| 11 Verify | Log in (entry 10) | No shell: it is a step inside signing in. |
| 17 Setup wizard | The installer, before an account exists | No shell, no sidebar, by design (its own step rail). |
| 16 States | Not shipped | A design specimen sheet, not a screen (entry 16, and open question 12). |

The three auth screens — 10 Log in, 11 Verify, 43 Reset password — do not use the shell at
all, so the sidebar does not exist on them.

**Breadcrumbs carry the parentage.** A child screen's breadcrumb is
`klytos.io › Pages › About us`, and while a child is open **the parent's nav item is the one
that carries `aria-current="page"`** — the sidebar never goes blank because you went one level
deeper.

---

## 6. Plugins in the sidebar

A plugin may contribute **one** item (entry 38 already fixes this: one entry, no new group, no
toolbar change).

**Which group.** The group its primary capability implies, declared by the plugin and
validated at registration against this table:

| Primary capability | Group |
|---|---|
| `content.*` | Content |
| `design.*` | Design |
| `ai.*`, `mcp.*` | Intelligence |
| `payments.*` | Monetisation |
| `privacy.*`, `consent.*` | Compliance |
| anything else, or none declared | System |

A plugin cannot choose *Site* or *Account*: Site is the install's own state and Account is the
person's, and neither is a plugin's to occupy.

**Where in the group.** Plugin items always sort **after every core item** in their group, and
alphabetically among themselves. No separator, no "Plugins" sub-caption, no visual difference
from a core item — inside the shell a plugin screen is a screen. This install ships two:
*Klytos Forms* (`content.forms`) lands at the foot of **Content** with `ks-dynamic_form`, and
*Klytos SEO* (`content.meta`) lands after it. A plugin's glyph is chosen by the plugin from
this sprite; if it names a glyph that is not in the sprite, or one already used by a core item
in its group, the shell falls back to `ks-extension`.

**Several plugins.** Beyond **five** plugin items in one group, that group shows the first
five (alphabetically) and then one item, **More plugins**, `ks-more_horiz`, linking to
`plugins.php` — which always lists every active plugin and its screen. The sidebar has a
bound; the Plugins screen is the complete list. There is no scroll-within-a-group and no
per-group disclosure.

A plugin item **never** carries a count. Counts are the admin's language for its own work, and
a plugin has no way to earn the same trust in it.

---

## 7. Missing capability: **hidden**

An item the person lacks the capability for is **not rendered**. Not disabled, not greyed,
not shown with a reason.

The sidebar is a map of what this person can do. A disabled row that will never enable is
noise on every page load, and it tells an author exactly which surfaces exist on this install
and who has them — a shape worth not publishing. If a whole group empties, the group vanishes
with it (§1).

This is not in tension with the admin's usual rule. Inside a screen a control the person
cannot use is **shown disabled with the reason in its accessible name** (manifest entries 1, 5,
35; `SPEC/accessibility.md` §7) — because there the person is looking at a specific object and
the absence of its action would be a mystery. Navigation is the opposite case: nothing is
missing, because there was never anything there.

**Consequences the build must handle:**

- Capability filtering happens **server-side, before render**. The markup for a hidden item is
  never sent.
- A person who reaches a screen they cannot see in the nav (a bookmark, a link in an email)
  gets the standard 403 screen, not a redirect to the overview — silently moving someone is
  worse than telling them.
- The **Overview** item is the one item that is always present for every authenticated person.
  Someone with nothing else can still land somewhere.
- The count on a visible item is computed with the same capability filter as its screen: if a
  person can see only their own pages, *Pages* counts only theirs.

---

## 8. The three shell controls, and their glyphs

`template-shell.md` §1–§2 names these controls and their behaviour but never named a glyph.
They are named here; two are drawn, one is deliberately not.

| Control | Where | Glyph |
|---|---|---|
| **Navigation** (opens the off-canvas drawer) | first control in the toolbar, below 900px | **`ks-menu`** |
| **Expand navigation** | foot of the 56px icon rail, 900–1199px | **`ks-chevron_right`** |
| **Theme toggle** | account row, all widths | **none — text only** |

`ks-menu` is new in the sprite for this control (`SPEC/assets-index.md` §3). The other
candidates in the sprite are already spoken for: `ks-more_horiz` is the toolbar's overflow
menu, `ks-format_align_left` is *Logs*.

**Expand navigation** points right because it widens the rail; it is icon-only with the
accessible name "Expand navigation". There is no matching collapse button — collapsing is the
breakpoint's job, per `template-shell.md` §2.

**The theme toggle carries no glyph, and this is the decision, not an omission.** It is a
`<button aria-pressed>` whose visible text is its state target — "Switch to dark mode" /
"Switch to light mode". A sun/moon pair would need two new symbols, would have to swap on
press, and would say less than the words already there; an icon-only toggle whose meaning is
its *current* versus *next* state is a well-known source of "which one am I in?". The account
row has the horizontal space, and the text is the clearer control.
