<?php

declare(strict_types=1);

use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Audit\Models\AuditEntry;
use Syriable\Casework\Cases\CaseState;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Enforcement\Models\Warning;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Reporting\ReportState;
use Workbench\App\Models\Post;

it('filters reports by state, subject, reporter, origin, and reason', function (): void {
    $post = Post::factory()->create();
    $reporter = Post::factory()->create();
    $reason = Reason::factory()->create(['key' => 'spam']);

    $pending = Report::factory()->about($post)->by($reporter)->create(['reason_id' => $reason->id]);
    Report::factory()->fromSystem()->create();
    $anonymous = Report::factory()->about($post)->create();

    expect(Report::query()->pending()->count())->toBe(3)
        ->and(Report::query()->whereState(ReportState::Pending)->count())->toBe(3)
        ->and(Report::query()->forSubject($post)->count())->toBe(2)
        ->and(Report::query()->byReporter($reporter)->pluck('id')->all())->toBe([$pending->id])
        ->and(Report::query()->fromSystem()->count())->toBe(1)
        ->and(Report::query()->anonymous()->pluck('id')->all())->toBe([$anonymous->id])
        ->and(Report::query()->withReason('spam')->pluck('id')->all())->toBe([$pending->id])
        ->and(Report::query()->withReason($reason)->pluck('id')->all())->toBe([$pending->id])
        ->and(Report::query()->open()->count())->toBe(3);
});

it('filters cases by state, assignee, priority, and subject', function (): void {
    $post = Post::factory()->create();
    $moderator = Post::factory()->create();

    $mine = CaseFile::factory()->about($post)->assignedTo($moderator)->create(['priority' => 'high']);
    CaseFile::factory()->create();

    expect(CaseFile::query()->open()->count())->toBe(2)
        ->and(CaseFile::query()->whereState(CaseState::Open)->count())->toBe(2)
        ->and(CaseFile::query()->decided()->count())->toBe(0)
        ->and(CaseFile::query()->assignedTo($moderator)->pluck('id')->all())->toBe([$mine->id])
        ->and(CaseFile::query()->wherePriority('high')->pluck('id')->all())->toBe([$mine->id])
        ->and(CaseFile::query()->forSubject($post)->pluck('id')->all())->toBe([$mine->id]);
});

it('applies the real-time expiry rule to restriction activity', function (): void {
    // I-09: a stored `active` state past expires_at evaluates inactive
    // everywhere, regardless of the expiry command's cadence.
    $subject = Post::factory()->create();

    $permanent = Restriction::factory()->about($subject)->create();
    $future = Restriction::factory()->about($subject)->expiringAt(now()->addWeek())->create();
    $stale = Restriction::factory()->about($subject)->stalePastExpiry()->create();

    expect(Restriction::query()->active()->pluck('id')->all())
        ->toBe([$permanent->id, $future->id])
        ->and($permanent->isActive())->toBeTrue()
        ->and($permanent->isPermanent())->toBeTrue()
        ->and($future->isActive())->toBeTrue()
        ->and($stale->isActive())->toBeFalse()
        ->and($stale->getAttribute('state'))->toBe('active');

    expect(Restriction::query()->expiringBefore(now())->pluck('id')->all())->toBe([$stale->id])
        ->and(Restriction::query()->forSubject($subject)->count())->toBe(3)
        ->and(Restriction::query()->ofType('suspension')->count())->toBe(3);
});

it('filters restrictions by scope', function (): void {
    Restriction::factory()->inScope('listings')->create();
    Restriction::factory()->create();

    expect(Restriction::query()->inScope('listings')->count())->toBe(1);
});

it('filters warnings by activity and subject', function (): void {
    $subject = Post::factory()->create();

    $active = Warning::factory()->about($subject)->create();
    $expired = Warning::factory()->about($subject)->expired()->create();

    expect(Warning::query()->forSubject($subject)->count())->toBe(2)
        ->and(Warning::query()->active()->pluck('id')->all())->toBe([$active->id])
        ->and($active->isActive())->toBeTrue()
        ->and($expired->isActive())->toBeFalse();
});

it('filters appeals by state, target, and appellant', function (): void {
    $appellant = Post::factory()->create();
    $restriction = Restriction::factory()->create();

    $appeal = Appeal::factory()->against($restriction)->by($appellant)->create();
    Appeal::factory()->create();

    expect(Appeal::query()->submitted()->count())->toBe(2)
        ->and(Appeal::query()->whereState('submitted')->count())->toBe(2)
        ->and(Appeal::query()->forTarget($restriction)->pluck('id')->all())->toBe([$appeal->id])
        ->and(Appeal::query()->byAppellant($appellant)->pluck('id')->all())->toBe([$appeal->id]);
});

it('filters audit entries by auditable, actor, action, and period', function (): void {
    $actor = Post::factory()->create();
    $case = CaseFile::factory()->create();

    $entry = AuditEntry::factory()->on($case)->by($actor)->action('case.opened')->create();
    AuditEntry::factory()->action('report.filed')->create();

    expect(AuditEntry::query()->forAuditable($case)->pluck('id')->all())->toBe([$entry->id])
        ->and(AuditEntry::query()->byActor($actor)->pluck('id')->all())->toBe([$entry->id])
        ->and(AuditEntry::query()->action('case.opened')->pluck('id')->all())->toBe([$entry->id])
        ->and(AuditEntry::query()->between(now()->subMinute(), now()->addMinute())->count())->toBe(2);
});

it('manages reason activation without touching history', function (): void {
    $reason = Reason::factory()->create();
    $report = Report::factory()->create(['reason_id' => $reason->id]);

    $reason->deactivate();

    // FR-155 / I-14: the historical report keeps referencing the
    // deactivated reason.
    expect(Reason::query()->active()->count())->toBe(0)
        ->and($report->refresh()->reason->is($reason))->toBeTrue()
        ->and($reason->refresh()->is_active)->toBeFalse();

    $reason->activate();

    expect($reason->refresh()->is_active)->toBeTrue();
});
