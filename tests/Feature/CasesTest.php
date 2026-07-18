<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Syriable\Casework\Cases\Events\CaseAssigned;
use Syriable\Casework\Cases\Events\CaseEscalated;
use Syriable\Casework\Cases\Events\CaseOpened;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Contracts\ScopeResolver;
use Syriable\Casework\Exceptions\IncompleteBuilder;
use Syriable\Casework\Exceptions\InvalidConfiguration;
use Syriable\Casework\Exceptions\InvalidTransition;
use Syriable\Casework\Facades\Casework;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Tests\Support\AssertsAudit;
use Workbench\App\Models\Post;

uses(AssertsAudit::class);

it('opens a case through the Phase 5 builder with fixed subject', function (): void {
    Event::fake([CaseOpened::class]);

    $post = Post::factory()->create();
    $reports = [Report::factory()->about($post)->create()];

    $case = Casework::openCase($post)
        ->bySystem()
        ->withReports($reports)
        ->priority('high')
        ->open();

    // I-05: the primary subject is fixed at creation.
    expect($case->subject->is($post))->toBeTrue()
        ->and($case->getAttribute('state'))->toBe('open')
        ->and($case->getAttribute('priority'))->toBe('high')
        ->and($case->reports()->count())->toBe(1);

    $this->assertAuditRecorded('case.opened', $case);
    Event::assertDispatched(CaseOpened::class);

    expect($reports[0]->refresh()->getAttribute('state'))->toBe('attached_to_case');
});

it('defaults priority and validates unknown priorities', function (): void {
    $post = Post::factory()->create();

    $case = Casework::openCase($post)->bySystem()->open();

    expect($case->getAttribute('priority'))->toBe('normal');

    expect(fn () => Casework::openCase($post)->bySystem()->priority('critical')->open())
        ->toThrow(InvalidConfiguration::class);

    expect(fn () => Casework::openCase($post)->open())
        ->toThrow(IncompleteBuilder::class);
});

it('joins the existing open case under the threshold strategy', function (): void {
    // Shipped default: threshold = 3.
    $post = Post::factory()->create();
    $reason = Reason::factory()->create();
    config()->set('casework.reporting.allow_anonymous', true);
    config()->set('casework.reporting.allow_duplicates', true);

    $first = Casework::report($post)->anonymously()->because($reason)->file();
    $second = Casework::report($post)->anonymously()->because($reason)->file();

    expect(CaseFile::query()->count())->toBe(0)
        ->and($first->refresh()->case_id)->toBeNull();

    $third = Casework::report($post)->anonymously()->because($reason)->file();

    // Threshold hit: one case, all three open reports attached.
    $case = CaseFile::query()->sole();

    expect($case->subject->is($post))->toBeTrue()
        ->and($first->refresh()->case_id)->toBe($case->id)
        ->and($second->refresh()->case_id)->toBe($case->id)
        ->and($third->refresh()->case_id)->toBe($case->id);

    // A fourth report joins the existing open case immediately.
    $fourth = Casework::report($post)->anonymously()->because($reason)->file();

    expect($fourth->refresh()->case_id)->toBe($case->id)
        ->and(CaseFile::query()->count())->toBe(1);
});

it('opens a case per report under the always strategy and none under manual', function (): void {
    config()->set('casework.reporting.allow_anonymous', true);
    config()->set('casework.cases.strategy', 'always');

    $post = Post::factory()->create();
    $reason = Reason::factory()->create();

    $report = Casework::report($post)->anonymously()->because($reason)->file();

    expect($report->refresh()->getAttribute('state'))->toBe('attached_to_case')
        ->and(CaseFile::query()->count())->toBe(1);

    config()->set('casework.cases.strategy', 'manual');

    $other = Casework::report(Post::factory()->create())->anonymously()->because($reason)->file();

    expect($other->refresh()->case_id)->toBeNull()
        ->and(CaseFile::query()->count())->toBe(1);
});

