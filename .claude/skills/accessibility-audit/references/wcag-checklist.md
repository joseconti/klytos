# WCAG 2.2 Complete Checklist

Reference checklist for manual accessibility audits. Every criterion is listed with
its level, a description, and testing guidance.

## Table of Contents

- [Principle 1: Perceivable](#principle-1-perceivable)
- [Principle 2: Operable](#principle-2-operable)
- [Principle 3: Understandable](#principle-3-understandable)
- [Principle 4: Robust](#principle-4-robust)

---

## Principle 1: Perceivable

Information and UI components must be presentable in ways users can perceive.

### 1.1 Text Alternatives

**1.1.1 Non-text Content (A)**
All non-text content has a text alternative serving equivalent purpose.
- Test: Check every `<img>` has `alt` attribute. Informative images need descriptive alt. Decorative images need `alt=""`. Complex images (charts, diagrams) need long description.
- Test: Check `<svg>` elements have `<title>` or `aria-label`.
- Test: Check `<canvas>` elements have fallback text content.
- Test: Check CSS background images that convey information have text alternatives.
- Test: Check icon fonts have `aria-label` or visually hidden text.

### 1.2 Time-based Media

**1.2.1 Audio-only and Video-only (Prerecorded) (A)**
Alternatives for prerecorded audio-only and video-only content.
- Test: Audio-only has text transcript.
- Test: Video-only (no audio) has text description or audio track describing the visual content.

**1.2.2 Captions (Prerecorded) (A)**
Synchronized captions for prerecorded audio in multimedia.
- Test: All prerecorded video with audio has captions.
- Test: Captions include dialogue AND non-speech sounds (music, sound effects).
- Test: Captions are synchronized with audio.

**1.2.3 Audio Description or Media Alternative (Prerecorded) (A)**
Audio description or text alternative for prerecorded video.
- Test: Important visual information not in dialogue is described via audio description or full text transcript.

**1.2.4 Captions (Live) (AA)**
Captions for live audio in multimedia.
- Test: Live events with audio have real-time captions.

**1.2.5 Audio Description (Prerecorded) (AA)**
Audio description for prerecorded video in multimedia.
- Test: Prerecorded video has audio description track describing important visual content.

**1.2.6 Sign Language (Prerecorded) (AAA)**
Sign language interpretation for prerecorded audio.

**1.2.7 Extended Audio Description (Prerecorded) (AAA)**
Extended audio description when pauses in foreground audio are insufficient.

**1.2.8 Media Alternative (Prerecorded) (AAA)**
Full text alternative for all prerecorded synchronized media.

**1.2.9 Audio-only (Live) (AAA)**
Alternative for live audio-only content.

### 1.3 Adaptable

**1.3.1 Info and Relationships (A)**
Information and relationships conveyed through presentation are programmatically determinable.
- Test: Headings use proper heading tags (h1-h6), not just bold/large text.
- Test: Lists use `<ul>`, `<ol>`, `<li>` — not line breaks with dashes.
- Test: Tables use `<th>` for headers with `scope` attribute.
- Test: Form fields are associated with labels via `<label for="">` or `aria-labelledby`.
- Test: Related form fields grouped with `<fieldset>` and `<legend>`.
- Test: Landmarks used (`<nav>`, `<main>`, `<aside>`, `<header>`, `<footer>`).

**1.3.2 Meaningful Sequence (A)**
Correct reading sequence is programmatically determinable.
- Test: DOM order matches visual order.
- Test: CSS does not reorder content in a way that changes meaning (flexbox order, grid placement).
- Test: Content reads logically when CSS is disabled.

**1.3.3 Sensory Characteristics (A)**
Instructions do not rely solely on shape, color, size, location, or sound.
- Test: Instructions like "click the red button" also identify the button by label.
- Test: "See the sidebar on the right" also names the section.

**1.3.4 Orientation (AA)**
Content not restricted to single display orientation.
- Test: Page works in both portrait and landscape.
- Test: No JavaScript/CSS that locks orientation unless essential.

**1.3.5 Identify Input Purpose (AA)**
Purpose of input fields collecting user data is programmatically determinable.
- Test: Input fields for personal data use `autocomplete` attribute with correct values (name, email, tel, address, etc.).

**1.3.6 Identify Purpose (AAA)**
Purpose of UI components, icons, and regions is programmatically determinable.

### 1.4 Distinguishable

**1.4.1 Use of Color (A)**
Color is not the only visual means of conveying information.
- Test: Links distinguishable from text by more than just color (underline, icon, or 3:1 contrast + non-color indicator on hover/focus).
- Test: Form errors indicated by more than just red text (icon, border, text message).
- Test: Chart/graph data distinguishable by more than color (patterns, labels).
- Test: Required fields marked by more than just color.

**1.4.2 Audio Control (A)**
Mechanism to pause, stop, or control volume for audio that auto-plays over 3 seconds.
- Test: No audio auto-plays, OR controls are provided immediately.

**1.4.3 Contrast (Minimum) (AA)**
Text contrast ratio at least 4.5:1 (3:1 for large text, 18pt+ or 14pt+ bold).
- Test: Use contrast checker on all text/background combinations.
- Test: Check placeholder text contrast (often too light).
- Test: Check disabled state text (exempt but should still be identifiable).
- Large text threshold: 18pt (24px) regular or 14pt (18.66px) bold.

**1.4.4 Resize Text (AA)**
Text can be resized to 200% without loss of content or functionality.
- Test: Zoom browser to 200%. Verify no text is clipped, overlapped, or hidden.
- Test: All functionality still works at 200% zoom.

**1.4.5 Images of Text (AA)**
Text is used instead of images of text, except where customizable or essential.
- Test: Logos are acceptable. Everything else should be real text.

**1.4.6 Contrast (Enhanced) (AAA)**
Text contrast ratio at least 7:1 (4.5:1 for large text).

**1.4.7 Low or No Background Audio (AAA)**
Speech foreground is at least 20dB louder than background.

**1.4.8 Visual Presentation (AAA)**
User can customize text colors, width (< 80 chars), alignment, line/paragraph spacing.

**1.4.9 Images of Text (No Exception) (AAA)**
Images of text used only for decoration or where presentation is essential.

**1.4.10 Reflow (AA)**
Content presents without two-dimensional scrolling at 320px width / 256px height.
- Test: Set browser to 320px wide. Verify no horizontal scrollbar appears.
- Test: Content stacks vertically, nothing is cut off.
- Exception: Data tables, maps, diagrams, toolbars may require horizontal scroll.

**1.4.11 Non-text Contrast (AA)**
UI components and graphical objects have 3:1 contrast against adjacent colors.
- Test: Form input borders against background.
- Test: Button backgrounds against page background.
- Test: Icons conveying information against their background.
- Test: Focus indicators against background.
- Test: Chart elements against their background.

**1.4.12 Text Spacing (AA)**
No loss of content when text spacing is modified (line height 1.5x, paragraph spacing 2x, letter spacing 0.12em, word spacing 0.16em).
- Test: Apply the above spacing via browser DevTools or bookmarklet. Verify no text is clipped or overlapped.

**1.4.13 Content on Hover or Focus (AA)**
Additional content triggered by hover/focus is dismissible, persistent, and hoverable.
- Test: Tooltips can be dismissed (Escape key) without moving focus.
- Test: Tooltip stays visible while hovering over it.
- Test: Tooltip stays visible until user dismisses it or trigger condition ends.

---

## Principle 2: Operable

UI components and navigation must be operable.

### 2.1 Keyboard Accessible

**2.1.1 Keyboard (A)**
All functionality operable via keyboard interface.
- Test: Tab through entire page. Every interactive element must be reachable.
- Test: Activate buttons with Enter and Space.
- Test: Activate links with Enter.
- Test: Operate dropdown menus with arrow keys.
- Test: Operate sliders with arrow keys.
- Test: Custom widgets (tabs, accordions, carousels) operable by keyboard.

**2.1.2 No Keyboard Trap (A)**
Focus can be moved away from any component using keyboard.
- Test: Tab through every focusable element. Verify focus never gets stuck.
- Test: Check modal dialogs allow tabbing within AND closing to escape.
- Test: Check embedded content (iframes, plugins) does not trap focus.

**2.1.4 Character Key Shortcuts (A)**
Single-character keyboard shortcuts can be turned off, remapped, or only active on focus.
- Test: Check if any single-key shortcuts exist (e.g., pressing "s" triggers search).
- Test: Verify they can be disabled or remapped, or only work when the relevant component is focused.

**2.1.3 Keyboard (No Exception) (AAA)**
All functionality operable via keyboard with no exceptions.

### 2.2 Enough Time

**2.2.1 Timing Adjustable (A)**
Users can turn off, adjust, or extend time limits.
- Test: Identify any timeouts (session, form submission, countdown).
- Test: Verify user can extend or disable the timeout.
- Test: Verify warning at least 20 seconds before timeout with option to extend.

**2.2.2 Pause, Stop, Hide (A)**
Moving, blinking, or auto-updating content lasting over 5 seconds can be paused/stopped/hidden.
- Test: Carousels, animations, auto-scrolling tickers have pause controls.
- Test: Auto-refreshing content can be paused.

**2.2.3 No Timing (AAA)** / **2.2.4 Interruptions (AAA)** / **2.2.5 Re-authenticating (AAA)** / **2.2.6 Timeouts (AAA)**
AAA criteria for timing. Document these as advisory findings if applicable.

### 2.3 Seizures and Physical Reactions

**2.3.1 Three Flashes or Below Threshold (A)**
No content flashes more than 3 times per second.
- Test: Review animations, video content, GIFs for rapid flashing.
- Test: Check CSS animations for rapid opacity/color changes.

**2.3.2 Three Flashes (AAA)** / **2.3.3 Animation from Interactions (AAA)**
AAA criteria. Check if `prefers-reduced-motion` is respected.

### 2.4 Navigable

**2.4.1 Bypass Blocks (A)**
Mechanism to bypass repeated content blocks.
- Test: Check for "Skip to main content" link as first focusable element.
- Test: Verify skip link becomes visible on focus and actually moves focus to main content.
- Test: Check ARIA landmarks are used to define page regions.

**2.4.2 Page Titled (A)**
Pages have descriptive titles.
- Test: Check `<title>` element exists and describes the page.
- Test: Title is unique across the site (not just the site name on every page).
- Test: For SPAs, title updates when route changes.

**2.4.3 Focus Order (A)**
Focus order preserves meaning and operability.
- Test: Tab order follows visual layout (left to right, top to bottom).
- Test: No `tabindex` values greater than 0.
- Test: Dynamically inserted content receives focus appropriately.

**2.4.4 Link Purpose (In Context) (A)**
Link purpose determinable from link text alone or with programmatic context.
- Test: No "click here" or "read more" links without additional context.
- Test: Links with identical text go to the same destination, OR are distinguishable by context.

**2.4.5 Multiple Ways (AA)**
More than one way to locate pages.
- Test: Site has at least two of: navigation menu, site search, sitemap, breadcrumbs.

**2.4.6 Headings and Labels (AA)**
Headings and labels describe topic or purpose.
- Test: Heading text is descriptive (not "Section 1").
- Test: Form labels clearly describe what is expected.

**2.4.7 Focus Visible (AA)**
Keyboard focus indicator is visible.
- Test: Tab through page. Every focused element has a visible outline/indicator.
- Test: Focus indicator has sufficient contrast (3:1 against adjacent colors).
- Test: No `outline: none` without replacement focus style.

**2.4.8 Location (AAA)** / **2.4.9 Link Purpose (Link Only) (AAA)** / **2.4.10 Section Headings (AAA)**
AAA criteria for navigation.

**2.4.11 Focus Not Obscured (Minimum) (AA) [WCAG 2.2 NEW]**
Focused elements are not entirely hidden by author-created content.
- Test: Tab through page with sticky headers, footers, cookie banners, chat widgets.
- Test: Verify the focused element is at least partially visible at all times.
- Test: Check modals and overlays don't obscure focusable elements behind them.

**2.4.12 Focus Not Obscured (Enhanced) (AAA) [WCAG 2.2 NEW]**
Focused elements are fully visible when focused.

**2.4.13 Focus Appearance (AAA) [WCAG 2.2 NEW]**
Focus indicator meets minimum area and contrast requirements.

### 2.5 Input Modalities

**2.5.1 Pointer Gestures (AA)**
Multipoint/path gestures have single-pointer alternatives.
- Test: Pinch-to-zoom has +/- buttons. Swipe has prev/next buttons.

**2.5.2 Pointer Cancellation (AA)**
Functions complete on up-event, not down-event.
- Test: Click and drag off the element — action should not fire.

**2.5.3 Label in Name (AA)**
Visible label text is included in the accessible name.
- Test: If button says "Submit", accessible name contains "Submit" (not something different).

**2.5.4 Motion Actuation (AAA)**
Motion-triggered functions have standard control alternatives and can be disabled.

**2.5.5 Target Size (Enhanced) (AAA)**
Touch targets minimum 44x44 CSS pixels.

**2.5.6 Concurrent Input Mechanisms (AAA)**
Content supports simultaneous use of multiple input modalities.

**2.5.7 Dragging Movements (AA) [WCAG 2.2 NEW]**
Drag operations have single-pointer alternatives.
- Test: Sortable lists have up/down buttons in addition to drag handles.
- Test: Drag-to-resize has a settings panel or keyboard alternative.
- Test: Map drag-to-pan has arrow buttons or keyboard controls.

**2.5.8 Target Size (Minimum) (AA) [WCAG 2.2 NEW]**
Interactive targets at least 24x24 CSS pixels, or with adequate spacing.
- Test: Measure clickable area of buttons, links, form controls.
- Test: Inline links within text are exempt if they span multiple characters.
- Test: Adjacent targets without spacing must each be 24x24 minimum.

---

## Principle 3: Understandable

Information and UI operation must be understandable.

### 3.1 Readable

**3.1.1 Language of Page (A)**
Default human language is programmatically determinable.
- Test: `<html>` has `lang` attribute with valid language code (e.g., `lang="en"`, `lang="es"`).

**3.1.2 Language of Parts (AA)**
Language changes within content are marked.
- Test: Inline text in different language has `lang` attribute on its container.

**3.1.3 Unusual Words (AAA)** / **3.1.4 Abbreviations (AAA)** / **3.1.5 Reading Level (AAA)** / **3.1.6 Pronunciation (AAA)**
AAA criteria for readability.

### 3.2 Predictable

**3.2.1 On Focus (A)**
No unexpected context change when component receives focus.
- Test: Tabbing to an element does not trigger navigation, form submission, or modal.

**3.2.2 On Input (A)**
No unexpected context change when user changes input value.
- Test: Selecting a radio button or dropdown option does not auto-submit or navigate away without warning.

**3.2.3 Consistent Navigation (AA)**
Navigation menus appear in the same relative order across pages.

**3.2.4 Consistent Identification (AA)**
Components with the same function have consistent labels across pages.
- Test: Search field is always labeled "Search" (not sometimes "Find" and sometimes "Search").

**3.2.5 Change on Request (AAA)**
Context changes only occur on user request.

**3.2.6 Consistent Help (A) [WCAG 2.2 NEW]**
Help mechanisms appear in the same relative position across pages.
- Test: If help link/chat/phone exists, it is in the same location on every page.
- Test: Check header, footer, and navigation for help placement consistency.

### 3.3 Input Assistance

**3.3.1 Error Identification (A)**
Input errors detected and described in text.
- Test: Submit form with empty required fields. Errors appear as text (not just color).
- Test: Error messages identify which field failed.

**3.3.2 Labels or Instructions (A)**
Labels or instructions provided for user input.
- Test: Every input has a visible label.
- Test: Complex inputs have format hints (e.g., "DD/MM/YYYY").
- Test: Required fields are marked.

**3.3.3 Error Suggestion (AA)**
Suggestions provided when input errors are detected.
- Test: Error messages suggest how to fix the problem (e.g., "Enter a valid email address like name@example.com").

**3.3.4 Error Prevention (Legal, Financial, Data) (AA)**
Submissions for important data are reversible, checked, or confirmed.
- Test: Financial transactions have confirmation step.
- Test: Legal agreements are reviewable before final submission.

**3.3.5 Help (AAA)** / **3.3.6 Error Prevention (All) (AAA)**
AAA criteria for input assistance.

**3.3.7 Redundant Entry (A) [WCAG 2.2 NEW]**
Previously entered information is auto-populated or selectable.
- Test: Multi-step forms do not ask for the same data twice.
- Test: If shipping/billing address is the same, user can indicate "same as above."
- Exception: Re-entering password for security is acceptable.

**3.3.8 Accessible Authentication (Minimum) (AA) [WCAG 2.2 NEW]**
Authentication does not require cognitive function tests unless alternatives exist.
- Test: Login does not depend on remembering a password without paste support.
- Test: CAPTCHAs have alternatives (audio, logic-based, or passkey/biometric).
- Test: Copy/paste is enabled on password fields.
- Test: Password managers can auto-fill credentials.

**3.3.9 Accessible Authentication (Enhanced) (AAA) [WCAG 2.2 NEW]**
Authentication uses no cognitive function tests at all.

---

## Principle 4: Robust

Content must be robust enough for diverse user agents and assistive technologies.

**4.1.2 Name, Role, Value (A)**
All UI components have programmatically determinable name, role, and value.
- Test: Custom widgets have appropriate ARIA roles.
- Test: Dynamic state changes are communicated (aria-expanded, aria-selected, aria-checked).
- Test: Custom components have accessible names (aria-label or aria-labelledby).

**4.1.3 Status Messages (AA)**
Status messages are communicated to assistive technologies without receiving focus.
- Test: Success messages use `role="status"` or `aria-live="polite"`.
- Test: Error summaries use `role="alert"` or `aria-live="assertive"`.
- Test: Loading indicators are announced.
- Test: Progress updates are communicated.

Note: SC 4.1.1 Parsing was removed in WCAG 2.2 as obsolete (modern browsers handle parsing errors gracefully).
