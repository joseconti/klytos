# Template — Auth (centred)

**Used by**: Log in · Verify · Reset password.

No shell. No sidebar, no toolbar, no status bar, no copilot. The only screens in the admin
that a signed-out person sees, and therefore the only ones a screen reader meets cold.

---

## 1. Anatomy

```
body                     --fondo-ventana, centred both axes, min-height 100dvh
└─ main   400px column, gap 18
   ├─ app icon           48px, klytos-icon.svg
   ├─ h1                 --type-page-title, centred
   ├─ [optional] lede    --type-caption, --texto-sutil, centred, max 2 lines
   ├─ card               --fondo-elevado, radius 10, padding 24, gap 14
   │  ├─ fields          38px tall controls (auth size), radius 6
   │  └─ primary button  38px, full width
   └─ reassurance strip  --type-caption, --texto-sutil, centred, one line
```

The reassurance strip is not decoration: it is where the admin says what happens next
("We never email you a password" / "This link expires in 30 minutes").

---

## 2. States

**Default** — email focused on Log in; the code field focused on Verify; the new-password
field focused on Reset password. Autofocus is acceptable here and **only** here, because the
page has one job.

**Hover / active** — as the shared button and field rules.

**Focus** — the 2px accent ring. On `--fondo-ventana` the ring measures 4.23:1 (light) /
7.64:1 (dark) — above the 3:1 minimum.

**Disabled** — the submit is never disabled on these screens. An empty form submits and
returns a real error; a disabled submit with no explanation is how people get stuck at a
login page.

**Loading** — submit goes `aria-busy="true"`, label "Signing in…". The fields stay enabled.

**Error — credentials** — a `role="alert"` panel above the card, focus moved to it, and the
message is deliberately non-specific about which half was wrong:
> "That email and password do not match an account. **Reset your password**"
No field-level red on either field: the admin does not tell an attacker which one was right.

**Error — second factor** — this one *is* specific, because the person is already
authenticated:
> "That code is not valid. Codes change every 30 seconds — check the clock on your device,
> or **use a recovery code**."
After 5 failures: "Too many attempts. Try again in 15 minutes, or **use a recovery code**."
The wait is stated in words and the field is `readonly`, not `disabled`, so it keeps its
label association.

**Error — expired reset token** — the card is replaced entirely, because there is nothing to
fill in:
> "This reset link has expired. Links last 30 minutes. **Send a new link**"

**Error — password rules not met** — inline, under the field, with the **rules always
visible** (not revealed on failure): a list of the four rules, each with a met/unmet state
carried by an icon **and** the words "met" / "not met" in the accessible name, never by
colour or by a strikethrough alone. The strength meter is a `<progress>` with a text label
("Strength: strong") — the bar alone is not the message.

**Success — log in** — redirect. No interstitial.

**Success — reset password** — a `role="status"` confirmation page, not a toast, and it says
what happened to the other sessions:
> "Your password was changed. You are signed in on this device; every other session was
> signed out."

**Empty** — not applicable.

---

## 3. Responsive

| Width | Behaviour |
|---|---|
| **≥ 1440** | Reference. 400px column, vertically centred. |
| **1200–1439** | Identical. |
| **900–1199** | Identical. |
| **< 900** | Column becomes `min(400px, 100% − 32px)`; vertical centring becomes top alignment with 48px top padding below 640px height, so the on-screen keyboard cannot push the submit off-screen. |

`min-height: 100dvh`, not `100vh` — `vh` is wrong on mobile browsers with a dynamic toolbar.
Nothing on these screens depends on viewport height.

---

## 4. Accessibility

- Landmarks: `<main>` only. **No `banner`, no `navigation`, no `contentinfo`** — there is
  nothing to navigate to. A skip link on a one-form page is noise; it is omitted here and
  only here.
- One `<h1>`: "Sign in to Klytos" / "Two-factor authentication" / "Choose a new password".
- The form is a `<form>` with a real `action` and `method="post"`. These screens work fully
  with JavaScript disabled; nothing on them is JS-only.
- `autocomplete`: `username` + `email` on the email field, `current-password` on log in,
  `one-time-code` + `inputmode="numeric"` on the code field, `new-password` on reset (twice).
- The code field is one input, not six boxes. Six boxes break paste, break screen readers,
  and break autofill.
- The password-rules list is `<ul>`; each `<li>`'s state is in its text, and the list is in
  the password field's `aria-describedby`.
- The strength meter is `<progress>` + a text label, both in `aria-describedby`.
- "Show password" is a `<button aria-pressed>`, not a checkbox, and it never removes focus
  from the field.
- Errors: `role="alert"`, focus moved on load, and the field that can be corrected keeps
  `aria-invalid` — except in the credentials case, where neither field is marked.
- The app icon is `aria-hidden="true"`; the `<h1>` already says the product.
- Both themes ship on auth too: the theme comes from the cookie, and where there is no
  cookie yet, from `prefers-color-scheme`.
