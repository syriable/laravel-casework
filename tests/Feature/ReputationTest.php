<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Syriable\Casework\Exceptions\ReporterBlocked;
use Syriable\Casework\Exceptions\ReportRateLimited;
use Syriable\Casework\Facades\Casework;
use Syriable\Casework\Reporting\Events\ReporterBlocked as ReporterBlockedEvent;
use Syriable\Casework\Reporting\Events\ReporterReputationChanged;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Reporting\Models\ReporterReputation;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Outcome;
use Syriable\Casework\Tests\Support\AssertsAudit;
use Syriable\Casework\Tests\Support\RecordingReputationPolicy;
use Workbench\App\Models\Post;

uses(AssertsAudit::class);

it('does nothing when reputation tracking is disabled (the default)', function (): void {
    $post = Post::factory()->create();
    $user = Post::factory()->create();
    $reason = Reason::factory()->create();

    $report = Casework::report($post)->by($user)->because($reason)->file();
    Casework::dismissReport($report, ActorRef::system());

    expect(ReporterReputation::query()->count())->toBe(0);
    $this->assertNoAuditRecorded('reporter.reputation_adjusted', $report);
});

it('decreases reputation when a standalone report is dismissed', function (): void {
    config()->set('casework.reporting.reputation.enabled', true);
    Event::fake([ReporterReputationChanged::class]);

    $post = Post::factory()->create();
    $user = Post::factory()->create();
    $reason = Reason::factory()->create();

    $report = Casework::report($post)->by($user)->because($reason)->file();
    Casework::dismissReport($report, ActorRef::system());

    expect($user->reputationScore())->toBe(-1);

    $entry = $this->assertAuditRecorded('reporter.reputation_adjusted', $user->reputation()->firstOrFail());
    expect($entry->payload)->toMatchArray(['delta' => -1, 'before' => 0, 'after' => -1, 'reason' => 'report.dismissed']);

    Event::assertDispatched(ReporterReputationChanged::class, fn (ReporterReputationChanged $event) => $event->reporter->is($user)
        && $event->before === 0
        && $event->after === -1
        && $event->reason === 'report.dismissed');
});

it('decreases reputation when a case decision dismisses the report', function (): void {
    config()->set('casework.reporting.reputation.enabled', true);

    $post = Post::factory()->create();
    $user = Post::factory()->create();
    $reason = Reason::factory()->create();

    $report = Casework::report($post)->by($user)->because($reason)->file();
    $case = Casework::openCase($post)->bySystem()->open();
    Casework::attachReport($report, $case, ActorRef::system());

    Casework::decide($case)->bySystem()->outcome(Outcome::DISMISS)->finalize();

    expect($user->reputationScore())->toBe(-1);
});

it('increases reputation when a case decision upholds or escalates the report', function (): void {
    config()->set('casework.reporting.reputation.enabled', true);

    $post = Post::factory()->create();
    $user = Post::factory()->create();
    $reason = Reason::factory()->create();

    $report = Casework::report($post)->by($user)->because($reason)->file();
    $case = Casework::openCase($post)->bySystem()->open();
    Casework::attachReport($report, $case, ActorRef::system());

    Casework::decide($case)->bySystem()->outcome(Outcome::UPHOLD)->finalize();

    expect($user->reputationScore())->toBe(1);
});

it('does not adjust reputation for system or anonymous reporters', function (): void {
    config()->set('casework.reporting.allow_anonymous', true);
    config()->set('casework.reporting.reputation.enabled', true);

    $post = Post::factory()->create();
    $reason = Reason::factory()->create();

    $anonymous = Casework::report($post)->anonymously()->because($reason)->file();
    $system = Casework::report($post)->bySystem()->because(Reason::factory()->create())->file();

    Casework::dismissReport($anonymous, ActorRef::system());
    Casework::dismissReport($system, ActorRef::system());

    expect(ReporterReputation::query()->count())->toBe(0);
});

it('blocks a reporter at or below the configured threshold', function (): void {
    config()->set('casework.reporting.reputation.enabled', true);
    config()->set('casework.reporting.reputation.block_threshold', -2);

    $post = Post::factory()->create();
    $user = Post::factory()->create();

    ReporterReputation::factory()->forReporter($user)->withScore(-2)->create();

    expect($user->isBlockedFromReporting())->toBeTrue()
        ->and(Casework::isReporterBlocked($user))->toBeTrue();

    expect(fn () => Casework::report($post)->by($user)->because(Reason::factory()->create())->file())
        ->toThrow(ReporterBlocked::class);

    expect(Report::query()->count())->toBe(0);
});

it('does not block reporting when block_threshold is null (tracking only)', function (): void {
    config()->set('casework.reporting.reputation.enabled', true);

    $post = Post::factory()->create();
    $user = Post::factory()->create();

    ReporterReputation::factory()->forReporter($user)->withScore(-50)->create();

    $report = Casework::report($post)->by($user)->because(Reason::factory()->create())->file();

    expect($report->exists)->toBeTrue();
});

