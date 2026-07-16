# Changelog

All notable changes to `laravel-casework` will be documented in this file.

## Unreleased

Post-1.0 hardening (Phase 18). All changes are backward compatible; the
only upgrade action is `php artisan migrate` for the two additive
migrations. See [ADR-0018](docs/adr/0018-mass-assignment-and-race-safety.md).

### Added

- `casework:make-reason` command to bootstrap report reasons from the
  console or a seeder (idempotent by key). See the
  [installation guide](docs/guide/installation.md#bootstrapping-reasons).
- `Contracts\FiltersEvents`: an optional notifier refinement — a notifier
  that lists the events it `subscribesTo()` is only resolved and invoked
  for those events, instead of for every catalog event.
- `Support\Concerns\ExpiresInRealTime`: a single `notExpired` scope that
  now backs every "active and not past expiry" query (I-09).
- Official scheduling example for `casework:expire-restrictions` and
  `casework:prune-audit` (docs/guide/enforcement.md, docs/guide/audit.md).

### Changed

- **Race-safe invariants.** The duplicate-report guard (I-02) is now
  backed by a nullable `dedupe_key` unique index, and the appeal-limit
  guard (FR-503) by a row lock on the appealed target — both invariants
  now hold under concurrency, not only in the single-request case.
- The restriction hot-path index is reordered to
  `(subject_type, subject_id, state, expires_at, type)` so the type-less
  `isRestricted()` and `activeRestrictions()` are fully index-served.
- Minimum PHP is now **8.3** (was 8.4); the package uses no 8.4-only
  syntax. `laravel/pint` moved to a stable release and
  `minimum-stability` is `stable`.
- Package models document their `$guarded = []` posture (ADR-0018) and
  hide the internal `dedupe_key` from serialization.

## 1.0.0 - 2026-07-15

Initial release: the complete, UI-agnostic trust & safety platform.

- Reporting: fluent report builder, reasons-as-data, duplicate guard
  (I-02), anonymous/system origins, configurable case strategies
  (`always`/`threshold`/`manual`/custom)
- Case management: assignment, investigation, notes, evidence,
  priorities, escalation, state machine-managed lifecycle
- Decisions: atomic decide (case transition + report resolution +
  enforcement, I-06/I-08), custom outcomes, supersession chains
- Enforcement: typed/scoped/expiring restrictions, permanent and
  temporary suspensions, warnings, early lift, real-time expiry rule
  (I-09) with `casework:expire-restrictions` bookkeeping, single-query
  `isRestricted()` hot path (NFR-04)
- Appeals: appeal window (FR-506) and per-target limit (FR-503),
  independent-reviewer guard (I-12), atomic overturn lifting
  restrictions and recording a superseding decision (I-13)
- Audit: one append-only entry per domain action via a non-swappable
  Recorder (I-04), model-layer immutability (ADR-0003), opt-in pruning
  (`casework:prune-audit`)
- Events: a dedicated after-commit event per action (ADR-0015), the
  generic `StateTransitioned` for custom transitions, config-listed
  `Notifier` hook
- Automation: `ReportIntakeStage` and `CaseTriageStage` pipelines with
  System attribution (FR-805)
- Authorization: safe-by-default policies per model, `ScopeResolver`
  scoped moderation (FR-602), self-moderation prevention (FR-604)
- Extension surface X1–X13: model overrides, workflow extension
  (add-only, boot-validated, ADR-0013), action/guard rebinds, strategy
  and resolver bindings — all boot-validated (`InvalidConfiguration`)
- Concurrency-safe transitions: an optimistic compare-and-swap on the
  state column rejects a losing concurrent transition with
  `InvalidTransition` rather than double-applying
- Works with bigint, ULID, and UUID keys on application models
  (ADR-0010); ships on Laravel 12+ / PHP 8.4+
