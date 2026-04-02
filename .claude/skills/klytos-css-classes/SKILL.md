---
name: Klytos CSS Classes
description: Complete reference of CSS design tokens, component classes, utility classes, and frontend styling in Klytos CMS. Use when building content, plugins, or admin templates that need consistent styling without hardcoding colors or writing custom CSS.
---

# Klytos CSS Classes & Design Tokens

## When to Use This Skill

Use this reference when building or modifying any UI in Klytos — admin pages, plugin interfaces, frontend content, or templates. **Always use design tokens and core CSS classes instead of hardcoding colors or writing custom CSS.**

---

## CSS Custom Properties (Design Tokens)

All tokens use the `--klytos-` prefix. Defined in `installer/admin/assets/css/klytos-tokens.css`.

### Quick Token Reference

**Colors:**
- Background: `--klytos-bg`, `--klytos-bg-elevated`, `--klytos-bg-sunken`
- Surfaces: `--klytos-surface`, `--klytos-surface-hover`, `--klytos-surface-active`
- Text: `--klytos-text`, `--klytos-text-secondary`, `--klytos-text-tertiary`
- Accent: `--klytos-accent`, `--klytos-accent-hover`, `--klytos-accent-subtle`
- Status: `--klytos-success`, `--klytos-warning`, `--klytos-error`, `--klytos-info` (and `-subtle`, `-text` variants)
- Sidebar: `--klytos-sidebar-bg`, `--klytos-sidebar-text`, `--klytos-sidebar-active`, `--klytos-sidebar-hover`

**Spacing & Sizing:**
- Radius: `--klytos-radius-sm`, `--klytos-radius`, `--klytos-radius-lg`, `--klytos-radius-xl`, `--klytos-radius-full`
- Shadows: `--klytos-shadow-sm`, `--klytos-shadow`, `--klytos-shadow-lg`
- Spacing: `--klytos-space-xs`, `--klytos-space-sm`, `--klytos-space`, `--klytos-space-md`, `--klytos-space-lg`, `--klytos-space-xl`, `--klytos-space-2xl`
- Fonts: `--klytos-font-sans`, `--klytos-font-mono`
- Transitions: `--klytos-transition-fast`, `--klytos-transition`, `--klytos-transition-slow`

---

## Admin Component Classes

| Class | Description |
|-------|-------------|
| `.btn` | Base button (required) |
| `.btn-primary` | Primary action (accent bg, white text) |
| `.btn-danger` | Destructive action (error bg) |
| `.btn-outline` | Transparent with border |
| `.btn-ghost` | Transparent, no border (text-only) |
| `.btn-sm`, `.btn-lg` | Size variants |
| `.btn-icon` | Square icon-only button (36x36) |
| `.card` | Content card (surface bg, border, padding, rounded) |
| `.card-header` | Card header (flex, space-between) |
| `.form-group` | Form field wrapper (margin-bottom) |
| `.form-control` | Input/select/textarea (full-width, bordered) |
| `.form-help` | Help text below input (small, muted) |
| `.toggle-switch` | Claude-style toggle (wraps `<input>` + `.toggle-track`) |
| `.alert`, `.alert-success`, `.alert-error`, `.alert-warning`, `.alert-info` | Alerts |
| `.badge-status` | Base badge (inline, pill-shaped) |
| `.badge-active`, `.badge-draft`, `.badge-urgent` | Status badges with color variants |
| `.table-wrap` | Overflow wrapper for tables |
| `table` | Styled by default (full-width, borders) |

---

## Utility Classes

### Flexbox & Grid

| Class | Description |
|-------|-------------|
| `.flex` | `display: flex` |
| `.flex-col` | `flex-direction: column` |
| `.flex-center` | `display: flex; align-items: center` |
| `.flex-between` | `display: flex; align-items: center; justify-content: space-between` |
| `.flex-gap-xs`, `.flex-gap-sm`, `.flex-gap`, `.flex-gap-md`, `.flex-gap-lg` | Gap helpers |
| `.grid-2`, `.grid-3`, `.grid-4` | 2, 3, 4-column equal grids (collapse to 1 on mobile) |
| `.grid-2-1`, `.grid-1-2` | 2fr/1fr and 1fr/2fr grids |

