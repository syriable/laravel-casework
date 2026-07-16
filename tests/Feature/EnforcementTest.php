<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Syriable\Casework\Enforcement\Actions\ApplyRestriction;
use Syriable\Casework\Enforcement\Events\RestrictionExpired;
use Syriable\Casework\Enforcement\Events\RestrictionLifted;
use Syriable\Casework\Enforcement\Events\RestrictionSuperseded;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Exceptions\IncompleteBuilder;
use Syriable\Casework\Exceptions\InvalidConfiguration;
use Syriable\Casework\Exceptions\InvalidTransition;
use Syriable\Casework\Facades\Casework;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Tests\Support\AssertsAudit;
use Workbench\App\Models\Post;

uses(AssertsAudit::class);

it('applies restrictions through the Phase 5 builder', function (): void {
    $user = Post::factory()->create();
    $moderator = Post::factory()->create();
    Gate::before(fn () => true);

    $restriction = Casework::restrict($user, 'suspension')
        ->by($moderator)
        ->for(days: 7)
        ->inScope('listings')
        ->because('Spam wave')
        ->apply();

    expect($restriction->getAttribute('state'))->toBe('active')
        ->and($restriction->refresh()->isActive())->toBeTrue()
        ->and($restriction->isPermanent())->toBeFalse()
        ->and($restriction->getAttribute('scope'))->toBe('listings')
        ->and($restriction->issuer->is($moderator))->toBeTrue();

    $this->assertAuditRecorded('restriction.applied', $restriction, $moderator);
});

it('suspends permanently and validates types and builder completeness', function (): void {
    $user = Post::factory()->create();

    $suspension = Casework::suspend($user)->bySystem()->permanently()->apply();

    expect($suspension->isPermanent())->toBeTrue()
        ->and($user->isSuspended())->toBeTrue();

    expect(fn () => Casework::restrict($user, 'shadowban')->bySystem()->permanently()->apply())
        ->toThrow(InvalidConfiguration::class);

    config()->set('casework.enforcement.restriction_types', ['shadowban']);

    $shadowban = Casework::restrict($user, 'shadowban')->bySystem()->permanently()->apply();

    expect($shadowban->getAttribute('type'))->toBe('shadowban');

    expect(fn () => Casework::suspend($user)->bySystem()->apply())
        ->toThrow(IncompleteBuilder::class)
        ->and(fn () => Casework::suspend($user)->permanently()->apply())
        ->toThrow(IncompleteBuilder::class);
});

it('lifts active restrictions with actor and reason', function (): void {
    Event::fake([RestrictionLifted::class]);

    $restriction = Restriction::factory()->create();

    Casework::lift($restriction, ActorRef::system(), 'Appeal upheld');

    expect($restriction->refresh()->getAttribute('state'))->toBe('lifted')
        ->and($restriction->lift_reason)->toBe('Appeal upheld')
        ->and($restriction->lifted_at)->not->toBeNull();

    $this->assertAuditRecorded('restriction.lifted', $restriction);
    Event::assertDispatched(RestrictionLifted::class, fn (RestrictionLifted $event) => $event->reason === 'Appeal upheld');

    // I-10: only currently active restrictions can be lifted.
    expect(fn () => Casework::lift($restriction, ActorRef::system(), 'again'))
        ->toThrow(InvalidTransition::class);

    $stale = Restriction::factory()->stalePastExpiry()->create();

    expect(fn () => Casework::lift($stale, ActorRef::system(), 'too late'))
        ->toThrow(InvalidTransition::class, 'I-10');
});

