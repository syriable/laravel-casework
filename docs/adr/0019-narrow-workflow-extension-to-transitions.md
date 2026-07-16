# ADR-0019 — Narrow Workflow Extension to Transitions Only

**Status:** Accepted
**Date:** 2026-07-16
**Supersedes:** [ADR-0013](0013-workflow-extension-limits.md)

## Context

ADR-0013 let applications add both custom *states* and custom
*transitions* to the four shipped workflows. Custom states required a
graph-reachability engine at boot (`WorkflowDefinition::validate()`
walked the transition graph to prove every custom state was reachable
from creation and had a path back to a core state or terminal — no
traps). That engine is the single most intricate piece of machinery in
the package, and in practice it protects a capability few applications
use: most real customizations are extra *paths* through the existing
lifecycle (a shortcut, a bypass gated by a guard, a "return to open"
step), not genuinely new named states.

## Problem

Is the custom-state capability, and the reachability engine it
requires, worth its complexity budget?

## Alternatives

1. **Keep ADR-0013 as-is** — custom states and transitions, with the
   reachability engine.
2. **Narrow to transitions only** — applications may add transitions
   between *existing* states (core or, transitively, any state already
   declared), but may never introduce a new state name.
3. **Remove workflow extension entirely** — the four workflows become
   fixed; applications model extra flow outside the package.

## Decision

**Alternative 2: narrow to transitions only.** A custom transition may
connect any two declared states, gated by guards, and gets the full
pipeline (authorization, guards, audit entry, the generic
`StateTransitioned` event) exactly as before. What changes:

- `WorkflowDefinition::customStates()` is removed. `states()` is now
  simply `coreStates()`.
- The reachability engine (`assertConnected()`/`walk()`) is removed —
  it has nothing left to verify, because a transition can never target
  a state that wasn't already reachable (it was already declared).
- The remaining `validate()` checks are unchanged in spirit: every
  transition endpoint must be a declared state, terminals stay closed,
  and a custom transition may never retarget or duplicate a core one
  by name.

Alternative 1 is rejected: the reachability engine is exactly the kind
of speculative generality the package's own engineering principles
warn against — "no abstraction without a second concrete consumer."
Alternative 3 is rejected: it removes a genuinely useful capability
(a guarded shortcut transition, e.g. sending a case back to the open
queue for a second look) that costs almost nothing to keep once the
graph analysis is gone.

## Consequences

- **+** `WorkflowDefinition` and its `validate()` are substantially
  simpler — no graph walk, no state-name-collision/length rules, no
  "unreachable" or "trapped state" failure modes to document or test.
- **+** Applications can still add real transitions (with guards, full
  audit/eventing) between the lifecycle's existing states — the common
  case survives untouched.
- **−** Introducing a genuinely new named state (e.g. `awaiting_legal`)
  is no longer supported by the package; that need is now met by
  modeling the extra step as application-owned data (e.g. a note or a
  flag on the case) rather than a workflow state. Documented as a
  breaking change in `UPGRADE.md`.
- This is a **major-version** change per the support policy: it
  removes public API (`WorkflowDefinition::customStates()` and the
  connectivity guarantees it made).