it('dispatches ReporterBlocked only on the transition into the blocked state', function (): void {
    config()->set('casework.reporting.reputation.enabled', true);
    config()->set('casework.reporting.reputation.block_threshold', -1);
    Event::fake([ReporterBlockedEvent::class]);

    $post = Post::factory()->create();
    $user = Post::factory()->create();

    // Both reports are filed before either is dismissed, so the second
    // filing doesn't get caught by its own reporter's block.
    $first = Casework::report($post)->by($user)->because(Reason::factory()->create())->file();
    $second = Casework::report($post)->by($user)->because(Reason::factory()->create())->file();

    // Two dismissals: the first crosses the threshold (0 -> -1), the
    // second stays at/below it (-1 -> -2) without re-firing the event.
    Casework::dismissReport($first, ActorRef::system());
    Casework::dismissReport($second, ActorRef::system());

    expect($user->reputationScore())->toBe(-2);
    Event::assertDispatchedTimes(ReporterBlockedEvent::class, 1);
    $this->assertAuditRecorded('reporter.blocked', $user->reputation()->firstOrFail());
});

it('rate-limits repeated reports within the configured window', function (): void {
    config()->set('casework.reporting.reputation.rate_limit', 2);
    config()->set('casework.reporting.reputation.rate_limit_window_minutes', 60);

    $post = Post::factory()->create();
    $user = Post::factory()->create();

    Casework::report($post)->by($user)->because(Reason::factory()->create())->file();
    Casework::report($post)->by($user)->because(Reason::factory()->create())->file();

    expect(fn () => Casework::report($post)->by($user)->because(Reason::factory()->create())->file())
        ->toThrow(ReportRateLimited::class);

    expect(Report::query()->count())->toBe(2);
});

it('does not rate-limit when rate_limit is null', function (): void {
    config()->set('casework.reporting.reputation.rate_limit', null);

    $post = Post::factory()->create();
    $user = Post::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        Casework::report($post)->by($user)->because(Reason::factory()->create())->file();
    }

    expect(Report::query()->count())->toBe(5);
});

it('ships a non-null rate_limit by default', function (): void {
    expect(config('casework.reporting.reputation.rate_limit'))->toBe(30)
        ->and(config('casework.reporting.allow_anonymous'))->toBeFalse();
});

it('excludes reports outside the rate-limit window', function (): void {
    config()->set('casework.reporting.reputation.rate_limit', 1);
    config()->set('casework.reporting.reputation.rate_limit_window_minutes', 60);

    $post = Post::factory()->create();
    $user = Post::factory()->create();

    Casework::report($post)->by($user)->because(Reason::factory()->create())->file();

    $this->travel(61)->minutes();

    $report = Casework::report($post)->by($user)->because(Reason::factory()->create())->file();

    expect($report->exists)->toBeTrue();
});

it('supports a custom reputation policy via config', function (): void {
    config()->set('casework.reporting.reputation.enabled', true);
    config()->set('casework.reporting.reputation.policy', RecordingReputationPolicy::class);

    $post = Post::factory()->create();
    $user = Post::factory()->create();
    $reason = Reason::factory()->create();

    $report = Casework::report($post)->by($user)->because($reason)->file();
    Casework::dismissReport($report, ActorRef::system());

    // The custom policy's distinctive -5, not the default -1.
    expect($user->reputationScore())->toBe(-5);
});

it('denies manual reputation adjustment to model actors by default', function (): void {
    $user = Post::factory()->create();
    $moderator = Post::factory()->create();

    Casework::adjustReputation($user, 3, 'manual', $moderator);
})->throws(AuthorizationException::class);

it('lets a moderator manually adjust reputation, fully audited', function (): void {
    Gate::before(fn () => true);
    Event::fake([ReporterReputationChanged::class]);

    $user = Post::factory()->create();
    $moderator = Post::factory()->create();

    $reputation = Casework::adjustReputation($user, 3, 'manual: helpful reporter', $moderator);

    expect($reputation->getAttribute('score'))->toBe(3)
        ->and($user->reputationScore())->toBe(3);

    $entry = $this->assertAuditRecorded('reporter.reputation_adjusted', $reputation, $moderator);
    expect($entry->payload)->toMatchArray(['delta' => 3, 'reason' => 'manual: helpful reporter']);

    Event::assertDispatched(ReporterReputationChanged::class, fn (ReporterReputationChanged $event) => $event->reason === 'manual: helpful reporter');
});

it('exposes the HasReporterReputation trait surface', function (): void {
    $user = Post::factory()->create();

    expect($user->reputationScore())->toBe(0)
        ->and($user->isBlockedFromReporting())->toBeFalse();

    ReporterReputation::factory()->forReporter($user)->withScore(-4)->create();

    config()->set('casework.reporting.reputation.block_threshold', -3);

    expect($user->reputationScore())->toBe(-4)
        ->and($user->isBlockedFromReporting())->toBeTrue();
});
