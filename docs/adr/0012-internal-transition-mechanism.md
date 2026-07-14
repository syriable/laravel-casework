# ADR-0012 — Internal Transition Mechanism

**Status:** Proposed (Gate G7)
**Date:** 2026-07-14
**Phase:** 7 — Workflows & State Machines

## Context

ADR-0007 committed to no third-party state machine package. Four lifecycles need guarded
transitions where every transition is the single state write-path, attributes an actor,
writes audit, and dispatches an event (I-03/I-04), and applications can extend workflows
within limits (FR-903, ADR-0013). Actions (ADR-0005) are the callers.

## Problem

What is the internal shape of the transition mechanism: state-pattern classes, enum
methods, or a declarative definition?

## Alternatives

1. **Declarative workflow definitions** — one `WorkflowDefinition` per lifecycle listing
   `TransitionDefinition`s (name, from-states, to-state, guard classes); a single small
   `States\Workflow` engine validates and executes; definitions resolved from the
   container.
2. **State-pattern classes** — a class per state owning its allowed transitions
   (spatie/model-states style, hand-rolled).
3. **Enum-with-methods** — backed enums with `canTransitionTo()` logic inline.

## Decision

**Alternative 1: declarative definitions + one tiny engine.** Per lifecycle, a
`WorkflowDefinition` (container-resolved, hence replaceable/extendable — the FR-903
entry point) declares the transition table exactly as written in the Phase 7 docs. The
engine's single `transition()` operation, called only by actions inside their
transaction, performs: verify from-state → run guards → write state → return; the
calling action then records audit and dispatches the event *in the same transaction
scope* per the fixed action pipeline (ADR-0005 order).

States themselves are string-backed enums (core states) unioned with plain strings
(custom states, ADR-0013); the `state` column is `string(32)` (Phase 6) — no class-per-
state, no table changes for extensions.

Alternatives 2–3 are rejected: class-per-state multiplies public surface and makes
app-added states require app-defined classes wired into package internals; enum methods
scatter the transition table across match-arms and cannot host app extensions at all.

## Consequences

- **+** The docs' transition tables and the code's definitions are the same shape —
  reviewable 1:1; exhaustive transition tests (NFR-07) iterate the definitions.
- **+** One engine (~small, internal) serves all four lifecycles; internal status means
  its internals can evolve without BC breaks (only definitions and thrown exceptions are
  public-adjacent).
- **+** Guards are classes → individually unit-testable and reusable (window, limit,
  independence, expiry guards).
- **−** Less type-magic than class-per-state (no per-state methods) — accepted; the
  package queries states, it does not attach behavior to them.