it('expires due restrictions via the command without affecting correctness', function (): void {
    Event::fake([RestrictionExpired::class]);

    $subject = Post::factory()->create();
    $stale = Restriction::factory()->about($subject)->stalePastExpiry()->create();
    $permanent = Restriction::factory()->about($subject)->create();
    $future = Restriction::factory()->about($subject)->expiringAt(now()->addWeek())->create();

    // The real-time rule holds before the command runs.
    expect($stale->isActive())->toBeFalse()
        ->and(Casework::isRestricted($subject))->toBeTrue();

    $this->artisan('casework:expire-restrictions')
        ->expectsOutputToContain('Expired 1')
        ->assertSuccessful();

    expect($stale->refresh()->getAttribute('state'))->toBe('expired')
        ->and($permanent->refresh()->getAttribute('state'))->toBe('active')
        ->and($future->refresh()->getAttribute('state'))->toBe('active');

    $this->assertAuditRecorded('restriction.expired', $stale);
    Event::assertDispatchedTimes(RestrictionExpired::class, 1);
});

it('supersedes an active restriction atomically with its replacement', function (): void {
    Event::fake([RestrictionSuperseded::class]);

    $subject = Post::factory()->create();
    $old = Restriction::factory()->about($subject)->create();

    $replacement = app(ApplyRestriction::class)->execute(
        $subject,
        ActorRef::system(),
        'suspension',
        now()->addDays(30),
        supersedes: $old,
    );

    expect($old->refresh()->getAttribute('state'))->toBe('superseded')
        ->and($old->supersededBy->is($replacement))->toBeTrue();

    $this->assertAuditRecorded('restriction.superseded', $old);
    Event::assertDispatched(RestrictionSuperseded::class, fn (RestrictionSuperseded $event) => $event->replacement->is($replacement));
});

it('issues and expires warnings', function (): void {
    $user = Post::factory()->create();

    $warning = Casework::warn($user)->bySystem()->because('First offence.')->issue();
    Casework::warn($user)->bySystem()->because('Old one.')->expiring(now()->subDay())->issue();

    expect($user->warnings()->count())->toBe(2)
        ->and($user->activeWarnings()->count())->toBe(1)
        ->and($warning->refresh()->isActive())->toBeTrue();

    $this->assertAuditRecorded('warning.issued', $warning);

    expect(fn () => Casework::warn($user)->bySystem()->issue())
        ->toThrow(IncompleteBuilder::class);
});

it('answers the enforcement hot path in a single query', function (): void {
    $user = Post::factory()->create();
    Casework::suspend($user)->bySystem()->for(days: 7)->apply();

    // Warm up: morph metadata, connection.
    $user->isRestricted();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $viaTrait = $user->isRestricted('suspension', 'listings') || $user->isRestricted('suspension');
    $viaFacade = Casework::isRestricted($user, type: 'suspension');

    // NFR-04: one indexed query per check (two trait calls + one facade).
    expect($viaTrait)->toBeTrue()
        ->and($viaFacade)->toBeTrue()
        ->and($queries)->toBe(3);
});

it('applies the real-time rule across trait and facade checks', function (): void {
    $user = Post::factory()->create();
    $restriction = Casework::suspend($user)->bySystem()->for(days: 1)->apply();

    expect($user->isSuspended())->toBeTrue();

    $this->travel(2)->days();

    // Stored state is still active; every check disagrees.
    expect($restriction->refresh()->getAttribute('state'))->toBe('active')
        ->and($user->isSuspended())->toBeFalse()
        ->and($user->activeRestrictions()->count())->toBe(0)
        ->and(Casework::isRestricted($user))->toBeFalse();
});

it('denies enforcement to model actors by default', function (): void {
    $moderator = Post::factory()->create();
    $user = Post::factory()->create();

    expect(fn () => Casework::restrict($user, 'suspension')->by($moderator)->permanently()->apply())
        ->toThrow(AuthorizationException::class)
        ->and(fn () => Casework::warn($user)->by($moderator)->because('x')->issue())
        ->toThrow(AuthorizationException::class);

    $restriction = Restriction::factory()->create();

    expect(fn () => Casework::lift($restriction, $moderator, 'because'))
        ->toThrow(AuthorizationException::class);
});
