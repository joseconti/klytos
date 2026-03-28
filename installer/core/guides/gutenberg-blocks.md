---
description: "Use when creating or editing pages in Klytos CMS via MCP. All HTML content MUST use Gutenberg block markup so it renders correctly in the visual editor."
globs: ["**/*.php", "**/*.html"]
alwaysApply: false
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

## Text Blocks

### Paragraph
```html
<!-- wp:paragraph -->
<p>Your text here.</p>
<!-- /wp:paragraph -->
```
With styling:
```html
<!-- wp:paragraph {"align":"center","style":{"color":{"text":"#64748b"}}} -->
<p class="has-text-align-center has-text-color" style="color:#64748b">Centered muted text.</p>
<!-- /wp:paragraph -->
```
With drop cap:
```html
<!-- wp:paragraph {"dropCap":true} -->
<p class="has-drop-cap">First paragraph with a large initial letter.</p>
<!-- /wp:paragraph -->
```

### Heading
```html
<!-- wp:heading -->
<h2 class="wp-block-heading">H2 Heading (default)</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">H1 Heading</h1>
<!-- /wp:heading -->

<!-- wp:heading {"level":3,"textAlign":"center"} -->
<h3 class="wp-block-heading has-text-align-center">Centered H3</h3>
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

<!-- wp:list-item -->
<li>Third item</li>
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

### Quote
```html
<!-- wp:quote -->
<blockquote class="wp-block-quote">
<p>The best way to predict the future is to create it.</p>
<cite>Peter Drucker</cite>
</blockquote>
<!-- /wp:quote -->
```

### Code
```html
<!-- wp:code -->
<pre class="wp-block-code"><code>const greeting = "Hello, World!";
console.log(greeting);</code></pre>
<!-- /wp:code -->
```

### Preformatted
```html
<!-- wp:preformatted -->
<pre class="wp-block-preformatted">Preformatted text
  preserves all spaces
    and line breaks.</pre>
<!-- /wp:preformatted -->
```

### Pullquote
```html
<!-- wp:pullquote -->
<figure class="wp-block-pullquote">
<blockquote>
<p>A highlighted quote that stands out from the text.</p>
<cite>Author Name</cite>
</blockquote>
</figure>
<!-- /wp:pullquote -->
```

### Verse
```html
<!-- wp:verse -->
<pre class="wp-block-verse">Roses are red,
Violets are blue,
Gutenberg blocks,
Are useful too.</pre>
<!-- /wp:verse -->
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
With caption:
```html
<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full">
<img src="/assets/images/hero.jpg" alt="Hero banner" />
<figcaption class="wp-element-caption">Our team at the annual conference.</figcaption>
</figure>
<!-- /wp:image -->
```
With link:
```html
<!-- wp:image {"sizeSlug":"large","linkDestination":"custom"} -->
<figure class="wp-block-image size-large">
<a href="https://example.com"><img src="/assets/images/banner.jpg" alt="Banner" /></a>
</figure>
<!-- /wp:image -->
```

### Gallery
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

### Audio
```html
<!-- wp:audio -->
<figure class="wp-block-audio">
<audio controls src="/assets/audio/podcast.mp3"></audio>
<figcaption class="wp-element-caption">Episode 42: AI and the future.</figcaption>
</figure>
<!-- /wp:audio -->
```

### File (download)
```html
<!-- wp:file {"href":"/assets/docs/whitepaper.pdf"} -->
<div class="wp-block-file">
<a href="/assets/docs/whitepaper.pdf">Download our whitepaper (PDF)</a>
</div>
<!-- /wp:file -->
```

### Cover (image with text overlay)
```html
<!-- wp:cover {"url":"/assets/images/hero-bg.jpg","dimRatio":60} -->
<div class="wp-block-cover">
<span aria-hidden="true" class="wp-block-cover__background has-background-dim-60 has-background-dim"></span>
<img class="wp-block-cover__image-background" src="/assets/images/hero-bg.jpg" alt="" />
<div class="wp-block-cover__inner-container">

<!-- wp:heading {"level":1,"textAlign":"center","style":{"color":{"text":"#ffffff"}}} -->
<h1 class="wp-block-heading has-text-align-center has-text-color" style="color:#ffffff">Welcome to Our Site</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"color":{"text":"#e2e8f0"}}} -->
<p class="has-text-align-center has-text-color" style="color:#e2e8f0">Build amazing things with AI.</p>
<!-- /wp:paragraph -->

</div>
</div>
<!-- /wp:cover -->
```

### Media & Text (side-by-side)
```html
<!-- wp:media-text {"mediaPosition":"left","mediaType":"image"} -->
<div class="wp-block-media-text is-stacked-on-mobile">
<figure class="wp-block-media-text__media">
<img src="/assets/images/feature.jpg" alt="Feature image" />
</figure>
<div class="wp-block-media-text__content">

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Feature Title</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Description of this feature with image alongside.</p>
<!-- /wp:paragraph -->

</div>
</div>
<!-- /wp:media-text -->
```

---

## Layout Blocks

### Columns
Two columns:
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

