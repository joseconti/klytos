---
name: site-builder-content
description: "Content generation templates for the Site Builder — questions by page type, content sources, typography combos, and image guidance."
trigger: When generating or importing content during the site building process.
---

# Content Generation Guide

This guide helps you generate, adapt, and structure content for each page type during site building.

## CRITICAL: Before Creating Any Content

You MUST read these guides first:
- `klytos_get_guide('gutenberg-blocks')` — all content MUST use Gutenberg block markup
- `klytos_get_guide('seo-content')` — every page needs proper SEO structure
- `klytos_get_guide('accessibility')` — WCAG 2.1 AA compliance

---

## Content Sources

Ask the user which source they prefer for EACH page:

### 1. User's Own Text Files

The user uploads files (txt, docx, pdf, md, html, csv).

**Workflow:**
1. Read the file content
2. Extract relevant text
3. Structure it into Gutenberg blocks
4. Add headings hierarchy (H2, H3) for SEO
5. Show the user the result for approval

### 2. External URLs (Dropbox, Google Drive, etc.)

The user provides a direct link to a file.

**Workflow:**
1. Download the file from the URL
2. Follow the same process as text files
3. Note: the link must point to the file, NOT to a directory

### 3. Dictation to the Assistant

The user describes what they want in conversational language.

**Workflow:**
1. Listen to the user's description
2. Ask clarifying questions if needed
3. Draft the content in proper Gutenberg block format
4. Present for review and iterate

### 4. Full AI Generation

The assistant generates content based on the site brief.

**Workflow:**
1. Use information gathered in Phase 1 (site type, sector, audience, tone)
2. Generate content that matches the brand voice
3. Include proper heading hierarchy, CTAs, and SEO elements
4. ALWAYS present generated content for user approval — never publish AI-generated content without review

---

## Questions by Page Type

### Homepage

**Essential questions:**
- What is the main message visitors should see first? (hero section)
- What are the 3-5 key things you want visitors to know about you/your business?
- Do you have a main call-to-action? (e.g., "Contact us", "View our work", "Get started")
- Do you want to showcase testimonials, clients, or partners on the home page?
- Is there a specific offer or announcement to highlight?

**Sections to generate:**
1. Hero: headline + subheadline + CTA button + hero image
2. Features/benefits: 3-6 cards with icon, title, description
3. About snippet: brief intro + "learn more" link
4. Social proof: testimonials, client logos, stats
5. CTA: final call to action before footer

### About / About Us

**Essential questions:**
- Tell me about yourself/your company in a few sentences
- When were you founded? What's your story?
- What makes you different from competitors?
- What are your values or mission?
- Do you have team photos to include?

**Sections to generate:**
1. Introduction paragraph
2. Mission/vision statement
3. History timeline (optional)
4. Team section (if applicable)
5. Values/principles

### Services

**Essential questions (for each service):**
- What is this service?
- Who is it for?
- What problem does it solve?
- What does the process look like?
- What is the pricing? (if public)
- Do you have examples or case studies?

**Sections to generate:**
1. Service overview
2. Benefits list
3. Process steps
4. Pricing (if applicable)
5. CTA to contact/request quote

### Contact

**Essential questions:**
- What's your email address?
- Phone number? (optional)
- Physical address? (optional)
- Business hours? (optional)
- Which form fields do you need? (name, email, phone, subject, message, file upload)
- Do you need a map?

**Sections to generate:**
1. Contact information
2. Contact form (via klytos-forms plugin)
3. Map/location (if applicable)
4. Business hours (if applicable)

### Blog / News

**Essential questions:**
- What topics will you write about?
- How often do you plan to publish?
- Do you have any existing posts to import?
- Do you want categories and tags?
- Should the blog have a sidebar?

**Sections to generate:**
1. Blog listing page layout
2. 2-3 sample posts (if user wants initial content)
3. Category structure

### Portfolio / Projects

