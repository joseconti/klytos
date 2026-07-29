# Design Brief — Klytos Admin

> **Reconstructed as-built, 2026-07-28 (D-067).** Phase 3 went live retroactively: the Claude Design
> project *Klytos CMS Redesign* delivered 41 screens, a PackDesk-derived token set and the logo
> family **before** Keel had authored a brief for it (D-065). This document is written from what the
> delivery, the technical plan and the recorded decisions demonstrate — it is not a brief that was
> handed over, and it is labelled that way so nobody reads it as the contract the delivery was
> measured against. That contract is `references/handoff-contract.md`, and the audit against it is
> `docs/BUILD-SPEC.md` §1.
>
> **Why write it at all, given the design already arrived?** Because Klytos keeps evolving and there
> will be more design work — the re-delivery itself already refers to a separate front-end design
> request. Without this, the next handoff starts from an improvised brief again, and every constraint
> below has to be rediscovered or, worse, gets left out and comes back as a Design Request. This is
> the reusable half; only the screen list is specific to the admin.

## 1. What is being designed, and what it must DO

The **Klytos admin panel**: the human interface to a self-hosted, AI-first CMS whose primary
interface is an MCP server. That inversion is the single most important thing for a designer to
understand, and it is not a detail of tone:

- The AI is the **main operator**. Most sites are built through `tools/call`, not through this UI.
- The admin exists so a human can **see what the AI did, correct it, and do the things a human is
  better at** — judgment, review, approval, configuration they do not want to describe in prose.
- Therefore the admin is **read-and-verify first, author second**. Density, scanability and honest
  state reporting matter more than authoring flourish. A screen that hides what changed is worse than
  one that is plain.

Every screen is specified by what it **does**, never only by how it looks.

## 2. The design system: inherited, not founded

- **Origin: the PackDesk Design System** (José Conti), palette "Mediterráneo". PackDesk is the parent
  brand; **Klytos is a product inside it and signs *by PackDesk***.
- Upstream owns those values. This project does **not** fork them. A divergence is a **Design
  Request**, never a creative choice — and the Phase 4 audit flags an unexplained divergence as a gap.
- The one file Klytos owns is a product-level layer on top (`tokens/klytos-admin.css` in the
  delivery), which **adds** derived values and never rewrites a base hex.
- The in-repo system that exists today (`installer/admin/assets/css/klytos-tokens.css`, 118
  `--klytos-*` tokens) is the **current state, not the target** (D-065).

## 3. Non-negotiable constraints

These are stack and policy facts. A design that violates one is not a preference disagreement; it
cannot be built as drawn.

| Constraint | Why |
|---|---|
| **No React, no framework, no bundler, no CDN** | The product is PHP 8.1+ with a custom microframework and vanilla CSS/JS, no front-end toolchain at all (`docs/03-technical-plan.md` §1). Prototypes are references; their markup is never the delivery |
| **Server-rendered, multi-page** | Filters, sorting, pagination and tabs are links; writes are form posts. Everything works with JavaScript disabled except a named, small set — each of which needs a non-JS path |
| **Every inline `<script>`/`<style>` carries a CSP nonce** | The admin sets a strict CSP. No inline `onclick`/`onchange` — event listeners inside a nonced block |
| **Both light and dark ship, and neither is "the accessible one"** | Both must meet AA independently |
| **20 locales, +30 % expansion headroom** | Every string is substitutable. A layout that breaks when a label grows a third is a defect, not a locale problem |
| **Every interactive element carries a stable `data-testid`** | `<screen>.<element>[.<id>]`, English, never translated, never the visible text. This is a **build and design** requirement: it is what lets the assistant drive the tests instead of asking the user to click. **Never repurpose an accessibility label as a test hook** — it is read aloud to a real user |
| **The admin lives at `/<random>-admin/`** | The directory name is randomized per install; nothing may hardcode it or display it in a public-facing URL |

## 4. Accessibility is a deliverable, not a review stage

Klytos is committed to **WCAG 2.2 AA as the floor, EN 301 549 and the European Accessibility Act**,
for the admin **and** for the HTML it generates (**D-007**). The measured baseline of the pre-redesign
admin is **~20–25 %** against that target.

