# Laravel Trust & Safety — Requirements

**Phase:** 1 — Requirements
**Produced by:** Domain Analysis team (T2)
**Approver:** Fable (Project Director)
**Status:** DRAFT — awaiting approval (Gate G1)
**Version:** 1.0.0
**Date:** 2026-07-14
**Upstream:** [Vision](vision.md) (Gate G0 approved 2026-07-14) · [Roadmap](ROADMAP.md)

---

## 1. Conventions

- **FR-xxx** — functional requirement; **NFR-xx** — non-functional requirement.
- Every requirement is a single testable statement using RFC-2119 keywords
  (**MUST / SHOULD / MAY**).
- **Priority** uses MoSCoW: `M` Must-have (v1.0 blocks release), `S` Should-have
  (v1.0 unless schedule forces deferral by ADR), `C` Could-have (v1.x candidate),
  `W` Won't-have (explicitly excluded from v1.0).
- **Trace** links each requirement to a vision pillar (P1–P7 in [vision.md §3](vision.md))
  and/or success metric (M1–M9).
- Requirements freeze at Gate G1. Changes afterward require a superseding ADR.
- Terminology used here ("report", "case", "subject", …) is provisional; Phase 2 produces
  the binding glossary and MAY rename terms without changing requirement substance.

## 2. Functional Requirements

### 2.1 Reporting (FR-100)

| ID | Requirement | Priority | Trace |
|---|---|---|---|
| FR-101 | Any Eloquent model MUST be reportable by applying a package-provided trait and contract. | M | P1, M1 |
| FR-102 | A report MUST record the reported subject (polymorphic), a reason, an optional free-text comment, and creation time. | M | P1 |
| FR-103 | A report MUST support three reporter origins: an authenticated model (polymorphic), the system (automated), and anonymous. | M | P1 |
| FR-104 | A report MUST have an explicit lifecycle state (at minimum: pending, under review, linked-to-case, resolved, dismissed) managed by a state machine. | M | P1, M5 |
| FR-105 | The package MUST prevent duplicate open reports by the same reporter on the same subject for the same reason, with a configuration switch to allow them. | S | P1 |
| FR-106 | The reportable trait MUST expose the subject's reports as an Eloquent relationship. | M | P1 |
| FR-107 | A report MAY carry application-defined structured metadata (e.g. content snapshot, URL, coordinates). | S | P7 |
| FR-108 | The package MUST support querying reports by subject, reporter, reason, and state without raw SQL. | M | P1 |

### 2.2 Report Reasons (FR-150)

| ID | Requirement | Priority | Trace |
|---|---|---|---|
| FR-151 | The package MUST ship a default, overridable taxonomy of report reasons. | M | P1 |
| FR-152 | Applications MUST be able to define custom reasons without modifying package code. | M | P7, M6 |
| FR-153 | A reason MUST support a machine key, human-readable label (localizable by the app), and active/inactive status. | M | P1 |
| FR-154 | Reasons SHOULD support hierarchy or grouping (category → reason) to one level. | S | P1 |
| FR-155 | Deactivating a reason MUST NOT invalidate historical reports referencing it. | M | P5 |

### 2.3 Case Management (FR-200)

| ID | Requirement | Priority | Trace |
|---|---|---|---|
| FR-201 | Reports MUST be groupable into a case; a case MUST reference one primary subject. | M | P2 |
| FR-202 | A case MUST have a state machine-managed lifecycle (at minimum: open, under investigation, awaiting decision, decided, closed). | M | P2, M5 |
| FR-203 | A case MUST support assignment to a moderator (polymorphic actor) and reassignment. | M | P2, P3 |
| FR-204 | A case MUST support a priority level (configurable set, sensible default set shipped). | M | P2 |
| FR-205 | The package MUST support automatic case creation from a report according to a configurable strategy (always, threshold-per-subject, manual-only). | S | P2, P7 |
| FR-206 | Adding a report to an existing open case for the same subject MUST be supported (manual and automatic). | M | P2 |
| FR-207 | The package MUST support querying cases by state, assignee, subject, and priority without raw SQL. | M | P2, P3 |
| FR-208 | A case MUST record which actor performed each lifecycle action (open, assign, escalate, close). | M | P5 |

### 2.4 Investigation (FR-250)

| ID | Requirement | Priority | Trace |
|---|---|---|---|
| FR-251 | Moderators MUST be able to attach timestamped, authored notes to a case. | M | P3 |
| FR-252 | A case MUST support attaching evidence records referencing arbitrary Eloquent models or structured data (no file storage implementation). | S | P3 |
| FR-253 | A case MUST support linking related cases and related subjects. | C | P3 |
| FR-254 | Investigation records MUST be immutable once written (corrections are new records). | M | P5 |

### 2.5 Decisions (FR-300)

