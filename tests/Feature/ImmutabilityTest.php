<?php

declare(strict_types=1);

use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Audit\Models\AuditEntry;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Cases\Models\Evidence;
use Syriable\Casework\Cases\Models\Note;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Exceptions\ImmutableRecord;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Reporting\ReportState;

/**
 * ADR-0003 / invariants I-03 and I-07.
 */
dataset('immutable models', [
    'decision' => [fn () => Decision::factory()->create()],
    'note' => [fn () => Note::factory()->create()],
    'evidence' => [fn () => Evidence::factory()->create()],
    'audit entry' => [fn () => AuditEntry::factory()->create()],
]);

it('rejects updates to immutable records', function (Closure $make): void {
    $record = $make();

    $record->forceFill(['created_at' => now()->addDay()])->save();
})->with('immutable models')->throws(ImmutableRecord::class);

it('rejects deletes of immutable records', function (Closure $make): void {
    $make()->delete();
})->with('immutable models')->throws(ImmutableRecord::class);

dataset('stateful models', [
    'report' => [fn () => Report::factory()->create(), ReportState::Resolved->value],
    'case' => [fn () => CaseFile::factory()->create(), 'closed'],
    'restriction' => [fn () => Restriction::factory()->create(), 'lifted'],
    'appeal' => [fn () => Appeal::factory()->create(), 'upheld'],
]);

it('rejects direct state writes on persisted records', function (Closure $make, string $state): void {
    $record = $make();

    $record->state = $state;
})->with('stateful models')->throws(ImmutableRecord::class);

it('allows non-state updates on stateful records', function (): void {
    $report = Report::factory()->create();
    $case = CaseFile::factory()->create();

    $report->update(['case_id' => $case->id]);

    expect($report->refresh()->case_id)->toBe($case->id);
});

it('writes state through the transition path only', function (): void {
    // The workflow engine (M3) is the sole caller of this internal method.
    $report = Report::factory()->create();

    $report->writeStateThroughTransition(ReportState::UnderReview->value);

    expect($report->refresh()->state)->toBe(ReportState::UnderReview->value);
});
