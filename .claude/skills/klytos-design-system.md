---
name: klytos-design-system
description: Design principles, color philosophy, component composition patterns, and rules for maintaining visual consistency across the Klytos admin panel. Reference this BEFORE writing any HTML/CSS in admin pages.
trigger: When building, modifying, or reviewing any admin page UI, form, table, card, modal, or component in Klytos.
---

# Klytos Design System

## Design Principles

1. **Dark-first** — Dark mode is the default. All designs start dark, light mode adapts.
2. **Warm neutrals** — Backgrounds use warm-neutral tones (`#1a1a1a`, `#262626`, `#333333`), NOT cold navy/slate.
3. **Generous whitespace** — Prefer spacing over density. Use `--klytos-space-lg` (1.5rem) for section padding.
4. **Subtle borders** — Borders are barely visible separators (`#3a3a3a` dark, `#e0e0e0` light), never heavy.
5. **Blue accent** — Interactive elements use `--klytos-accent` blue. Status colors (green/yellow/red) for feedback only.
6. **Sidebar always dark** — Even in light mode, the sidebar stays dark.
7. **System font stack** — No custom font loading. Use the system sans-serif (`--klytos-font-sans`).

---

## Color Usage Rules

### When to use each token

| Token | Use for |
|-------|---------|
| `--klytos-bg` | Page background |
| `--klytos-bg-elevated` | Cards, modals, dropdowns (things that "float") |
| `--klytos-bg-sunken` | Inset areas: code blocks, inputs, nested containers |
| `--klytos-surface` | Panel/card backgrounds within the main area |
| `--klytos-surface-hover` | Hover state for any surface |
| `--klytos-border` | Standard borders (cards, inputs, tables) |
| `--klytos-border-subtle` | Very light separators (table rows, internal dividers) |
| `--klytos-text` | Primary text |
| `--klytos-text-secondary` | Labels, help text, secondary info |
| `--klytos-text-tertiary` | Placeholders, disabled text, timestamps |
| `--klytos-accent` | Links, active tabs, primary buttons, toggle ON |
| `--klytos-accent-subtle` | Tinted backgrounds for accent (active sidebar item, selected row) |
| `--klytos-success` / `-subtle` / `-text` | Success state feedback |
| `--klytos-warning` / `-subtle` / `-text` | Warning state feedback |
| `--klytos-error` / `-subtle` / `-text` | Error state feedback, destructive buttons |

### NEVER do

- Hard-code colors (`#2563eb`, `rgb(...)`) — always use `var(--klytos-*)` tokens
- Use inline `style="color:..."` — use utility classes (`.text-muted`, `.text-error`) or component CSS
- Use `--admin-*` or `--chat-*` in new code — those are backward-compat aliases only
- Mix light and dark hardcoded values in the same component
- Use `rgba()` for subtle backgrounds — use the `-subtle` token variants instead

---

## Component Composition Patterns

### Settings Page Layout

```html
<div class="card">
    <div class="card-header">
        <h3>Section Title</h3>
        <span class="text-muted text-sm">Description</span>
    </div>

    <div class="form-group">
        <label>Field Label</label>
        <input type="text" class="form-control">
        <p class="form-help">Help text here</p>
    </div>

    <!-- Toggle setting (Claude-style) -->
    <div class="flex-between mb-2">
        <div>
            <strong>Feature Name</strong>
            <p class="text-muted text-sm">Description of this feature</p>
        </div>
        <label class="toggle-switch">
            <input type="checkbox" name="feature" value="1">
            <span class="toggle-track"></span>
        </label>
    </div>
</div>
```

### Data Table

```html
<div class="card">
    <div class="card-header">
        <h3>Items</h3>
        <button class="btn btn-primary btn-sm">Add New</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Item name</td>
                    <td><span class="badge-status badge-active">Active</span></td>
                    <td>
                        <button class="btn btn-ghost btn-sm">Edit</button>
                        <button class="btn btn-ghost btn-sm text-error">Delete</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
```

### Connector/Integration List (Claude-style)

```html
<div class="card">
    <div class="card-header">
        <h3>Connections</h3>
        <button class="btn btn-outline btn-sm">Explore</button>
    </div>
    <ul class="connector-list">
        <li class="connector-list-item">
            <div class="connector-icon"><img src="icon.svg" alt=""></div>
            <div class="connector-info">
                <div class="connector-name">Service Name</div>
                <div class="connector-detail">https://example.com</div>
            </div>
            <span class="connector-status">Connected</span>
            <button class="btn btn-outline btn-sm">Configure</button>
        </li>
    </ul>
</div>
```

### Dashboard Stats

```html
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Pages</div>
        <div class="stat-value">42</div>
        <div class="stat-detail">3 published today</div>
    </div>
</div>
```

### Selection Cards (radio/checkbox groups)

```html
<div class="selection-cards cols-2">
    <label class="selection-card">
        <input type="radio" name="choice" value="a" checked>
        <div class="selection-card-body">
            <span class="selection-card-title">Option A</span>
            <span class="selection-card-desc">Description of option A</span>
        </div>
    </label>
    <label class="selection-card">
        <input type="radio" name="choice" value="b">
        <div class="selection-card-body">
            <span class="selection-card-title">Option B</span>
            <span class="selection-card-desc">Description of option B</span>
        </div>
    </label>
</div>
```

Variants: `.cols-2`, `.cols-3` for grid columns. `.selection-card-body.horizontal` for side-by-side layout. `.selection-card-body.centered` for icon-centered cards (like the importer). JS in `footer.php` auto-highlights on change.

### Alert Messages

```html
<div class="alert alert-success">Settings saved successfully.</div>
<div class="alert alert-warning">This action requires confirmation.</div>
<div class="alert alert-error">An error occurred.</div>
```

