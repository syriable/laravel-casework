# ADR-0013 — Workflow Extension Limits

**Status:** Proposed (Gate G7)
**Date:** 2026-07-14
**Phase:** 7 — Workflows & State Machines

## Context

FR-903: applications may add states and transitions, but core states are non-removable.
Unlimited workflow surgery would break package operations (decide assumes decidable
states exist), events, audit semantics, and upgrade safety.

## Problem

Exactly how far may an application modify the shipped workflows?

## Alternatives

1. **Bounded extension** — add-only, with structural rules preserving core semantics.
2. **Full replacement** — apps may swap entire workflow definitions.
3. **No extension** — workflows fixed; apps model extra flow outside the package.

## Decision

**Alternative 1: bounded, add-only extension**, via extending the container-bound
`WorkflowDefinition` (ADR-0012). Rules, enforced by the engine at boot:

1. **Core states and core transitions are non-removable and non-retargetable.**
2. **Terminal states stay closed** — no outgoing transitions may be added to
   resolved/dismissed/closed/expired/lifted/superseded/upheld/overturned/rejected.
3. **Custom states must be connected**: reachable from a core or custom state, and with
   at least one path to a core state or terminal (no traps). Verified at registration;
   violations throw at boot, not at runtime.
4. **Custom transitions get full pipeline treatment**: policy authorization, guards,
   audit entry (`{aggregate}.{transition}` key), and a generic `StateTransitioned` event
   carrying aggregate, from, to, transition name, and actor (custom transitions do not
   mint package event classes; apps listen to the generic event or dispatch their own in
   listeners).
5. **Custom state names** are plain strings, must not collide with core names, and fit
   the `string(32)` column (Phase 6).
6. Package operations keep their documented semantics: e.g. `decide` remains legal from
   every non-terminal, pre-decided core state; custom pre-decided states may declare
   themselves decidable by listing the `decide` transition from themselves.

Full replacement (2) is rejected — it makes every package guarantee (I-03…I-13)
unverifiable and every upgrade a gamble. No extension (3) fails FR-903 and real T&S
practice (e.g. an `awaiting_legal` case state).

## Consequences

- **+** Apps add real workflow steps (`awaiting_legal`, `pending_second_review`) with
  full audit/eventing, zero forks, zero migrations.
- **+** Invariants and upgrade safety survive: core flow is always present, terminals
  final, traps impossible — and violations fail fast at boot.
- **−** Some conceivable customizations (removing investigation, renaming states) are
  impossible by design — documented as such; the escape hatch is building outside the
  package, not bending it.
- Testing strategy (Phase 11) must include extension-registration cases: valid additions,
  each rule violation, and custom-transition pipeline coverage.
