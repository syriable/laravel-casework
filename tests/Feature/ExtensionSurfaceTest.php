<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Syriable\Casework\Cases\CaseState;
use Syriable\Casework\Cases\CaseWorkflow;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Contracts\ScopeResolver;
use Syriable\Casework\Exceptions\CaseworkException;
use Syriable\Casework\Facades\Casework;
use Syriable\Casework\Reporting\Actions\FileReport;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\States\TransitionDefinition;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\ModelRegistry;
use Syriable\Casework\Tests\Support\CustomFileReport;
use Syriable\Casework\Tests\Support\CustomReport;
use Syriable\Casework\Tests\Support\OpenReportPolicy;
use Syriable\Casework\Tests\Support\RecordingGuard;
use Syriable\Casework\Tests\Support\RecordingStrategy;
use Syriable\Casework\Tests\Support\VetoGuard;
use Workbench\App\Models\Post;

/**
 * Extension-surface verification (extending.md §3): each X-point works
 * end to end through config/container alone — no package modification.
 */
it('resolves model overrides everywhere, relations included (X1)', function (): void {
    config()->set('casework.models.report', CustomReport::class);
    config()->set('casework.cases.strategy', 'always');

    Reason::factory()->create(['key' => 'spam']);

    $report = Casework::report(Post::factory()->create())
        ->bySystem()
        ->because('spam')
        ->withMetadata(['spam' => true])
        ->file();

    expect($report)->toBeInstanceOf(CustomReport::class)
        ->and(CustomReport::query()->flaggedAsSpam()->count())->toBe(1);

    // Relations resolve through the registry: the case sees the subclass.
    /** @var CaseFile $case */
    $case = CaseFile::query()->firstOrFail();

    expect($case->reports()->firstOrFail())->toBeInstanceOf(CustomReport::class);
});

it('uses rebound action classes (X11)', function (): void {
    app()->bind(FileReport::class, CustomFileReport::class);

    Reason::factory()->create(['key' => 'spam']);

    $report = Casework::report(Post::factory()->create())->bySystem()->because('spam')->file();

    expect($report->refresh()->metadata)->toBe(['decorated' => true]);
});

it('resolves transition guards through the container, so one guard is rebindable (X13)', function (): void {
    $definition = new class extends CaseWorkflow
    {
        protected function customTransitions(): array
        {
            return [
                new TransitionDefinition(
                    'sendToLegal',
                    [CaseState::Open->value],
                    CaseState::UnderInvestigation->value,
                    [RecordingGuard::class],
                ),
                new TransitionDefinition('legalCleared', [CaseState::UnderInvestigation->value], CaseState::Open->value),
            ];
        }
    };

    $definition->validate();
    app()->instance(CaseWorkflow::class, $definition);
    app()->bind(RecordingGuard::class, VetoGuard::class);

    $case = CaseFile::factory()->create();

    try {
        (new Workflow(app(CaseWorkflow::class)))->transition($case, 'sendToLegal', ActorRef::system());
        $this->fail('Expected the rebound guard to veto.');
    } catch (RuntimeException $exception) {
        expect($exception)->toBeInstanceOf(CaseworkException::class)
            ->and($exception->getMessage())->toBe('vetoed');
    }

    expect($case->refresh()->getAttribute('state'))->toBe(CaseState::Open->value);
});

it('enforces a bound ScopeResolver on scoped operations (X6)', function (): void {
    Gate::before(fn () => true);

    app()->instance(ScopeResolver::class, new class implements ScopeResolver
    {
        public function scopesFor(Model $actor): ?array
        {
            return ['fashion'];
        }

        public function scopeOf(Model $subject): ?string
        {
            return 'electronics';
        }
    });

    $moderator = Post::factory()->create();
    $case = CaseFile::factory()->create();

    // Policy grants everything, yet the scope boundary denies.
    expect(fn () => Casework::escalateCase($case, $moderator, 'urgent'))
        ->toThrow(AuthorizationException::class, 'scopes');
});

it('consults a configured custom case strategy class (X7)', function (): void {
    RecordingStrategy::$consultedFor = [];
    config()->set('casework.cases.strategy', RecordingStrategy::class);

    Reason::factory()->create(['key' => 'spam']);

    $report = Casework::report(Post::factory()->create())->bySystem()->because('spam')->file();

    expect(RecordingStrategy::$consultedFor)->toBe([$report->getKey()])
        ->and(CaseFile::query()->count())->toBe(0);
});

it('honors an application-registered policy override (X12)', function (): void {
    $user = Post::factory()->create();

    $denied = Casework::report(Post::factory()->create());
    Reason::factory()->create(['key' => 'spam']);

    $report = $denied->by($user)->because('spam')->file();

    // Default policy: model actors cannot dismiss (safe-by-default).
    expect(fn () => Casework::dismissReport($report, $user))
        ->toThrow(AuthorizationException::class);

    // The application's own policy replaces the default entirely.
    Gate::policy(ModelRegistry::classFor('report'), OpenReportPolicy::class);

    Casework::dismissReport($report, $user);

    expect($report->refresh()->getAttribute('state'))->toBe('dismissed');
});