Three columns with custom widths:
```html
<!-- wp:columns -->
<div class="wp-block-columns">

<!-- wp:column {"width":"25%"} -->
<div class="wp-block-column" style="flex-basis:25%">
<!-- wp:paragraph -->
<p>Sidebar</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%">
<!-- wp:paragraph -->
<p>Main content</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"25%"} -->
<div class="wp-block-column" style="flex-basis:25%">
<!-- wp:paragraph -->
<p>Sidebar</p>
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
Wide separator:
```html
<!-- wp:separator {"className":"is-style-wide"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide" />
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
Single button:
```html
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons">

<!-- wp:button -->
<div class="wp-block-button">
<a class="wp-block-button__link wp-element-button" href="/contact/">Get Started</a>
</div>
<!-- /wp:button -->

</div>
<!-- /wp:buttons -->
```

Multiple buttons:
```html
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons">

<!-- wp:button -->
<div class="wp-block-button">
<a class="wp-block-button__link wp-element-button" href="/pricing/">View Pricing</a>
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
<td>Plugins</td>
<td>Free only</td>
<td>All plugins</td>
</tr>
<tr>
<td>Support</td>
<td>Community</td>
<td>Priority</td>
</tr>
</tbody>
</table>
<figcaption class="wp-element-caption">Feature comparison table.</figcaption>
</figure>
<!-- /wp:table -->
```

### Custom HTML
```html
<!-- wp:html -->
<div class="custom-embed">
  <iframe src="https://example.com/widget" width="100%" height="400" frameborder="0"></iframe>
</div>
<!-- /wp:html -->
```

---

## Embed Blocks

### YouTube
```html
<!-- wp:embed {"url":"https://www.youtube.com/watch?v=dQw4w9WgXcQ","type":"video","providerNameSlug":"youtube","responsive":true} -->
<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio">
<div class="wp-block-embed__wrapper">
https://www.youtube.com/watch?v=dQw4w9WgXcQ
</div>
<figcaption class="wp-element-caption">Video caption.</figcaption>
</figure>
<!-- /wp:embed -->
```

### Vimeo
```html
<!-- wp:embed {"url":"https://vimeo.com/123456789","type":"video","providerNameSlug":"vimeo","responsive":true} -->
<figure class="wp-block-embed is-type-video is-provider-vimeo wp-block-embed-vimeo wp-embed-aspect-16-9 wp-has-aspect-ratio">
<div class="wp-block-embed__wrapper">
https://vimeo.com/123456789
</div>
</figure>
<!-- /wp:embed -->
```

### Twitter / X
```html
<!-- wp:embed {"url":"https://twitter.com/user/status/123456","type":"rich","providerNameSlug":"twitter"} -->
<figure class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter">
<div class="wp-block-embed__wrapper">
https://twitter.com/user/status/123456
</div>
</figure>
<!-- /wp:embed -->
```

### Spotify
```html
<!-- wp:embed {"url":"https://open.spotify.com/track/xxx","type":"rich","providerNameSlug":"spotify"} -->
<figure class="wp-block-embed is-type-rich is-provider-spotify wp-block-embed-spotify">
<div class="wp-block-embed__wrapper">
https://open.spotify.com/track/xxx
</div>
</figure>
<!-- /wp:embed -->
```

---

## Complete Page Examples

### Landing Page
```html
<!-- wp:cover {"url":"/assets/images/hero.jpg","dimRatio":70,"minHeight":600,"minHeightUnit":"px"} -->
<div class="wp-block-cover" style="min-height:600px">
<span aria-hidden="true" class="wp-block-cover__background has-background-dim-70 has-background-dim"></span>
<img class="wp-block-cover__image-background" src="/assets/images/hero.jpg" alt="" />
<div class="wp-block-cover__inner-container">

<!-- wp:heading {"level":1,"textAlign":"center","style":{"color":{"text":"#ffffff"}}} -->
<h1 class="wp-block-heading has-text-align-center has-text-color" style="color:#ffffff">Build Websites Entirely with AI</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"color":{"text":"#e2e8f0"},"typography":{"fontSize":"1.25rem"}}} -->
<p class="has-text-align-center has-text-color" style="color:#e2e8f0;font-size:1.25rem">The AI-first CMS powered by Model Context Protocol. Free, privacy-first, blazing fast.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons">
<!-- wp:button {"style":{"color":{"background":"#3b82f6"}}} -->
<div class="wp-block-button">
<a class="wp-block-button__link has-background wp-element-button" style="background-color:#3b82f6" href="/get-started/">Get Started Free</a>
</div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline","style":{"color":{"text":"#ffffff"}}} -->
<div class="wp-block-button is-style-outline">
<a class="wp-block-button__link has-text-color wp-element-button" style="color:#ffffff" href="/docs/">Documentation</a>
</div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->

</div>
</div>
<!-- /wp:cover -->

<!-- wp:spacer {"height":"80px"} -->
<div style="height:80px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">Why Klytos?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"color":{"text":"#64748b"}}} -->
<p class="has-text-align-center has-text-color" style="color:#64748b">Everything you need to build and manage websites with AI.</p>
<!-- /wp:paragraph -->

<!-- wp:spacer {"height":"40px"} -->
<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:columns -->
<div class="wp-block-columns">

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3,"textAlign":"center"} -->
<h3 class="wp-block-heading has-text-align-center">AI-First</h3>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Create and manage your entire site through natural conversation with any AI assistant.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3,"textAlign":"center"} -->
<h3 class="wp-block-heading has-text-align-center">Privacy-First</h3>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">GDPR compliant analytics. No cookies. No tracking. Your data stays yours.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3,"textAlign":"center"} -->
<h3 class="wp-block-heading has-text-align-center">Blazing Fast</h3>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Static HTML output. No database queries on the frontend. Perfect Lighthouse scores.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->
```

### Blog Post
```html
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">How to Build a Website with AI in 5 Minutes</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"text":"#64748b"}}} -->
<p class="has-text-color" style="color:#64748b">Published on March 26, 2026 · 5 min read</p>
<!-- /wp:paragraph -->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity" />
<!-- /wp:separator -->

<!-- wp:paragraph -->
<p>In this tutorial, we will walk you through the process of creating a complete website using Klytos CMS and an AI assistant.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Step 1: Install Klytos</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>First, download Klytos and upload it to your server...</p>
<!-- /wp:paragraph -->

<!-- wp:code -->
<pre class="wp-block-code"><code>curl -O https://klytos.io/download/latest.zip
unzip latest.zip</code></pre>
<!-- /wp:code -->

<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large">
<img src="/assets/images/install-screenshot.jpg" alt="Klytos installation screen" />
<figcaption class="wp-element-caption">The Klytos installation wizard.</figcaption>
</figure>
<!-- /wp:image -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Step 2: Connect Your AI</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Copy the MCP connection URL from the admin panel and paste it into your AI tool.</p>
<!-- /wp:paragraph -->

<!-- wp:quote -->
<blockquote class="wp-block-quote">
<p>Any AI that supports MCP can manage your Klytos site. Claude, GPT, Gemini — they all work.</p>
</blockquote>
<!-- /wp:quote -->
```

### Services Page with Sub-pages
```html
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Our Services</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontSize":"1.2rem"},"color":{"text":"#64748b"}}} -->
<p class="has-text-color" style="color:#64748b;font-size:1.2rem">We offer a range of digital marketing services to help your business grow.</p>
<!-- /wp:paragraph -->

<!-- wp:spacer {"height":"40px"} -->
<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:columns -->
<div class="wp-block-columns">

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"style":{"color":{"background":"#f8fafc"},"border":{"radius":"12px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="background-color:#f8fafc;border-radius:12px">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">SEO</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Improve your search rankings with our data-driven SEO strategies.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline">
<a class="wp-block-button__link wp-element-button" href="/servicios/seo/">Learn More</a>
</div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"style":{"color":{"background":"#f8fafc"},"border":{"radius":"12px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="background-color:#f8fafc;border-radius:12px">
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Marketing</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Reach your audience with targeted marketing campaigns.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline">
<a class="wp-block-button__link wp-element-button" href="/servicios/marketing/">Learn More</a>
</div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->
```

---

## Style Attributes Reference

### Colors
```json
{"style":{"color":{"text":"#1e293b","background":"#f1f5f9"}}}
```

### Typography
```json
{"style":{"typography":{"fontSize":"1.25rem","fontWeight":"600","lineHeight":"1.4"}}}
```

### Spacing (padding/margin)
```json
{"style":{"spacing":{"padding":{"top":"2rem","bottom":"2rem","left":"1rem","right":"1rem"},"margin":{"top":"0","bottom":"2rem"}}}}
```

### Border
```json
{"style":{"border":{"radius":"12px","width":"1px","color":"#e2e8f0"}}}
```

### Combined
```json
{"style":{"color":{"background":"#1e293b","text":"#ffffff"},"spacing":{"padding":{"top":"4rem","bottom":"4rem"}},"border":{"radius":"16px"}}}
```

---

## Important Notes for AI Assistants

1. **Always wrap every piece of content in a block.** Never leave raw HTML outside of block comments.
2. **Use semantic blocks.** Use `wp:heading` for headings, `wp:paragraph` for paragraphs — not raw `<h2>` or `<p>` tags.
3. **Nest blocks correctly.** Columns contain Column blocks. Buttons contain Button blocks. Groups contain any blocks.
4. **Use `class="wp-block-heading"`** on all headings. This is required for the editor to recognize them.
5. **Use `class="wp-block-list"`** on `<ul>` and `<ol>` elements.
6. **Images should use `class="wp-block-image"`** on the `<figure>` wrapper.
7. **Buttons always need a `wp:buttons` parent** wrapping `wp:button` children.
8. **Self-closing blocks** (separator, spacer, embed) end with `/-->` instead of having a closing comment.
9. **When setting colors inline**, also add the corresponding utility class (e.g., `has-text-color`, `has-background`).
10. **For Klytos CMS specifically**, images should reference `/assets/images/` paths (the public assets directory).
