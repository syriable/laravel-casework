<?php

declare(strict_types=1);

use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Audit\Models\AuditEntry;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Cases\Models\Evidence;
use Syriable\Casework\Cases\Models\Note;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Enforcement\Models\Warning;
use Syriable\Casework\Reporting\Models\Report;
use Workbench\App\Models\Post;
use Workbench\App\Models\UlidPost;

it('resolves polymorphic subjects for bigint and ULID keys', function (): void {
    // ADR-0010: string(36) morph columns accept both key strategies.
    $post = Post::factory()->create();
    $ulidPost = UlidPost::factory()->create();

    $bigintReport = Report::factory()->about($post)->create();
    $ulidReport = Report::factory()->about($ulidPost)->create();

    expect($bigintReport->subject)->toBeInstanceOf(Post::class)
        ->and($bigintReport->subject->is($post))->toBeTrue()
        ->and($ulidReport->subject)->toBeInstanceOf(UlidPost::class)
        ->and($ulidReport->subject->is($ulidPost))->toBeTrue();
});

it('wires the case aggregate relations', function (): void {
    $case = CaseFile::factory()->create();
    $reports = Report::factory()->count(2)->create(['case_id' => $case->id]);
    $note = Note::factory()->create(['case_id' => $case->id]);
    $evidence = Evidence::factory()->create(['case_id' => $case->id]);
    $decision = Decision::factory()->create(['case_id' => $case->id]);

    expect($case->reports)->toHaveCount(2)
        ->and($case->notes->first()->is($note))->toBeTrue()
        ->and($case->evidence->first()->is($evidence))->toBeTrue()
        ->and($case->decisions->first()->is($decision))->toBeTrue()
        ->and($reports->first()->case->is($case))->toBeTrue()
        ->and($note->case->is($case))->toBeTrue()
        ->and($decision->case->is($case))->toBeTrue();
});

it('wires report reason, reporter, and resolving decision', function (): void {
    $reporter = Post::factory()->create();
    $decision = Decision::factory()->create();
    $report = Report::factory()->by($reporter)->create(['decision_id' => $decision->id]);

    expect($report->reason->is($report->reason()->first()))->toBeTrue()
        ->and($report->reporter->is($reporter))->toBeTrue()
        ->and($report->decision->is($decision))->toBeTrue();
});

it('wires decision enforcement and supersession', function (): void {
    $original = Decision::factory()->create();
    $superseding = Decision::factory()->create(['supersedes_id' => $original->id]);
    $restriction = Restriction::factory()->create(['decision_id' => $superseding->id]);
    $warning = Warning::factory()->create(['decision_id' => $superseding->id]);

    expect($superseding->supersedes->is($original))->toBeTrue()
        ->and($superseding->restrictions->first()->is($restriction))->toBeTrue()
        ->and($superseding->warnings->first()->is($warning))->toBeTrue()
        ->and($restriction->decision->is($superseding))->toBeTrue()
        ->and($warning->decision->is($superseding))->toBeTrue();
});

it('wires restriction supersession and appeal targets', function (): void {
    $replacement = Restriction::factory()->create();
    $restriction = Restriction::factory()->create(['superseded_by_id' => $replacement->id]);
    $appellant = Post::factory()->create();
    $appeal = Appeal::factory()->against($restriction)->by($appellant)->create();

    expect($restriction->supersededBy->is($replacement))->toBeTrue()
        ->and($appeal->appealed->is($restriction))->toBeTrue()
        ->and($appeal->appellant->is($appellant))->toBeTrue();
});

it('wires audit actor and auditable morphs', function (): void {
    $actor = Post::factory()->create();
    $case = CaseFile::factory()->create();
    $entry = AuditEntry::factory()->on($case)->by($actor)->create();

    expect($entry->auditable->is($case))->toBeTrue()
        ->and($entry->actor->is($actor))->toBeTrue();

    $systemEntry = AuditEntry::factory()->create();

    expect($systemEntry->actor)->toBeNull();
});
