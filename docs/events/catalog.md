# Laravel Trust & Safety — Event Catalog

**Phase:** 8 — Events
**Produced by:** Event Architecture team (T9); security review by T10
**Approver:** Fable (Project Director)
**Status:** DRAFT — awaiting approval (Gate G8)
**Version:** 1.0.0
**Date:** 2026-07-14
**Upstream:** [Workflows](../workflows/overview.md) (Gate G7 approved 2026-07-14)
**ADRs introduced:** [0014](../adr/0014-event-payload-policy.md), [0015](../adr/0015-event-dispatch-semantics.md)

One event per meaningful occurrence — exactly the set below, no more (review criterion).
Audit keys are the same dot-namespaced strings fixed in Phase 7; the event class ↔ audit
key mapping is 1:1 and authoritative here. All events live in their module's `Events/`
namespace (ADR-0004), are `final readonly` classes with public typed properties
(ADR-0014), and are dispatched after commit by their action (ADR-0015).

## Shared Payload Types

- `ActorRef` (Support VO): `?Model $actor` + `Origin $origin` — ADR-0002 attribution.
- Transition events implement `Contracts\StateTransitionEvent`:
  `string $from`, `string $to`, `ActorRef $by` (FR-802).

## Catalog

### Reporting (`Syriable\Casework\Reporting\Events`)

| Event | Audit key | Payload (beyond `ActorRef $by`) |
|---|---|---|
| `ReportFiled` | `report.filed` | `Report $report` |
| `ReportReviewStarted` † | `report.review_started` | `Report $report` |
| `ReportAttachedToCase` † | `report.attached_to_case` | `Report $report`, `CaseFile $case` |
| `ReportDismissed` † | `report.dismissed` | `Report $report` |
| `ReportResolved` † | `report.resolved` | `Report $report`, `Decision $decision` (nullable when resolved unattached) |

### Cases (`Syriable\Casework\Cases\Events`)

| Event | Audit key | Payload |
|---|---|---|
| `CaseOpened` | `case.opened` | `CaseFile $case` |
| `CaseInvestigationStarted` † | `case.investigation_started` | `CaseFile $case` |
| `CaseAwaitingDecision` † | `case.awaiting_decision` | `CaseFile $case` |
| `CaseDecided` † | `case.decided` | `CaseFile $case`, `Decision $decision`, `Collection<Restriction> $restrictions`, `Collection<Warning> $warnings` |
| `CaseClosed` † | `case.closed` | `CaseFile $case` |
| `CaseAssigned` | `case.assigned` | `CaseFile $case`, `Model $assignee`, `?Model $previousAssignee` |
| `CaseEscalated` | `case.escalated` | `CaseFile $case`, `string $fromPriority`, `string $toPriority` |
| `CaseNoteAdded` | `case.note_added` | `Note $note` |
| `CaseEvidenceAttached` | `case.evidence_attached` | `Evidence $evidence` |

### Enforcement (`Syriable\Casework\Enforcement\Events`)

| Event | Audit key | Payload |
|---|---|---|
| `RestrictionApplied` | `restriction.applied` | `Restriction $restriction` |
| `RestrictionExpired` † | `restriction.expired` | `Restriction $restriction` |
| `RestrictionLifted` † | `restriction.lifted` | `Restriction $restriction`, `string $reason` |
| `RestrictionSuperseded` † | `restriction.superseded` | `Restriction $restriction`, `Restriction $replacement` |
| `WarningIssued` | `warning.issued` | `Warning $warning` |

### Appeals (`Syriable\Casework\Appeals\Events`)

| Event | Audit key | Payload |
|---|---|---|
| `AppealSubmitted` | `appeal.submitted` | `Appeal $appeal` |
| `AppealReviewStarted` † | `appeal.review_started` | `Appeal $appeal` |
| `AppealUpheld` † | `appeal.upheld` | `Appeal $appeal` |
| `AppealOverturned` † | `appeal.overturned` | `Appeal $appeal`, `Decision $supersedingDecision`, `Collection<Restriction> $lifted` |
| `AppealRejected` † | `appeal.rejected` | `Appeal $appeal`, `?string $reason` |
| `AppealAssigned` | `appeal.assigned` | `Appeal $appeal`, `Model $reviewer` |

