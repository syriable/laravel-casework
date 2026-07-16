<?php

declare(strict_types=1);

use Syriable\Casework\Facades\Casework;
use Syriable\Casework\Reporting\Models\Reason;
use Workbench\App\Models\Post;

it('creates a reason from a key with a derived label', function (): void {
    $this->artisan('casework:make-reason', ['key' => 'hate_speech'])
        ->expectsOutputToContain('Created active reason [hate_speech]')
        ->assertSuccessful();

    $reason = Reason::query()->where('key', 'hate_speech')->firstOrFail();

    expect($reason->getAttribute('label'))->toBe('Hate Speech')
        ->and($reason->getAttribute('category'))->toBeNull()
        ->and($reason->getAttribute('is_active'))->toBeTrue();
});

it('accepts an explicit label, category, and inactive flag', function (): void {
    $this->artisan('casework:make-reason', [
        'key' => 'spam',
        'label' => 'Unsolicited spam',
        '--category' => 'content',
        '--inactive' => true,
    ])->assertSuccessful();

    $reason = Reason::query()->where('key', 'spam')->firstOrFail();

    expect($reason->getAttribute('label'))->toBe('Unsolicited spam')
        ->and($reason->getAttribute('category'))->toBe('content')
        ->and($reason->getAttribute('is_active'))->toBeFalse();
});

it('is idempotent: re-running updates the existing reason in place', function (): void {
    $this->artisan('casework:make-reason', ['key' => 'spam', 'label' => 'First'])->assertSuccessful();
    $this->artisan('casework:make-reason', ['key' => 'spam', 'label' => 'Second'])
        ->expectsOutputToContain('Updated active reason [spam]')
        ->assertSuccessful();

    expect(Reason::query()->where('key', 'spam')->count())->toBe(1)
        ->and(Reason::query()->where('key', 'spam')->value('label'))->toBe('Second');
});

it('rejects a blank key', function (): void {
    $this->artisan('casework:make-reason', ['key' => '   '])
        ->expectsOutputToContain('non-empty reason key')
        ->assertFailed();

    expect(Reason::query()->count())->toBe(0);
});

it('creates a reason that can immediately back a filed report', function (): void {
    $this->artisan('casework:make-reason', ['key' => 'harassment'])->assertSuccessful();

    $report = Casework::report(Post::factory()->create())
        ->bySystem()
        ->because('harassment')
        ->file();

    expect($report->exists)->toBeTrue();
});
