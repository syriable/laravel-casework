<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Syriable\Casework\Appeals\Events\AppealAssigned;
use Syriable\Casework\Appeals\Events\AppealOverturned;
use Syriable\Casework\Appeals\Events\AppealRejected;
use Syriable\Casework\Appeals\Events\AppealSubmitted;
use Syriable\Casework\Appeals\Events\AppealUpheld;
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Audit\Models\AuditEntry;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Enforcement\Events\RestrictionLifted;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Exceptions\AppealLimitReached;
use Syriable\Casework\Exceptions\AppealWindowClosed;
use Syriable\Casework\Exceptions\IncompleteBuilder;
use Syriable\Casework\Exceptions\InvalidTransition;
use Syriable\Casework\Exceptions\ReviewerNotIndependent;
use Syriable\Casework\Facades\Casework;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Outcome;
use Syriable\Casework\Tests\Support\AssertsAudit;
use Workbench\App\Models\Post;

uses(AssertsAudit::class);

it('submits an appeal through the Phase 5 builder', function (): void {
    Event::fake([AppealSubmitted::class]);
    Gate::before(fn () => true);

    $user = Post::factory()->create();
    $restriction = Restriction::factory()->create();

    $appeal = Casework::appeal($restriction)
        ->by($user)
        ->statement('I believe this was a mistake.')
        ->submit();

    expect($appeal->getAttribute('state'))->toBe('submitted')
        ->and($appeal->appealed->is($restriction))->toBeTrue()
        ->and($appeal->appellant->is($user))->toBeTrue()
        ->and($appeal->getAttribute('statement'))->toBe('I believe this was a mistake.');

    $this->assertAuditRecorded('appeal.submitted', $appeal, $user);
    Event::assertDispatched(AppealSubmitted::class);

    expect(fn () => Casework::appeal($restriction)->statement('x')->submit())
        ->toThrow(IncompleteBuilder::class)
        ->and(fn () => Casework::appeal($user)->bySystem()->submit())
        ->toThrow(InvalidArgumentException::class, 'appealable');
});

it('enforces the appeal window through its exact edge', function (): void {
    config()->set('casework.appeals.limit_per_target', 10);

    $start = Carbon::parse('2026-01-01 12:00:00');
    $this->travelTo($start);

    $restriction = Restriction::factory()->create();

    // FR-506 edge: the exact expiry instant still accepts submissions.
    $this->travelTo($start->clone()->addDays(30));

    $atEdge = Casework::appeal($restriction)->bySystem()->submit();

    expect($atEdge->exists)->toBeTrue();

    $this->travelTo($start->clone()->addDays(30)->addSecond());

    expect(fn () => Casework::appeal($restriction)->bySystem()->submit())
        ->toThrow(AppealWindowClosed::class, '30-day');

    // A null window disables the check entirely.
    config()->set('casework.appeals.window_days', null);

    $late = Casework::appeal($restriction)->bySystem()->submit();

    expect($late->exists)->toBeTrue();
});

it('enforces the per-target appeal limit at N versus N+1', function (): void {
    $restriction = Restriction::factory()->create();

    Casework::appeal($restriction)->bySystem()->submit();

    // Default limit: one appeal per target.
    expect(fn () => Casework::appeal($restriction)->bySystem()->submit())
        ->toThrow(AppealLimitReached::class, 'limit of 1');

    config()->set('casework.appeals.limit_per_target', 2);

    $second = Casework::appeal($restriction)->bySystem()->submit();

    expect($second->exists)->toBeTrue()
        ->and(fn () => Casework::appeal($restriction)->bySystem()->submit())
        ->toThrow(AppealLimitReached::class, 'limit of 2');
});

it('requires an independent reviewer for assignment (I-12)', function (): void {
    Event::fake([AppealAssigned::class]);
    Gate::before(fn () => true);

    $issuer = Post::factory()->create();
    $other = Post::factory()->create();
    $lead = Post::factory()->create();

    $restriction = Restriction::factory()->issuedBy($issuer)->create();
    $appeal = Appeal::factory()->against($restriction)->create();

    expect(fn () => Casework::assignAppeal($appeal, to: $issuer, by: $lead))
        ->toThrow(ReviewerNotIndependent::class, 'I-12');

    Casework::assignAppeal($appeal, to: $other, by: $lead);

    expect($appeal->refresh()->reviewer->is($other))->toBeTrue();

    $this->assertAuditRecorded('appeal.assigned', $appeal, $lead);
    Event::assertDispatched(AppealAssigned::class, fn (AppealAssigned $event) => $event->reviewer->is($other));

    // The original decider of an appealed decision is equally excluded.
    $decision = Decision::factory()->decidedBy($issuer)->create();
    $decisionAppeal = Appeal::factory()->against($decision)->create();

    expect(fn () => Casework::assignAppeal($decisionAppeal, to: $issuer, by: $lead))
        ->toThrow(ReviewerNotIndependent::class);

    // Independence is configurable.
    config()->set('casework.appeals.require_independent_reviewer', false);

    Casework::assignAppeal($appeal, to: $issuer, by: $lead);

    expect($appeal->refresh()->reviewer->is($issuer))->toBeTrue();
});

