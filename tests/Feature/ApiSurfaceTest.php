<?php

declare(strict_types=1);

use Syriable\Casework\Casework;

/**
 * Freeze enforcement (Phase 16, NFR-08): the public API surface must
 * match the frozen manifest in docs/api/frozen-api-1.0.md. Adding,
 * removing, or renaming anything below fails here — so no public-API
 * drift lands without a deliberate manifest edit (and an ADR).
 *
 * Concrete actions, builders, models, and the workflow engine are
 * intentionally absent: they are replaceable-but-not-stable
 * (ADR-0017 §BC scope).
 */

/**
 * @param  class-string  $namespaceMarker  a class in the target namespace
 * @return list<string> short class names declared under that namespace
 */
function classesIn(string $directory, string $namespace): array
{
    $base = dirname(__DIR__, 2).'/src/'.$directory;

    $names = [];

    foreach (glob($base.'/*.php') ?: [] as $file) {
        $names[] = basename($file, '.php');
    }

    sort($names);

    return $names;
}

it('freezes the contract surface', function (): void {
    expect(classesIn('Contracts', 'Contracts'))->toBe([
        'CaseStrategy',
        'CaseTriageStage',
        'Notifier',
        'ReportIntakeStage',
        'Reportable',
        'Restrictable',
        'ScopeResolver',
        'StateTransitionEvent',
        'Stateful',
        'TransitionGuard',
    ]);
});

it('freezes the facade method surface', function (): void {
    $methods = array_map(
        fn (ReflectionMethod $m): string => $m->getName(),
        (new ReflectionClass(Casework::class))->getMethods(ReflectionMethod::IS_PUBLIC),
    );

    // Public but not part of the domain surface: the constructor and
    // the ActorRef coercion helper are excluded by being private, so
    // every public method here is a frozen operation.
    sort($methods);

    expect($methods)->toBe([
        'appeal',
        'assignAppeal',
        'assignCase',
        'attachEvidence',
        'attachReport',
        'closeCase',
        'decide',
        'dismissReport',
        'escalateCase',
        'isRestricted',
        'lift',
        'note',
        'openCase',
        'report',
        'resolveAppeal',
        'restrict',
        'startAppealReview',
        'startInvestigation',
        'startReportReview',
        'submitForDecision',
        'suspend',
        'warn',
    ]);
});

it('freezes the exception surface', function (): void {
    expect(classesIn('Exceptions', 'Exceptions'))->toBe([
        'AppealLimitReached',
        'AppealWindowClosed',
        'CaseworkException',
        'DuplicateReport',
        'ImmutableRecord',
        'IncompleteBuilder',
        'InvalidConfiguration',
        'InvalidTransition',
        'InvalidWorkflow',
        'ReviewerNotIndependent',
        'UnknownReason',
    ]);
});

it('freezes the trait surface', function (): void {
    expect(classesIn('Concerns', 'Concerns'))->toBe([
        'InteractsWithReports',
        'InteractsWithRestrictions',
    ]);
});

it('freezes the event surface', function (): void {
    $events = [
        ...classesIn('Reporting/Events', 'Reporting\\Events'),
        ...classesIn('Cases/Events', 'Cases\\Events'),
        ...classesIn('Enforcement/Events', 'Enforcement\\Events'),
        ...classesIn('Appeals/Events', 'Appeals\\Events'),
        ...classesIn('States/Events', 'States\\Events'),
    ];

    sort($events);

    expect($events)->toBe([
        'AppealAssigned',
        'AppealOverturned',
        'AppealRejected',
        'AppealReviewStarted',
        'AppealSubmitted',
        'AppealUpheld',
        'CaseAssigned',
        'CaseAwaitingDecision',
        'CaseClosed',
        'CaseDecided',
        'CaseEscalated',
        'CaseEvidenceAttached',
        'CaseInvestigationStarted',
        'CaseNoteAdded',
        'CaseOpened',
        'ReportAttachedToCase',
        'ReportDismissed',
        'ReportFiled',
        'ReportResolved',
        'ReportReviewStarted',
        'RestrictionApplied',
        'RestrictionExpired',
        'RestrictionLifted',
        'RestrictionSuperseded',
        'StateTransitioned',
        'WarningIssued',
    ]);
});
