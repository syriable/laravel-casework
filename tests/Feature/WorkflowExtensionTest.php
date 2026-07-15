<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Syriable\Casework\Appeals\AppealWorkflow;
use Syriable\Casework\Cases\CaseState;
use Syriable\Casework\Cases\CaseWorkflow;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Enforcement\RestrictionWorkflow;
use Syriable\Casework\Exceptions\CaseworkException;
use Syriable\Casework\Exceptions\InvalidTransition;
use Syriable\Casework\Exceptions\InvalidWorkflow;
use Syriable\Casework\Reporting\ReportWorkflow;
use Syriable\Casework\States\Events\StateTransitioned;
use Syriable\Casework\States\TransitionDefinition;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Tests\Support\RecordingGuard;
use Syriable\Casework\Tests\Support\VetoGuard;

/**
 * ADR-0013: bounded, add-only workflow extension — every rule violation
 * throws at boot, valid extensions get the full pipeline.
 */
function legalWorkflow(): CaseWorkflow
{
    return new class extends CaseWorkflow
    {
        protected function customStates(): array
        {
            return ['awaiting_legal'];
        }

        protected function customTransitions(): array
        {
            return [
                new TransitionDefinition('sendToLegal', [CaseState::UnderInvestigation->value], 'awaiting_legal'),
                new TransitionDefinition('legalCleared', ['awaiting_legal'], CaseState::AwaitingDecision->value),
                // Rule 6: custom states may declare core transitions from
                // themselves, toward the same target.
                new TransitionDefinition('decide', ['awaiting_legal'], CaseState::Decided->value),
            ];
        }
    };
}

it('accepts a connected custom state and runs its full pipeline', function (): void {
    $definition = legalWorkflow();
    $definition->validate();

    app()->instance(CaseWorkflow::class, $definition);
    Event::fake([StateTransitioned::class]);

    $workflow = new Workflow(app(CaseWorkflow::class));
    $case = CaseFile::factory()->create();

    $workflow->transition($case, 'startInvestigation', ActorRef::system());
    $workflow->transition($case, 'sendToLegal', ActorRef::system());

    expect($case->refresh()->getAttribute('state'))->toBe('awaiting_legal');

    // Core `decide` widened from the custom state still reaches `decided`.
    $workflow->transition($case, 'decide', ActorRef::system());

    expect($case->refresh()->getAttribute('state'))->toBe(CaseState::Decided->value);

    // Rule 4: only the custom transitions dispatch the generic event.
    Event::assertDispatchedTimes(StateTransitioned::class, 2);
    Event::assertDispatched(StateTransitioned::class, fn (StateTransitioned $event) => $event->transition === 'sendToLegal'
        && $event->from === CaseState::UnderInvestigation->value
        && $event->to === 'awaiting_legal');
});

