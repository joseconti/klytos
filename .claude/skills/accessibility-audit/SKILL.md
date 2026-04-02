---
name: accessibility-audit
description: >
  Perform comprehensive web accessibility audits covering WCAG 2.2 (A, AA, AAA),
  European Accessibility Act (EAA), ADA, Section 508, and EN 301 549.
  Generates detailed audit reports with findings, severity levels, legal risk assessment,
  and actionable fix recommendations. Use this skill whenever the user mentions
  accessibility, a11y, WCAG, ADA compliance, EAA, Section 508, screen reader compatibility,
  keyboard navigation issues, color contrast, ARIA attributes, or wants to verify
  their site meets accessibility standards. Also trigger when reviewing HTML/CSS/JS
  for inclusive design, preparing for an accessibility lawsuit, or building accessible
  components from scratch. If someone asks "is my site accessible?" or "does this
  meet WCAG?" — this is the skill to use.
---

# Accessibility Audit Skill

This skill guides a comprehensive web accessibility audit following international standards
and legal requirements. It combines automated testing with manual review checklists to
produce a professional audit report.

## Table of Contents

1. [Audit Workflow](#audit-workflow)
2. [Phase 1: Scope and Context](#phase-1-scope-and-context)
3. [Phase 2: Automated Testing](#phase-2-automated-testing)
4. [Phase 3: Manual Review](#phase-3-manual-review)
5. [Phase 4: Report Generation](#phase-4-report-generation)
6. [Reference Files](#reference-files)
7. [Severity Classification](#severity-classification)
8. [Legal Risk Assessment](#legal-risk-assessment)

---

## Audit Workflow

Every audit follows four phases. Adapt scope and depth to the user's needs, but
never skip the manual review — automated tools catch only 30-40% of real accessibility
issues.

```
Phase 1: Scope & Context  -->  Phase 2: Automated Testing
       |                              |
       v                              v
Phase 4: Report Generation  <--  Phase 3: Manual Review
```

---

## Phase 1: Scope and Context

Before touching any code, establish what you are auditing and against which standards.

### 1.1 Determine target pages or components

Ask the user (or inspect the project) to identify:
- Which pages, routes, or components to audit
- Whether this is a full site audit or a focused review
- Any known problem areas or user complaints

### 1.2 Determine applicable standards

Not all projects need every standard. Use this decision tree:

| If the project... | Then require... |
|---|---|
| Serves EU consumers (any business) | WCAG 2.1 AA + EAA/EN 301 549 |
| Is a US state/local government site | WCAG 2.1 AA (ADA Title II) |
| Is a US private sector site | WCAG 2.1 AA (de facto, ADA Title III case law) |
| Sells to US federal government | WCAG 2.0 AA (Section 508) |
| Wants best practice coverage | WCAG 2.2 AA (superset of all above) |
| Wants maximum inclusivity | WCAG 2.2 AAA where feasible |

When in doubt, audit against **WCAG 2.2 Level AA** — it is a superset that satisfies
all the above requirements. Read `references/legal-standards.md` for detailed
legal context on each standard.

### 1.3 Determine conformance target

- **Level A**: Bare minimum, removes the worst barriers
- **Level AA**: Industry standard, legally required in most jurisdictions
- **Level AAA**: Aspirational, recommended where feasible (especially for government, healthcare, education)

Default to **Level AA** unless the user specifies otherwise.

---

## Phase 2: Automated Testing

Run automated tools first to catch the low-hanging fruit. This is fast and gives
you a baseline, but remember: automated tools find at most 30-40% of WCAG issues.

### 2.1 Select and run tools

Read `references/automated-tools.md` for detailed guidance on each tool. The
recommended approach depends on what is available:

**If you have access to the source code (HTML/CSS/JS files):**
1. Review HTML structure for semantic correctness
2. Check CSS for contrast, text sizing, focus styles
3. Check JS for keyboard event handlers, focus management, ARIA usage
4. Use grep/search to find common anti-patterns (see Section 2.2)

**If you have access to a running site (via browser tools):**
1. Run axe-core or Lighthouse via browser extension
2. Run WAVE for visual overlay of issues
3. Run pa11y CLI for CI-compatible output

**If you have access to a build system:**
1. Integrate axe-core or pa11y into the test pipeline
2. Run as part of CI/CD

### 2.2 Common anti-patterns to search for in code

These are patterns that almost always indicate accessibility problems. Search for
them in the codebase:

```
# Images without alt text
<img> tags without alt attribute
<img alt=""> on informative images (empty alt is only for decorative images)

# Non-semantic interactive elements
<div onclick=       (should be <button>)
<span onclick=      (should be <button> or <a>)
<a> without href    (should be <button>)

# Missing form labels
<input> without associated <label> or aria-label
<select> without associated <label>
<textarea> without associated <label>

# Keyboard traps and missing handlers
tabindex values > 0  (breaks natural tab order)
onmousedown/onmouseover without keyboard equivalents
outline: none / outline: 0  (removes focus indicator)

# Missing landmarks and structure
No <main> element
No <nav> element
Missing lang attribute on <html>
No <h1> on the page
Heading levels that skip (h1 to h3)

# Color-only indicators
class names suggesting color-only state (e.g., .red-error, .green-success)
without accompanying text or icon

# Autoplaying media
autoplay attribute on <video> or <audio>

# Missing ARIA on dynamic content
Modal dialogs without role="dialog"
Dynamic content updates without aria-live regions
Custom widgets without appropriate ARIA roles
```

### 2.3 Record findings

For each finding, record:
- **Criterion**: Which WCAG SC it violates (e.g., 1.1.1)
- **Level**: A, AA, or AAA
- **Location**: File path, line number, selector, or URL
- **Description**: What the problem is
- **Severity**: Critical / Major / Minor / Advisory (see Severity Classification below)
- **Recommendation**: How to fix it

---

## Phase 3: Manual Review

This is where you catch the 60-70% of issues that automated tools miss. Go through
each category systematically.

Read `references/wcag-checklist.md` for the complete criterion-by-criterion checklist.
Below is a summary of what to review manually:

### 3.1 Keyboard Navigation
- Tab through all interactive elements — verify logical order
- Verify visible focus indicators on every focusable element
- Check no keyboard traps exist
- Verify all functionality is operable without a mouse
- Test modal dialogs: focus trap inside, Escape to close, focus returns on close
- Check skip links work (bypass repeated content)

### 3.2 Screen Reader Compatibility
- Heading hierarchy: one H1, no skipped levels, descriptive text
- All images have meaningful alt text (or alt="" for decorative)
- Form fields have programmatically associated labels
- Tables have proper headers (th, scope, or aria-labelledby)
- Dynamic content changes are announced (aria-live regions)
- Error messages are announced and associated with their fields
- Page title is descriptive

### 3.3 Visual Design
- Color contrast meets minimums (4.5:1 normal text, 3:1 large text, 3:1 UI components)
- Color is not the sole means of conveying information
- Text can be resized to 200% without loss of content
- Content reflows without horizontal scrolling at 320px width
- Text spacing can be adjusted without breaking layout
- Focus indicators have sufficient contrast (3:1 minimum)

### 3.4 Forms and Validation
- All fields have visible labels
- Required fields are indicated (not just by color)
- Error messages identify the field and suggest correction
- Errors are announced to screen readers (aria-describedby + aria-invalid)
- Form data is preserved after validation errors
- Authentication methods don't require cognitive function tests (WCAG 2.2)

### 3.5 Multimedia
- Prerecorded video has synchronized captions
- Prerecorded video has audio description (AA)
- Audio-only content has text transcript
- Media player controls are keyboard accessible
- No content flashes more than 3 times per second

### 3.6 WCAG 2.2 New Criteria (check these specifically)
- 2.4.11 Focus Not Obscured: focused elements not hidden by sticky headers/overlays
- 2.5.7 Dragging Movements: drag operations have button/keyboard alternatives
- 2.5.8 Target Size (Minimum): interactive targets at least 24x24 CSS pixels
- 3.2.6 Consistent Help: help mechanisms in same position across pages
- 3.3.7 Redundant Entry: previously entered data not re-requested
- 3.3.8 Accessible Authentication: login without cognitive function tests

### 3.7 Content and Structure
- Language attribute set on html element
- Language changes within content marked with lang attribute
- Semantic HTML used appropriately (nav, main, article, section, aside)
- Lists use proper list markup (ul/ol/li)
- Data tables vs layout tables distinguished (role="presentation" for layout)

### 3.8 Mobile and Responsive
- Content works in both portrait and landscape
- Touch targets are at least 24x24px (44x44px preferred)
- Gestures have single-pointer alternatives
- Content does not require horizontal scrolling at 320px width
- Zoom to 200% does not break functionality

---

## Phase 4: Report Generation

Generate a professional audit report. Read `references/report-template.md` for the
full template structure. The report must include:

### 4.1 Report Structure

```
1. Executive Summary
   - Overall conformance level achieved
   - Total findings by severity
   - Legal risk assessment
   - Top 3 priority fixes

2. Scope and Methodology
   - Pages/components audited
   - Standards applied
   - Tools used
   - Date of audit

3. Findings
   - Organized by WCAG principle (Perceivable, Operable, Understandable, Robust)
   - Each finding includes: criterion, level, severity, location, description, recommendation, code example
   - Findings sorted by severity within each principle

4. Conformance Checklist
   - Every applicable WCAG criterion listed
   - Status: Pass / Fail / Not Applicable / Not Tested
   - Notes for each criterion

5. Legal Compliance Summary
   - Status against each applicable regulation
   - Risk level and recommended actions

6. Remediation Roadmap
   - Priority 1: Critical and Major findings (fix immediately)
   - Priority 2: Minor findings (fix in next sprint)
   - Priority 3: Advisory items (plan for future)
   - Estimated effort per finding
```

### 4.2 Output format

Generate the report as a Markdown file by default. If the user requests it, also
generate as HTML for a more visual presentation.

Save the report to the project directory as `accessibility-audit-report.md` (or
the user's preferred location).

---

## Reference Files

Read these files when you need detailed information during the audit:

| File | When to read |
|---|---|
| `references/wcag-checklist.md` | During Phase 3 manual review — contains every WCAG 2.2 criterion with testing instructions |
| `references/legal-standards.md` | During Phase 1 scope definition and Phase 4 legal compliance summary |
| `references/automated-tools.md` | During Phase 2 when selecting and running automated tools |
| `references/report-template.md` | During Phase 4 when generating the audit report |
| `references/aria-patterns.md` | When reviewing ARIA usage on custom widgets and dynamic content |

---

## Severity Classification

Use this classification consistently across all findings:

**Critical** — Prevents a user group from completing a core task. Must fix before
any release. Examples: keyboard trap, missing form labels on login, no alt text
on essential images, no captions on instructional video.

**Major** — Significantly degrades the experience for a user group but workarounds
may exist. Fix within the current development cycle. Examples: poor color contrast
on body text, missing heading hierarchy, focus indicator invisible on some elements.

**Minor** — Inconvenience that does not block task completion. Fix in next planned
maintenance. Examples: redundant ARIA attributes, suboptimal tab order in non-critical
section, decorative image with non-empty alt text.

**Advisory** — Best practice recommendation or AAA-level suggestion. Plan for future
improvement. Examples: reading level above secondary education, no sign language
interpretation for video, touch targets between 24px and 44px.

---

## Legal Risk Assessment

When generating the legal compliance summary, assess risk for each applicable regulation:

**High Risk** — Multiple Critical or Major findings directly violating required criteria.
Active litigation likely if left unaddressed. Relevant for:
- EAA: Fines up to 100,000 EUR or 4% revenue
- ADA: Settlements averaging 25,000-75,000 USD; class actions can exceed 6M USD
- Section 508: Contract loss risk

**Medium Risk** — Some Major findings with workarounds available. Exposure exists
but immediate litigation unlikely. Remediation plan reduces risk significantly.

**Low Risk** — Minor findings only. Meets core conformance requirements. Ongoing
monitoring recommended.

Read `references/legal-standards.md` for jurisdiction-specific details including
enforcement timelines, penalty structures, and case law trends.

---

## Important Reminders

- Never claim a site is "fully accessible" — accessibility is a spectrum and ongoing process
- Automated tools are a starting point, not the finish line
- Test with real assistive technology when possible (screen readers, keyboard-only)
- Consider cognitive and motor disabilities, not just visual impairments
- Accessibility benefits all users, not just those with disabilities (SEO, mobile, performance)
- Document what was NOT tested (e.g., "screen reader testing was not performed") so
  the report is transparent about its limitations