That number is why accessibility is stated here rather than checked later: a 41-screen rewrite is
simultaneously the best and the **last cheap** opportunity to close the gap. Building all 41 to a
spec that is silent on accessibility rebuilds the deficit at full scale, in new code, with the
receipts saying it was a redesign.

The handoff must therefore carry, per `references/accessibility.md` and contract rule 9:

- **Contrast pairs with measured ratios**, both themes, including every badge/chip text colour on its
  own tint, primary-button text on the accent, the focus ring on both surfaces, and disabled states.
  Where a pair fails, **the token or the pattern changes** and the delivery says which.
- **Focus order and the visible focus indicator** per screen, plus skip-to-content.
- **Accessible name, role and state per component and per state.** A switch and a checkbox are
  different roles; the delivery says which each control is, and does not leave it to the build.
- **Heading and landmark structure** per screen: one `h1`, the levels, and the
  banner/navigation/main/complementary/contentinfo landmarks with their labels.
- **Target sizes** — 24 × 24 CSS px minimum, or a documented exception with its reason.
- **Text scaling, forced-colors and reduced-motion** stated as rules, not mentions.
- **Error identification that is never colour-only.**

**Data tables are the specific trap.** Every list screen in this admin is a data table. A table built
from `display:grid` is not exposed as a table to assistive technology without the full explicit ARIA
role set — and a *partial* ARIA table is worse than a plain grid. The delivery must choose one of:
a real `<table>` with grid layout applied to it; `display:grid` plus the complete
`table`/`rowgroup`/`row`/`columnheader`/`cell` set written out per element; or a documented reason
these are not data tables.

## 5. What Design delivers (contract summary)

The full contract is `references/handoff-contract.md`; the parts that have actually bitten this
project are:

- **Build once, reuse by manifest.** Structurally identical screens are one template plus a manifest
  row per consumer, never N regenerated pages.
- **Every unique screen has its SPEC file** with **all** applicable states — default, hover, focus,
  active, disabled, loading, empty, error, success — and the responsive behaviour at each declared
  breakpoint. States gathered onto one "States" specimen sheet do not satisfy this.
- **Tokens are exact, centralized, and agree with the delivered styles.** The SPEC and the CSS saying
  different things is a gap, not a rounding error — it happened, on the type scale, and it blocked
  the build.
- **Every logo and icon in BOTH SVG and PNG**, PNG at intrinsic size plus the platform's required
  densities. Every asset in a form the build drops in directly: **the build never converts, resizes,
  recolors, rasterizes or re-exports anything.** Subsetting a font is a build-side transformation and
  therefore an incomplete handoff.
- **Fonts are assets.** Ship the actual self-hosted files at their final paths with the `@font-face`
  rules written against those exact paths, plus the licence file — or, where licensing forbids it, a
  complete acquisition block per font (exact source, licence note, exact files, exact target path).
  A font that is merely named is incomplete. `@font-face` rules whose paths resolve to nothing are
  worse than none, because they look configured.
- **`SPEC/open-questions.md` exists and ends with zero unresolved items.** Its absence is not the
  same as zero open questions.
- **Ask, never invent.** Anything that is the user's decision — a brand call, a copy decision, a
  policy — is asked. A guess recorded as a specification is the most expensive thing a handoff can
  contain, because it is indistinguishable from a decision.

## 6. Copy

English, sentence case, impersonal, no emoji. Every string substitutable for i18n. Placeholder copy
is **labelled** as placeholder in the SPEC, so it can never ship as real.

## 7. Where the built code will live

`installer/admin/` — 42 page controllers, `bootstrap.php` (auth guard, CSP nonce), `templates/`
(header, sidebar, footer), `partials/`, `assets/css/`. Design does not need to match that structure;
it needs to know the target is server-rendered PHP with hand-written CSS and no build step.

## 8. Design viewport and breakpoints

Design viewport **1440 × 976**. Every screen states its behaviour at each declared breakpoint —
numbers, not adjectives. "Recreate at 1440 and allow to reflow" is not a responsive specification.
