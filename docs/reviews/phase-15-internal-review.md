# Phase 15 — Internal Review Report

**Phase:** 15 — Internal Review
**Reviewer:** Fable (Project Director)
**Approver:** Project owner (Gate G15)
**Status:** DRAFT — awaiting approval
**Date:** 2026-07-15
**Upstream:** Phase 14 complete (milestones M1–M10 merged, PRs #2–#26)

Whole-package review after the implementation phase: architectural
consistency against the ADRs, API coherence, dead code, a performance
pass (N+1, indexes, batch memory), and a security pass. Two findings,
both resolved in this PR; no ADR inconsistencies and no
critical/security findings remain open.

## 1. Method

- **ADR & invariant conformance** — every action re-read against
  ADR-0001…0017 and invariants I-01…I-15; the pipeline
  (authorize → guard → transact → transition → audit → events) and
  occurrence ordering (effects before summaries, ADR-0015) confirmed
  per action.
- **Verification duty** — the README/quickstart flow executes verbatim
  against the workbench app (`tests/Feature/QuickstartWalkthroughTest`),
  and both doc-enforcement scripts pass (completeness + FR
  traceability).
- **Static sweep** — every `Actions/*` wraps its work in a
  transaction; no `TODO/FIXME/dd/dump/ray` (enforced by `ArchTest`);
  every support/value class is referenced; hot-path indexes verified
  against the queries that use them.
- **Concurrency reasoning** — state-transition write path analysed for
  lost-update / double-apply under concurrent callers.

## 2. Findings

| # | Severity | Area | Status |
|---|---|---|---|
| R-01 | Medium | Concurrency — lost update on state transitions | **Fixed** |
| R-02 | Low | Performance — expiry command memory on large backlog | **Fixed** |

### R-01 — Concurrent transitions could double-apply (Medium)

`Workflow::transition()` read the from-state, ran guards, then wrote
the to-state unconditionally. Two callers acting on the same record
could both observe the same from-state, both pass guards, and both
write — e.g. two `decide` calls on one case producing two decisions,
or two `lift` calls double-recording. The per-action `DB::transaction`
bounded atomicity but, without row locking or a conditional write, did
not serialize the read-modify-write.

**Resolution.** `writeStateThroughTransition()` gained an optional
`$expectedFrom`; the engine now performs a **compare-and-swap** —
`UPDATE … SET state = :to WHERE id = :id AND state = :from`. A write
matching zero rows means a concurrent transition already moved the
record, and the engine throws `InvalidTransition` ("transitioned
concurrently"). The loser aborts; its surrounding transaction rolls
back its side effects. Consistent with ADR-0012 (the engine remains
the sole state writer) and I-03. Regression test:
`WorkflowExtensionTest` → "rejects a losing concurrent transition via
the optimistic state check".

### R-02 — Expiry command loaded all due rows into memory (Low)

`ExpireRestrictions::execute()` fetched every due restriction with a
single `->get()` before looping. Correct, but on a large backlog
(e.g. a lapsed scheduler resuming) it could hold an unbounded result
set in memory.

**Resolution.** The command now processes in bounded passes of 500,
re-querying the due set each pass. Because expiring a row removes it
from that set, the window advances without cursor bookkeeping and
tolerates rows that lapse between passes. Per-row transaction, audit,
and event semantics are unchanged; the real-time rule (I-09) already
governs correctness regardless of when the command runs. Covered by
the existing `EnforcementTest` expiry case.

## 3. Confirmed sound (no change required)

- **Atomic decide / overturn** (I-06/I-08/I-13) — case transition,
  report resolution, enforcement, and audit commit as one unit;
  rollback tests present for both.
- **Real-time enforcement** (I-09) — `isRestricted()` (trait and
  facade) resolves inside the composite hot-path index in one query
  (NFR-04); expiry is derived, not stored.
- **Audit integrity** (I-04) — the `Recorder` is `final` and not
  container-swappable; every action records exactly one entry; no
  extension point reaches the write path. `ArchTest` pins models
  side-effect-free and the recorder final.
- **Fixed subject** (I-05) — `AttachReportToCase` rejects a case
  concerning a different subject; morph ids normalized for the
  bigint/ULID mix (ADR-0010).
- **Reviewer independence & self-moderation** (I-12/FR-604) — enforced
  at both assignment and review; toggle matrix tested.
- **Extension guarantees** (Phase 9 §3) — pipeline parity, boot-time
  validation (stage/notifier/model/workflow), and ordering all covered
  by `ExtensionSurfaceTest` / `AutomationTest`.
- **Indexes** — reports (subject+state, reporter), restrictions
  (subject+type+state+expiry hot path, state+expiry), appeals (target,
  reviewer+state) all present and matched to their queries.

## 4. Definition of Done — Phase 15

- [x] Whole package reviewed against ADRs and invariants I-01…I-15
- [x] API coherence / dead-code / performance / security passes done
- [x] All findings resolved or waived (R-01, R-02 resolved; none waived)
- [x] Zero known inconsistencies with ADRs; no critical/security
      findings open
- [x] README/guide examples execute against the workbench
      (verification duty)
- [ ] Project owner approval — **Gate G15**

**Next phase upon approval:** Phase 16 — Stabilization (freeze the
public API, defect-only fixes, upgrade-path validation).
