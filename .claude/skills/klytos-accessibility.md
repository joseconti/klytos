---
description: "Use when creating page content in Klytos CMS. Ensures all HTML meets WCAG 2.1 AA accessibility standards for screen readers, keyboard navigation, and assistive technologies."
globs: ["**/*.php", "**/*.html"]
alwaysApply: false
---

# Klytos CMS — Accessibility Guide (WCAG 2.1 AA)

## CRITICAL RULE

All page content created via MCP MUST be accessible. Accessibility is not optional — it is a legal requirement in the EU (European Accessibility Act) and improves SEO.

---

## 1. Images — Always Provide Alt Text

**Every image MUST have an `alt` attribute.**

### Informative images (convey information)
```html
<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large">
<img src="/assets/images/dashboard.jpg"
     alt="Klytos admin dashboard showing 5 published pages and real-time analytics graph" />
</figure>
<!-- /wp:image -->
```

### Decorative images (visual only, no information)
```html
<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large">
<img src="/assets/images/decorative-wave.svg" alt="" role="presentation" />
</figure>
<!-- /wp:image -->
```

**Rules:**
- Describe WHAT the image shows, not THAT it is an image ("Photo of..." is redundant)
- 5-15 words is ideal
- If the image contains text, include that text in the alt
- Decorative images get `alt=""` (empty, NOT omitted)
- Never use "image", "photo", "picture" as the entire alt text

---

## 2. Headings — Logical Structure

Screen readers use headings to navigate. The hierarchy MUST be logical.

**Rules:**
- One `<h1>` per page
- Never skip heading levels (H1 → H2 → H3, not H1 → H3)
- Headings describe the section content
- Don't use headings just for visual styling — use CSS instead

```html
<!-- CORRECT -->
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Our Services</h1>
<!-- /wp:heading -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Web Development</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Frontend Development</h3>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Backend Development</h3>
<!-- /wp:heading -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Digital Marketing</h2>
<!-- /wp:heading -->
```

---

## 3. Links — Descriptive and Distinguishable

**Rules:**
- Link text MUST describe the destination: "View our pricing plans" not "Click here"
- Links must be visually distinguishable from surrounding text (underline or color + another indicator)
- External links should indicate they open in a new window
- Never use "click here", "read more", "learn more" as standalone link text

```html
<!-- CORRECT -->
<!-- wp:paragraph -->
<p>Read our <a href="/docs/getting-started/">complete getting started guide</a> to set up Klytos in 5 minutes.</p>
<!-- /wp:paragraph -->

<!-- For external links -->
<!-- wp:paragraph -->
<p>View the <a href="https://spec.modelcontextprotocol.io/" target="_blank" rel="noopener noreferrer">MCP Protocol specification <span class="screen-reader-text">(opens in new window)</span></a> for technical details.</p>
<!-- /wp:paragraph -->
```

```html
<!-- WRONG -->
<p>To learn more, <a href="/docs/">click here</a>.</p>
<p><a href="/pricing/">Read more</a></p>
```

---

## 4. Color Contrast

Text MUST have sufficient contrast against its background.

**WCAG AA minimum contrast ratios:**
- Normal text (< 18px): **4.5:1**
- Large text (≥ 18px bold or ≥ 24px): **3:1**
- UI components and graphics: **3:1**

**Safe combinations with common backgrounds:**

| Background | Safe Text Colors |
|-----------|-----------------|
| `#ffffff` (white) | `#1e293b`, `#334155`, `#475569` (NOT `#94a3b8`) |
| `#f1f5f9` (light gray) | `#1e293b`, `#334155` (NOT `#64748b`) |
| `#1e293b` (dark) | `#ffffff`, `#f1f5f9`, `#e2e8f0` (NOT `#64748b`) |
| `#3b82f6` (blue) | `#ffffff` only |

**When setting colors in blocks:**
```html
<!-- CORRECT: White text on dark background (contrast > 12:1) -->
<!-- wp:group {"style":{"color":{"background":"#1e293b","text":"#f1f5f9"}}} -->

<!-- WRONG: Gray text on light background (contrast ~2.5:1, FAILS) -->
<!-- wp:group {"style":{"color":{"background":"#f1f5f9","text":"#94a3b8"}}} -->
```

---

## 5. Forms — Labels and Error Messages

Every form field MUST have a visible label.

```html
<!-- wp:html -->
<form action="/contact/" method="post">
  <div class="form-group">
    <label for="name">Full Name <span aria-label="required">*</span></label>
    <input type="text" id="name" name="name" required
           aria-describedby="name-help"
           autocomplete="name" />
    <span id="name-help" class="form-help">Enter your first and last name.</span>
  </div>

  <div class="form-group">
    <label for="email">Email Address <span aria-label="required">*</span></label>
    <input type="email" id="email" name="email" required
           autocomplete="email"
           aria-describedby="email-error" />
    <span id="email-error" class="form-error" role="alert" hidden>Please enter a valid email address.</span>
  </div>

  <div class="form-group">
    <label for="message">Message</label>
    <textarea id="message" name="message" rows="5"></textarea>
  </div>

  <button type="submit">Send Message</button>
</form>
<!-- /wp:html -->
```

**Rules:**
- Every `<input>` has a `<label>` with matching `for`/`id`
- Required fields are marked with `required` attribute AND visual indicator
- Error messages use `role="alert"` for screen readers
- Use `autocomplete` attributes for common fields
- Use `aria-describedby` to link help text to inputs

