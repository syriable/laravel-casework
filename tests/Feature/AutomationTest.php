<?php

declare(strict_types=1);

use Syriable\Casework\Audit\Models\AuditEntry;
use Syriable\Casework\Cases\Events\CaseOpened;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Exceptions\CaseworkException;
use Syriable\Casework\Facades\Casework;
use Syriable\Casework\Reporting\Events\ReportFiled;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Support\Origin;
use Syriable\Casework\Tests\Support\AssertsAudit;
use Syriable\Casework\Tests\Support\AutoDismissStage;
use Syriable\Casework\Tests\Support\EscalatesTriageStage;
use Syriable\Casework\Tests\Support\ForceCaseStage;
use Syriable\Casework\Tests\Support\RecordingNotifier;
use Syriable\Casework\Tests\Support\RefusesIntakeStage;
use Syriable\Casework\Tests\Support\SecondNotifier;
use Syriable\Casework\Tests\Support\SelectiveNotifier;
use Syriable\Casework\Tests\Support\ShortCircuitStage;
use Syriable\Casework\Tests\Support\SuppressCaseStage;
use Syriable\Casework\Tests\Support\TagsMetadataStage;
use Workbench\App\Models\Post;

uses(AssertsAudit::class);

beforeEach(function (): void {
    RecordingNotifier::$received = [];
});

function fileAutomatedReport(?string $comment = null): Report
{
    if (Reason::query()->where('key', 'spam')->doesntExist()) {
        Reason::factory()->create(['key' => 'spam']);
    }

    $pending = Casework::report(Post::factory()->create())
        ->bySystem()
        ->because('spam')
        ->withMetadata(['url' => 'https://example.test']);

    if ($comment !== null) {
        $pending = $pending->comment($comment);
    }

    return $pending->file();
}

it('hands every event to configured notifiers in listed order (X8)', function (): void {
    config()->set('casework.notifiers', [RecordingNotifier::class, SecondNotifier::class]);
    config()->set('casework.cases.strategy', 'always');

    fileAutomatedReport();

    // One report → filed, case opened, attached: every event reaches
    // both notifiers, first-listed first.
    expect(RecordingNotifier::$received)->toContain(RecordingNotifier::class.':'.ReportFiled::class)
        ->and(RecordingNotifier::$received)->toContain(RecordingNotifier::class.':'.CaseOpened::class);

    foreach ([ReportFiled::class, CaseOpened::class] as $event) {
        $pair = array_values(array_filter(
            RecordingNotifier::$received,
            fn (string $entry) => str_ends_with($entry, $event),
        ));

        expect($pair)->toBe([
            RecordingNotifier::class.':'.$event,
            SecondNotifier::class.':'.$event,
        ]);
    }
});

it('lets intake stages enrich metadata before persistence (X9)', function (): void {
    config()->set('casework.pipelines.intake', [TagsMetadataStage::class]);

    $report = fileAutomatedReport();

    expect($report->refresh()->metadata)->toBe([
        'url' => 'https://example.test',
        'spam_score' => 0.97,
    ]);
});

it('short-circuits the intake pipeline when a stage returns early', function (): void {
    config()->set('casework.pipelines.intake', [ShortCircuitStage::class, TagsMetadataStage::class]);

    $report = fileAutomatedReport();

    expect($report->refresh()->metadata)->toBe([
        'url' => 'https://example.test',
        'short_circuited' => true,
    ]);
});

it('persists auto-dismissed reports as dismissed with System attribution', function (): void {
    config()->set('casework.pipelines.intake', [AutoDismissStage::class]);
    config()->set('casework.cases.strategy', 'always');

    $report = fileAutomatedReport();

    expect($report->refresh()->getAttribute('state'))->toBe('dismissed')
        ->and(CaseFile::query()->count())->toBe(0);

    // Filed and dismissed both land in the audit trail.
    $this->assertAuditRecorded('report.filed', $report);
    $dismissal = $this->assertAuditRecorded('report.dismissed', $report);

    expect($dismissal->origin)->toBe(Origin::System);
});

it('lets stages force or suppress case creation over the strategy', function (): void {
    config()->set('casework.cases.strategy', 'always');
    config()->set('casework.pipelines.intake', [SuppressCaseStage::class]);

    $suppressed = fileAutomatedReport();

    expect(CaseFile::query()->count())->toBe(0)
        ->and($suppressed->refresh()->getAttribute('case_id'))->toBeNull();

    config()->set('casework.cases.strategy', 'manual');
    config()->set('casework.pipelines.intake', [ForceCaseStage::class]);

    $forced = fileAutomatedReport();

    expect(CaseFile::query()->count())->toBe(1)
        ->and($forced->refresh()->getAttribute('case_id'))->not->toBeNull();
});

it('refuses intake entirely when a stage throws — nothing persists', function (): void {
    config()->set('casework.pipelines.intake', [RefusesIntakeStage::class]);

    try {
        fileAutomatedReport();
        $this->fail('Expected the intake stage to refuse.');
    } catch (RuntimeException $exception) {
        expect($exception)->toBeInstanceOf(CaseworkException::class)
            ->and($exception->getMessage())->toBe('refused at intake');
    }

    expect(Report::query()->count())->toBe(0)
        ->and(AuditEntry::query()->count())->toBe(0);
});

it('runs triage stages after CaseOpened with full pipeline treatment (X10)', function (): void {
    config()->set('casework.cases.strategy', 'always');
    config()->set('casework.pipelines.triage', [EscalatesTriageStage::class]);

    $report = fileAutomatedReport();

    /** @var CaseFile $case */
    $case = CaseFile::query()->firstOrFail();

    expect($case->getAttribute('priority'))->toBe('urgent')
        ->and($report->refresh()->getAttribute('case_id'))->toBe($case->getKey());

    // The automated escalation is audited exactly like a human one.
    $entry = $this->assertAuditRecorded('case.escalated', $case);

    expect($entry->origin)->toBe(Origin::System);
});

it('skips notifiers that did not subscribe to an event (X8 filtering)', function (): void {
    config()->set('casework.notifiers', [SelectiveNotifier::class]);
    config()->set('casework.cases.strategy', 'always');

    SelectiveNotifier::$received = [];
    SelectiveNotifier::$instantiations = 0;

    // One filing emits ReportFiled, CaseOpened, and ReportAttachedToCase;
    // the notifier subscribes to ReportFiled only.
    fileAutomatedReport();

    expect(SelectiveNotifier::$received)->toBe([ReportFiled::class])
        // Built once, for its single subscribed event — never for the
        // events it filtered out.
        ->and(SelectiveNotifier::$instantiations)->toBe(1);
});

it('still delivers every event to notifiers that do not filter', function (): void {
    config()->set('casework.notifiers', [RecordingNotifier::class]);
    config()->set('casework.cases.strategy', 'always');

    RecordingNotifier::$received = [];

    fileAutomatedReport();

    // A plain Notifier keeps the original fan-out (BC).
    expect(RecordingNotifier::$received)->toContain(RecordingNotifier::class.':'.ReportFiled::class)
        ->and(RecordingNotifier::$received)->toContain(RecordingNotifier::class.':'.CaseOpened::class);
});