| ID | Requirement | Priority | Trace |
|---|---|---|---|
| FR-301 | A case MUST be resolvable by a decision record naming the deciding actor, an outcome, and an optional rationale. | M | P3, P5 |
| FR-302 | The package MUST ship default outcomes (at minimum: dismiss, uphold, escalate) and MUST allow application-defined outcomes. | M | P7, M6 |
| FR-303 | A decision MUST be able to carry zero or more enforcement actions (warning, restriction, suspension) applied atomically with the decision. | M | P3 |
| FR-304 | Decisions MUST be immutable; reversal or amendment MUST be a new decision referencing the original. | M | P5 |
| FR-305 | A decision on a case MUST resolve the linked reports' states accordingly. | M | P1, P2 |

### 2.6 Enforcement — Restrictions, Warnings, Suspensions (FR-400)

| ID | Requirement | Priority | Trace |
|---|---|---|---|
| FR-401 | Any Eloquent model MUST be restrictable by applying a package-provided trait and contract. | M | P3 |
| FR-402 | A restriction MUST have a type (application-extensible), a scope, an issuing actor, an optional originating decision, and a state machine-managed lifecycle (active, expired, lifted, superseded). | M | P3, P7, M5 |
| FR-403 | Restrictions MUST support temporary (expires_at) and permanent (no expiry) variants. | M | P3 |
| FR-404 | Expired temporary restrictions MUST transition to expired automatically via a schedulable artisan command, and MUST also evaluate as inactive in real time regardless of command cadence. | M | P3 |
| FR-405 | The package MUST provide an efficient runtime check API: "does subject X have an active restriction of type Y (in scope Z)?" | M | P3, M1 |
| FR-406 | A warning MUST be issuable against a subject with reason text, issuing actor, and optional expiry, and MUST be queryable (count, active/expired). | M | P3 |
| FR-407 | Suspension MUST be provided as a first-class restriction type (time-boxed or indefinite). | M | P3 |
| FR-408 | Lifting a restriction early MUST record the lifting actor and reason. | M | P5 |
| FR-409 | Restrictions on a subject MUST be queryable as history (all) and as current (active only). | M | P3, P5 |

### 2.7 Appeals (FR-500)

| ID | Requirement | Priority | Trace |
|---|---|---|---|
| FR-501 | A decision or a restriction MUST be appealable by the affected subject's actor. | M | P4 |
| FR-502 | An appeal MUST have a state machine-managed lifecycle (at minimum: submitted, under review, upheld, overturned, rejected). | M | P4, M5 |
| FR-503 | The number of permitted appeals per decision/restriction MUST be configurable (default: one). | M | P4 |
| FR-504 | An overturned appeal MUST be capable of lifting the associated restriction(s) and recording a superseding decision. | M | P4 |
| FR-505 | Appeal review MUST support assignment to an actor other than the original decider, enforceable via configuration. | S | P4 |
| FR-506 | An appeal window (time limit after decision/restriction) MUST be configurable; expired windows reject submission. | S | P4 |

### 2.8 Authorization & Scoped Permissions (FR-600)

| ID | Requirement | Priority | Trace |
|---|---|---|---|
| FR-601 | Every state-changing package operation MUST be authorizable through Laravel Gates/Policies supplied by the package and overridable by the application. | M | P3, P7 |
| FR-602 | Moderator abilities MUST be scopable to areas/categories (e.g. moderate only "listings"), with scope resolution delegated to an application-implementable contract. | M | P3 |
| FR-603 | The package MUST NOT depend on any specific permission package, while remaining compatible with them (e.g. spatie/laravel-permission). | M | P7 |
| FR-604 | Self-moderation MUST be preventable: an actor MUST NOT decide a case or review an appeal concerning themselves (configurable). | S | P4 |

### 2.9 Audit History (FR-700)

| ID | Requirement | Priority | Trace |
|---|---|---|---|
| FR-701 | Every domain action (report filed, state transition, assignment, note, decision, enforcement, appeal action, lift, expiry) MUST write an audit entry. | M | P5, M5 |
| FR-702 | Audit entries MUST record actor (or system), action, auditable subject, timestamp, and a structured payload of relevant changes. | M | P5 |
| FR-703 | Audit entries MUST be append-only; the package MUST expose no API to update or delete them. | M | P5 |
| FR-704 | Audit history MUST be queryable by subject, actor, action type, and time range. | M | P5 |
| FR-705 | The package MAY provide an opt-in pruning command with a configurable retention period; pruning is the application's explicit choice. | C | P5 |

### 2.10 Events, Notification & Automation Hooks (FR-800)

| ID | Requirement | Priority | Trace |
|---|---|---|---|
| FR-801 | Every meaningful domain occurrence MUST dispatch a dedicated Laravel event carrying the relevant model(s). | M | P6, P7, M5 |
| FR-802 | Every state transition event MUST expose the from-state, to-state, and triggering actor. | M | P5, M5 |
| FR-803 | The package MUST NOT send any notification itself; it MUST define contracts/hook points where applications register their own notifiers. | M | P6, P7 |
| FR-804 | The package MUST provide automation hook points allowing applications to intercept report intake and case triage (e.g. auto-dismiss, auto-escalate, external scoring) via contracts, before default handling proceeds. | M | P7 |
| FR-805 | Automation hooks MUST be able to trigger the same domain operations as human actors, attributed as system actions. | M | P5, P7 |