it('assigns and reassigns with previous assignee in the event', function (): void {
    Event::fake([CaseAssigned::class]);

    $case = CaseFile::factory()->create();
    $first = Post::factory()->create();
    $second = Post::factory()->create();

    Casework::assignCase($case, $first, ActorRef::system());
    Casework::assignCase($case, $second, ActorRef::system());

    expect($case->refresh()->assignee->is($second))->toBeTrue();

    $this->assertAuditRecorded('case.assigned', $case);
    Event::assertDispatched(CaseAssigned::class, fn (CaseAssigned $event) => $event->assignee->is($second) && ($event->previousAssignee?->is($first) ?? false));
});

it('walks the case lifecycle with audits at each step', function (): void {
    $case = CaseFile::factory()->create();

    Casework::startInvestigation($case, ActorRef::system());
    expect($case->refresh()->getAttribute('state'))->toBe('under_investigation');
    $this->assertAuditRecorded('case.investigation_started', $case);

    Casework::submitForDecision($case, ActorRef::system());
    expect($case->refresh()->getAttribute('state'))->toBe('awaiting_decision');
    $this->assertAuditRecorded('case.awaiting_decision', $case);

    // close requires decided (M7 provides decide; simulate via engine path).
    expect(fn () => Casework::closeCase($case, ActorRef::system()))
        ->toThrow(InvalidTransition::class);

    $case->writeStateThroughTransition('decided');
    Casework::closeCase($case, ActorRef::system());

    expect($case->refresh()->getAttribute('state'))->toBe('closed');
    $this->assertAuditRecorded('case.closed', $case);
});

it('escalates priority and rejects unknown or unworkable escalations', function (): void {
    Event::fake([CaseEscalated::class]);

    $case = CaseFile::factory()->create();

    Casework::escalateCase($case, ActorRef::system(), 'urgent');

    expect($case->refresh()->getAttribute('priority'))->toBe('urgent');
    $this->assertAuditRecorded('case.escalated', $case);
    Event::assertDispatched(CaseEscalated::class, fn (CaseEscalated $event) => $event->fromPriority === 'normal' && $event->toPriority === 'urgent');

    expect(fn () => Casework::escalateCase($case, ActorRef::system(), 'apocalyptic'))
        ->toThrow(InvalidConfiguration::class);

    $case->writeStateThroughTransition('closed');

    expect(fn () => Casework::escalateCase($case, ActorRef::system(), 'high'))
        ->toThrow(InvalidTransition::class);
});

it('records notes and evidence with attribution', function (): void {
    $case = CaseFile::factory()->create();
    $author = Post::factory()->create();
    $referenced = Post::factory()->create();
    Gate::before(fn () => true);

    $note = Casework::note($case, $author, 'Subject has two prior cases.');
    $evidence = Casework::attachEvidence($case, ActorRef::system(), $referenced, ['note' => 'screenshot hash']);

    expect($note->author->is($author))->toBeTrue()
        ->and($note->case->is($case))->toBeTrue()
        ->and($evidence->subject->is($referenced))->toBeTrue()
        ->and($evidence->data)->toBe(['note' => 'screenshot hash']);

    $this->assertAuditRecorded('case.note_added', $case, $author);
    $this->assertAuditRecorded('case.evidence_attached', $case);

    expect(fn () => Casework::attachEvidence($case, ActorRef::system()))
        ->toThrow(IncompleteBuilder::class);
});

it('denies case moderation to model actors by default', function (): void {
    $moderator = Post::factory()->create();
    $case = CaseFile::factory()->create();

    Casework::assignCase($case, $moderator, $moderator);
})->throws(AuthorizationException::class);

it('enforces moderation scopes on top of policy grants', function (): void {
    Gate::before(fn () => true); // the app grants everything…

    // …but the resolver scopes this moderator to "listings" while the
    // case's subject belongs to "electronics".
    app()->instance(ScopeResolver::class, new class implements ScopeResolver
    {
        public function scopesFor(Model $actor): ?array
        {
            return ['listings'];
        }

        public function scopeOf(Model $subject): ?string
        {
            return 'electronics';
        }
    });

    $moderator = Post::factory()->create();
    $case = CaseFile::factory()->create();

    expect(fn () => Casework::assignCase($case, $moderator, $moderator))
        ->toThrow(AuthorizationException::class, 'scope');

    // System attribution is never scoped.
    Casework::assignCase($case, $moderator, ActorRef::system());

    expect($case->refresh()->assignee->is($moderator))->toBeTrue();
});
