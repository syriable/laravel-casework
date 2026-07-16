# ADR-0007 — Runtime Dependency Policy

**Status:** Accepted
**Date:** 2026-07-14

## Context

NFR-13 requires a minimal dependency surface. The package needs state machines,
polymorphic models, events, and policies — all achievable with the framework itself. Every
runtime dependency is a supply-chain, BC, and version-matrix liability for every consumer.

## Problem

Which runtime dependencies are permitted, and specifically: do we adopt a third-party
state machine package?

## Alternatives

1. **Framework-only**: `illuminate/contracts` + `spatie/laravel-package-tools`; build the
   minimal transition mechanism internally.
2. **Framework + `spatie/laravel-model-states`** for lifecycles.
3. **Unrestricted case-by-case** additions during implementation.

## Decision

**Alternative 1: framework-only.** Runtime `require` stays exactly `php`,
`illuminate/contracts`, `spatie/laravel-package-tools`. The lifecycle mechanism is
a minimal internal component scoped to guarded transitions, actor
attribution, and event + audit emission — not a general-purpose
state machine library.

`spatie/laravel-model-states` (2) is rejected for v1: it is class-per-state oriented,
brings its own extension model that would compete with ours, and adds a version
matrix we don't control. Unrestricted additions (3) are rejected outright; any future
addition requires a superseding ADR (this is the enforcement point for NFR-13).

## Consequences

- **+** Consumers inherit no transitive constraints beyond the framework; Laravel
  11/12/13 matrix stays ours to manage.
- **+** Transition semantics exactly match invariants I-03/I-04 — nothing unused, nothing
  missing.
- **−** We own ~one small internal mechanism — accepted; the mechanism
  is internal, so we can change it without BC impact as
  long as configured workflows keep working.
- Dev-dependencies remain unrestricted (Pest, PHPStan, Pint, Rector, Testbench).