### Generic (`Syriable\Casework\States\Events`)

| Event | Audit key | Payload |
|---|---|---|
| `StateTransitioned` | `{aggregate}.{transition}` | `Model $subject`, `string $transition`, `string $from`, `string $to` — dispatched **only** for app-defined custom transitions (ADR-0013 rule 4) |

† implements `StateTransitionEvent`. Creation events (`ReportFiled`, `CaseOpened`,
`RestrictionApplied`, `WarningIssued`, `AppealSubmitted`) carry no `$from` — creation is
the implicit pseudo-state transition (workflows overview §3).

Audit-only keys with no dedicated event class: none — the mapping is total in both
directions. Audit payload shape per key mirrors the event payload as scalars/ids
(ADR-0011); documented per key in implementation.

## Notification Hooks (FR-803)

Two equivalent integration styles; the package sends nothing itself:

1. **Plain listeners** — every event above is a normal Laravel event; `Event::listen()`
   / event discovery work as in any app.
2. **`Contracts\Notifier`** — single-entry-point convenience:

```php
interface Notifier
{
    public function notify(object $event): void;   // receives every catalog event
}
```

Classes listed under `config('casework.notifiers')` are resolved from the container and
invoked (in order) for every event, after commit. A notifier decides internally what it
cares about, and may queue its own jobs. This is registration-by-config — no package
modification (review criterion).

## Automation Hooks (FR-804/805)

Laravel-pipeline-style stages at the two intercept points, bound via config:

```php
interface ReportIntakeStage
{
    /** Runs inside FileReport after guards, before persistence. */
    public function handle(ReportIntake $intake, Closure $next): ReportIntake;
}

interface CaseTriageStage
{
    /** Runs after CaseOpened commits, as the System actor. */
    public function handle(CaseFile $case, Closure $next): CaseFile;
}
```

`ReportIntake` (mutable pending-intake context) lets stages: adjust metadata, force or
suppress case creation (overriding the configured strategy), auto-dismiss (report is
persisted as dismissed with System attribution), or throw a domain exception to refuse
intake. Triage stages may call any package operation (escalate, assign, decide) — they
receive System attribution (FR-805) and full pipeline treatment (audit + events), so an
external-ML auto-suspend is fully audited like a human decision.

## Security Review (T10) — Payload Exposure Sign-off

- Events carry live Eloquent models: listeners see exactly what the application could
  already query — the package adds no new exposure surface, and payloads never include
  computed secrets or credentials.
- **Queued listeners serialize models** (`SerializesModels` re-fetches on handle):
  documented caution — a queued listener handling `ReportFiled` re-reads current data,
  so deleted subjects yield null morphs (schema §2); apps queuing payload *contents*
  (e.g. into external systems) take responsibility for opaque texts (comments,
  rationales) which may contain end-user PII (NFR-09/10 boundary).
- Anonymous reports carry `ActorRef` with null actor — no identity exists to leak.
- The package itself registers **no** listeners and logs **no** payloads (NFR-10).

**T10 verdict: approved** — no payload reductions required; cautions above must appear
in the documentation (Phase 12 item).

## Definition of Done — Phase 8

- [x] Total 1:1 event ↔ audit-key mapping; one event per occurrence, none speculative
- [x] Payloads carry models with typed properties (FR-801); transition events expose from/to/actor (FR-802)
- [x] Notification + automation hooks require zero package modification
- [x] T10 security sign-off on payload exposure recorded
- [x] ADRs 0014–0015 drafted
- [ ] Fable review passed
- [ ] Project owner approval — **Gate G8**

**Next phase upon approval:** Phase 9 — Extension System (complete contracts list,
container binding map, what is final vs open, security review of extension surface).
