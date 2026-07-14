# ADR-0011 — Audit Table Shape

**Status:** Proposed (Gate G6)
**Date:** 2026-07-14
**Phase:** 6 — Database Design

## Context

Every domain action writes an audit entry (FR-701); the table is append-only (FR-703),
must answer subject/actor/action/time-range queries (FR-704), and will be the package's
highest-volume table. C5 Audit is a pure sink — no domain behavior reads it.

## Problem

One generic audit table, per-context audit tables, or full event sourcing?

## Alternatives

1. **Single generic table** — polymorphic `auditable`, dot-namespaced `action` key,
   JSON `payload`, actor attribution per ADR-0002.
2. **Per-context tables** (`report_audits`, `case_audits`, …) — typed columns per
   context, five tables to maintain and union for cross-context timelines.
3. **Event sourcing** — audit entries are the source of truth; state is derived.

## Decision

**Alternative 1: one `casework_audit_entries` table.** Dot-namespaced action keys
(`report.filed`, `case.decided`, `restriction.lifted`, …) with the authoritative key list
maintained in the Phase 8 event catalog (one name per occurrence, shared between events
and audit). Structured details go in the JSON `payload`; package queries never filter on
payload contents — only on the indexed columns (auditable, actor, action, created_at).

Event sourcing (3) is rejected as framework-building (roadmap: never over-engineer);
per-context tables (2) are rejected because the primary access pattern — "everything
about this subject/actor across contexts" (P5 persona) — would require five-table unions, and the
schema duplicates ADR-0002/0001 columns five times for no query gain.

## Consequences

- **+** Subject and actor timelines are single index-backed queries; one write path
  (the Audit Recorder, architecture §2) keeps I-04 enforceable.
- **+** New auditable actions cost a key string, not a migration.
- **−** JSON payloads are schemaless — mitigated: payload *shape per action key* is
  documented in the Phase 8 catalog and covered by tests; payloads are additive-only
  after release (NFR-08).
- **−** One big table — mitigated by covering indexes, insert-only workload, and opt-in
  pruning (FR-705).
