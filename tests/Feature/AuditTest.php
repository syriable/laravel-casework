<?php

declare(strict_types=1);

use Syriable\Casework\Audit\Models\AuditEntry;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Origin;
use Syriable\Casework\Tests\Support\AssertsAudit;
use Workbench\App\Models\Post;

uses(AssertsAudit::class);

it('records model-attributed entries with payload', function (): void {
    $moderator = Post::factory()->create();
    $case = CaseFile::factory()->create();

    $entry = (new Recorder)->record(
        ActorRef::model($moderator),
        'case.opened',
        $case,
        ['priority' => 'normal'],
    );

    expect($entry->refresh()->origin)->toBe(Origin::Model)
        ->and($entry->actor->is($moderator))->toBeTrue()
        ->and($entry->auditable->is($case))->toBeTrue()
        ->and($entry->action)->toBe('case.opened')
        ->and($entry->payload)->toBe(['priority' => 'normal'])
        ->and($entry->getAttribute('updated_at'))->toBeNull();

    $this->assertAuditRecorded('case.opened', $case, $moderator);
});

it('records system entries with a null payload column', function (): void {
    $case = CaseFile::factory()->create();

    $entry = (new Recorder)->record(ActorRef::system(), 'case.closed', $case);

    expect($entry->refresh()->origin)->toBe(Origin::System)
        ->and($entry->actor)->toBeNull()
        ->and($entry->getAttributes()['payload'])->toBeNull();

    $this->assertNoAuditRecorded('case.opened', $case);
});

it('refuses to prune without an opt-in retention', function (): void {
    AuditEntry::factory()->create(['created_at' => now()->subYears(2)]);

    // Default config: audit.prune_after_days is null.
    $this->artisan('casework:prune-audit')
        ->expectsOutputToContain('opt-in')
        ->assertFailed();

    expect(AuditEntry::query()->count())->toBe(1);
});

it('prunes by the configured retention', function (): void {
    config()->set('casework.audit.prune_after_days', 30);

    $old = AuditEntry::factory()->create(['created_at' => now()->subDays(45)]);
    $recent = AuditEntry::factory()->create(['created_at' => now()->subDays(5)]);

    $this->artisan('casework:prune-audit')->assertSuccessful();

    expect(AuditEntry::query()->pluck('id')->all())->toBe([$recent->id]);
});

it('prunes by an explicit --before overriding the config', function (): void {
    config()->set('casework.audit.prune_after_days', 3650);

    $ancient = AuditEntry::factory()->create(['created_at' => now()->subYears(2)]);
    $kept = AuditEntry::factory()->create(['created_at' => now()->subDays(45)]);

    $this->artisan('casework:prune-audit', ['--before' => now()->subYear()->toDateString()])
        ->assertSuccessful();

    expect(AuditEntry::query()->pluck('id')->all())->toBe([$kept->id]);
});
