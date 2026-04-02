# Automated Testing Tools Reference

Guide for selecting and running automated accessibility testing tools.
Automated tools catch approximately 30-40% of WCAG issues. Always complement
with manual testing.

---

## Tool Selection Guide

| Scenario | Recommended Tool(s) |
|---|---|
| Quick check during development | WAVE browser extension or Lighthouse |
| Comprehensive code review | axe-core (browser extension or programmatic) |
| CI/CD pipeline integration | pa11y-ci or axe-core via Playwright/Puppeteer |
| Enterprise/compliance reporting | IBM Equal Access Checker |
| Non-technical stakeholder review | WAVE (visual overlay) |

---

## axe-core (Deque Systems)

### Overview
The most comprehensive open-source accessibility engine. Powers many other tools
(including Lighthouse's accessibility audit). Checks against WCAG 2.2 AA.

### What It Catches
- Missing alt text on images
- Color contrast violations
- Missing form labels
- ARIA attribute errors and misuse
- Heading hierarchy issues
- Missing document language
- Missing landmarks
- Link text issues
- Table structure problems
- Focus management issues (partial)

### What It Misses
- Keyboard navigation flow and logic
- Screen reader announcement quality
- Meaningful alt text (can detect missing, not quality)
- Visual layout issues beyond contrast
- Content logic and reading order
- Complex ARIA pattern correctness
- User workflow accessibility
- Cognitive accessibility

### Browser Extension Usage
1. Install "axe DevTools" from Chrome Web Store
2. Open Chrome DevTools (F12)
3. Navigate to "axe DevTools" tab
4. Click "Scan All of My Page"
5. Review results grouped by severity

### Programmatic Usage (Node.js)

```bash
npm install @axe-core/playwright  # For Playwright
npm install axe-core               # Core engine
```

**With Playwright:**
```javascript
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

test('accessibility check', async ({ page }) => {
  await page.goto('https://example.com');
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag22aa'])
    .analyze();
  expect(results.violations).toEqual([]);
});
```

**With Puppeteer:**
```javascript
const puppeteer = require('puppeteer');
const { AxePuppeteer } = require('@axe-core/puppeteer');

const browser = await puppeteer.launch();
const page = await browser.newPage();
await page.goto('https://example.com');
const results = await new AxePuppeteer(page)
  .withTags(['wcag2a', 'wcag2aa', 'wcag22aa'])
  .analyze();
console.log(results.violations);
```

### CI/CD Integration
- GitHub Actions: `@axe-core/cli` or custom Playwright tests
- Jenkins: Run via Node.js build step
- Results can be output as JSON, HTML, or JUnit XML

---

## Google Lighthouse

### Overview
Built into Chrome DevTools. Runs a subset of axe-core rules plus additional
performance-related accessibility checks. Good entry point but less comprehensive
than axe-core directly.

### What It Catches
- Color contrast issues
- Missing alt text
- Form label association
- Heading hierarchy
- Document language
- Basic ARIA errors
- Tab order issues (partial)
- Touch target sizes (partial)

### What It Misses
- Many WCAG 2.2 criteria
- Keyboard navigation flow
- Screen reader compatibility
- Complex ARIA patterns
- Content on hover/focus behavior
- Reflow at different viewports
- Text spacing tolerance

### Browser Usage
1. Open Chrome DevTools (F12)
2. Navigate to "Lighthouse" tab
3. Select "Accessibility" category
4. Click "Analyze page load"
5. Review accessibility score and individual audits

### CLI Usage

```bash
npm install -g lighthouse

# Basic audit
lighthouse https://example.com --only-categories=accessibility --output=json

# With specific settings
lighthouse https://example.com \
  --only-categories=accessibility \
  --output=json \
  --output-path=./accessibility-report.json \
  --chrome-flags="--headless"
```

### Interpreting the Score
Lighthouse scores 0-100 based on the severity and number of issues found.
A score of 100 does NOT mean the site is fully accessible — it means no automated
issues were detected. Manual testing is still required.

---

## pa11y

### Overview
Lightweight, fast, CLI-first tool for automated accessibility testing. Excellent
for CI/CD pipelines. Checks against WCAG 2.2 AA using the HTML CodeSniffer engine.

### What It Catches
- HTML validation issues
- WCAG violations (similar coverage to axe)
- Section 508 violations
- Best practice issues
- Form accessibility
- Image alt text

### What It Misses
- Same limitations as other automated tools
- Less granular ARIA checking than axe-core
- No visual overlay (CLI only)

### CLI Usage

```bash
npm install -g pa11y

# Basic test
pa11y https://example.com

# With specific standard
pa11y https://example.com --standard WCAG2AA

# JSON output for parsing
pa11y https://example.com --reporter json

# Test multiple pages
pa11y https://example.com/page1 https://example.com/page2

# With viewport size (for responsive testing)
pa11y https://example.com --viewport.width 320 --viewport.height 568
```

### pa11y-ci (Multiple Pages)

```bash
npm install -g pa11y-ci
```

Create `.pa11yci` config file:
```json
{
  "defaults": {
    "standard": "WCAG2AA",
    "timeout": 10000
  },
  "urls": [
    "https://example.com/",
    "https://example.com/about",
    "https://example.com/contact",
    {
      "url": "https://example.com/login",
      "actions": [
        "set field #username to test@example.com",
        "set field #password to password123",
        "click element #submit"
      ]
    }
  ]
}
```

Run: `pa11y-ci`

### CI/CD Integration
pa11y-ci is designed for CI pipelines. Returns exit code 1 if any issues found,
making it easy to fail builds on accessibility regressions.

---

## WAVE (WebAIM)

### Overview
Visual evaluation tool that overlays accessibility information directly on the page.
Excellent for non-technical reviewers and quick visual audits.

### What It Catches
- Contrast errors
- Missing alt text
- Form labels
- Heading structure
- Page title issues
- Redundant links
- Suspicious alternative text
- ARIA usage issues

### What It Misses
- Keyboard interaction
- Screen reader announcements
- Complex ARIA patterns
- Dynamic content behavior
- Cognitive accessibility

### Browser Extension Usage
1. Install "WAVE Evaluation Tool" from Chrome Web Store
2. Click WAVE icon in toolbar on any page
3. Review sidebar with categorized issues
4. Click icons on the page to see issue details
5. Use "Contrast" tab for detailed color analysis

### API Usage (Paid)

```bash
# WAVE API requires subscription
curl "https://wave.webaim.org/api/request?key=YOUR_KEY&url=https://example.com&reporttype=4"
```

### Strengths for Audits
- Non-technical stakeholders can understand the visual overlay
- Quick way to identify structural issues
- Contrast checker built in with suggested alternatives
- Shows positive features too (good practices already in place)

---

## IBM Equal Access Accessibility Checker

### Overview
IBM's open-source accessibility checker with browser extension and Node.js module.
Strong ARIA checking and particularly good for conformance reporting.

### What It Catches
- WCAG violations with detailed rule explanations
- ARIA best practices and errors
- Color contrast
- Form accessibility
- Heading structure
- Keyboard tab order visualization (unique feature)

### Browser Extension Usage
1. Install "IBM Equal Access Accessibility Checker" from Chrome Web Store
2. Open Chrome DevTools (F12)
3. Navigate to "Accessibility Assessment" tab
4. Click "Scan" to run assessment
5. Use "Keyboard Checker Mode" to visualize tab order

### Programmatic Usage

```bash
npm install accessibility-checker
```

```javascript
const { getCompliance } = require('accessibility-checker');

async function checkPage(url) {
  const result = await getCompliance(url, 'my-test');
  console.log(result.report.results);
}
```

### Integration with Test Frameworks
Works with Selenium, Puppeteer, Playwright, and Cypress. Produces reports
compatible with accessibility conformance documentation.

---

## Recommended Tool Stack for Different Project Types

### Small Project / Single Developer
1. WAVE browser extension for quick visual checks
2. Lighthouse for baseline score
3. Manual keyboard + screen reader testing

### Medium Project / Team
1. axe-core browser extension during development
2. pa11y-ci in CI/CD pipeline to prevent regressions
3. WAVE for visual review before releases
4. Manual testing with NVDA + VoiceOver

### Large Project / Enterprise
1. axe-core integrated into Playwright/Cypress test suite
2. pa11y-ci scanning all critical pages in CI
3. IBM Equal Access Checker for conformance reporting
4. WAVE for stakeholder reviews
5. Dedicated manual testing with multiple screen readers
6. Regular third-party audits

### Automated Testing Limitations

No combination of tools catches everything. The following categories always
require manual testing:

- Keyboard navigation flow (logical order, no traps)
- Screen reader announcement quality and accuracy
- Alt text quality (descriptive, not just present)
- Content reading order vs visual order
- Color-as-sole-indicator patterns
- Focus management in dynamic interactions
- Cognitive accessibility (reading level, clear instructions)
- Touch device usability
- Assistive technology compatibility across browsers
