---
name: Klytos Gutenberg Blocks
description: Guide to Gutenberg block markup for Klytos CMS — CRITICAL for page creation via MCP. All HTML content MUST use Gutenberg block comment delimiters so the visual editor can parse blocks correctly. Use when creating or updating pages with content.
---

# Klytos CMS — Gutenberg Block Markup Reference

## CRITICAL RULE

When creating or updating pages in Klytos via MCP (`klytos_create_page`, `klytos_update_page`), the `content_html` field **MUST** use Gutenberg block comment delimiters. Without them, the visual editor (Gutenberg) cannot parse the content back into editable blocks.

**WRONG** (plain HTML — editor cannot parse it):
```html
<h2>About Us</h2>
<p>We are a company...</p>
```

**CORRECT** (Gutenberg block markup — editor works perfectly):
```html
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">About Us</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We are a company...</p>
<!-- /wp:paragraph -->
```

## Syntax Rules

1. **Opening comment:** `<!-- wp:blockname -->` or `<!-- wp:blockname {"attr":"value"} -->`
2. **Closing comment:** `<!-- /wp:blockname -->`
3. **Self-closing blocks:** `<!-- wp:blockname {"attr":"value"} /-->`
4. **Attributes** are a JSON object inside the opening comment
5. **Nested blocks** go between the parent's HTML tags
6. Every piece of visible content MUST be wrapped in a block

---

## Essential Text Blocks

### Paragraph
```html
<!-- wp:paragraph -->
<p>Your text here.</p>
<!-- /wp:paragraph -->
```

### Heading
```html
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">H2 Heading</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">H1 Heading</h1>
<!-- /wp:heading -->
```

Levels: 1, 2, 3, 4, 5, 6. Default is 2.

### List
```html
<!-- wp:list -->
<ul class="wp-block-list">
<!-- wp:list-item -->
<li>First item</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Second item</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
```

Ordered list:
```html
<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list">
<!-- wp:list-item -->
<li>Step one</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Step two</li>
<!-- /wp:list-item -->
</ol>
<!-- /wp:list -->
```

---

## Media Blocks

### Image
```html
<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large">
<img src="/assets/images/photo.jpg" alt="Description of the image" />
</figure>
<!-- /wp:image -->
```

### Gallery (3 columns)
```html
<!-- wp:gallery {"columns":3,"linkTo":"none"} -->
<figure class="wp-block-gallery has-nested-images columns-3 is-cropped">

<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large">
<img src="/assets/images/photo1.jpg" alt="Photo 1" />
</figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large">
<img src="/assets/images/photo2.jpg" alt="Photo 2" />
</figure>
<!-- /wp:image -->

<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large">
<img src="/assets/images/photo3.jpg" alt="Photo 3" />
</figure>
<!-- /wp:image -->

</figure>
<!-- /wp:gallery -->
```

### Video
```html
<!-- wp:video -->
<figure class="wp-block-video">
<video controls src="/assets/video/demo.mp4"></video>
<figcaption class="wp-element-caption">Product demo video.</figcaption>
</figure>
<!-- /wp:video -->
```

---

## Layout Blocks

### Columns (2-column)
```html
<!-- wp:columns -->
<div class="wp-block-columns">

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Left Column</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Content for the left column.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Right Column</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Content for the right column.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->
```

### Group (container with background)
```html
<!-- wp:group {"style":{"color":{"background":"#f1f5f9"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="background-color:#f1f5f9">

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">Section Title</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Section description goes here.</p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:group -->
```

### Separator
```html
<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity" />
<!-- /wp:separator -->
```

### Spacer
```html
<!-- wp:spacer {"height":"60px"} -->
<div style="height:60px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->
```

---

## Interactive Blocks

### Buttons
```html
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons">

<!-- wp:button -->
<div class="wp-block-button">
<a class="wp-block-button__link wp-element-button" href="/contact/">Get Started</a>
</div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline">
<a class="wp-block-button__link wp-element-button" href="/demo/">Request Demo</a>
</div>
<!-- /wp:button -->

</div>
<!-- /wp:buttons -->
```

### Table
```html
<!-- wp:table -->
<figure class="wp-block-table">
<table>
<thead>
<tr>
<th>Feature</th>
<th>Free</th>
<th>Premium</th>
</tr>
</thead>
<tbody>
<tr>
<td>Pages</td>
<td>Unlimited</td>
<td>Unlimited</td>
</tr>
<tr>
<td>Support</td>
<td>Community</td>
<td>Priority</td>
</tr>
</tbody>
</table>
<figcaption class="wp-element-caption">Feature comparison.</figcaption>
</figure>
<!-- /wp:table -->
```

---

## Important Notes

1. **Always wrap every piece of content in a block.** Never leave raw HTML outside of block comments.
2. **Use semantic blocks.** Use `wp:heading` for headings, `wp:paragraph` for paragraphs — not raw `<h2>` or `<p>` tags.
3. **Nest blocks correctly.** Columns contain Column blocks. Buttons contain Button blocks. Groups contain any blocks.
4. **Use `class="wp-block-heading"`** on all headings. This is required for the editor to recognize them.
5. **Use `class="wp-block-list"`** on `<ul>` and `<ol>` elements.
6. **Images should use `class="wp-block-image"`** on the `<figure>` wrapper.
7. **Buttons always need a `wp:buttons` parent** wrapping `wp:button` children.
8. **Self-closing blocks** (separator, spacer, embed) end with `/-->` instead of having a closing comment.
9. **For Klytos CMS specifically**, images should reference `/assets/images/` paths (the public assets directory).

---

## Quick Example: Simple Landing Page

```html
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Welcome to Our Site</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Build amazing things with AI and static HTML.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons">
<!-- wp:button -->
<div class="wp-block-button">
<a class="wp-block-button__link wp-element-button" href="/get-started/">Get Started</a>
</div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
```

---

## Source Files

- Gutenberg documentation reference format
- Editor: `admin/page-editor.php`

**For the complete block reference with all block types, style attributes, and embed examples, see the `references/complete-blocks.md` file.**
