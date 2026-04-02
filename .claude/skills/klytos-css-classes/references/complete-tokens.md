# Complete Klytos CSS Tokens & Classes Reference

## All Design Tokens

### Backgrounds

| Token | Dark Default | Light Default | Use for |
|-------|-------------|---------------|---------|
| `--klytos-bg` | `#1a1a1a` | `#f5f5f5` | Page background |
| `--klytos-bg-elevated` | `#2d2d2d` | `#ffffff` | Cards, modals, dropdowns |
| `--klytos-bg-sunken` | `#141414` | `#ebebeb` | Inputs, code blocks, inset areas |

### Surfaces

| Token | Dark Default | Light Default | Use for |
|-------|-------------|---------------|---------|
| `--klytos-surface` | `#262626` | `#ffffff` | Panel/card backgrounds |
| `--klytos-surface-hover` | `#333333` | `#f9f9f9` | Hover state for surfaces |
| `--klytos-surface-active` | `#3a3a3a` | `#f0f0f0` | Active/pressed state |

### Borders

| Token | Dark Default | Light Default | Use for |
|-------|-------------|---------------|---------|
| `--klytos-border` | `#3a3a3a` | `#e0e0e0` | Standard borders |
| `--klytos-border-subtle` | `#2e2e2e` | `#ebebeb` | Subtle separators |

### Text

| Token | Dark Default | Light Default | Use for |
|-------|-------------|---------------|---------|
| `--klytos-text` | `#e8e8e8` | `#1a1a1a` | Primary text |
| `--klytos-text-secondary` | `#999999` | `#666666` | Labels, muted text |
| `--klytos-text-tertiary` | `#666666` | `#999999` | Placeholders, disabled |

### Accent

| Token | Dark Default | Light Default | Use for |
|-------|-------------|---------------|---------|
| `--klytos-accent` | `#5b8def` | `#2563eb` | Links, buttons, active elements |
| `--klytos-accent-hover` | `#4a7de0` | `#1d4ed8` | Hover state |
| `--klytos-accent-subtle` | `rgba(91,141,239,0.12)` | `rgba(37,99,235,0.08)` | Tinted accent backgrounds |
| `--klytos-accent-text` | `#ffffff` | `#ffffff` | Text on accent background |

### Status Colors

| Token | Value | Use for |
|-------|-------|---------|
| `--klytos-success` | `#22c55e` | Success indicators |
| `--klytos-success-subtle` | `rgba(34,197,94,0.12)` | Success backgrounds |
| `--klytos-success-text` | `#86efac` (dark) / `#15803d` (light) | Success text |
| `--klytos-warning` | `#f59e0b` | Warning indicators |
| `--klytos-warning-subtle` | `rgba(245,158,11,0.12)` | Warning backgrounds |
| `--klytos-warning-text` | `#fcd34d` (dark) / `#b45309` (light) | Warning text |
| `--klytos-error` | `#ef4444` | Error indicators |
| `--klytos-error-subtle` | `rgba(239,68,68,0.12)` | Error backgrounds |
| `--klytos-error-text` | `#fca5a5` (dark) / `#dc2626` (light) | Error text |
| `--klytos-info` | `#5b8def` | Info indicators |
| `--klytos-info-subtle` | `rgba(91,141,239,0.12)` | Info backgrounds |
| `--klytos-info-text` | `#a5b4fc` (dark) / `#1d4ed8` (light) | Info text |

### Sidebar

| Token | Value | Use for |
|-------|-------|---------|
| `--klytos-sidebar-bg` | `#141414` | Sidebar background (always dark) |
| `--klytos-sidebar-text` | `#999999` | Sidebar text color |
| `--klytos-sidebar-active` | `rgba(91,141,239,0.15)` | Active item background |
| `--klytos-sidebar-hover` | `rgba(255,255,255,0.08)` | Hover state |
| `--klytos-sidebar-border` | `rgba(255,255,255,0.1)` | Sidebar internal borders |

### Border Radius

