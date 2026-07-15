<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Syriable\Casework\Audit\Models\AuditEntry;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Exceptions\DuplicateReport;
use Syriable\Casework\Exceptions\IncompleteBuilder;
use Syriable\Casework\Exceptions\InvalidTransition;
use Syriable\Casework\Exceptions\UnknownReason;
use Syriable\Casework\Facades\Casework;
use Syriable\Casework\Reporting\Actions\AttachReportToCase;
use Syriable\Casework\Reporting\Actions\ResolveReport;
use Syriable\Casework\Reporting\Events\ReportAttachedToCase;
use Syriable\Casework\Reporting\Events\ReportDismissed;
use Syriable\Casework\Reporting\Events\ReportFiled;
use Syriable\Casework\Reporting\Events\ReportResolved;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Origin;
use Syriable\Casework\Tests\Support\AssertsAudit;
use Workbench\App\Models\Post;

uses(AssertsAudit::class);

it('files a report through the Phase 5 builder chain', function (): void {
    Event::fake([ReportFiled::class]);

    $post = Post::factory()->create();
    $user = Post::factory()->create();
    Reason::factory()->create(['key' => 'spam']);

    // The exact quickstart chain from docs/api/public-api.md §2.
    $report = Casework::report($post)
        ->by($user)
        ->because('spam')
        ->comment('Links to a phishing site')
        ->withMetadata(['url' => 'https://example.test'])
        ->file();

    expect($report->exists)->toBeTrue()
        ->and($report->getAttribute('state'))->toBe('pending')
        ->and($report->refresh()->origin)->toBe(Origin::Model)
        ->and($report->reporter->is($user))->toBeTrue()
        ->and($report->subject->is($post))->toBeTrue()
        ->and($report->comment)->toBe('Links to a phishing site')
        ->and($report->metadata)->toBe(['url' => 'https://example.test']);

    $this->assertAuditRecorded('report.filed', $report, $user);
    Event::assertDispatched(ReportFiled::class, fn (ReportFiled $event) => $event->report->is($report));
});

it('files anonymous and system reports', function (): void {
    $post = Post::factory()->create();
    $reason = Reason::factory()->create();

    $anonymous = Casework::report($post)->anonymously()->because($reason)->file();
    $system = Casework::report($post)->bySystem()->because($reason)->file();

    expect($anonymous->refresh()->origin)->toBe(Origin::Anonymous)
        ->and($anonymous->reporter)->toBeNull()
        ->and($system->refresh()->origin)->toBe(Origin::System);
});

it('blocks anonymous reports when disabled', function (): void {
    config()->set('casework.reporting.allow_anonymous', false);

    Casework::report(Post::factory()->create())
        ->anonymously()
        ->because(Reason::factory()->create())
        ->file();
})->throws(AuthorizationException::class);

it('rejects unknown and inactive reasons', function (): void {
    $post = Post::factory()->create();

    expect(fn () => Casework::report($post)->bySystem()->because('nope')->file())
        ->toThrow(UnknownReason::class);

    $inactive = Reason::factory()->inactive()->create();

    expect(fn () => Casework::report($post)->bySystem()->because($inactive)->file())
        ->toThrow(UnknownReason::class);
});

it('enforces the duplicate guard for model reporters', function (): void {
    $post = Post::factory()->create();
    $user = Post::factory()->create();
    $reason = Reason::factory()->create();
    $other = Reason::factory()->create();

    $first = Casework::report($post)->by($user)->because($reason)->file();

    // I-02: same reporter + subject + reason while open.
    expect(fn () => Casework::report($post)->by($user)->because($reason)->file())
        ->toThrow(DuplicateReport::class);

    // Different reason: allowed.
    Casework::report($post)->by($user)->because($other)->file();

    // Terminal report: re-filing allowed.
    Casework::dismissReport($first, ActorRef::system());
    Casework::report($post)->by($user)->because($reason)->file();

    // Config off: duplicates allowed.
    config()->set('casework.reporting.allow_duplicates', true);
    Casework::report($post)->by($user)->because($reason)->file();

    expect(Report::query()->count())->toBe(4);
});

it('denies moderation abilities to model actors by default', function (): void {
    $moderator = Post::factory()->create();
    $report = Report::factory()->create();

    Casework::dismissReport($report, $moderator);
})->throws(AuthorizationException::class);

