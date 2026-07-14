# Laravel Trust & Safety — Vision Document

**Phase:** 0 — Vision
**Produced by:** Product & Vision team (T1)
**Approver:** Fable (Project Director)
**Status:** DRAFT — awaiting approval (Gate G0)
**Version:** 1.0.0
**Date:** 2026-07-14
**Upstream:** [Engineering Roadmap](ROADMAP.md) (Gate G-R approved 2026-07-14)

---

## 1. Vision Statement

> Any Eloquent model can be reported. Every report can become a case. Every case moves
> through an explicit, auditable workflow to a decision. Every decision can carry
> consequences — warnings, restrictions, suspensions — and every consequence can be
> appealed. Everything that happens is recorded, observable, and extensible. No UI is
> ever shipped.

Laravel Trust & Safety (`syriable/laravel-casework`) aims to become the obvious, boring,
reliable default for moderation in Laravel applications — what `spatie/laravel-permission`
is to authorization. A developer should reach for it the moment their application has
user-generated anything, and never feel the need to fork it.

## 2. Problem Statement

Every application with user-generated content eventually needs moderation. Today, Laravel
developers face three bad options:

1. **Build in-house.** Nearly every team re-implements the same primitives — report
   buttons, moderation queues, ban flags, appeal emails — as ad-hoc columns and
   conditionals. The result is untested, unauditable, and grows worse under pressure
   (which is exactly when trust & safety tooling is exercised).
2. **Assemble fragments.** The ecosystem offers narrow, mostly stale pieces: content
   approval statuses, user-ban traits, generic activity logs. None compose into a
   coherent report → case → decision → consequence → appeal lifecycle, and none share a
   domain model.
3. **Adopt a UI-coupled solution.** Admin-panel plugins bind moderation to one frontend
   stack, which fails the moment the application is an API, a mobile backend, or uses a
   different admin technology.

There is no maintained, comprehensive, UI-agnostic trust & safety platform in the Laravel
ecosystem. That is the gap this package fills.

## 3. Product Pillars

1. **Report anything.** Any Eloquent model becomes reportable with one trait; reporters
   may be users, systems, or anonymous sources.
2. **Case-driven moderation.** Reports aggregate into cases — the unit of investigation,
   assignment, and decision — mirroring how real trust & safety teams work.
3. **Explicit enforcement.** Warnings, temporary and permanent restrictions, and
   suspensions are first-class records with lifecycles, not boolean columns.
4. **Due process.** Decisions and restrictions are appealable through their own workflow.
5. **Accountability.** An immutable audit history records every domain action; every
   transition emits an event.
6. **Headless by design.** Zero UI, zero routes, zero views. Blade, Livewire, Filament,
   Inertia, Vue, React, mobile, and pure-API frontends are all equal citizens.
7. **Extensible by contract.** Reasons, outcomes, restriction types, workflows, and
   resolvers are swappable through contracts and container bindings — never by forking.

## 4. Personas

The package models moderation *domain actors* as data; its only direct *users* are
developers. Both matter to the design.

### P1 — The Integrating Developer (primary)
Builds a Laravel application with user-generated content. Wants moderation working in an
afternoon without reading source code, using idiomatic Laravel (traits, facades, events,
policies). Judges the package by its README, API elegance, and upgrade stability.

### P2 — The Platform Engineer (primary)
Owns a larger application with an existing moderation team and custom needs: bespoke
report reasons, extra workflow steps, ML-assisted triage, external notification systems.
Judges the package by its extension points, event surface, and schema quality at scale.

### P3 — The Moderator (modeled actor)
Reviews cases, investigates, decides, enforces. Never touches the package directly — the
application builds their tooling — but the domain model must express their reality:
assignment, scoped permissions, notes, evidence, escalation, decision records.

### P4 — The Subject / End User (modeled actor)
Is reported, warned, restricted, suspended — and appeals. The domain model must express
due process: knowing what restriction applies, until when, and how an appeal proceeds.

### P5 — The Compliance Stakeholder (modeled actor)
Needs to answer "who did what, when, and why" months later. Served entirely by the audit
history and decision records; the package feeds transparency reporting without
implementing it (a stated non-goal).

## 5. Positioning & Ecosystem Survey

| Existing option | What it covers | Why it doesn't fill the gap |
|---|---|---|
| `hootlex/laravel-moderation` | Approve/reject statuses on content models | Single-model approval flow; no reports, cases, actors, enforcement, appeals, or audit; long unmaintained. |
| `cybercog/laravel-ban` | Banning/suspending a model | Enforcement fragment only; no reporting or workflow leading to the ban, no appeals, no audit trail of why. |
| `spatie/laravel-activitylog` | Generic activity logging | Audit building block, not a moderation domain; no lifecycle semantics. |
| `spatie/laravel-model-states` / `laravel-model-status` | Generic state machines/statuses | Infrastructure, not a domain solution; teams still design the entire T&S model themselves. |
| Admin-panel moderation plugins (e.g. Filament ecosystem) | Moderation UI over app models | UI-coupled by definition; unusable for API-first or non-matching stacks; domain logic lives in the UI layer. |
| In-house builds | Whatever the team wrote | The true competitor: expensive, untested, unauditable, rebuilt at every company. |

**Positioning:** Laravel Trust & Safety is the *domain layer* those options lack — a
complete, composable moderation model beneath any frontend. It competes with in-house
builds by being better tested, better documented, and free; it complements UI ecosystems
by giving them something correct to render.

## 6. Success Metrics

Measurable at v1.0 release unless noted:

| # | Metric | Target |
|---|---|---|
| M1 | Install to first persisted report (`composer require` → migrate → `report()`) | ≤ 5 minutes following README only |
| M2 | Full report → case → decision → restriction → appeal flow wired | Achievable from README + docs without reading package source |
| M3 | UI shipped | Zero routes, views, controllers, assets, or JS |
| M4 | Public API documentation coverage | 100% of Phase 5 API surface has a doc home |
| M5 | State transitions observable | 100% emit an event and write an audit entry |
| M6 | Extensibility without forking | Custom reason, outcome, and restriction type each addable purely in application code |
| M7 | Static analysis | PHPStan at highest practical level, zero baseline growth |
| M8 | Test discipline | Every state machine has exhaustive transition tests; CI matrix green on all supported PHP × Laravel versions |
| M9 | BC discipline (post-release) | No breaking change without a major version and documented upgrade path |

## 7. Guiding Constraints

Inherited from the roadmap and binding on all phases:

- **UI-agnostic, absolutely.** Any deliverable that introduces UI is rejected at review.
- **Laravel-native.** Eloquent, events, gates/policies, container, config, artisan — no
  parallel framework.
- **Scope discipline.** The non-goals in [ROADMAP.md §2](ROADMAP.md#non-goals-v10)
  (no ML analysis, no notification channels, no user management, no API endpoints, no
  multi-tenancy framework, no compliance features) are part of this vision, not
  exceptions to it.
- **Architecture first.** This vision authorizes requirements work (Phase 1), nothing more.

## 8. Definition of Done — Phase 0

- [x] Vision statement ratified against roadmap §1
- [x] Personas enumerated (developer users and modeled domain actors)
- [x] Ecosystem survey and positioning documented
- [x] Success metrics defined and measurable
- [ ] Fable review passed
- [ ] Project owner approval — **Gate G0**

**Next phase upon approval:** Phase 1 — Requirements (numbered, testable FR/NFR set with
MoSCoW priorities, traceable to this vision).
