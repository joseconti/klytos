# Accessibility Audit Report Template

Use this template to generate the final audit report. Replace placeholders with
actual audit data. The report should be generated as Markdown by default.

---

## Template

```markdown
# Accessibility Audit Report

**Project:** [Project Name]
**URL(s):** [URLs audited]
**Date:** [Audit date]
**Auditor:** [Name / AI-assisted audit]
**Standard:** [WCAG 2.2 Level AA / other]
**Applicable Regulations:** [EAA, ADA, Section 508, etc.]

---

## Executive Summary

### Overall Conformance
[Statement of conformance level achieved, e.g., "The audited pages do not currently
meet WCAG 2.2 Level AA conformance. X critical and Y major issues were identified
that must be resolved to achieve conformance."]

### Findings Summary

| Severity | Count |
|----------|-------|
| Critical | X     |
| Major    | X     |
| Minor    | X     |
| Advisory | X     |
| **Total**| **X** |

### Top Priority Fixes
1. [Most impactful fix — brief description]
2. [Second priority — brief description]
3. [Third priority — brief description]

### Legal Risk Assessment
[Brief risk assessment based on applicable regulations. E.g., "Given the X critical
findings affecting core user flows, the current legal risk under the EAA is HIGH.
Under ADA Title III case law, the site presents MEDIUM risk due to Y."]

---

## Scope and Methodology

### Pages Audited

| Page | URL | Type |
|------|-----|------|
| [Page name] | [URL] | [Homepage / Form / Checkout / etc.] |

### Standards Applied
- WCAG 2.2 Level AA (primary)
- [Additional standards as applicable]

### Tools Used
- [List automated tools used, e.g., axe-core v4.x, WAVE, Lighthouse]
- Manual keyboard navigation testing
- Manual screen reader testing with [NVDA / VoiceOver / etc.]
- [Other tools or methods]

### Limitations
[Document what was NOT tested. E.g., "Screen reader testing was performed with
NVDA on Chrome only. Mobile device testing was not performed. Third-party embedded
content (maps, payment forms) was not audited."]

---

## Findings

### Principle 1: Perceivable

#### Finding P-001: [Short title]
- **Criterion:** [e.g., 1.1.1 Non-text Content]
- **Level:** [A / AA / AAA]
- **Severity:** [Critical / Major / Minor / Advisory]
- **Location:** [File path, URL, or component name]
- **Description:** [What the problem is]
- **Impact:** [Who is affected and how]
- **Current Code:**
  ```html
  [Code showing the problem]
  ```
- **Recommended Fix:**
  ```html
  [Code showing the solution]
  ```
- **Estimated Effort:** [Low / Medium / High]

[Repeat for each finding under Perceivable]

### Principle 2: Operable

#### Finding O-001: [Short title]
[Same structure as above]

### Principle 3: Understandable

#### Finding U-001: [Short title]
[Same structure as above]

### Principle 4: Robust

#### Finding R-001: [Short title]
[Same structure as above]

---

## Conformance Checklist

### Level A Criteria

| Criterion | Description | Status | Notes |
|-----------|-------------|--------|-------|
| 1.1.1 | Non-text Content | Pass/Fail/N/A | [Details] |
| 1.2.1 | Audio-only and Video-only | Pass/Fail/N/A | |
| 1.2.2 | Captions (Prerecorded) | Pass/Fail/N/A | |
| 1.2.3 | Audio Description or Media Alternative | Pass/Fail/N/A | |
| 1.3.1 | Info and Relationships | Pass/Fail/N/A | |
| 1.3.2 | Meaningful Sequence | Pass/Fail/N/A | |
| 1.3.3 | Sensory Characteristics | Pass/Fail/N/A | |
| 1.4.1 | Use of Color | Pass/Fail/N/A | |
| 1.4.2 | Audio Control | Pass/Fail/N/A | |
| 2.1.1 | Keyboard | Pass/Fail/N/A | |
| 2.1.2 | No Keyboard Trap | Pass/Fail/N/A | |
| 2.1.4 | Character Key Shortcuts | Pass/Fail/N/A | |
| 2.2.1 | Timing Adjustable | Pass/Fail/N/A | |
| 2.2.2 | Pause, Stop, Hide | Pass/Fail/N/A | |
| 2.3.1 | Three Flashes or Below Threshold | Pass/Fail/N/A | |
| 2.4.1 | Bypass Blocks | Pass/Fail/N/A | |
| 2.4.2 | Page Titled | Pass/Fail/N/A | |
| 2.4.3 | Focus Order | Pass/Fail/N/A | |
| 2.4.4 | Link Purpose (In Context) | Pass/Fail/N/A | |
| 3.1.1 | Language of Page | Pass/Fail/N/A | |
| 3.2.1 | On Focus | Pass/Fail/N/A | |
| 3.2.2 | On Input | Pass/Fail/N/A | |
| 3.2.6 | Consistent Help [2.2] | Pass/Fail/N/A | |
| 3.3.1 | Error Identification | Pass/Fail/N/A | |
| 3.3.2 | Labels or Instructions | Pass/Fail/N/A | |
| 3.3.7 | Redundant Entry [2.2] | Pass/Fail/N/A | |
| 4.1.2 | Name, Role, Value | Pass/Fail/N/A | |

### Level AA Criteria

| Criterion | Description | Status | Notes |
|-----------|-------------|--------|-------|
| 1.2.4 | Captions (Live) | Pass/Fail/N/A | |
| 1.2.5 | Audio Description (Prerecorded) | Pass/Fail/N/A | |
| 1.3.4 | Orientation | Pass/Fail/N/A | |
| 1.3.5 | Identify Input Purpose | Pass/Fail/N/A | |
| 1.4.3 | Contrast (Minimum) | Pass/Fail/N/A | |
| 1.4.4 | Resize Text | Pass/Fail/N/A | |
| 1.4.5 | Images of Text | Pass/Fail/N/A | |
| 1.4.10 | Reflow | Pass/Fail/N/A | |
| 1.4.11 | Non-text Contrast | Pass/Fail/N/A | |
| 1.4.12 | Text Spacing | Pass/Fail/N/A | |
| 1.4.13 | Content on Hover or Focus | Pass/Fail/N/A | |
| 2.4.5 | Multiple Ways | Pass/Fail/N/A | |
| 2.4.6 | Headings and Labels | Pass/Fail/N/A | |
| 2.4.7 | Focus Visible | Pass/Fail/N/A | |
| 2.4.11 | Focus Not Obscured (Min) [2.2] | Pass/Fail/N/A | |
| 2.5.1 | Pointer Gestures | Pass/Fail/N/A | |
| 2.5.2 | Pointer Cancellation | Pass/Fail/N/A | |
| 2.5.3 | Label in Name | Pass/Fail/N/A | |
| 2.5.7 | Dragging Movements [2.2] | Pass/Fail/N/A | |
| 2.5.8 | Target Size (Minimum) [2.2] | Pass/Fail/N/A | |
| 3.1.2 | Language of Parts | Pass/Fail/N/A | |
| 3.2.3 | Consistent Navigation | Pass/Fail/N/A | |
| 3.2.4 | Consistent Identification | Pass/Fail/N/A | |
| 3.3.3 | Error Suggestion | Pass/Fail/N/A | |
| 3.3.4 | Error Prevention (Legal, Financial) | Pass/Fail/N/A | |
| 3.3.8 | Accessible Authentication [2.2] | Pass/Fail/N/A | |
| 4.1.3 | Status Messages | Pass/Fail/N/A | |

---

## Legal Compliance Summary

### European Accessibility Act (EAA)

| Requirement | Status | Risk Level |
|-------------|--------|------------|
| WCAG 2.1 AA web compliance | [Met / Not Met / Partial] | [High / Medium / Low] |
| Customer support accessibility | [Met / Not Met / Not Assessed] | |
| Documentation accessibility | [Met / Not Met / Not Assessed] | |
| Authentication alternatives | [Met / Not Met / N/A] | |

**EAA Overall Risk:** [High / Medium / Low]
**Potential Exposure:** [Up to 100,000 EUR or 4% revenue]
**Recommendation:** [Specific action items]

### ADA (US)

| Requirement | Status | Risk Level |
|-------------|--------|------------|
| WCAG 2.1 AA conformance | [Met / Not Met / Partial] | [High / Medium / Low] |
| Core user flows accessible | [Yes / No / Partial] | |

**ADA Litigation Risk:** [High / Medium / Low]
**Typical Settlement Range:** [Based on severity of findings]
**Recommendation:** [Specific action items]

### Section 508 (if applicable)

| Requirement | Status | Risk Level |
|-------------|--------|------------|
| WCAG 2.0 AA conformance | [Met / Not Met / Partial] | [High / Medium / Low] |
| VPAT documentation current | [Yes / No / N/A] | |

---

## Remediation Roadmap

### Priority 1: Critical (Fix Immediately)

| Finding | Description | Estimated Effort | Assigned To |
|---------|-------------|-----------------|-------------|
| [ID] | [Brief description] | [Hours / Days] | [Team/Person] |

### Priority 2: Major (Fix This Sprint)

| Finding | Description | Estimated Effort | Assigned To |
|---------|-------------|-----------------|-------------|
| [ID] | [Brief description] | [Hours / Days] | [Team/Person] |

### Priority 3: Minor (Fix Next Sprint)

| Finding | Description | Estimated Effort | Assigned To |
|---------|-------------|-----------------|-------------|
| [ID] | [Brief description] | [Hours / Days] | [Team/Person] |

### Priority 4: Advisory (Plan for Future)

| Finding | Description | Estimated Effort | Assigned To |
|---------|-------------|-----------------|-------------|
| [ID] | [Brief description] | [Hours / Days] | [Team/Person] |

### Estimated Total Remediation Effort
- Priority 1 (Critical): [X hours/days]
- Priority 2 (Major): [X hours/days]
- Priority 3 (Minor): [X hours/days]
- **Total**: [X hours/days]

---

## Appendix

### Testing Environment
- Browser(s): [Chrome XX, Firefox XX, Safari XX]
- Operating System: [Windows 11, macOS XX]
- Screen Reader(s): [NVDA XX, VoiceOver, JAWS XX]
- Device(s): [Desktop, tablet model, mobile model]
- Viewport(s) tested: [1920x1080, 768x1024, 320x568]

### References
- WCAG 2.2: https://www.w3.org/TR/WCAG22/
- Understanding WCAG 2.2: https://www.w3.org/WAI/WCAG22/Understanding/
- ARIA Authoring Practices: https://www.w3.org/WAI/ARIA/apg/
- EAA Directive: https://eur-lex.europa.eu/eli/dir/2019/882/oj
- EN 301 549: https://www.etsi.org/deliver/etsi_en/301500_301599/301549/
- ADA Title II Rule: https://www.ada.gov/resources/web-guidance/
```

---

## Report Generation Guidelines

When filling in the template:

1. **Be specific in descriptions.** "Some images lack alt text" is not useful.
   "5 product images on /shop lack alt attributes (lines 45, 67, 89, 102, 118 in
   shop.html)" is actionable.

2. **Include code examples.** Show the current problematic code AND the recommended
   fix. Developers can copy-paste the fix.

3. **Estimate effort realistically.** A missing alt attribute is 5 minutes. Rebuilding
   a custom dropdown to be keyboard-accessible is a day.

4. **Prioritize by user impact.** A missing alt on a hero image is more impactful than
   a missing alt on a decorative border.

5. **Note what was NOT tested.** Transparency about limitations builds trust and
   sets expectations for follow-up testing.

6. **Use finding IDs consistently.** P-001 (Perceivable), O-001 (Operable),
   U-001 (Understandable), R-001 (Robust). This makes referencing findings in
   the remediation roadmap clear.

7. **Legal risk should be conservative.** When in doubt, assess risk as higher.
   It is better to over-prepare than to be caught off-guard.