### Spacing

| Class | Description |
|-------|-------------|
| `.mb-0` through `.mb-4` | `0` to `2rem` bottom margin |
| `.mt-0` through `.mt-3` | `0` to `1.5rem` top margin |
| `.p-0` through `.p-3` | `0` to `1.5rem` padding |
| `.px-1`, `.px-2` | Horizontal padding |
| `.py-1`, `.py-2` | Vertical padding |

### Typography

| Class | Description |
|-------|-------------|
| `.text-xs`, `.text-sm`, `.text-base`, `.text-lg`, `.text-xl`, `.text-2xl` | Font sizes |
| `.text-muted`, `.text-dim`, `.text-accent` | Text colors |
| `.text-success`, `.text-warning`, `.text-error` | Status text colors |
| `.text-center`, `.text-right` | Alignment |
| `.text-upper` | Uppercase + tracking |
| `.text-mono` | Monospace font |
| `.font-normal`, `.font-medium`, `.font-bold`, `.font-heavy` | Font weights |
| `.truncate`, `.break-all` | Overflow handling |

### Display

| Class | Description |
|-------|-------------|
| `.hidden`, `.block`, `.inline-block` | Display modes |
| `.w-full`, `.w-auto` | Width |
| `.max-w-sm`, `.max-w-md`, `.max-w-lg` | Max-width (400/600/800px) |
| `.rounded`, `.rounded-lg`, `.rounded-full` | Border radius |
| `.border`, `.border-b` | Borders |

---

## Frontend CSS Classes (Public-Facing)

| Class | Description |
|-------|-------------|
| `.klytos-container` | Centered container with max-width and responsive padding |
| `.klytos-header`, `.klytos-header.sticky` | Page header |
| `.klytos-main` | Main content area |
| `.klytos-footer` | Footer |
| `.klytos-section`, `.klytos-section-alt` | Content sections |
| `.klytos-hero` | Hero section |
| `.klytos-grid-2`, `.klytos-grid-3`, `.klytos-grid-4` | Column grids |
| `.klytos-btn`, `.klytos-btn-primary`, `.klytos-btn-secondary`, `.klytos-btn-outline` | Buttons |
| `.klytos-card`, `.klytos-card-grid` | Cards |
| `.klytos-text-block`, `.klytos-image-text`, `.klytos-gallery`, `.klytos-cta`, `.klytos-faq`, `.klytos-stats`, `.klytos-testimonials`, `.klytos-team`, `.klytos-logos` | Content block components |

---

## Responsive Breakpoints

| Breakpoint | Target | Effect |
|------------|--------|--------|
| `max-width: 768px` | Mobile | Grids collapse, sidebar collapses, padding reduces |
| `max-width: 782px` | Editor | Editor sidebar hidden, header compressed |
| `max-width: 480px` | Small phones | Stats grid 1-column |

---

## Best Practices

1. Always use `--klytos-*` tokens — never hardcode colors
2. Always use component/utility classes — never use inline `style=""`
3. Test both themes — dark (default) and light mode
4. Test at 768px — ensure mobile layout works
5. Use plugin ID prefix for custom classes: `.my-plugin-widget`
6. Never override core classes — extend with your own selectors

---

## Source Files

| File | Purpose |
|------|---------|
| `installer/admin/assets/css/klytos-tokens.css` | All design tokens |
| `installer/admin/assets/css/klytos-base.css` | Reset, body, layout |
| `installer/admin/assets/css/klytos-components.css` | All components |
| `installer/admin/assets/css/klytos-utilities.css` | Utility classes |
| `installer/admin/templates/header.php` | Loads all CSS |

**For the complete token reference with all colors, sizes, and variants, see the `references/complete-tokens.md` file.**