it('never lets the appellant review their own appeal (FR-604)', function (): void {
    Gate::before(fn () => true);

    $user = Post::factory()->create();
    $appeal = Appeal::factory()->by($user)->create();

    expect(fn () => Casework::assignAppeal($appeal, to: $user, by: $user))
        ->toThrow(AuthorizationException::class, 'themselves')
        ->and(fn () => Casework::startAppealReview($appeal, $user))
        ->toThrow(AuthorizationException::class, 'themselves');

    config()->set('casework.authorization.prevent_self_moderation', false);
    config()->set('casework.appeals.require_independent_reviewer', false);

    Casework::assignAppeal($appeal, to: $user, by: $user);

    expect($appeal->refresh()->reviewer->is($user))->toBeTrue();
});

it('starts review, vets independence, and records the acting reviewer', function (): void {
    Gate::before(fn () => true);

    $issuer = Post::factory()->create();
    $reviewer = Post::factory()->create();

    $restriction = Restriction::factory()->issuedBy($issuer)->create();
    $appeal = Appeal::factory()->against($restriction)->create();

    expect(fn () => Casework::startAppealReview($appeal, $issuer))
        ->toThrow(ReviewerNotIndependent::class);

    Casework::startAppealReview($appeal, $reviewer);

    expect($appeal->refresh()->getAttribute('state'))->toBe('under_review')
        ->and($appeal->reviewer->is($reviewer))->toBeTrue();

    $this->assertAuditRecorded('appeal.review_started', $appeal, $reviewer);

    // A pre-assigned reviewer is not overwritten by whoever starts.
    $assigned = Post::factory()->create();
    $other = Appeal::factory()->against($restriction)->reviewedBy($assigned)->create();

    Casework::startAppealReview($other, $reviewer);

    expect($other->refresh()->reviewer->is($assigned))->toBeTrue();
});

it('upholds an appeal and refuses further resolution', function (): void {
    Event::fake([AppealUpheld::class]);

    $appeal = Appeal::factory()->underReview()->create();

    Casework::resolveAppeal($appeal)->bySystem()->uphold('Original decision was sound.');

    expect($appeal->refresh()->getAttribute('state'))->toBe('upheld');

    $this->assertAuditRecorded('appeal.upheld', $appeal);
    Event::assertDispatched(AppealUpheld::class);

    expect(fn () => Casework::resolveAppeal($appeal)->bySystem()->uphold())
        ->toThrow(InvalidTransition::class)
        ->and(fn () => Casework::assignAppeal($appeal, to: Post::factory()->create(), by: ActorRef::system()))
        ->toThrow(InvalidTransition::class, 'reassigned')
        ->and(fn () => Casework::resolveAppeal(Appeal::factory()->underReview()->create())->uphold())
        ->toThrow(IncompleteBuilder::class);
});

it('rejects an appeal administratively straight from submitted', function (): void {
    Event::fake([AppealRejected::class]);

    $appeal = Appeal::factory()->create();

    Casework::resolveAppeal($appeal)->bySystem()->reject('Duplicate of an earlier appeal.');

    expect($appeal->refresh()->getAttribute('state'))->toBe('rejected');

    $entry = $this->assertAuditRecorded('appeal.rejected', $appeal);

    expect($entry->payload)->toBe(['reason' => 'Duplicate of an earlier appeal.']);

    Event::assertDispatched(
        AppealRejected::class,
        fn (AppealRejected $event) => $event->reason === 'Duplicate of an earlier appeal.' && $event->from === 'submitted',
    );
});