| Token | Value |
|-------|-------|
| `--klytos-radius-sm` | `4px` |
| `--klytos-radius` | `8px` |
| `--klytos-radius-lg` | `12px` |
| `--klytos-radius-xl` | `16px` |
| `--klytos-radius-full` | `9999px` |

### Shadows

| Token | Value |
|-------|-------|
| `--klytos-shadow-sm` | `0 1px 3px rgba(0,0,0,0.3)` |
| `--klytos-shadow` | `0 4px 12px rgba(0,0,0,0.25)` |
| `--klytos-shadow-lg` | `0 20px 60px rgba(0,0,0,0.5)` |

### Typography

| Token | Value |
|-------|-------|
| `--klytos-font-sans` | `-apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', sans-serif` |
| `--klytos-font-mono` | `'JetBrains Mono', 'Fira Code', 'Cascadia Code', Consolas, monospace` |

### Spacing

| Token | Value |
|-------|-------|
| `--klytos-space-xs` | `0.25rem` |
| `--klytos-space-sm` | `0.5rem` |
| `--klytos-space` | `0.75rem` |
| `--klytos-space-md` | `1rem` |
| `--klytos-space-lg` | `1.5rem` |
| `--klytos-space-xl` | `2rem` |
| `--klytos-space-2xl` | `3rem` |

### Transitions

| Token | Value |
|-------|-------|
| `--klytos-transition-fast` | `0.12s ease` |
| `--klytos-transition` | `0.2s ease` |
| `--klytos-transition-slow` | `0.3s ease` |

### Layout

| Token | Value |
|-------|-------|
| `--klytos-sidebar-width` | `260px` |
| `--klytos-sidebar-collapsed` | `60px` |

## All Component Classes

### Buttons

| Class | Description |
|-------|-------------|
| `.btn` | Base button (required) |
| `.btn-primary` | Primary action (accent bg, white text) |
| `.btn-danger` | Destructive action (error bg) |
| `.btn-outline` | Transparent with border |
| `.btn-ghost` | Transparent, no border (text-only) |
| `.btn-sm` | Small variant |
| `.btn-lg` | Large variant |
| `.btn-icon` | Square icon-only button (36x36) |

### Cards

| Class | Description |
|-------|-------------|
| `.card` | Content card (surface bg, border, padding, rounded) |
| `.card-header` | Card header (flex, space-between) |

### Forms

| Class | Description |
|-------|-------------|
| `.form-group` | Form field wrapper (margin-bottom) |
| `.form-control` | Input/select/textarea (full-width, bordered) |
| `.form-help` | Help text below input (small, muted) |
| `.toggle-switch` | Claude-style toggle (wraps `<input>` + `.toggle-track`) |

### Tables

| Class | Description |
|-------|-------------|
| `.table-wrap` | Overflow wrapper for tables |
| `table` | Styled by default (full-width, borders) |

### Alerts

| Class | Description |
|-------|-------------|
| `.alert` | Base alert |
| `.alert-success` | Green success |
| `.alert-error` | Red error |
| `.alert-warning` | Yellow warning |
| `.alert-info` | Blue info |

### Badges

| Class | Description |
|-------|-------------|
| `.badge-status` | Base badge (inline, pill-shaped) |
| `.badge-active`, `.badge-published`, `.badge-completed` | Green |
| `.badge-draft`, `.badge-viewer`, `.badge-dismissed` | Gray |
| `.badge-inactive`, `.badge-urgent` | Red |
| `.badge-premium`, `.badge-in_progress` | Yellow |
| `.badge-medium`, `.badge-admin`, `.badge-open` | Blue |
| `.badge-low`, `.badge-editor` | Green |
| `.badge-high` | Orange |
| `.badge-owner` | Purple |

### Tabs

| Class | Description |
|-------|-------------|
| `.tabs` | Tab container (flex, border-bottom) |
| `.tab` | Individual tab |
| `.tab.active` | Active tab (accent color + border) |

### Modals

| Class | Description |
|-------|-------------|
| `.modal-overlay` | Full-screen overlay (hidden by default) |
| `.modal-overlay.active` | Visible overlay |
| `.modal` | Modal box (surface bg, rounded, shadow) |

