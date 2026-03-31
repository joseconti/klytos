---
name: klytos-css-classes
description: Reference of all CSS classes and design tokens available in Klytos CMS. Use when building content, plugins, or templates that need to integrate visually with the core design.
trigger: When the user needs to style content, use core CSS classes, apply design tokens, or build visually consistent UI in Klytos.
---

# Klytos CSS Classes & Design Tokens

## When to Use This Skill

Use this reference when building content, plugin UI, or templates that need to look consistent with the Klytos core design. **Always use core CSS classes instead of writing custom CSS** — this ensures visual consistency and theme compatibility.

---

## CSS Custom Properties (Design Tokens)

All theme-configurable values are exposed as CSS custom properties with the `--klytos-` prefix.

### Colors

```css
--klytos-primary         /* Brand primary color (default: #2563eb blue) */
--klytos-secondary       /* Secondary color (default: #7c3aed purple) */
--klytos-accent          /* Accent/highlight color (default: #f59e0b amber) */
--klytos-background      /* Page background (default: #ffffff) */
--klytos-surface         /* Card/box backgrounds (default: #f8fafc) */
--klytos-text            /* Main text color (default: #1e293b) */
--klytos-text-muted      /* Secondary/muted text (default: #64748b) */
--klytos-border          /* Border color (default: #e2e8f0) */
--klytos-success         /* Success state (default: #22c55e green) */
--klytos-warning         /* Warning state (default: #f59e0b amber) */
--klytos-error           /* Error state (default: #ef4444 red) */
```

### Typography

```css
--klytos-font-heading    /* Heading font (default: 'Inter', sans-serif) */
--klytos-font-body       /* Body font (default: 'Inter', sans-serif) */
--klytos-font-code       /* Code font (default: 'JetBrains Mono', monospace) */
```

Typography uses a **modular scale of 1.25x**:
- `h1`: 2.488em
- `h2`: 1.99em
- `h3`: 1.59em
- `h4`: 1.27em
- `h5`: 1.1em
- `h6`: 1em

### Layout

```css
--klytos-max-width       /* Container max-width (default: 1200px) */
--klytos-radius          /* Border radius (default: 8px) */
--klytos-spacing         /* Base spacing unit (default: 1rem) */
```

### Using Design Tokens in Custom CSS

```css
.my-plugin-widget {
    background: var(--klytos-surface);
    border: 1px solid var(--klytos-border);
    border-radius: var(--klytos-radius);
    padding: var(--klytos-spacing);
    color: var(--klytos-text);
    font-family: var(--klytos-font-body);
}

.my-plugin-widget h3 {
    color: var(--klytos-primary);
    font-family: var(--klytos-font-heading);
}
```

---

## Frontend CSS Classes (Public-Facing)

### Layout

| Class | Description |
|---|---|
| `.klytos-container` | Centered container with max-width and responsive padding |
| `.klytos-header` | Page header with logo + navigation |
| `.klytos-header.sticky` | Fixed header (z-index: 100) |
| `.klytos-main` | Main content area (min-height: 60vh) |
| `.klytos-footer` | Footer with border-top, centered text |
| `.klytos-page` | Page content wrapper |

### Sections

| Class | Description |
|---|---|
| `.klytos-section` | Content section (padding: 3rem 0) |
| `.klytos-section-alt` | Alternating section (background: surface) |
| `.klytos-hero` | Hero section (padding: 4rem 0, text-align: center) |

### Grid System

| Class | Description |
|---|---|
| `.klytos-grid-2` | 2-column grid |
| `.klytos-grid-3` | 3-column grid |
| `.klytos-grid-4` | 4-column grid |

All grids use `gap: var(--klytos-spacing)` and collapse to 1 column on mobile (≤768px).

```html
<div class="klytos-grid-3">
    <div class="klytos-card">Item 1</div>
    <div class="klytos-card">Item 2</div>
    <div class="klytos-card">Item 3</div>
</div>
```

### Buttons

| Class | Description |
|---|---|
| `.klytos-btn` | Base button (inline-flex, padding, transition) |
| `.klytos-btn-primary` | Primary action (primary color bg, white text) |
| `.klytos-btn-secondary` | Secondary action (secondary color bg) |
| `.klytos-btn-outline` | Transparent with border (2px solid primary) |
| `.klytos-btn-lg` | Large variant (more padding, larger font) |

Buttons have hover effects: `translateY(-1px)` + shadow.

