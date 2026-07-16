# Event Catalog

**ADRs:** [0014 — event payload policy](../adr/0014-event-payload-policy.md),
[0015 — event dispatch semantics](../adr/0015-event-dispatch-semantics.md)

One event per meaningful occurrence — exactly the set below, no more.
The event class ↔ audit key mapping is 1:1 and authoritative here. All
events live in their module's `Events/` namespace (ADR-0004), are
`final readonly` classes with public typed properties (ADR-0014), and
are dispatched after commit by their action (ADR-0015).

## Shared Payload Types

- `ActorRef` (Support VO): `?Model $actor` + `Origin $origin` — ADR-0002 attribution.
- Transition events implement `Contracts\StateTransitionEvent`:
  `string $from`, `string $to`, `ActorRef $by`.

## Catalog

### Reporting (`Syriable\Casework\Reporting\Events`)

| Event | Audit key | Payload (beyond `ActorRef $by`) |
|---|---|---|
| `ReportFiled` | `report.filed` | `Report $report` |
| `ReportReviewStarted` † | `report.review_started` | `Report $report` |
| `ReportAttachedToCase` † | `report.attached_to_case` | `Report $report`, `CaseFile $case` |
| `ReportDismissed` † | `report.dismissed` | `Report $report` |
| `ReportResolved` † | `report.resolved` | `Report $report`, `Decision $decision` (nullable when resolved unattached) |
| `ReporterReputationChanged` | `reporter.reputation_adjusted` | `ReporterReputation $reputation`, `Model $reporter`, `int $before`, `int $after`, `string $reason`, `?Report $report` |
| `ReporterBlocked` | `reporter.blocked` | `ReporterReputation $reputation`, `Model $reporter`, `int $score` — dispatched only on the transition into the blocked state |

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
| `StateTransitioned` | `{aggregate}.{transition}` | `Model $subject`, `string $transition`, `string $from`, `string $to` — dispatched **only** for app-defined custom transitions (ADR-0019) |

† implements `StateTransitionEvent`. Creation events (`ReportFiled`, `CaseOpened`,
`RestrictionApplied`, `WarningIssued`, `AppealSubmitted`) carry no `$from` — creation is
the implicit pseudo-state transition (see [workflows](../guide/workflows.md)).

Audit-only keys with no dedicated event class: none — the mapping is total in both
directions. Audit payload shape per key mirrors the event payload as scalars/ids
(ADR-0011); documented per key in implementation.

## Notification Hooks

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
modification required.

## Automation Hooks

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
receive System attribution and full pipeline treatment (audit + events), so an
external-ML auto-suspend is fully audited like a human decision.

## Payload Exposure Notes

- Events carry live Eloquent models: listeners see exactly what the application could
  already query — the package adds no new exposure surface, and payloads never include
  computed secrets or credentials.
- **Queued listeners serialize models** (`SerializesModels` re-fetches on handle):
  a queued listener handling `ReportFiled` re-reads current data, so deleted subjects
  yield null morphs; apps queuing payload *contents* (e.g. into external systems) take
  responsibility for opaque texts (comments, rationales) which may contain end-user PII.
- Anonymous reports carry `ActorRef` with null actor — no identity exists to leak.
- The package itself registers **no** listeners and logs **no** payloads.