---

## 6. Tables — Accessible Structure

```html
<!-- wp:table -->
<figure class="wp-block-table">
<table>
<caption>Comparison of CMS features and capabilities</caption>
<thead>
<tr>
<th scope="col">Feature</th>
<th scope="col">Klytos</th>
<th scope="col">WordPress</th>
</tr>
</thead>
<tbody>
<tr>
<th scope="row">Page Speed</th>
<td>100/100</td>
<td>60-80/100</td>
</tr>
<tr>
<th scope="row">AI Native</th>
<td>Yes (MCP)</td>
<td>No (plugin)</td>
</tr>
</tbody>
</table>
</figure>
<!-- /wp:table -->
```

**Rules:**
- Use `<caption>` to describe the table's purpose
- Use `<th scope="col">` for column headers
- Use `<th scope="row">` for row headers
- Never use tables for layout — only for tabular data

---

## 7. Video and Audio — Captions and Transcripts

```html
<!-- wp:video -->
<figure class="wp-block-video">
<video controls>
  <source src="/assets/video/demo.mp4" type="video/mp4" />
  <track kind="captions" src="/assets/video/demo-captions-en.vtt" srclang="en" label="English" default />
  <track kind="captions" src="/assets/video/demo-captions-es.vtt" srclang="es" label="Español" />
  Your browser does not support the video element.
</video>
<figcaption class="wp-element-caption">Product demo showing how to create a page with AI. <a href="/assets/video/demo-transcript.txt">Read transcript</a>.</figcaption>
</figure>
<!-- /wp:video -->
```

**Rules:**
- Videos MUST have captions (`<track kind="captions">`)
- Provide a text transcript link for audio content
- Never autoplay audio or video
- Always include the `controls` attribute

---

## 8. Semantic HTML — Use the Right Elements

| Content | Correct Element | Wrong Element |
|---------|----------------|---------------|
| Navigation links | `<nav>` | `<div>` |
| Page header | `<header>` | `<div>` |
| Page footer | `<footer>` | `<div>` |
| Main content | `<main>` | `<div>` |
| Article/post | `<article>` | `<div>` |
| Section | `<section>` | `<div>` |
| Sidebar | `<aside>` | `<div>` |
| Emphasis | `<strong>`, `<em>` | `<b>`, `<i>` (for non-semantic bold/italic) |
| List | `<ul>`, `<ol>` | `<div>` with dashes |
| Quote | `<blockquote>` | `<div class="quote">` |
| Time/date | `<time datetime="...">` | plain text |

---

## 9. ARIA — When and How to Use

**Rule: Use native HTML first. ARIA is a last resort.**

### When ARIA IS needed:
```html
<!-- Live region for dynamic content updates -->
<div aria-live="polite" aria-atomic="true" id="status-message">
  Page saved successfully.
</div>

<!-- Custom toggle button -->
<button aria-expanded="false" aria-controls="menu-panel">
  Menu
</button>
<div id="menu-panel" hidden>...</div>

<!-- Tab interface -->
<div role="tablist">
  <button role="tab" aria-selected="true" aria-controls="tab-panel-1">Tab 1</button>
  <button role="tab" aria-selected="false" aria-controls="tab-panel-2">Tab 2</button>
</div>
<div id="tab-panel-1" role="tabpanel">Content 1</div>
<div id="tab-panel-2" role="tabpanel" hidden>Content 2</div>
```

### When ARIA is NOT needed (use HTML instead):
```html
<!-- DON'T: <div role="button"> → DO: <button> -->
<!-- DON'T: <div role="navigation"> → DO: <nav> -->
<!-- DON'T: <span role="link"> → DO: <a href="..."> -->
<!-- DON'T: <div role="heading" aria-level="2"> → DO: <h2> -->
```

---

## 10. Keyboard Navigation

All interactive elements MUST be keyboard accessible.

**Rules:**
- All links, buttons, and form fields must be reachable with Tab
- Custom interactive elements need `tabindex="0"` if not natively focusable
- Focus order must follow visual order (left-to-right, top-to-bottom)
- Focus must be visible (never use `outline: none` without an alternative)
- Escape key should close modals/popups
- Enter/Space should activate buttons

---

## 11. Language

```html
<!-- Page language is set by Klytos via the lang field -->
<!-- For mixed-language content within a page: -->
<!-- wp:paragraph -->
<p>The French word <span lang="fr">bonjour</span> means hello.</p>
<!-- /wp:paragraph -->
```

---

## Accessibility Checklist — Use Before Publishing

- [ ] All images have descriptive `alt` text (or `alt=""` for decorative)
- [ ] Heading hierarchy is logical (H1 → H2 → H3, no skips)
- [ ] Link text is descriptive (no "click here")
- [ ] Color contrast meets 4.5:1 for text, 3:1 for large text
- [ ] Forms have visible labels linked to inputs
- [ ] Tables have `<caption>`, `<th scope="col/row">`
- [ ] Videos have captions, audio has transcripts
- [ ] Semantic HTML elements used (`<nav>`, `<main>`, `<header>`, etc.)
- [ ] No information conveyed by color alone
- [ ] Interactive elements are keyboard accessible
- [ ] Page language is correctly set