```html
<a href="/contact/" class="klytos-btn klytos-btn-primary">Contact Us</a>
<a href="/learn-more/" class="klytos-btn klytos-btn-outline">Learn More</a>
```

### Cards

| Class | Description |
|---|---|
| `.klytos-card` | Content card (surface bg, border, padding: 1.5rem, radius) |
| `.klytos-card-grid` | Responsive card grid (auto-fit, minmax(280px, 1fr)) |

```html
<div class="klytos-card-grid">
    <div class="klytos-card">
        <h3>Service 1</h3>
        <p>Description here.</p>
    </div>
    <div class="klytos-card">
        <h3>Service 2</h3>
        <p>Description here.</p>
    </div>
</div>
```

### Navigation

| Class | Description |
|---|---|
| `.klytos-nav` | Navigation bar container |
| `.klytos-menu` | Menu list (flex, gap) |
| `.klytos-menu-item` | Menu item (li) |
| `.has-children` | Item with submenu |
| `.klytos-submenu` | Nested submenu (hidden, shown on hover) |
| `.klytos-logo` | Site logo/brand link |

### Content Blocks (from Seed Data)

| Class | Description |
|---|---|
| `.klytos-text-block` | Simple text section |
| `.klytos-image-text` | Image + text side-by-side |
| `.klytos-gallery` | Image gallery grid |
| `.klytos-cta` | Call-to-action section |
| `.klytos-faq` | FAQ accordion |
| `.klytos-stats` | Statistics counter grid |
| `.klytos-testimonials` | Testimonial cards |
| `.klytos-team` | Team member grid |
| `.klytos-logos` | Logo bar (partners/clients) |
| `.klytos-cookie-banner` | Cookie notice |

### Breadcrumbs

| Class | Description |
|---|---|
| `.klytos-breadcrumbs` | Breadcrumb navigation container |
| `.breadcrumb-list` | Breadcrumb ordered list |
| `.breadcrumb-item` | Individual breadcrumb item |

### Text Utilities

| Class | Description |
|---|---|
| `.klytos-text-center` | Center-aligned text |
| `.klytos-text-muted` | Muted text color |

---

## Styled HTML Elements

The core CSS styles these elements globally (no class needed):

| Element | Styling |
|---|---|
| `h1-h6` | Heading font, weight 700, line-height 1.2, modular scale |
| `p` | Body font, line-height 1.6 |
| `a` | Primary color, hover underline |
| `code` | Code font (JetBrains Mono), surface bg |
| `pre` | Surface bg, padding 1rem, radius, overflow-x: auto |
| `blockquote` | Border-left 4px solid primary, padding 0.5em 1em |
| `img` | max-width: 100%, height: auto |

---

## Responsive Breakpoints

| Breakpoint | Target | Effect |
|---|---|---|
| `max-width: 768px` | Mobile | Grids → 1 column, hero text shrinks, menu stacks vertically |
| `max-width: 782px` | Editor | Sidebar hidden, header compressed |

### Mobile Behavior

- `.klytos-grid-2/3/4` → single column
- `.klytos-hero h1` → font-size: 2rem (from 3rem)
- `.klytos-menu` → flex-direction: column
- `.klytos-header` → flex-direction: column
- `.klytos-btn-lg` → width: 100%

---

## Admin Panel CSS Classes

For plugin admin pages, use these classes (different from frontend):

| Class | Description |
|---|---|
| `.card` | Admin card |
| `.btn`, `.btn-primary`, `.btn-danger`, `.btn-sm` | Admin buttons |
| `.form-group`, `.form-label`, `.form-control` | Form elements |
| `.alert-success`, `.alert-error`, `.alert-warning` | Alert messages |
| `.badge-*` | Status badges |
| `.table` | Data tables |

See `klytos-admin-sidebar` skill for the complete admin CSS reference.

---

## Best Practices

1. **Always use design tokens** (`var(--klytos-*)`) instead of hard-coded colors
2. **Always use core classes** instead of writing custom CSS
3. **Test with different themes** — your plugin should adapt to the site's color scheme
4. **Respect breakpoints** — test on mobile (768px)
5. **Use the prefix** — custom classes should use your plugin ID: `.my-plugin-widget`
6. **Never override core classes** — extend them with your own selectors

---

## Source Files

- Frontend CSS generation: `core/build-engine.php`
- Seed data (block HTML/CSS): `installer/seed-data.php`
- Theme configuration: `core/theme-manager.php`
- Admin CSS: `admin/templates/header.php` (inline styles)
- Templates: `templates/default.html`, `templates/landing.html`
