# ADR-0015 — Event Dispatch Semantics

**Status:** Accepted
**Date:** 2026-07-14

## Context

Actions execute inside database transactions (ADR-0005): guards → writes → audit →
events. If events dispatch *inside* the transaction, listeners (and queued jobs they
push) can observe or act on state that subsequently rolls back — a classic source of
ghost notifications ("you have been suspended" for a suspension that never committed).
Cross-aggregate actions (decide, overturn) emit multiple events from one transaction.

## Problem

When, relative to the transaction, are domain events dispatched — and what does the
package promise listeners?

## Alternatives

1. **Dispatch after commit** — actions collect events during the transaction and
   dispatch them (in order) once it commits; nothing dispatches on rollback.
2. **Dispatch inline** — fire as each write happens; rely on apps to use
   `ShouldHandleEventsAfterCommit` / `afterCommit` queues.
3. **Transactional outbox** — events persisted with the transaction, relayed by a worker.

## Decision

**Alternative 1: after-commit dispatch, package-guaranteed.** The action pipeline's final
step runs after its own transaction commits (Laravel's `DB::afterCommit` when nested in an
app transaction). Guarantees documented to listeners:

- An event means its transaction **committed** — listeners always observe durable state.
- Events from one action dispatch **in occurrence order**: deciding a case with two
  restrictions dispatches `RestrictionApplied`, `RestrictionApplied`, then `CaseDecided`
  as the summarizing event — effects before their summary.
- Rolled-back operations dispatch **nothing** (audit rolls back with them — I-04 stays
  consistent).
- Dispatch is synchronous (Laravel's default dispatcher); **listener** execution model is
  the app's choice — guidance: fast/side-effect-free listeners sync, anything doing I/O
  (mail, HTTP, ML scoring) queued. The package ships no listeners (catalog security
  section).

Inline dispatch (2) makes correctness the consumer's homework — rejected. The outbox (3)
is infrastructure the package must not impose (no tables/workers beyond requirements;
over-engineering).

## Consequences

- **+** Ghost-notification class of bugs is impossible by construction; notifier and
  automation hooks inherit the guarantee.
- **+** Within-transaction consumers that genuinely need pre-commit interception have a
  designed alternative: intake/triage pipeline stages, not events.
- **−** Events cannot abort the operation (they fire post-commit) — intentional; vetoing
  belongs to guards and pipeline stages, and the docs must say so.
- **−** In-memory test assertions must account for after-commit timing — Testbench runs
  transactions to completion, so `Event::fake()` works unchanged.
