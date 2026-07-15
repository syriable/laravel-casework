# Changelog

All notable changes to `laravel-casework` will be documented in this file.

## Unreleased (1.0.0)

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
- Works with bigint, ULID, and UUID keys on application models
  (ADR-0010); ships on Laravel 12+ / PHP 8.4+
