<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Syriable\Casework\Facades\Casework;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\Support\Outcome;
use Workbench\App\Models\Post;

/**
 * M10 acceptance: the README/quickstart walkthrough executes verbatim
 * against the workbench app (documentation standard §7 — every
 * example runnable). The workbench Post plays both User and content
 * roles: it implements Reportable + Restrictable with both traits.
 */
it('runs the README quickstart end to end', function (): void {
    Gate::before(fn () => true);   // quickstart: grant via policies in production

    $post = Post::factory()->create();
    $user = Post::factory()->create();
    $reportingUser = Post::factory()->create();
    $moderator = Post::factory()->create();
    $reviewer = Post::factory()->create();

    // 2. Seed a report reason — reasons are rows, not code.
    Reason::create(['key' => 'spam', 'label' => 'Spam or misleading', 'is_active' => true]);

    // 3. File a report.
    $report = Casework::report($post)
        ->by($reportingUser)
        ->because('spam')
        ->comment('Links to a phishing site')
        ->file();

    expect($report->exists)->toBeTrue()
        ->and($report->getAttribute('state'))->toBe('pending');

    // …or open a case explicitly.
    $case = Casework::openCase($post)->bySystem()->open();

    expect($case->getAttribute('state'))->toBe('open');

    // 4. Decide, with enforcement attached — atomically. The README
    // compresses this; the subject of the case is $post, so the
    // suspension lands there.
    $decision = Casework::decide($case)
        ->by($moderator)
        ->outcome(Outcome::UPHOLD)
        ->rationale('Repeated spam after warning.')
        ->withSuspension(days: 30)
        ->finalize();

    expect($case->refresh()->getAttribute('state'))->toBe('decided')
        ->and($decision->outcome)->toBe('uphold');

    // 5. Check enforcement — the hot path.
    expect($post->isSuspended())->toBeTrue()
        ->and(Casework::isRestricted($post, type: 'suspension'))->toBeTrue();

    // 6. Appeal, review, overturn.
    $appeal = Casework::appeal($decision)->by($user)->statement('This was a mistake.')->submit();

    Casework::startAppealReview($appeal, by: $reviewer);
    Casework::resolveAppeal($appeal)->by($reviewer)->overturn(rationale: 'Evidence insufficient');

    expect($appeal->refresh()->getAttribute('state'))->toBe('overturned')
        ->and($post->isSuspended())->toBeFalse()
        ->and($appeal->resultingDecision?->supersedes?->is($decision))->toBeTrue();
});