**Essential questions (for each project):**
- Project name and client
- What was the challenge/brief?
- What was your solution?
- What were the results?
- Do you have images/screenshots?
- Link to live project?

**Sections to generate:**
1. Project overview
2. Challenge & solution
3. Image gallery
4. Results/metrics
5. Link to live project

### Products / Catalog

**Essential questions (for each product):**
- Product name
- Description
- Price
- Specifications/features
- Images (minimum 1, recommended 3-5)
- Categories
- Availability

**Sections to generate:**
1. Product description
2. Specifications table
3. Image gallery
4. Related products (optional)

### FAQ

**Essential questions:**
- What are the most common questions your customers ask?
- Do you want to organize FAQs by category?
- How many questions approximately?

**Format:** Use FAQ CPT or accordion blocks

### Privacy Policy / Terms

**Essential questions:**
- Company legal name
- Country/jurisdiction
- Do you collect personal data? (forms, analytics)
- Do you use cookies? (analytics, marketing)
- Do you share data with third parties?

**Note:** Offer to generate a template, but ALWAYS recommend legal review. AI-generated legal text is a starting point, not final.

---

## Typography Combinations

Recommend based on site type and tone:

| Site type | Heading font | Body font | Tone |
|-----------|-------------|-----------|------|
| Corporate | Inter | Inter | Clean, professional |
| Corporate premium | Playfair Display | Source Sans 3 | Elegant, authoritative |
| Tech / SaaS | Inter | Inter | Modern, clean |
| Tech bold | Space Grotesk | Inter | Bold, innovative |
| Creative / Portfolio | Playfair Display | Source Sans 3 | Artistic, refined |
| Creative modern | DM Sans | DM Sans | Contemporary, minimal |
| Blog personal | Merriweather | Source Sans 3 | Readable, literary |
| Blog modern | Inter | Inter | Clean, accessible |
| Documentation | JetBrains Mono | Inter | Technical, precise |
| Restaurant / Food | Cormorant Garamond | Montserrat | Warm, inviting |
| Legal / Finance | Libre Baskerville | Source Sans 3 | Formal, trustworthy |
| Education | Nunito | Nunito | Friendly, approachable |
| NGO | Source Sans 3 | Source Sans 3 | Humanist, warm |

**Default parameters for `klytos_set_fonts`:**
- `fonts.code`: `"JetBrains Mono"` (universal)
- `fonts.heading_weight`: `700` (bold headings)
- `fonts.body_weight`: `400` (regular body)
- `fonts.base_size`: `"16px"`
- `fonts.scale_ratio`: `1.25` (major third scale)
- `fonts.google_fonts_url`: construct from chosen fonts

**Google Fonts URL format:**
```
https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@400;700&display=swap
```

---

## Image Guidance

### 5 Ways to Get Images

| Method | When to use | MCP tool |
|--------|-------------|----------|
| Direct upload | User has files ready | `klytos_upload_asset` |
| AI generation | No images available, Gemini API configured | `klytos_generate_ai_image` |
| External URL | Files on Dropbox/Drive/etc. | `klytos_upload_asset` with URL |
| Screenshots | As design reference only | N/A |
| Placeholder | Decide later, keep building | Use placeholder text in alt |

### AI Image Generation

If Gemini API key is configured (`klytos_ai_get_config`):
- Offer to generate: hero images, section backgrounds, illustrative icons, team placeholders
- Always describe the style: "minimalist illustration", "professional photography style", "flat icon"
- Match the site's color palette and tone
- Generated images should be reviewed by the user before publishing

If NO Gemini API key:
- Mention they can configure it later in Settings > AI Providers
- Use placeholders for now

### Image Best Practices

- Hero images: 1920x1080px minimum
- Thumbnails/cards: 800x600px
- Team photos: 400x400px (square)
- Logos: SVG preferred, otherwise PNG with transparency
- Always set `alt` text for accessibility (WCAG requirement)
- Use descriptive filenames: `team-photo-jane-smith.jpg` not `IMG_3847.jpg`
