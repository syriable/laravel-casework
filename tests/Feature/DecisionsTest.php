<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Syriable\Casework\Audit\Models\AuditEntry;
use Syriable\Casework\Cases\Events\CaseDecided;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Enforcement\Events\RestrictionApplied;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Exceptions\IncompleteBuilder;
use Syriable\Casework\Exceptions\InvalidConfiguration;
use Syriable\Casework\Exceptions\InvalidTransition;
use Syriable\Casework\Facades\Casework;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Support\Outcome;
use Syriable\Casework\Tests\Support\AssertsAudit;
use Workbench\App\Models\Post;

uses(AssertsAudit::class);

it('decides a case atomically with enforcement and report resolution', function (): void {
    $post = Post::factory()->create();
    $case = CaseFile::factory()->about($post)->create();
    $attached = Report::factory()->about($post)->create(['case_id' => $case->id]);
    $attached->writeStateThroughTransition('attached_to_case');

    // The Phase 5 §4 chain.
    $decision = Casework::decide($case)
        ->bySystem()
        ->outcome(Outcome::UPHOLD)
        ->rationale('Repeated spam after warning.')
        ->withSuspension(days: 30)
        ->withRestriction('suspension', permanent: true, scope: 'listings')
        ->withWarning('Final notice.')
        ->finalize();

    expect($case->refresh()->getAttribute('state'))->toBe('decided')
        ->and($decision->case->is($case))->toBeTrue()
        ->and($decision->outcome)->toBe('uphold')
        ->and($decision->restrictions()->count())->toBe(2)
        ->and($decision->warnings()->count())->toBe(1)
        ->and($attached->refresh()->getAttribute('state'))->toBe('resolved')
        ->and($attached->decision->is($decision))->toBeTrue()
        ->and($post->isSuspended())->toBeTrue();

    $this->assertAuditRecorded('case.decided', $case);
    $this->assertAuditRecorded('restriction.applied', $decision->restrictions()->firstOrFail());
    $this->assertAuditRecorded('warning.issued', $decision->warnings()->firstOrFail());
    $this->assertAuditRecorded('report.resolved', $attached);
});

it('dispatches enforcement events before the summarizing CaseDecided', function (): void {
    // Occurrence order (ADR-0015): effects, then summary.
    $order = [];
    Event::listen(RestrictionApplied::class, function () use (&$order): void {
        $order[] = RestrictionApplied::class;
    });
    Event::listen(CaseDecided::class, function () use (&$order): void {
        $order[] = CaseDecided::class;
    });

    $case = CaseFile::factory()->create();

    Casework::decide($case)->bySystem()->outcome(Outcome::DISMISS)->withSuspension(days: 7)->finalize();

    expect($order)->toBe([RestrictionApplied::class, CaseDecided::class]);
});

it('accepts configured custom outcomes and rejects unknown ones', function (): void {
    config()->set('casework.decisions.outcomes', ['uphold_with_education']);

    $decision = Casework::decide(CaseFile::factory()->create())
        ->bySystem()
        ->outcome('uphold_with_education')
        ->finalize();

    expect($decision->outcome)->toBe('uphold_with_education');

    expect(fn () => Casework::decide(CaseFile::factory()->create())->bySystem()->outcome('nope')->finalize())
        ->toThrow(InvalidConfiguration::class);
});

it('records supersession chains', function (): void {
    $case = CaseFile::factory()->create();
    $original = Casework::decide($case)->bySystem()->outcome(Outcome::DISMISS)->finalize();

    // A superseding decision on the already-decided case (FR-304): the
    // case is not decidable again — deciding requires a pre-decided
    // state — so supersession happens through appeals (M8) or a fresh
    // case; here we assert the chain field itself via a new case.
    $second = CaseFile::factory()->create();
    $superseding = Casework::decide($second)
        ->bySystem()
        ->outcome(Outcome::UPHOLD)
        ->supersedes($original)
        ->finalize();

    expect($superseding->supersedes->is($original))->toBeTrue();
});

it('refuses deciding an already decided case', function (): void {
    $case = CaseFile::factory()->create();
    Casework::decide($case)->bySystem()->outcome(Outcome::DISMISS)->finalize();

    Casework::decide($case)->bySystem()->outcome(Outcome::DISMISS)->finalize();
})->throws(InvalidTransition::class);

it('prevents self-moderation when configured', function (): void {
    Gate::before(fn () => true);

    $moderator = Post::factory()->create();
    $ownCase = CaseFile::factory()->about($moderator)->create();

    expect(fn () => Casework::decide($ownCase)->by($moderator)->outcome(Outcome::DISMISS)->finalize())
        ->toThrow(AuthorizationException::class, 'themselves');

    config()->set('casework.authorization.prevent_self_moderation', false);

    $decision = Casework::decide($ownCase)->by($moderator)->outcome(Outcome::DISMISS)->finalize();

    expect($decision->exists)->toBeTrue();
});

it('validates the decision builder at finalize', function (): void {
    $case = CaseFile::factory()->create();

    expect(fn () => Casework::decide($case)->outcome(Outcome::DISMISS)->finalize())
        ->toThrow(IncompleteBuilder::class)
        ->and(fn () => Casework::decide($case)->bySystem()->finalize())
        ->toThrow(IncompleteBuilder::class)
        ->and(Decision::query()->count())->toBe(0);
});

it('rolls back the entire decision when the surrounding transaction aborts', function (): void {
    // I-08: decision, enforcement, resolution, and audit are one unit.
    $post = Post::factory()->create();
    $case = CaseFile::factory()->about($post)->create();

    try {
        DB::transaction(function () use ($case): void {
            Casework::decide($case)->bySystem()->outcome(Outcome::UPHOLD)->withSuspension(days: 7)->finalize();

            throw new RuntimeException('abort');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($case->refresh()->getAttribute('state'))->toBe('open')
        ->and(Decision::query()->count())->toBe(0)
        ->and(Restriction::query()->count())->toBe(0)
        ->and(AuditEntry::query()->count())->toBe(0)
        ->and($post->isSuspended())->toBeFalse();
});
