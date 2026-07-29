# Rubric — API ergonomics (Klytos CMS)

> Recorded per Keel Phase 2 §6a, accepted 2026-07-28 (**D-066**). This is the one domain in this
> project where "correct" and "good" come apart: a hook, an MCP tool or a plugin entry point can pass
> every mechanical check — conventions, i18n, docs, capability gate — and still be a shape third
> parties have to work around forever.
>
> **Why this domain and not another.** Klytos is released, has installs, and is explicitly built to be
> extended: plugins register MCP tools, admin pages, routes and hooks. Once a third party binds to a
> shape, that shape cannot change in a release without breaking their site. Visual choices can be
> revised; a published surface cannot.
>
> **How it is applied — three rules, no exceptions.**
> 1. **Recorded, never improvised.** The reviewer applies THIS file. No rubric on record for a domain
>    means no rubric pass for it — it does not mean the reviewer invents criteria.
> 2. **The reviewer flags, never rewrites.** A rubric finding is a `file:line` observation with the
>    criterion it misses and a concrete alternative shape. Acting on it is the author's call.
> 3. **The fixed artifact wins.** Where this rubric disagrees with `docs/03-technical-plan.md`,
>    `docs/decisions.md`, `docs/BUILD-SPEC.md` or the design handoff, the artifact governs and this
>    file is the thing that gets corrected.
>
> Applied at authoring time (the slice that adds or changes a public surface), not as a later sweep.

## Scope

Every surface a third party can reach: `klytos_*` global helpers, `Klytos\…` public classes and
methods, hooks (actions and filters), MCP tools registered through `ToolRegistry`, admin-page and
route registrations, and the plugin entry-point contract. Internal helpers that no plugin can reach
are out of scope.

## Criteria

### 1. The name says what it does, in the caller's vocabulary

Good: the name reads as the sentence the caller is thinking (`klytos_get_page_by_path`,
`page.before_save`). Weak: the name describes the implementation, the storage backend or the
internal class that happens to own it. A caller who has never read the source should be able to
guess the name from the intent, and guess the intent from the name.

Hook names are dot-namespaced lowercase and read `<subject>.<moment>` — the subject first, so an
alphabetical list of hooks groups by what they act on rather than by when.

### 2. The signature is the smallest thing that can do the job — and can grow

A parameter that exists for one caller's convenience is a parameter every future caller has to read.
Prefer a required minimum plus an options array over a long positional list: positional parameters
four and beyond cannot be extended without a breaking change, an options key can. Booleans that
select behaviour (`$hard = true`) hide the decision at the call site — name the behaviour instead.

### 3. Return shapes are predictable, and failure is not a surprise

One surface returns one kind of thing. A method that returns an object on success, `false` on a
missing record and `null` on a permission failure forces every caller to write three branches and
guess which is which. Say in the docblock what "not found" looks like versus "refused" versus
"broke", and keep that answer the same across sibling methods. A refusal carries a machine-readable
reason (the `SafeHttp` `REASON_*` constants are the shape to copy) — an assertion that a call was
refused is worth much less than an assertion of WHY, and this project has already paid for that
lesson (**L-012**).

### 4. Every meaningful decision is reachable from outside

Per the recorded extensibility rule: user-facing strings are filterable, queries and responses are
filterable, and a decision exposes a before/after action. The rubric adds the ergonomic half — an
extension point is only real if a plugin can use it **without** re-implementing the surrounding
logic. Pass the listener enough context to decide (the subject, the actor, the operative values), and
say plainly in the docblock what mutating the filtered value does and does not change downstream.

A seam is not a sink (**L-019**): if the docblock says something is logged, audited or notified,
name the listener that writes it — or state, in those words, that core subscribes nothing.

### 5. The MCP tool contract is written for a caller that cannot ask a follow-up question

An MCP tool's description and `inputSchema` are the entire briefing an AI gets. Judge them as such:
the description states what the tool does, what it changes, and what it requires to have happened
first; enums are exhaustive; a parameter whose wrong value silently produces a wrong result is
described, not merely typed. Annotations are honest — a tool that writes is never advertised as
read-only. The refusal message names the tool and the fix, never the caller's role or the required
capability (**D-046**).

### 6. The obvious call is the safe call

The path of least resistance should be the correct one: escaping on by default, the capability check
inside the surface rather than expected of the caller, the destructive variant harder to reach than
the safe one. Where the safe form is the longer one, that is a finding.

### 7. Consistency with what already exists beats local elegance

A new surface that is 10 % nicer but shaped unlike its five siblings makes the whole API harder. Grep
`docs/api/INDEX.md` for the neighbours first; match their naming, parameter order, return shape and
error convention. If the existing shape is genuinely wrong, that is a decision entry and a change to
all of them — not one better-shaped outlier.

### 8. One documented example is enough to use it correctly

The runnable example in `docs/api/` is part of the surface, not decoration. If the example needs
prose around it to be usable, the surface is under-specified — most often because a prerequisite
(boot order, a capability, a prior call) is implicit. This is the cheapest place to catch an
ergonomics problem, because writing the example is where an awkward shape first hurts.

## Reviewer output

Findings only, ordered by severity:

`installer/core/example.php:123 — criterion N — what the shape costs a caller — a concrete alternative`

If a surface passes, one line saying so. Silence is not a pass.
