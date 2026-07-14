# Laravel Trust & Safety — Database Schema

**Phase:** 6 — Database Design
**Produced by:** Database Design team (T7)
**Approver:** Fable (Project Director)
**Status:** DRAFT — awaiting approval (Gate G6)
**Version:** 1.0.0
**Date:** 2026-07-14
**Upstream:** [Public API](../api/public-api.md) (Gate G5 approved 2026-07-14) · [Domain Model](../domain/entities.md)
**ADRs introduced:** [0010](../adr/0010-identifier-strategy.md), [0011](../adr/0011-audit-table-shape.md)

Ten tables, all sharing the configurable prefix (default `casework_`, FR-952/954).
Identifier strategy per ADR-0010: **bigint auto-increment primary keys; polymorphic
`*_id` columns are `string(36)`** to accept bigint, UUID, or ULID application keys
unmodified. Actor attribution columns follow ADR-0002 (nullable morph + `origin`).
Immutable tables have no `updated_at` (ADR-0003). The schema is public API after release
(roadmap §4); every required query pattern from Phase 5 has a covering index.

Column conventions: `state` `string(32)`; `origin` `string(16)`; type/outcome/priority
open-set values `string(64)`; morph type columns `string(255)`.

---

## 1. Tables

### `casework_reasons` (FR-150)
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| key | string, **unique** | machine key, stable (FR-153) |
| label | string | app-localizable |
| category | string nullable, indexed | one-level grouping (FR-154) |
| is_active | boolean default true | deactivation ≠ deletion (FR-155) |
| timestamps | | |

### `casework_reports` (FR-100)
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| subject_type / subject_id | string / string(36) | ADR-0001 |
| reporter_type / reporter_id | nullable morph | ADR-0002 |
| origin | string(16) | model / system / anonymous |
| reason_id | FK → reasons, restrict | |
| comment | text nullable | opaque (NFR-09) |
| metadata | json nullable | FR-107 |
| state | string(32) | machine-managed (FR-104) |
| case_id | FK → cases, nullable, restrict | set when attached |
| decision_id | FK → decisions, nullable, restrict | set on resolution (FR-305) |
| timestamps | | |

**Indexes:** `(subject_type, subject_id, state)` — subject listings + duplicate guard
lookup (I-02, with reporter/reason filter); `(reporter_type, reporter_id)`; `(state)`;
`(case_id)`; `(reason_id)`.

### `casework_cases` (FR-200)
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| subject_type / subject_id | morph | primary subject, fixed (I-05) |
| state | string(32) | FR-202 |
| priority | string(64) | FR-204 |
| assignee_type / assignee_id | nullable morph | origin-model only (ADR-0002) |
| timestamps | | |

**Indexes:** `(subject_type, subject_id)`; `(assignee_type, assignee_id, state)` — "my
open cases"; `(state, priority)` — queue views.

### `casework_case_notes` (FR-251) — immutable
id · case_id FK cascade-restrict · author morph + origin · body text · created_at.
**Index:** `(case_id, created_at)`.

### `casework_case_evidence` (FR-252) — immutable
id · case_id FK restrict · subject morph nullable (referenced model) · data json nullable
· author morph + origin · created_at. **Index:** `(case_id)`, `(subject_type, subject_id)`.

### `casework_decisions` (FR-300) — immutable
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| case_id | FK → cases, restrict | |
| decider_type / decider_id | nullable morph + origin | system possible via hooks (FR-805) |
| origin | string(16) | |
| outcome | string(64) | open set (FR-302) |
| rationale | text nullable | opaque |
| supersedes_id | FK → decisions, nullable, restrict | FR-304 |
| created_at | | no updated_at |

**Indexes:** `(case_id, created_at)`; `(outcome)`.

### `casework_restrictions` (FR-400)
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| subject_type / subject_id | morph | ADR-0001 |
| type | string(64) | open set; `suspension` shipped |
| scope | string nullable | FR-402/602 |
| issuer_type / issuer_id + origin | nullable morph + string(16) | |
| decision_id | FK nullable, restrict | direct restrictions allowed |
| state | string(32) | active / expired / lifted / superseded |
| expires_at | timestamp nullable | null ⇔ permanent (I-09) |
| lifted_at / lifted_by_type / lifted_by_id / lift_reason | nullable | FR-408 |
| superseded_by_id | FK → restrictions, nullable | |
| rationale | text nullable | |
| timestamps | | |

