<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Exceptions\AppealLimitReached;
use Syriable\Casework\Exceptions\DuplicateReport;
use Syriable\Casework\Facades\Casework;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Support\ActorRef;
use Workbench\App\Models\Post;

/**
 * Race-safety regression tests (Phase 18 review). The application-level
 * pre-checks close the common case; these assert the layer beneath them —
 * the report dedupe unique index and the appeal-target row lock — which is
 * what actually holds when two writers slip past a pre-check at once.
 */
it('assigns a dedupe key only to attributable, dedupe-guarded reports', function (): void {
    $post = Post::factory()->create();
    $user = Post::factory()->create();
    $reason = Reason::factory()->create();

    $model = Casework::report($post)->by($user)->because($reason)->file();
    $system = Casework::report($post)->bySystem()->because($reason)->file();
    $anonymous = Casework::report($post)->anonymously()->because($reason)->file();

    expect($model->getAttribute('dedupe_key'))->not->toBeNull()
        // System and anonymous origins carry no comparable identity,
        // so they are never constrained by the index.
        ->and($system->getAttribute('dedupe_key'))->toBeNull()
        ->and($anonymous->getAttribute('dedupe_key'))->toBeNull();

    // With duplicates explicitly allowed, the key is dropped so the index
    // never constrains them.
    config()->set('casework.reporting.allow_duplicates', true);

    $allowed = Casework::report($post)->by($user)->because($reason)->file();

    expect($allowed->getAttribute('dedupe_key'))->toBeNull();
});

it('lets the database reject a second open report for the same tuple', function (): void {
    $post = Post::factory()->create();
    $user = Post::factory()->create();
    $reason = Reason::factory()->create();

    $first = Casework::report($post)->by($user)->because($reason)->file();
    $key = $first->getAttribute('dedupe_key');

    // A writer that raced past the pre-check would try to insert the same
    // fingerprint; the unique index rejects it at the database layer. The
    // insert is wrapped in a transaction so the violation is savepoint-
    // scoped — mirroring the real racing request (FileReport transacts)
    // and keeping PostgreSQL's aborted-transaction rule out of the test's
    // outer wrapper.
    expect(fn () => DB::transaction(fn () => Report::factory()->create([
        'subject_type' => $post->getMorphClass(),
        'subject_id' => $post->getKey(),
        'reporter_type' => $user->getMorphClass(),
        'reporter_id' => $user->getKey(),
        'reason_id' => $reason->getKey(),
        'dedupe_key' => $key,
    ])))->toThrow(QueryException::class);
});

it('translates a raced unique violation into DuplicateReport', function (): void {
    $post = Post::factory()->create();
    $user = Post::factory()->create();
    $reason = Reason::factory()->create();

    // Derive the fingerprint from a real filing, then free the pre-check's
    // open-state view of it.
    $first = Casework::report($post)->by($user)->because($reason)->file();
    $key = $first->getAttribute('dedupe_key');
    Casework::dismissReport($first, ActorRef::system());

    // Reintroduce the fingerprint on a row the open-state pre-check does
    // not see, standing in for a concurrent insert that has not yet become
    // visible. The next filing passes the pre-check but hits the index.
    Report::factory()->create([
        'subject_type' => $post->getMorphClass(),
        'subject_id' => $post->getKey(),
        'reporter_type' => $user->getMorphClass(),
        'reporter_id' => $user->getKey(),
        'reason_id' => $reason->getKey(),
        'state' => 'dismissed',
        'dedupe_key' => $key,
    ]);

    expect(fn () => Casework::report($post)->by($user)->because($reason)->file())
        ->toThrow(DuplicateReport::class);
});

it('frees the dedupe key when a report is resolved or dismissed', function (): void {
    $post = Post::factory()->create();
    $user = Post::factory()->create();
    $reason = Reason::factory()->create();

    $dismissed = Casework::report($post)->by($user)->because($reason)->file();
    Casework::dismissReport($dismissed, ActorRef::system());

    expect($dismissed->refresh()->getAttribute('dedupe_key'))->toBeNull();

    // The slot is free, so the same reporter can file the same tuple again.
    $refiled = Casework::report($post)->by($user)->because($reason)->file();

    expect($refiled->exists)->toBeTrue()
        ->and($refiled->getAttribute('dedupe_key'))->not->toBeNull();
});

it('counts the appeal limit against committed rows only', function (): void {
    // guardLimit now runs inside the transaction behind a lock on the
    // target; an appeal whose surrounding transaction rolls back must not
    // consume the allowance.
    $restriction = Restriction::factory()->create();

    try {
        DB::transaction(function () use ($restriction): void {
            Casework::appeal($restriction)->bySystem()->submit();

            throw new RuntimeException('abort');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(Appeal::query()->count())->toBe(0);

    // The default limit of one is still available afterwards.
    $appeal = Casework::appeal($restriction)->bySystem()->submit();

    expect($appeal->exists)->toBeTrue()
        ->and(fn () => Casework::appeal($restriction)->bySystem()->submit())
        ->toThrow(AppealLimitReached::class);
});
