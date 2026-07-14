# ADR-0005 — Actions as Domain Operations

**Status:** Proposed (Gate G4)
**Date:** 2026-07-14
**Phase:** 4 — Architecture

## Context

Invariants I-03/I-04 require every state change to pass through one guarded path that
authorizes, validates, transacts, transitions, audits, and dispatches events. The package
needs a home for these operations that is testable, container-resolvable, and overridable
(FR-904), without turning models into god objects.

## Problem

What shape do domain operations take: fat models, service classes grouping many methods,
or single-purpose action classes?

## Alternatives

1. **Fat models** — `$report->dismiss()` implements everything on the Eloquent model.
2. **Context services** — `ReportingService`, `CaseService`, … each with many methods.
3. **Single-purpose action classes** — one class per operation (`FileReport`,
   `DecideCase`, `LiftRestriction`) with one public `execute`-style method, resolved from
   the container.

## Decision

**Alternative 3: single-purpose action classes**, one per domain operation, per module
(`{Module}\Actions\{Verb}{Noun}`). Each action follows the fixed internal order:
**authorize → guard invariants → transaction → mutate/transition → audit → events.**
Actions are bound in the container and therefore individually replaceable/decoratable by
applications (FR-904); hooks (FR-804) are invoked inside the relevant actions.

Models stay thin (relations, scopes, casts, derived accessors). Convenience entry points
(trait methods like `$post->report(...)`, the `Casework` facade) are thin delegators to
actions — sugar defined in Phase 5, never a second implementation.

## Consequences

- **+** One operation = one class = one test file; exhaustive invariant testing (NFR-07)
  stays tractable.
- **+** Per-operation replaceability and decoration via the container — the extension
  system (Phase 9) gets fine-grained interception for free.
- **+** The authorize→…→events ordering is enforceable by convention and review, and
  documented once.
- **−** More classes than a service approach — accepted: each is small, named after the
  ubiquitous language, and discoverable in its module.
- **−** Risk of anemic-domain criticism — accepted deliberately: in Eloquent-land, thin
  models + explicit operations is the maintainable idiom (Spatie practice), and invariants
  live in exactly one place.