### Dropdowns

| Class | Description |
|-------|-------------|
| `.dropdown-menu` | Dropdown container |
| `.dropdown-item` | Dropdown option |
| `.dropdown-item.active` | Selected option |
| `.dropdown-divider` | Separator line |

### Connector List

| Class | Description |
|-------|-------------|
| `.connector-list` | List container |
| `.connector-list-item` | Integration row (icon + info + status + action) |
| `.connector-icon` | Icon container |
| `.connector-info` | Name + detail wrapper |
| `.connector-name` | Service name |
| `.connector-detail` | URL or subtitle |
| `.connector-status` | Connection status text |

### Stats Grid

| Class | Description |
|-------|-------------|
| `.stats-grid` | Auto-fit grid for stat cards |
| `.stat-card` | Individual stat card |
| `.stat-label` | Stat label (small, uppercase) |
| `.stat-value` | Stat number (large, bold) |
| `.stat-detail` | Extra detail (small, muted) |

### Selection Cards

| Class | Description |
|-------|-------------|
| `.selection-cards` | Grid container for radio/checkbox cards |
| `.selection-cards.cols-2` | 2-column layout |
| `.selection-cards.cols-3` | 3-column layout |
| `.selection-card` | `<label>` wrapping hidden input + card body |
| `.selection-card-body` | Visual card (border, padding, transitions) |
| `.selection-card-body.horizontal` | Side-by-side layout |
| `.selection-card-body.centered` | Icon-centered layout |
| `.selection-card-title` | Card title text |
| `.selection-card-desc` | Card description text |
| `.selection-card-icon` | Icon element inside card |

JS in `footer.php` auto-adds `.selected` class on input change.

### Other Components

| Class | Description |
|-------|-------------|
| `.empty-state` | Centered empty state with icon/heading |
| `.mono` | Monospace text |
| `.token-display` | Token/key display (sunken bg, monospace) |
| `.priority-dot` + `.urgent`/`.high`/`.medium`/`.low` | Colored dot indicator |
| `.chart-bar` / `.chart-bar-item` | Simple bar chart |

## All Utility Classes

### Grid System

| Class | Description |
|-------|-------------|
| `.grid-2` | 2-column equal grid |
| `.grid-3` | 3-column equal grid |
| `.grid-4` | 4-column equal grid |
| `.grid-2-1` | 2fr + 1fr grid |
| `.grid-1-2` | 1fr + 2fr grid |

All grids collapse to 1 column on mobile (768px).

### Flexbox

| Class | Description |
|-------|-------------|
| `.flex` | `display: flex` |
| `.flex-col` | `flex-direction: column` |
| `.flex-center` | `display: flex; align-items: center` |
| `.flex-between` | `display: flex; align-items: center; justify-content: space-between` |
| `.flex-end` | `justify-content: flex-end` |
| `.flex-wrap` | `flex-wrap: wrap` |
| `.flex-1` | `flex: 1` |
| `.flex-gap-xs` | `gap: 0.25rem` |
| `.flex-gap-sm` | `gap: 0.5rem` |
| `.flex-gap` | `gap: 0.75rem` |
| `.flex-gap-md` | `gap: 1rem` |
| `.flex-gap-lg` | `gap: 1.5rem` |

### Spacing - Margins

| Class | Value |
|-------|-------|
| `.mb-0` through `.mb-4` | `0` to `2rem` bottom margin |
| `.mt-0` through `.mt-3` | `0` to `1.5rem` top margin |
| `.mr-1`, `.mr-2` | Right margin |
| `.ml-auto` | Push to right |

### Spacing - Padding

| Class | Value |
|-------|-------|
| `.p-0` through `.p-3` | `0` to `1.5rem` padding |
| `.px-1`, `.px-2` | Horizontal padding |
| `.py-1`, `.py-2` | Vertical padding |

### Typography