it('honors application policy overrides', function (): void {
    Event::fake([ReportDismissed::class]);
    Gate::before(fn () => true); // the app grants moderation

    $moderator = Post::factory()->create();
    $report = Report::factory()->create();

    Casework::dismissReport($report, $moderator);

    expect($report->refresh()->getAttribute('state'))->toBe('dismissed');
    $this->assertAuditRecorded('report.dismissed', $report, $moderator);
    Event::assertDispatched(ReportDismissed::class, fn (ReportDismissed $event) => $event->from === 'pending' && $event->to === 'dismissed');
});

it('moves a report under review with audit and event', function (): void {
    $report = Report::factory()->create();

    Casework::startReportReview($report, ActorRef::system());

    expect($report->refresh()->getAttribute('state'))->toBe('under_review');
    $this->assertAuditRecorded('report.review_started', $report);
});

it('attaches an open report to a same-subject open case', function (): void {
    Event::fake([ReportAttachedToCase::class]);

    $post = Post::factory()->create();
    $report = Report::factory()->about($post)->create();
    $case = CaseFile::factory()->about($post)->create();

    app(AttachReportToCase::class)->execute($report, $case, ActorRef::system());

    expect($report->refresh()->getAttribute('state'))->toBe('attached_to_case')
        ->and($report->case_id)->toBe($case->id);

    $this->assertAuditRecorded('report.attached_to_case', $report);
    Event::assertDispatched(ReportAttachedToCase::class);
});

it('refuses attachment across subjects or to closed-phase cases', function (): void {
    $post = Post::factory()->create();
    $report = Report::factory()->about($post)->create();

    $foreignCase = CaseFile::factory()->create();

    expect(fn () => app(AttachReportToCase::class)->execute($report, $foreignCase, ActorRef::system()))
        ->toThrow(InvalidTransition::class, 'different subject');

    $decidedCase = CaseFile::factory()->about($post)->create();
    $decidedCase->writeStateThroughTransition('decided');

    expect(fn () => app(AttachReportToCase::class)->execute($report, $decidedCase, ActorRef::system()))
        ->toThrow(InvalidTransition::class, 'no longer open');
});

it('refuses to dismiss an attached report directly', function (): void {
    $case = CaseFile::factory()->create();
    $report = Report::factory()->create(['case_id' => $case->id]);
    $report->writeStateThroughTransition('attached_to_case');

    Casework::dismissReport($report, ActorRef::system());
})->throws(InvalidTransition::class);

it('resolves a report and records the resolving decision', function (): void {
    Event::fake([ReportResolved::class]);

    $report = Report::factory()->create();
    $decision = Decision::factory()->create();

    app(ResolveReport::class)->execute($report, ActorRef::system(), $decision);

    expect($report->refresh()->getAttribute('state'))->toBe('resolved')
        ->and($report->decision->is($decision))->toBeTrue();

    $this->assertAuditRecorded('report.resolved', $report);
    Event::assertDispatched(ReportResolved::class, fn (ReportResolved $event) => $event->decision?->is($decision) ?? false);
});

it('validates the builder at its terminal call', function (): void {
    $post = Post::factory()->create();

    expect(fn () => Casework::report($post)->because('spam')->file())
        ->toThrow(IncompleteBuilder::class)
        ->and(fn () => Casework::report($post)->bySystem()->file())
        ->toThrow(IncompleteBuilder::class);

    // Builders are inert: nothing was persisted (ADR-0009).
    expect(Report::query()->count())->toBe(0);
});

it('dispatches nothing when the surrounding transaction rolls back', function (): void {
    // ADR-0015: an event means its transaction committed.
    $dispatched = [];
    Event::listen(ReportFiled::class, function () use (&$dispatched): void {
        $dispatched[] = true;
    });

    $post = Post::factory()->create();
    $reason = Reason::factory()->create();

    try {
        DB::transaction(function () use ($post, $reason): void {
            Casework::report($post)->bySystem()->because($reason)->file();

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($dispatched)->toBe([])
        ->and(Report::query()->count())->toBe(0)
        ->and(AuditEntry::query()->action('report.filed')->count())->toBe(0);
});

it('exposes the Reportable trait surface', function (): void {
    $post = Post::factory()->create();
    $reason = Reason::factory()->create();

    Casework::report($post)->bySystem()->because($reason)->file();
    $dismissed = Casework::report($post)->anonymously()->because($reason)->file();
    Casework::dismissReport($dismissed, ActorRef::system());
    CaseFile::factory()->about($post)->create();

    expect($post->reports()->count())->toBe(2)
        ->and($post->openReports()->count())->toBe(1)
        ->and($post->hasOpenReports())->toBeTrue()
        ->and($post->cases()->count())->toBe(1);
});