### 2.11 Extension Points (FR-900)

| ID | Requirement | Priority | Trace |
|---|---|---|---|
| FR-901 | Every package model MUST be replaceable with an application subclass via configuration. | M | P7, M6 |
| FR-902 | Reasons, decision outcomes, and restriction types MUST each be extensible purely from application code. | M | P7, M6 |
| FR-903 | Workflow/state machine definitions MUST allow application-registered additional states and transitions within documented limits (core states are non-removable). | S | P7 |
| FR-904 | All replaceable behaviors (resolvers, strategies, hooks) MUST be bound in the container against package contracts. | M | P7 |

### 2.12 Configuration & Operations (FR-950)

| ID | Requirement | Priority | Trace |
|---|---|---|---|
| FR-951 | The package MUST work with zero configuration changes after publishing migrations (sensible defaults for every key). | M | M1 |
| FR-952 | A single published config file MUST govern: model overrides, reason taxonomy source, case-creation strategy, appeal limits/windows, duplicate-report policy, table names/prefix. | M | P7 |
| FR-953 | The package MUST provide artisan commands for operational tasks only (at minimum: expire due restrictions), all schedulable. | M | P3 |
| FR-954 | Migrations MUST be publishable and customizable before first run (table prefix at minimum). | M | P7 |

## 3. Non-Functional Requirements

| ID | Requirement | Priority | Trace |
|---|---|---|---|
| NFR-01 | The package MUST ship zero routes, controllers, views, Blade/Livewire/Inertia components, or frontend assets. | M | P6, M3 |
| NFR-02 | The package MUST support the PHP and Laravel versions declared in `composer.json` (currently PHP ≥ 8.4; Laravel 11/12/13) with a full CI matrix. | M | M8 |
| NFR-03 | The package MUST support MySQL ≥ 8.0, PostgreSQL ≥ 14, MariaDB ≥ 10.10, and SQLite (for testing) without database-specific code paths in the public API. | M | M8 |
| NFR-04 | Runtime enforcement checks (FR-405) MUST be O(1) queries against indexed columns and MUST document a caching extension point. | M | P3 |
| NFR-05 | All list-returning APIs MUST avoid N+1 patterns and be usable with eager loading. | M | M8 |
| NFR-06 | PHPStan MUST pass at the highest practical level with no baseline growth; Pint MUST pass; type coverage MUST be ≥ 95%. | M | M7 |
| NFR-07 | Every state machine MUST have exhaustive transition tests (every allowed and disallowed transition). | M | M8 |
| NFR-08 | Public API MUST follow semantic versioning with documented upgrade guides for breaking changes. | M | M9 |
| NFR-09 | All user-supplied text (comments, notes, rationales) MUST be stored as opaque data — never evaluated, rendered, or interpolated by the package. | M | P5 |
| NFR-10 | The package MUST NOT log, transmit, or externally expose report/case content; all data stays in the application's database. | M | P5 |
| NFR-11 | Every public class, method, and config key MUST be documented; README MUST enable the M1/M2 flows unaided. | M | M1, M2, M4 |
| NFR-12 | Database identifiers strategy (bigint vs ULID/UUID) MUST be decided by ADR in Phase 6 and applied consistently, with morph columns sized accordingly. | M | M8 |
| NFR-13 | The package SHOULD keep runtime dependencies to `illuminate/*` and `spatie/laravel-package-tools` only; any addition requires an ADR. | S | P7 |

## 4. Won't-Have (v1.0) — Restated Non-Goals

Per [ROADMAP.md §2](ROADMAP.md) and binding here: no UI of any kind (W-01), no ML/content
analysis (W-02), no notification channel implementations (W-03), no user management (W-04),
no multi-tenancy framework (W-05), no legal/compliance report generation (W-06), no
REST/GraphQL endpoints (W-07), no non-Eloquent persistence (W-08). Any requirement above
found to conflict with these is a defect in this document.

## 5. Traceability Summary

- Every vision pillar P1–P7 is covered: P1 → FR-100/150, P2 → FR-200, P3 → FR-250/300/400/600,
  P4 → FR-500, P5 → FR-700 + immutability requirements, P6 → FR-800/NFR-01, P7 → FR-900/950
  and extension requirements throughout.
- Every success metric M1–M9 is enforced by at least one requirement (M1: FR-951/NFR-11;
  M2: NFR-11; M3: NFR-01; M4: NFR-11; M5: FR-104/202/402/502/701/801; M6: FR-152/302/902;
  M7: NFR-06; M8: NFR-02/03/07; M9: NFR-08).

## 6. Definition of Done — Phase 1

- [x] All §2 scope capabilities from the roadmap expressed as numbered, testable requirements
- [x] MoSCoW priorities assigned
- [x] Traceability to vision pillars and success metrics complete
- [x] Non-goals restated; no UI requirements present
- [ ] Fable review passed
- [ ] Project owner approval — **Gate G1** (requirements freeze)

**Next phase upon approval:** Phase 2 — Domain Discovery (glossary and domain map;
bounded contexts: Reporting, Case Management, Enforcement, Appeals, Audit).