| Class | Description |
|-------|-------------|
| `.text-xs` | `0.75rem` |
| `.text-sm` | `0.85rem` |
| `.text-base` | `0.9rem` |
| `.text-lg` | `1.1rem` |
| `.text-xl` | `1.25rem` |
| `.text-2xl` | `1.5rem` |
| `.text-muted` | Secondary text color |
| `.text-dim` | Tertiary text color |
| `.text-accent` | Accent color |
| `.text-success` | Success text |
| `.text-warning` | Warning text |
| `.text-error` | Error text |
| `.text-center` | Center align |
| `.text-right` | Right align |
| `.text-upper` | Uppercase + tracking |
| `.text-mono` | Monospace font |
| `.font-normal` / `.font-medium` / `.font-bold` / `.font-heavy` | Font weights |
| `.truncate` | Ellipsis overflow |
| `.break-all` | Break words |

### Topbar Helpers

| Class | Description |
|-------|-------------|
| `.topbar-left` | Left section of admin topbar |
| `.topbar-center` | Center section |
| `.topbar-right` | Right section |

### Action Bar

| Class | Description |
|-------|-------------|
| `.action-bar` | Page action bar (flex, space-between) |
| `.action-bar .filters` | Filter controls area |

### Display & Sizing

| Class | Description |
|-------|-------------|
| `.hidden` / `.block` / `.inline-block` | Display modes |
| `.w-full` / `.w-auto` | Width |
| `.max-w-sm` / `.max-w-md` / `.max-w-lg` | Max-width (400/600/800px) |
| `.rounded` / `.rounded-lg` / `.rounded-full` | Border radius |
| `.border` / `.border-b` | Borders |

## Frontend CSS Classes (Public-Facing)

### Layout

| Class | Description |
|-------|-------------|
| `.klytos-container` | Centered container with max-width and responsive padding |
| `.klytos-header` / `.klytos-header.sticky` | Page header |
| `.klytos-main` | Main content area |
| `.klytos-footer` | Footer |
| `.klytos-page` | Page content wrapper |

### Sections

| Class | Description |
|-------|-------------|
| `.klytos-section` | Content section (padding: 3rem 0) |
| `.klytos-section-alt` | Alternating section (surface bg) |
| `.klytos-hero` | Hero section |

### Grid

| Class | Description |
|-------|-------------|
| `.klytos-grid-2` / `.klytos-grid-3` / `.klytos-grid-4` | Column grids |

### Buttons

| Class | Description |
|-------|-------------|
| `.klytos-btn` | Base button |
| `.klytos-btn-primary` / `.klytos-btn-secondary` / `.klytos-btn-outline` | Variants |
| `.klytos-btn-lg` | Large variant |

### Cards

| Class | Description |
|-------|-------------|
| `.klytos-card` | Content card |
| `.klytos-card-grid` | Responsive card grid |

### Content Blocks

| Class | Description |
|-------|-------------|
| `.klytos-text-block` | Simple text section |
| `.klytos-image-text` | Image + text side-by-side |
| `.klytos-gallery` | Image gallery grid |
| `.klytos-cta` | Call-to-action section |
| `.klytos-faq` | FAQ accordion |
| `.klytos-stats` | Statistics counter grid |
| `.klytos-testimonials` | Testimonial cards |
| `.klytos-team` | Team member grid |
| `.klytos-logos` | Logo bar |

## Frontend Design Tokens

```css
--klytos-primary         /* Brand primary color */
--klytos-secondary       /* Secondary color */
--klytos-accent          /* Accent/highlight color */
--klytos-background      /* Page background */
--klytos-surface         /* Card/box backgrounds */
--klytos-text            /* Main text color */
--klytos-text-muted      /* Secondary text */
--klytos-border          /* Border color */
--klytos-font-heading    /* Heading font family */
--klytos-font-body       /* Body font family */
--klytos-font-code       /* Code font family */
--klytos-max-width       /* Container max-width (default: 1200px) */
--klytos-radius          /* Border radius (default: 8px) */
--klytos-spacing         /* Base spacing unit (default: 1rem) */
```

## Backward Compatibility

Legacy aliases exist for `--admin-*` and unprefixed vars — do NOT use them in new code. Always prefer `--klytos-*` prefixed tokens.
