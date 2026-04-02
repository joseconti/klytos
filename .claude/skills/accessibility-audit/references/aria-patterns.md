# ARIA Patterns Reference

Common ARIA patterns for custom widgets, with correct implementation and common mistakes.
Use this reference when auditing ARIA usage during Phase 3 manual review.

---

## When to Use ARIA

ARIA (Accessible Rich Internet Applications) should only be used when native HTML
semantics are insufficient. The first rule of ARIA: if you can use a native HTML
element that provides the semantics you need, use it instead of adding ARIA.

**Good**: `<button>Submit</button>`
**Bad**: `<div role="button" tabindex="0">Submit</div>`

ARIA adds accessibility information but does NOT add behavior. A `div` with
`role="button"` still needs JavaScript for Enter/Space key handling, focus management,
and all the behavior a `<button>` provides natively.

---

## Landmark Roles

Landmarks help screen reader users navigate the page structure quickly.

### Correct Usage

```html
<header role="banner">         <!-- One per page, maps to <header> -->
  <nav role="navigation" aria-label="Main menu">
    ...
  </nav>
</header>

<main role="main">             <!-- One per page, maps to <main> -->
  <section aria-labelledby="section-title">
    <h2 id="section-title">Products</h2>
    ...
  </section>
</main>

<aside role="complementary">   <!-- Maps to <aside> -->
  ...
</aside>

<footer role="contentinfo">    <!-- One per page, maps to <footer> -->
  ...
</footer>
```

### Common Mistakes
- Multiple `role="banner"` or `role="contentinfo"` on a page
- Using `role="navigation"` without `aria-label` when there are multiple navs
- Missing `<main>` landmark entirely
- Nesting landmarks incorrectly (e.g., `<main>` inside `<aside>`)

---

## Modal Dialog

### Correct Implementation

```html
<div role="dialog" aria-modal="true" aria-labelledby="dialog-title">
  <h2 id="dialog-title">Confirm Deletion</h2>
  <p>Are you sure you want to delete this item?</p>
  <button>Cancel</button>
  <button>Delete</button>
</div>
```

### Required Behavior
- Focus moves to the dialog (or its first focusable element) when opened
- Focus is trapped within the dialog (Tab cycles through dialog elements only)
- Escape key closes the dialog
- Focus returns to the element that triggered the dialog when closed
- Background content is inert (not focusable, not readable by screen reader)

### Common Mistakes
- Missing `aria-modal="true"` (screen readers may still read background content)
- No focus trap (user can Tab to elements behind the dialog)
- Focus does not return to trigger element on close
- Missing accessible name (`aria-labelledby` or `aria-label`)
- Using `role="dialog"` on a non-modal popover (use `role="dialog"` without `aria-modal`)

---

## Tabs

### Correct Implementation

```html
<div role="tablist" aria-label="Product information">
  <button role="tab" id="tab-1" aria-selected="true" aria-controls="panel-1">
    Description
  </button>
  <button role="tab" id="tab-2" aria-selected="false" aria-controls="panel-2" tabindex="-1">
    Reviews
  </button>
  <button role="tab" id="tab-3" aria-selected="false" aria-controls="panel-3" tabindex="-1">
    Specifications
  </button>
</div>

<div role="tabpanel" id="panel-1" aria-labelledby="tab-1">
  <!-- Description content -->
</div>

<div role="tabpanel" id="panel-2" aria-labelledby="tab-2" hidden>
  <!-- Reviews content -->
</div>

<div role="tabpanel" id="panel-3" aria-labelledby="tab-3" hidden>
  <!-- Specifications content -->
</div>
```

### Required Behavior
- Arrow keys move between tabs (Left/Right for horizontal, Up/Down for vertical)
- Tab key moves focus from the tab to the tab panel content
- Only the active tab has `tabindex="0"` (others have `tabindex="-1"`)
- `aria-selected="true"` on the active tab only
- Home/End keys move to first/last tab (optional but recommended)

### Common Mistakes
- All tabs have `tabindex="0"` (forces users to Tab through every tab)
- Missing `aria-controls` linking tabs to panels
- Missing `aria-selected` state management
- Using links instead of buttons for tabs
- Tab panels missing `aria-labelledby`

---

## Accordion

### Correct Implementation

```html
<div>
  <h3>
    <button aria-expanded="true" aria-controls="section-1-content">
      Section 1 Title
    </button>
  </h3>
  <div id="section-1-content" role="region" aria-labelledby="section-1-btn">
    <!-- Expanded content -->
  </div>

  <h3>
    <button aria-expanded="false" aria-controls="section-2-content">
      Section 2 Title
    </button>
  </h3>
  <div id="section-2-content" role="region" aria-labelledby="section-2-btn" hidden>
    <!-- Collapsed content -->
  </div>
</div>
```

### Required Behavior
- `aria-expanded` toggles between "true" and "false"
- Enter/Space activates the accordion header
- Content panel is hidden/shown accordingly

### Common Mistakes
- Missing `aria-expanded` attribute
- Using `display: none` without `hidden` attribute (some screen readers may still read)
- Headers not using actual heading elements (breaks heading navigation)
- Missing `aria-controls` association

---

## Dropdown Menu

### Correct Implementation

```html
<button aria-haspopup="true" aria-expanded="false" aria-controls="menu-1">
  Actions
</button>
<ul role="menu" id="menu-1" hidden>
  <li role="menuitem"><a href="/edit">Edit</a></li>
  <li role="menuitem"><a href="/duplicate">Duplicate</a></li>
  <li role="separator"></li>
  <li role="menuitem"><a href="/delete">Delete</a></li>
</ul>
```

