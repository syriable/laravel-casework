# ADR-0018 — Mass-Assignment Posture and Race-Safe Invariants

**Status:** Accepted (Phase 18 — Post-1.0 hardening)
**Date:** 2026-07-16
**Supersedes:** none — refines ADR-0003 (immutability), ADR-0005 (actions), ADR-0017 (final vs open)

## Context

Two questions surfaced in the post-1.0 review:

1. Every package model declares `protected $guarded = [];`. Is that safe, and
   why is it not `$fillable`?
2. Two invariants — I-02 (no duplicate open report by the same reporter on the
   same subject for the same reason) and FR-503 (appeals per target ≤ the
   configured limit) — were enforced only by a read-then-write application
   check. Under concurrency, two requests can both pass the check and both
   write, violating the invariant. The state machine already guards its writes
   with an optimistic compare-and-swap (Phase 15, R-01); these count-based
   invariants had no equivalent backstop.

## Decision

### Mass assignment

`protected $guarded = []` stays, and is documented as intentional.

- Package models are written **only** through the audited actions (ADR-0005),
  which pass explicit, closed attribute arrays — never request input. There is
  no code path from user input to a model write that does not go through an
  action.
- `$fillable` would fight two existing guarantees: the actions rely on
  whole-array assignment, and models are an **open** extension point (ADR-0017,
  X1) — every override subclass would have to re-declare a fillable list, and a
  forgotten column would silently drop writes.
- The genuinely sensitive columns are guarded structurally, not by a fillable
  list: the `state` column is immutable outside transitions (`GuardsStateColumn`,
  I-03), immutable records reject all updates (`PreventsMutation`, I-04/I-07),
  and the internal `dedupe_key` is `$hidden`.

The rule for **applications**: do not bind request input to these models
directly. Construct operations through the facade / actions. Each model now
carries a one-line comment pointing here.

### Race-safe invariants

- **I-02 (duplicate reports).** A nullable `dedupe_key` column carries a
  fingerprint of `(subject, reporter, reason)` while a report is open, under a
  unique index. Because every supported engine (MySQL, PostgreSQL, SQLite)
  permits many NULLs under a unique index, the constraint binds exactly the
  open, attributable reports the invariant covers: system/anonymous reports and
  closed reports carry a NULL key. `FileReport` keeps the pre-check as the fast
  path and translates a losing race (a unique violation) into the same
  `DuplicateReport` exception. `ResolveReport`/`DismissReport` release the key
  when a report closes, so the reporter may file the same tuple again later.

  A unique index is preferred here over a lock because reports on hot content
  are exactly the high-contention case; the index lets writers race and rejects
  the loser, rather than serializing every report for a subject.

- **FR-503 (appeal limit).** The limit is configurable (N), which no static
  unique index can express, so `SubmitAppeal` takes a row lock on the appealed
  target inside the transaction before counting and inserting. The target (a
  decision or restriction) always exists, so the lock holds even for the first
  appeal, serializing concurrent submissions per target. SQLite treats the lock
  as a no-op but already serializes writers.

## Consequences

- **+** Both invariants now hold under concurrency, enforced at the layer that
  actually arbitrates a race.
- **+** The mass-assignment posture is explicit and discoverable, and the
  override story (ADR-0017) is preserved.
- **−** One additive migration adds `dedupe_key`; the dedupe fingerprint must be
  released on close (handled inside the resolve/dismiss actions).
- **−** The appeal row lock adds one `SELECT … FOR UPDATE` per submission — a
  negligible cost on a low-frequency operation.