it('rejects rule violations at boot', function (Closure $definition, string $fragment): void {
    try {
        $definition()->validate();
    } catch (InvalidWorkflow $exception) {
        expect($exception->getMessage())->toContain($fragment);

        return;
    }

    $this->fail("Expected InvalidWorkflow containing [{$fragment}]");
})->with([
    'transition out of a terminal state' => [
        fn () => new class extends CaseWorkflow
        {
            protected function customTransitions(): array
            {
                return [new TransitionDefinition('reopen', [CaseState::Closed->value], CaseState::Open->value)];
            }
        },
        'terminal state',
    ],
    'unreachable custom state' => [
        fn () => new class extends CaseWorkflow
        {
            protected function customStates(): array
            {
                return ['limbo'];
            }

            protected function customTransitions(): array
            {
                return [new TransitionDefinition('leaveLimbo', ['limbo'], CaseState::Open->value)];
            }
        },
        'unreachable',
    ],
    'trapped custom state' => [
        fn () => new class extends CaseWorkflow
        {
            protected function customStates(): array
            {
                return ['trap'];
            }

            protected function customTransitions(): array
            {
                return [new TransitionDefinition('enterTrap', [CaseState::Open->value], 'trap')];
            }
        },
        'no path back',
    ],
    'core name collision' => [
        fn () => new class extends CaseWorkflow
        {
            protected function customStates(): array
            {
                return [CaseState::Open->value];
            }
        },
        'collides',
    ],
    'overlong state name' => [
        fn () => new class extends CaseWorkflow
        {
            protected function customStates(): array
            {
                return [str_repeat('a', 33)];
            }
        },
        '32 characters',
    ],
    'retargeted core transition' => [
        fn () => new class extends CaseWorkflow
        {
            protected function customTransitions(): array
            {
                return [new TransitionDefinition('close', [CaseState::Decided->value], CaseState::Open->value)];
            }
        },
        'retargets',
    ],
    'duplicated core from-state' => [
        fn () => new class extends CaseWorkflow
        {
            protected function customTransitions(): array
            {
                return [new TransitionDefinition('decide', [CaseState::Open->value], CaseState::Decided->value)];
            }
        },
        'duplicates',
    ],
    'undeclared target state' => [
        fn () => new class extends CaseWorkflow
        {
            protected function customTransitions(): array
            {
                return [new TransitionDefinition('vanish', [CaseState::Open->value], 'nowhere')];
            }
        },
        'undeclared state',
    ],
]);

it('runs guards through the container and lets them veto', function (): void {
    RecordingGuard::$seen = [];

    $definition = new class extends CaseWorkflow
    {
        protected function customStates(): array
        {
            return ['second_review'];
        }

        protected function customTransitions(): array
        {
            return [
                new TransitionDefinition('requestSecondReview', [CaseState::Open->value], 'second_review', [RecordingGuard::class]),
                new TransitionDefinition('failSecondReview', ['second_review'], CaseState::Open->value, [VetoGuard::class]),
            ];
        }
    };
    $definition->validate();

    $workflow = new Workflow($definition);
    $case = CaseFile::factory()->create();
    $actor = ActorRef::system();

    $workflow->transition($case, 'requestSecondReview', $actor, ['note' => 'double-check']);

    expect(RecordingGuard::$seen)->toHaveCount(1)
        ->and(RecordingGuard::$seen[0]->record->is($case))->toBeTrue()
        ->and(RecordingGuard::$seen[0]->by)->toBe($actor)
        ->and(RecordingGuard::$seen[0]->context)->toBe(['note' => 'double-check'])
        ->and($case->refresh()->getAttribute('state'))->toBe('second_review');

    try {
        $workflow->transition($case, 'failSecondReview', $actor);

        $this->fail('Expected the veto guard to throw');
    } catch (CaseworkException) {
        // Vetoed before any write: state unchanged.
        expect($case->refresh()->getAttribute('state'))->toBe('second_review');
    }
});

it('rejects a losing concurrent transition via the optimistic state check (R-01)', function (): void {
    $workflow = new Workflow(app(CaseWorkflow::class));
    $case = CaseFile::factory()->create();

    // A second in-memory handle on the same row — both still see 'open'.
    $stale = CaseFile::query()->findOrFail($case->getKey());

    // First writer wins: open -> decided.
    $workflow->transition($case, 'decide', ActorRef::system());

    // The stale handle's transition is legal from its in-memory state,
    // but the compare-and-swap finds the row already moved: it loses.
    try {
        $workflow->transition($stale, 'startInvestigation', ActorRef::system());

        $this->fail('Expected the concurrent transition to be rejected');
    } catch (InvalidTransition $exception) {
        expect($exception->getMessage())->toContain('concurrently');
    }

    expect($case->refresh()->getAttribute('state'))->toBe(CaseState::Decided->value);
});

it('keeps the shipped definitions valid', function (): void {
    foreach ([
        ReportWorkflow::class,
        CaseWorkflow::class,
        RestrictionWorkflow::class,
        AppealWorkflow::class,
    ] as $definition) {
        app($definition)->validate();
    }
})->throwsNoExceptions();