**Indexes:** `(subject_type, subject_id, type, state, expires_at)` — **the FR-405/NFR-04
hot path**: `WHERE subject AND type AND state='active' AND (expires_at IS NULL OR
expires_at > now)` resolves entirely in this index; `(state, expires_at)` — expiry
command scan (FR-404); `(decision_id)`.

### `casework_warnings` (FR-406)
id · subject morph · issuer morph + origin · decision_id FK nullable · reason text ·
expires_at nullable · created_at (+ updated_at for expiry bookkeeping only).
**Index:** `(subject_type, subject_id, expires_at)` — active-warning counts.

### `casework_appeals` (FR-500)
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| appealed_type / appealed_id | morph | Decision or Restriction (FR-501) |
| appellant_type / appellant_id + origin | morph | |
| statement | text nullable | opaque |
| state | string(32) | FR-502 |
| reviewer_type / reviewer_id | nullable morph | independence I-12 |
| resulting_decision_id | FK → decisions, nullable | set on overturn (FR-504) |
| timestamps | | |

**Indexes:** `(appealed_type, appealed_id)` — appeal-count limit check (I-11);
`(state)`; `(reviewer_type, reviewer_id, state)`.

### `casework_audit_entries` (FR-700) — append-only (ADR-0011)
| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| actor_type / actor_id + origin | nullable morph + string(16) | ADR-0002 |
| action | string(64) | dot-namespaced key (`case.decided`) |
| auditable_type / auditable_id | morph | what was acted on |
| payload | json nullable | AuditPayload (FR-702) |
| created_at | | **no** updated_at |

**Indexes:** `(auditable_type, auditable_id, created_at)` — subject timeline;
`(actor_type, actor_id, created_at)` — actor history; `(action, created_at)`;
`(created_at)` — pruning (FR-705).

## 2. Foreign Key & Deletion Policy

- FKs exist **only between package tables**, all `ON DELETE RESTRICT` — history is never
  cascade-destroyed; the package exposes no deletes for immutable records (ADR-0003).
- Polymorphic references have no FK (ADR-0001) and **must survive subject deletion**:
  reports/restrictions/audit outlive their targets. Query APIs tolerate missing morph
  targets (nullable `->subject`).
- No soft deletes on any package table: lifecycle states (dismissed, closed, lifted,
  expired) already express "no longer active" — soft deletes would duplicate state
  machines (Simple > Clever).

## 3. Migration Plan

- One migration per table, ordered by dependency:
  `reasons → cases → decisions → reports → case_notes → case_evidence → restrictions →
  warnings → appeals → audit_entries`.
- Published via `casework-migrations` tag; prefix read from config at run time; morph
  `*_id` columns may be tightened by the app before first run (FR-954, ADR-0010).
- v1.0 ships these ten migrations as the complete baseline; post-release schema changes
  are additive migrations governed by NFR-08.

## 4. Scale Review Notes

- Highest-volume tables: `reports` and `audit_entries` (every action writes one).
  Both are insert-mostly with covering read indexes; no updates on audit, state-only
  updates on reports. JSON columns are never filtered on in package queries — no
  JSON-path indexes required.
- The duplicate-report guard (I-02) reads via `(subject_type, subject_id, state)` +
  reporter/reason filter — acceptable selectivity because open reports per subject are
  bounded in practice; revisit with a dedicated composite only if profiling demands
  (no premature indexes).
- All four supported databases (NFR-03) get identical DDL through the schema builder —
  nothing vendor-specific.

## 5. Definition of Done — Phase 6

- [x] Every Phase 5 query pattern has a covering index; hot path index-only
- [x] Identifier and audit-shape decisions ADR-recorded (NFR-12)
- [x] FK/deletion policy protects history; no soft-delete duplication
- [x] Migration order and publish/customize flow defined
- [x] No premature denormalization (zero derived/duplicate columns)
- [ ] Fable review passed
- [ ] Project owner approval — **Gate G6**

**Next phase upon approval:** Phase 7 — Workflows & State Machines (state diagrams,
transition tables and guards for report, case, restriction, appeal; internal transition
mechanism per ADR-0007; extension limits per FR-903).