it('overturns an appealed direct restriction by lifting it', function (): void {
    $user = Post::factory()->create();
    $restriction = Restriction::factory()->about($user)->create();
    $appeal = Appeal::factory()->against($restriction)->underReview()->create();

    expect($user->isSuspended())->toBeTrue();

    Casework::resolveAppeal($appeal)->bySystem()->overturn('Original evidence insufficient');

    expect($appeal->refresh()->getAttribute('state'))->toBe('overturned')
        ->and($restriction->refresh()->getAttribute('state'))->toBe('lifted')
        ->and($restriction->lift_reason)->toBe('Original evidence insufficient')
        ->and($user->isSuspended())->toBeFalse()
        // A direct restriction carries no decision — nothing to supersede.
        ->and($appeal->getAttribute('resulting_decision_id'))->toBeNull()
        ->and(Decision::query()->count())->toBe(0);

    $this->assertAuditRecorded('restriction.lifted', $restriction);
    $this->assertAuditRecorded('appeal.overturned', $appeal);
});

it('overturns an appealed decision atomically with lift and supersession (I-13)', function (): void {
    $user = Post::factory()->create();
    $reviewer = Post::factory()->create();
    Gate::before(fn () => true);

    $case = CaseFile::factory()->about($user)->create();
    $original = Decision::factory()->create(['case_id' => $case->getKey(), 'outcome' => Outcome::UPHOLD]);

    $active = Restriction::factory()->about($user)->create(['decision_id' => $original->getKey()]);
    $stale = Restriction::factory()->about($user)->stalePastExpiry()->create(['decision_id' => $original->getKey()]);

    $appeal = Appeal::factory()->against($original)->by($user)->underReview()->create();

    Casework::resolveAppeal($appeal)->by($reviewer)->overturn('Reversed on appeal.');

    $appeal->refresh();

    /** @var Decision $superseding */
    $superseding = $appeal->resultingDecision;

    expect($appeal->getAttribute('state'))->toBe('overturned')
        ->and($active->refresh()->getAttribute('state'))->toBe('lifted')
        // Inactive restrictions have nothing to lift.
        ->and($stale->refresh()->getAttribute('state'))->toBe('active')
        ->and($superseding)->not->toBeNull()
        ->and($superseding->outcome)->toBe(Outcome::DISMISS)
        ->and($superseding->supersedes->is($original))->toBeTrue()
        ->and($superseding->case->is($case))->toBeTrue()
        ->and($superseding->decider->is($reviewer))->toBeTrue();

    $this->assertAuditRecorded('appeal.overturned', $appeal, $reviewer);
});

it('dispatches lift effects before the summarizing AppealOverturned', function (): void {
    $order = [];
    Event::listen(RestrictionLifted::class, function () use (&$order): void {
        $order[] = RestrictionLifted::class;
    });
    Event::listen(AppealOverturned::class, function () use (&$order): void {
        $order[] = AppealOverturned::class;
    });

    $restriction = Restriction::factory()->create();
    $appeal = Appeal::factory()->against($restriction)->underReview()->create();

    Casework::resolveAppeal($appeal)->bySystem()->overturn();

    expect($order)->toBe([RestrictionLifted::class, AppealOverturned::class]);
});

it('rolls back the entire overturn when the surrounding transaction aborts', function (): void {
    $user = Post::factory()->create();
    $case = CaseFile::factory()->about($user)->create();
    $original = Decision::factory()->create(['case_id' => $case->getKey()]);
    $restriction = Restriction::factory()->about($user)->create(['decision_id' => $original->getKey()]);
    $appeal = Appeal::factory()->against($original)->underReview()->create();

    try {
        DB::transaction(function () use ($appeal): void {
            Casework::resolveAppeal($appeal)->bySystem()->overturn();

            throw new RuntimeException('abort');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($appeal->refresh()->getAttribute('state'))->toBe('under_review')
        ->and($appeal->getAttribute('resulting_decision_id'))->toBeNull()
        ->and($restriction->refresh()->getAttribute('state'))->toBe('active')
        ->and(Decision::query()->count())->toBe(1)
        ->and(AuditEntry::query()->count())->toBe(0);
});

it('denies appeal operations to model actors by default', function (): void {
    $user = Post::factory()->create();
    $restriction = Restriction::factory()->create();
    $appeal = Appeal::factory()->against($restriction)->create();

    expect(fn () => Casework::appeal($restriction)->by($user)->submit())
        ->toThrow(AuthorizationException::class)
        ->and(fn () => Casework::assignAppeal($appeal, to: Post::factory()->create(), by: $user))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => Casework::startAppealReview($appeal, $user))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => Casework::resolveAppeal($appeal)->by($user)->reject())
        ->toThrow(AuthorizationException::class);
});
