# Laravel Trust & Safety — Extension System Specification

**Phase:** 9 — Extension System
**Produced by:** Extension System team (T11); security review by T10
**Approver:** Fable (Project Director)
**Status:** DRAFT — awaiting approval (Gate G9)
**Version:** 1.0.0
**Date:** 2026-07-14
**Upstream:** [Event Catalog](events/catalog.md) (Gate G8 approved 2026-07-14)
**ADRs introduced:** [0016](adr/0016-extension-binding-strategy.md), [0017](adr/0017-final-vs-open-policy.md)

Every extension point below exists because a plausible application needs it (review
criterion); nothing else is open. Governing rule (ADR-0016): **config declares *what*,
the container resolves *how*.** Openness rules per class kind: ADR-0017.

---

## 1. Extension Point Inventory

| # | Extension point | Motivating use case | Mechanism |
|---|---|---|---|
| X1 | **Model overrides** (FR-901) | Add relations/scopes/casts to `Report`, tenant column on `CaseFile` | Subclass package model → `config('casework.models.report')`; all package code resolves classes through the model registry, relations included |
| X2 | **Custom reasons** (FR-152) | Marketplace adds `counterfeit`, `prohibited_item` | Plain Eloquent rows (`Reason::create`) — no code needed |
| X3 | **Custom outcomes** (FR-302) | `uphold_with_education`, `no_violation_verified` | String-backed open set: `config('casework.outcomes')` extends shipped constants; unknown outcomes rejected by `decide` guard |
| X4 | **Custom restriction types** (FR-402) | `shadowban`, `feature_limit:chat`, `demonetized` | `config('casework.restriction_types')` extends shipped `suspension`; hot-path check (FR-405) works for any type unchanged |
| X5 | **Workflow states/transitions** (FR-903) | `awaiting_legal` case state; `pending_second_review` appeal state | Extend the lifecycle's container-bound `WorkflowDefinition`; bounded by ADR-0013 (add-only, terminals closed, boot-validated) |
| X6 | **Scope resolution** (FR-602) | Category moderators: fashion mods can't decide electronics cases | Implement `Contracts\ScopeResolver`, bind in container |
| X7 | **Case strategy** (FR-205) | Open a case only for subjects with ≥3 reports in 24h, else queue | Implement `Contracts\CaseStrategy`; shipped: `always`, `threshold`, `manual`; selected/parameterized via config |
| X8 | **Notification routing** (FR-803) | Notify moderators in Slack, subjects by mail | Implement `Contracts\Notifier`, list in `config('casework.notifiers')` — or plain event listeners |
| X9 | **Intake automation** (FR-804) | Auto-dismiss reports from banned reporters; external ML spam score → auto-escalate | Implement `Contracts\ReportIntakeStage`, list in `config('casework.intake_pipeline')` |
| X10 | **Triage automation** (FR-804) | Auto-assign by scope; auto-decide obvious ToS violations | Implement `Contracts\CaseTriageStage`, list in `config('casework.triage_pipeline')` |
| X11 | **Action replacement/decoration** (FR-904) | Custom duplicate-report rule; extra validation before `decide` | Subclass the action, rebind its class in the container (`bind(FileReport::class, CustomFileReport::class)`) |
| X12 | **Policy overrides** (FR-601) | Integrate spatie/laravel-permission roles into moderation authz | Register your own policy for any package model; package registers defaults only if none present |
| X13 | **Guard replacement** | Loosen/tighten a single transition guard (e.g. custom appeal window logic) | Guards are container-resolved classes (ADR-0012) — rebind individually |

Deliberately **not** extension points: event classes (`final`, ADR-0014), the workflow
engine, value objects, builders, exceptions, table structure beyond published-migration
edits (ADR-0010), and audit writing (single Recorder — I-04 must stay unforgeable from
the extension surface).

## 2. Contracts List (complete, v1.0)

| Contract | Purpose | Default binding |
|---|---|---|
| `Reportable` / `Restrictable` | model participation markers (with traits) | n/a (app implements) |
| `ScopeResolver` | actor scopes + subject scope (FR-602) | `NullScopeResolver` (everything unscoped) — singleton |
| `CaseStrategy` | when reports open/join cases (FR-205) | `ThresholdCaseStrategy` via config selector — bind |
| `Notifier` | single-entry notification hook | none registered — config list |
| `ReportIntakeStage` | intake pipeline stage | empty pipeline — config list |
| `CaseTriageStage` | triage pipeline stage | empty pipeline — config list |
| `StateTransitionEvent` | transition event surface (FR-802) | implemented by catalog events |
| `WorkflowDefinition` (×4) | lifecycle definitions (ADR-0012/0013) | shipped definitions — singleton per lifecycle |
| `CaseworkException` | catchable marker (ADR-0006) | implemented by all package exceptions |

Plus the model registry (config map, X1) and container-bound action classes (X11) —
concrete-class bindings rather than interfaces, per ADR-0016.

## 3. Guarantees to Extension Authors

1. **Pipeline parity** (FR-805): anything a stage/notifier/custom action does through
   package operations receives full authorize→guard→transact→audit→event treatment,
   attributed as System (or the acting model where supplied).
2. **Boot-time validation**: invalid workflow extensions (ADR-0013), unknown model
   overrides (not subclassing the package model), and non-implementing config-listed
   classes fail at boot with explicit exceptions — never silently at runtime.
3. **BC scope**: contracts in §2 are public API (NFR-08). Concrete action class names
   and constructor signatures are *replaceable but not stable* — decorating subclasses
   should call the parent, not copy internals; upgrade guide will flag action-internal
   changes.
4. **Order**: config-listed stages/notifiers execute in listed order; pipelines
   short-circuit per their contract semantics (catalog §Automation).

## 4. Security Review (T10) — Extension Surface

- **Privileged code**: intake/triage stages and rebound actions run with System
  authority inside domain transactions. Documented: treat these class lists like
  middleware — code review them; the package never instantiates classes from request
  input, only from config/container (no injection path from user data).
- **Authorization weakening** (X12): overriding policies can remove protections — the
  package defaults stay safe-by-default (deny unknown actors, self-moderation guard on),
  and docs must state that overrides own their consequences.
- **Audit integrity**: no extension point can suppress or forge audit entries — the
  Recorder is not swappable, stages act *through* audited operations (I-04 unforgeable
  from the extension surface). Verified against X1–X13.
- **Model overrides** (X1): subclasses could disable guarded-state protections in theory
  — mitigated: state writes go through the engine regardless of model class; overriding
  `state` mutators is flagged as unsupported in docs.

**T10 verdict: approved**, with the three documentation obligations above added to the
Phase 12 checklist.

## 5. Definition of Done — Phase 9

- [x] Every extension point has a concrete motivating use case; closed surface enumerated
- [x] Complete contracts list with default bindings; binding strategy ADR-recorded
- [x] Final-vs-open policy ADR-recorded
- [x] Extension-author guarantees (parity, boot validation, BC scope, ordering) stated
- [x] T10 security review of the extension surface recorded
- [ ] Fable review passed
- [ ] Project owner approval — **Gate G9**

**Next phase upon approval:** Phase 10 — Configuration (the complete annotated config
file: every key, default, and validation posture).
