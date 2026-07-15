<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Appeals\AppealWorkflow;
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Cases\CaseWorkflow;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Contracts\Stateful;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Enforcement\RestrictionWorkflow;
use Syriable\Casework\Exceptions\InvalidTransition;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Reporting\ReportWorkflow;
use Syriable\Casework\States\TransitionDefinition;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;

/**
 * The generated exhaustive harness (testing strategy §3.1, NFR-07):
 * every (state, transition) pair per lifecycle is derived from the
 * bound WorkflowDefinition — allowed pairs must succeed with the
 * documented target state; every other pair must throw
 * InvalidTransition and leave state untouched.
 */
dataset('lifecycles', [
    'report' => [ReportWorkflow::class, fn (): Model&Stateful => Report::factory()->create(), 8, 25],
    'case' => [CaseWorkflow::class, fn (): Model&Stateful => CaseFile::factory()->create(), 6, 25],
    'restriction' => [RestrictionWorkflow::class, fn (): Model&Stateful => Restriction::factory()->create(), 3, 16],
    'appeal' => [AppealWorkflow::class, fn (): Model&Stateful => Appeal::factory()->create(), 5, 25],
]);

function placeInState(Model&Stateful $record, string $state): Model&Stateful
{
    if (Workflow::stateOf($record) !== $state) {
        $record->writeStateThroughTransition($state);
    }

    return $record;
}

it('exercises every state and transition pair', function (string $definitionClass, Closure $make, int $expectedAllowed, int $expectedPairs): void {
    $definition = app($definitionClass);
    $workflow = new Workflow($definition);

    $names = array_values(array_unique(array_map(
        fn ($transition) => $transition->name,
        $definition->transitions(),
    )));

    $pairs = 0;
    $allowed = 0;

    foreach ($definition->states() as $state) {
        foreach ($names as $name) {
            $pairs++;
            $record = placeInState($make(), $state);
            $expected = $definition->find($name, $state);

            if ($expected instanceof TransitionDefinition) {
                $allowed++;
                $workflow->transition($record, $name, ActorRef::system());

                expect($record->refresh()->getAttribute('state'))->toBe($expected->to);

                continue;
            }

            try {
                $workflow->transition($record, $name, ActorRef::system());

                $this->fail("Expected InvalidTransition for [{$name}] from [{$state}]");
            } catch (InvalidTransition $exception) {
                expect($exception->transition)->toBe($name)
                    ->and($exception->fromState)->toBe($state)
                    ->and($record->refresh()->getAttribute('state'))->toBe($state);
            }
        }
    }

    // The matrix dimensions come from the Phase 7 tables; a drifting
    // definition changes these counts and fails loudly.
    expect($pairs)->toBe($expectedPairs)
        ->and($allowed)->toBe($expectedAllowed);
})->with('lifecycles');

it('initializes creation transitions on unsaved records', function (): void {
    $creations = [
        [ReportWorkflow::class, new Report, 'file', 'pending'],
        [CaseWorkflow::class, new CaseFile, 'open', 'open'],
        [RestrictionWorkflow::class, new Restriction, 'apply', 'active'],
        [AppealWorkflow::class, new Appeal, 'submit', 'submitted'],
    ];

    foreach ($creations as [$definitionClass, $record, $name, $initial]) {
        $workflow = new Workflow(app($definitionClass));

        $workflow->initialize($record, $name, ActorRef::system());

        expect($record->getAttribute('state'))->toBe($initial)
            ->and($record->exists)->toBeFalse();
    }
});

it('rejects unknown creation transitions', function (): void {
    $workflow = new Workflow(app(ReportWorkflow::class));

    $workflow->initialize(new Report, 'startReview', ActorRef::system());
})->throws(InvalidTransition::class);

it('binds each workflow definition as a singleton', function (): void {
    foreach ([
        ReportWorkflow::class, CaseWorkflow::class,
        RestrictionWorkflow::class, AppealWorkflow::class,
    ] as $definition) {
        expect(app($definition))->toBe(app($definition));
    }
});
