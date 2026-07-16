# Frozen Public API — v1.0

**Phase:** 16 — Stabilization
**Status:** FROZEN at Gate G16
**Stability:** NFR-08 — everything below is public API. Changes require
a superseding ADR and a major version; additions are minor-version
features (append to this manifest in the same PR).

The `ApiSurfaceTest` enforces this manifest mechanically: adding,
removing, or renaming anything in the frozen sets fails CI, so no
public-API drift lands without a deliberate manifest edit (and thus an
ADR).

Explicitly **not** frozen (replaceable-but-not-stable, ADR-0017 §BC
scope): concrete action classes and their constructor signatures,
pending-operation builder internals, the workflow engine internals,
and the model classes' non-relation internals. Decorating subclasses
call the parent (extending guide).

## Contracts (11)

`Contracts\CaseStrategy`, `CaseTriageStage`, `Notifier`,
`ReportIntakeStage`, `Reportable`, `Restrictable`, `ScopeResolver`,
`StateTransitionEvent`, `Stateful`, `TransitionGuard`.

Added in 1.1 (additive, optional): `Contracts\FiltersEvents` — a
notifier may implement it to `subscribesTo()` specific events (X8).

## Facade methods (22)

`report`, `dismissReport`, `startReportReview`, `openCase`,
`attachReport`, `assignCase`, `startInvestigation`,
`submitForDecision`, `escalateCase`, `closeCase`, `note`,
`attachEvidence`, `decide`, `restrict`, `suspend`, `warn`, `lift`,
`appeal`, `assignAppeal`, `startAppealReview`, `resolveAppeal`,
`isRestricted`.

## Events (26)

Reporting: `ReportFiled`, `ReportReviewStarted`,
`ReportAttachedToCase`, `ReportDismissed`, `ReportResolved`.
Cases: `CaseOpened`, `CaseAssigned`, `CaseInvestigationStarted`,
`CaseAwaitingDecision`, `CaseEscalated`, `CaseNoteAdded`,
`CaseEvidenceAttached`, `CaseDecided`, `CaseClosed`.
Enforcement: `RestrictionApplied`, `RestrictionLifted`,
`RestrictionExpired`, `RestrictionSuperseded`, `WarningIssued`.
Appeals: `AppealSubmitted`, `AppealAssigned`, `AppealReviewStarted`,
`AppealUpheld`, `AppealOverturned`, `AppealRejected`.
Generic: `StateTransitioned`.

## Exceptions (11)

`CaseworkException` (marker), `DuplicateReport`, `UnknownReason`,
`InvalidTransition`, `IncompleteBuilder`, `ImmutableRecord`,
`InvalidConfiguration`, `InvalidWorkflow`, `AppealWindowClosed`,
`AppealLimitReached`, `ReviewerNotIndependent`.

## Traits (2)

`Concerns\InteractsWithReports`, `Concerns\InteractsWithRestrictions`.

## Value objects & helpers

`Support\ActorRef`, `Support\Origin` (enum), `Support\Outcome`,
`Support\RestrictionType`, `Support\NullScopeResolver`.

## Config keys

`table_prefix`, `models.*` (10 keys), `reporting.allow_duplicates`,
`reporting.allow_anonymous`, `cases.strategy`, `cases.threshold`,
`cases.priorities`, `cases.default_priority`, `decisions.outcomes`,
`enforcement.restriction_types`, `appeals.limit_per_target`,
`appeals.window_days`, `appeals.require_independent_reviewer`,
`authorization.prevent_self_moderation`, `notifiers`,
`pipelines.intake`, `pipelines.triage`, `audit.prune_after_days`.

## Artisan commands

`casework:expire-restrictions`, `casework:prune-audit`.

Added in 1.1: `casework:make-reason` (bootstrap report reasons).
