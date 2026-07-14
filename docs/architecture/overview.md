# Laravel Trust & Safety — Package Architecture

**Phase:** 4 — Architecture
**Produced by:** Software Architecture (T4) & Laravel/Package Architecture (T5) teams
**Approver:** Fable (Project Director)
**Status:** DRAFT — awaiting approval (Gate G4)
**Version:** 1.0.0
**Date:** 2026-07-14
**Upstream:** [Domain Model](../domain/entities.md) + ADRs 0001–0003 (Gate G3 approved 2026-07-14)
**ADRs introduced:** [0004](../adr/0004-domain-first-package-layout.md), [0005](../adr/0005-actions-as-domain-operations.md), [0006](../adr/0006-exception-strategy.md), [0007](../adr/0007-runtime-dependency-policy.md)

This document fixes the package's internal structure: namespaces, layering, the service
provider's responsibilities, error strategy, and dependency policy. Public API signatures
are Phase 5; state machine internals are Phase 7; schema is Phase 6.

---

## 1. Package Layout (ADR-0004)

Domain-first modules mirroring the bounded contexts (C1–C5), with shared and integration
code at the edges:

```
src/
  CaseworkServiceProvider.php        ← the only Laravel bootstrapping point
  Casework.php                       ← facade root (thin, delegates to actions)
  Facades/
    Casework.php
  Reporting/                         ← C1
    Models/        Report.php, Reason.php
    Actions/       FileReport.php, DismissReport.php, …
    Events/        ReportFiled.php, …
  Cases/                             ← C2 (owns Decisions per domain map)
    Models/        CaseFile.php*, Note.php, Evidence.php, Decision.php
    Actions/       OpenCase.php, AssignCase.php, DecideCase.php, …
    Events/        CaseOpened.php, CaseDecided.php, …
  Enforcement/                       ← C3
    Models/        Restriction.php, Warning.php
    Actions/       ApplyRestriction.php, LiftRestriction.php, IssueWarning.php, …
    Events/        RestrictionApplied.php, …
  Appeals/                           ← C4
    Models/        Appeal.php
    Actions/       SubmitAppeal.php, ReviewAppeal.php, …
    Events/        AppealSubmitted.php, …
  Audit/                             ← C5
    Models/        AuditEntry.php
    Recorder.php                     ← the single audit write-path
  Concerns/                          ← opt-in application traits
    Reportable.php, Restrictable.php
  Contracts/                         ← every replaceable behavior (Phase 9 completes list)
  States/                            ← state machine infrastructure (Phase 7 fills in)
  Support/                           ← value objects (ActorRef, Origin, Scope, …)
  Policies/                          ← default policies, all overridable
  Exceptions/
  Commands/                          ← operational artisan commands only
database/
  migrations/  factories/
config/casework.php
```

\* Class name for the Case entity is finalized in Phase 5 (`case` is a PHP reserved word;
the *domain term* remains "Case" per the glossary).

Namespaces follow directories exactly (`Syriable\Casework\Enforcement\Actions\LiftRestriction`).
Events, models, and actions for one concept live together — a contributor working on
appeals touches one directory.

## 2. Layering

Four layers, dependency arrows point inward only:

| Layer | Contains | May depend on |
|---|---|---|
| **1. Models** | Eloquent models, relations, query scopes, casts; *no* domain operations | Support, Contracts |
| **2. Domain operations** | Action classes (ADR-0005), state machines, Audit Recorder | Models, Support, Contracts, Events |
| **3. Contracts & Events** | Interfaces for replaceable behavior; domain events | Models (as payload types), Support |
| **4. Integration edge** | Service provider, facade, config, commands, policies | everything below |

Rules:

- Models never dispatch events, never write audit entries, never authorize — that is
  action-layer work. Models stay queryable, factory-friendly, and side-effect-free.
- Every state change flows through exactly one action (invariants I-03/I-04): the action
  authorizes (policy), guards (invariants), transacts, transitions, records audit, and
  dispatches events — in that order.
- Cross-module operations (decide → resolve reports + apply enforcement; overturn → lift
  + supersede) are actions in the *owning* module (Cases, Appeals) calling other modules'
  actions inside one transaction — matching the domain map's dependency arrows. Modules
  never reach into another module's models directly for writes.
- The Audit Recorder is the only writer to audit storage; it is called by actions, never
  by models or listeners (keeps C5 a pure sink).

## 3. Service Provider Responsibilities

`CaseworkServiceProvider` (built on `spatie/laravel-package-tools`) does exactly:

1. Register `config/casework.php` (publishable, tag `casework-config`).
2. Load publishable migrations (tag `casework-migrations`) honoring the configured table
   prefix (FR-954).
3. Bind every contract to its default implementation (`singleton` for stateless services,
   `bind` for per-resolution strategies) — the complete binding map is a Phase 9
   deliverable; model overrides resolve through config (FR-901).
4. Register default policies via `Gate::policy()` only when the application has not
   already registered its own (FR-601).
5. Register artisan commands (FR-953).

The provider registers **no** routes, views, view components, broadcasts channels, or
scheduled tasks (scheduling the expiry command is the application's explicit choice,
documented — never auto-scheduled).

## 4. Exception Strategy (ADR-0006)

- Marker interface `Syriable\Casework\Exceptions\CaseworkException` on every package
  exception — one `catch` surface for applications.
- Concrete exceptions are domain-named and carry context as typed properties:
  `InvalidTransition`, `ImmutableRecord` (ADR-0003), `DuplicateReport`,
  `AppealWindowClosed`, `AppealLimitReached`, `ReviewerNotIndependent`,
  `UnknownReason`, …
- Authorization failures use Laravel's native `AuthorizationException` (via policies) —
  no parallel mechanism.
- Exceptions are thrown, never returned; no result-object pattern (Simple > Clever).

## 5. Dependency Policy (ADR-0007)

- Runtime: `illuminate/*` (via `illuminate/contracts`) and
  `spatie/laravel-package-tools`. Nothing else without a superseding ADR (NFR-13).
- Notably: **no** state-machine package, **no** spatie/laravel-model-states — Phase 7
  designs a minimal internal transition mechanism scoped to exactly what invariants
  I-03/I-04 need (Never over-engineer; also keeps the dependency surface stable).
- Dev-only tooling (Pest, PHPStan, Pint, Testbench, Rector) is unrestricted.

## 6. Cross-Cutting Placement

| Concern | Where it lives |
|---|---|
| Authorization (FR-600) | `Policies/` + gates; every action's first step; scope resolution via `Contracts\ScopeResolver` |
| Events (FR-800) | Per-module `Events/`; plain readonly classes; dispatched by actions only |
| Hooks (FR-803–805) | Contracts (`Contracts\ReportIntakePipeline`, `Contracts\CaseTriagePipeline`, …) resolved from the container inside the relevant actions |
| Extension (FR-900) | `Contracts/` + config model map; completed in Phase 9 |
| Configuration (FR-950) | Single `config/casework.php`; completed in Phase 10 |

## 7. Definition of Done — Phase 4

- [x] Package layout and namespaces fixed, mapped to bounded contexts
- [x] Layering rules explicit; single write-path per state change and per audit entry
- [x] Service provider scope closed (and UI-freedom preserved)
- [x] Exception and dependency strategies decided by ADR
- [x] No framework-within-framework: four ADRs, all rejecting heavier alternatives
- [ ] Fable review passed
- [ ] Project owner approval — **Gate G4**

**Next phase upon approval:** Phase 5 — Public API (traits, facade, action signatures,
model query surface, naming — including the Case class-name decision).