### Required Behavior
- Enter/Space opens menu from trigger button
- Arrow keys navigate between menu items
- Enter activates the focused menu item
- Escape closes the menu and returns focus to trigger
- Home/End move to first/last item
- Type-ahead: typing a letter moves to next item starting with that letter

### Common Mistakes
- Missing `aria-haspopup="true"` on trigger
- Missing `aria-expanded` state management
- No keyboard navigation within menu items
- Menu does not close on Escape
- Focus not managed when menu opens/closes

---

## Live Regions

Use live regions to announce dynamic content changes to screen readers.

### Types

```html
<!-- Polite: announced when screen reader finishes current speech -->
<div aria-live="polite">
  3 items added to your cart
</div>

<!-- Assertive: announced immediately, interrupting current speech -->
<div role="alert">
  Error: Your session has expired. Please log in again.
</div>

<!-- Status: like polite, used for status updates -->
<div role="status">
  Saving... Done!
</div>
```

### Guidelines
- Use `aria-live="polite"` for most updates (cart count, save confirmation)
- Use `role="alert"` or `aria-live="assertive"` only for urgent messages (errors, warnings)
- Use `role="status"` for status messages (loading, saving, search results count)
- The live region must exist in the DOM BEFORE content is injected into it
- Use `aria-atomic="true"` when the entire region should be re-read on any change

### Common Mistakes
- Creating the live region AND its content simultaneously (screen reader may not detect)
- Using `aria-live="assertive"` for non-urgent updates (interrupts user flow)
- Too many live regions competing (screen reader gets overwhelmed)
- Updating live regions too frequently (e.g., every keystroke of a search)
- Not using `aria-atomic` when partial updates would be confusing

---

## Form Patterns

### Labels and Descriptions

```html
<!-- Visible label (preferred) -->
<label for="email">Email address</label>
<input type="email" id="email" aria-describedby="email-hint">
<span id="email-hint">We will never share your email.</span>

<!-- Hidden label (use only when visible label is redundant) -->
<input type="search" aria-label="Search products">

<!-- Group label -->
<fieldset>
  <legend>Shipping Address</legend>
  <label for="street">Street</label>
  <input type="text" id="street">
  ...
</fieldset>
```

### Error States

```html
<label for="email">Email address</label>
<input type="email" id="email"
  aria-invalid="true"
  aria-describedby="email-error">
<span id="email-error" role="alert">
  Please enter a valid email address (e.g., name@example.com)
</span>
```

### Required Fields

```html
<label for="name">Full name <span aria-hidden="true">*</span></label>
<input type="text" id="name" aria-required="true" required>
```

### Common Mistakes
- Using `placeholder` as a substitute for `<label>` (disappears when typing)
- `aria-invalid="true"` set before user interacts with the field
- Error messages not associated with the field via `aria-describedby`
- Missing `<fieldset>`/`<legend>` for radio button and checkbox groups
- Using `aria-required` without also using the `required` attribute (or vice versa)

---

## Custom Toggle / Switch

### Correct Implementation

```html
<button role="switch" aria-checked="false" aria-label="Dark mode">
  <span aria-hidden="true">Off</span>
</button>
```

### Required Behavior
- Space key toggles the switch
- `aria-checked` toggles between "true" and "false"
- Screen reader announces "Dark mode, switch, off" (or similar)

---

## Breadcrumb Navigation

### Correct Implementation

```html
<nav aria-label="Breadcrumb">
  <ol>
    <li><a href="/">Home</a></li>
    <li><a href="/products">Products</a></li>
    <li><a href="/products/shoes" aria-current="page">Shoes</a></li>
  </ol>
</nav>
```

### Key Points
- Use `<nav>` with `aria-label="Breadcrumb"`
- Use ordered list for semantic structure
- Mark current page with `aria-current="page"`
- Visual separators (/, >) should be CSS-generated, not in the DOM

---

## Tooltip

### Correct Implementation

```html
<button aria-describedby="tooltip-1">
  Settings
</button>
<div role="tooltip" id="tooltip-1">
  Manage your account preferences
</div>
```

### Required Behavior (WCAG 1.4.13)
- Tooltip appears on hover AND focus
- User can hover over the tooltip itself without it disappearing
- Escape key dismisses the tooltip without moving focus
- Tooltip persists until user dismisses or moves away

---

## ARIA Attribute Quick Reference

| Attribute | Purpose | Used On |
|---|---|---|
| `aria-label` | Accessible name (when no visible label) | Any element |
| `aria-labelledby` | Points to element containing the accessible name | Any element |
| `aria-describedby` | Points to element with additional description | Any element |
| `aria-expanded` | Whether a collapsible section is open | Button, link |
| `aria-controls` | Points to the element this controls | Button, tab |
| `aria-selected` | Whether item is selected | Tab, option, row |
| `aria-checked` | Whether checkbox/switch is checked | Checkbox, switch |
| `aria-hidden` | Hide from assistive technology | Decorative elements |
| `aria-live` | Announce dynamic changes | Container for updates |
| `aria-invalid` | Whether input has validation error | Form fields |
| `aria-required` | Whether input is required | Form fields |
| `aria-disabled` | Whether element is disabled | Any interactive |
| `aria-current` | Current item in a set | Link, step, date |
| `aria-haspopup` | Element triggers a popup | Button |
| `aria-modal` | Whether dialog traps focus | Dialog |
| `aria-atomic` | Re-read entire region on change | Live regions |
| `aria-busy` | Region is being updated | Live regions |
