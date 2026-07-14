# Laravel Trust & Safety — Workflows Overview

**Phase:** 7 — Workflows & State Machines
**Produced by:** Workflow & State Machine Design team (T8)
**Approver:** Fable (Project Director)
**Status:** DRAFT — awaiting approval (Gate G7)
**Version:** 1.0.0
**Date:** 2026-07-14
**Upstream:** [Public API](../api/public-api.md) (G5) · [Schema](../database/schema.md) (G6)
**ADRs introduced:** [0012](../adr/0012-internal-transition-mechanism.md), [0013](../adr/0013-workflow-extension-limits.md)

Four lifecycles, one per stateful aggregate: [Report](report.md), [Case](case.md),
[Restriction](restriction.md), [Appeal](appeal.md).

## Shared Semantics (apply to every lifecycle)

1. **Transitions are the only write-path for `state`** (I-03). Direct assignment throws
   `ImmutableRecord`-family exceptions (ADR-0003); invalid transitions throw
   `InvalidTransition` carrying the model, from-state, and attempted transition (ADR-0006).
2. **Every transition** authorizes first, then guards, then — inside the action's
   transaction — writes state, records one audit entry (dot-key listed per lifecycle),
   and dispatches one past-tense event exposing `from`, `to`, and actor (I-04, FR-802).
3. **Creation is a transition** from the implicit *(new)* pseudo-state; it follows the
   same pipeline (event + audit, guards as creation invariants).
4. **Terminal states** have no outgoing transitions — for anyone, including extensions
   (ADR-0013). Corrections are new records (new report, new case, superseding decision or
   restriction), never resurrection.
5. **Actor column** in the tables below names *who may trigger* subject to policies
   (FR-601); "System" means automation hooks or scheduled commands attributed per
   ADR-0002.
6. **No reopen transitions in v1** — deliberate simplicity; revisit only via a
   superseding ADR if real usage demands it.
7. Mechanism implementing all of this: a minimal internal declarative workflow component,
   ADR-0012. Application extension limits: ADR-0013.

## Lifecycle Summary

| Aggregate | States | Terminal |
|---|---|---|
| Report | pending, under_review, attached_to_case, resolved, dismissed | resolved, dismissed |
| Case | open, under_investigation, awaiting_decision, decided, closed | closed |
| Restriction | active, expired, lifted, superseded | expired, lifted, superseded |
| Appeal | submitted, under_review, upheld, overturned, rejected | upheld, overturned, rejected |

Review criterion check (roadmap Phase 7): every state is reachable; every non-terminal
state has an exit; every transition maps 1:1 to an event and an audit key.

## Definition of Done — Phase 7

- [x] Four state machines with diagrams + transition tables (guards, actors, events, audit keys)
- [x] Transition mechanism designed within ADR-0007's framework-only constraint
- [x] Extension limits defined (FR-903) — core non-removable, terminals closed
- [ ] Fable review passed
- [ ] Project owner approval — **Gate G7**

**Next phase upon approval:** Phase 8 — Events (full catalog with payload contracts,
notification & automation hook architecture, security sign-off on payload exposure).