### Modal Dialog

```html
<div class="modal-overlay" id="my-modal">
    <div class="modal">
        <h3>Confirm Action</h3>
        <p class="text-muted">Are you sure?</p>
        <div class="flex-end flex-gap mt-3">
            <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
            <button class="btn btn-danger">Delete</button>
        </div>
    </div>
</div>
```

---

## Layout Rules

- **Page structure**: Always use `header.php` → `sidebar.php` → content → `footer.php`
- **Content width**: Pages fill `admin-main` (padded 1.5rem). No max-width by default.
- **Cards**: Use `.card` for any content block. Don't use bare `<div>` with inline bg/border.
- **Grids**: Use `.grid-2`, `.grid-3`, `.grid-2-1` classes. All collapse to 1-column on mobile.
- **Spacing**: Use utility classes (`.mb-2`, `.mt-1`, `.flex-gap`) instead of inline `style="margin:..."`.
- **Flexbox**: Use `.flex-center`, `.flex-between`, `.flex-end` instead of inline flex styles.

---

## Button Hierarchy

| Priority | Class | Use for |
|----------|-------|---------|
| Primary | `.btn .btn-primary` | Main action per page (Save, Create, Publish) |
| Outline | `.btn .btn-outline` | Secondary actions (Cancel, Back, Configure) |
| Ghost | `.btn .btn-ghost` | Tertiary actions, table row actions (Edit, View) |
| Danger | `.btn .btn-danger` | Destructive actions (Delete, Remove) |
| Icon | `.btn .btn-icon` | Icon-only buttons (close, settings, hamburger) |

Use `.btn-sm` for buttons inside tables, cards, or compact areas.

---

## Form Patterns

- Always wrap inputs in `.form-group` with a `<label>` and optional `.form-help`
- Use `.form-control` for all inputs, selects, and textareas
- Use `.toggle-switch` for boolean on/off settings (preferred over checkboxes)
- Required fields: add `<span style="color:var(--klytos-error);">*</span>` inside the label
- Dark mode: `.form-control` gets `--klytos-bg-sunken` background automatically

---

## Badge / Status Patterns

Use `.badge-status` + variant class for all status indicators:

| State | Class | Color |
|-------|-------|-------|
| Active/Published | `.badge-active` / `.badge-published` | Green |
| Draft/Inactive | `.badge-draft` / `.badge-inactive` | Gray / Red |
| Warning/In Progress | `.badge-premium` / `.badge-in_progress` | Yellow |
| Error/Urgent | `.badge-urgent` | Red |
| Info/Open | `.badge-open` / `.badge-medium` | Blue |

---

## Responsive Rules

- **768px**: Sidebar collapses to 60px icons-only. Grids become 1-column. Cards reduce padding.
- **480px**: Stats grid becomes 1-column.
- **Editor (782px)**: Sidebar hidden, header compressed to 48px.
- Always test new components at 768px before merging.

---

## CSS Architecture

### File organization

```
installer/admin/assets/css/
├── klytos-tokens.css      ← Design tokens (EDIT HERE to change colors globally)
├── klytos-base.css        ← Reset, body, layout (admin-layout, topbar, admin-main)
├── klytos-components.css  ← All reusable components (buttons, cards, forms, etc.)
├── klytos-sidebar.css     ← Admin sidebar navigation
├── klytos-utilities.css   ← Utility classes (grid, flex, spacing, text)
├── ai-chat.css            ← AI chat page (uses --chat-* mapped to --klytos-*)
├── klytos-editor.css      ← Gutenberg editor overrides + dark mode
├── klytos-logs.css        ← Log viewer page
└── klytos-plugins.css     ← Plugin management page
```

### Adding new styles

1. **Component styles** → add to `klytos-components.css`
2. **Utility classes** → add to `klytos-utilities.css`
3. **Page-specific styles** → create `klytos-{page}.css`, load via `admin.stylesheets` filter
4. **Plugin styles** → plugin's own CSS file, using `--klytos-*` tokens
5. **NEVER** add styles inline in PHP `<style>` blocks or `style=""` attributes

### Token fallback pattern

Always include a fallback when using tokens, for resilience:
```css
color: var(--klytos-text, #e8e8e8);
```

---

## Hooks for Styling

- `klytos_apply_filters('admin.stylesheets', [])` — add CSS URLs to load
- `klytos_do_action('admin.head', $cspNonce)` — inject `<style>` with nonce (last resort)
- `klytos_do_action('admin.head_meta', $cspNonce)` — add meta tags

---

## Checklist for New Admin Pages

- [ ] Uses `header.php` / `sidebar.php` / `footer.php` template structure
- [ ] All colors use `--klytos-*` tokens (no hardcoded hex)
- [ ] Uses `.card`, `.btn`, `.form-group`, etc. component classes
- [ ] No inline `style=""` (use utility classes instead)
- [ ] Responsive at 768px (test collapsed sidebar)
- [ ] Dark and light modes both look correct
- [ ] All `<script>` tags have `nonce="<?= klytos_esc_attr($cspNonce) ?>"`
- [ ] Action hooks present: `admin.{page}.before`, `admin.{page}.after`
- [ ] Form CSRF: uses `klytos_csrf_field()` and `klytos_verify_csrf()`

---

## Source Files

- Tokens: `installer/admin/assets/css/klytos-tokens.css`
- Components: `installer/admin/assets/css/klytos-components.css`
- Base layout: `installer/admin/assets/css/klytos-base.css`
- Sidebar: `installer/admin/assets/css/klytos-sidebar.css`
- Utilities: `installer/admin/assets/css/klytos-utilities.css`
- Header template: `installer/admin/templates/header.php`

