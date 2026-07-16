<?php

declare(strict_types=1);

namespace Syriable\Casework\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\Support\ModelRegistry;

/**
 * Bootstrap a report reason from the console (reasons-as-data, FR-102).
 * Reasons are the one piece of reference data a report needs before it can
 * be filed, so shipping a first-class way to create them removes the main
 * first-run friction point (Phase 18 review).
 *
 * Idempotent by key: re-running with the same key updates the label,
 * category, and active flag, which makes it safe to call from a seeder or
 * a deployment script.
 */
final class MakeReasonCommand extends Command
{
    protected $signature = 'casework:make-reason
        {key : The unique machine key, e.g. spam}
        {label? : Human-readable label (defaults to a headline-cased key)}
        {--category= : Optional grouping category}
        {--inactive : Create the reason inactive (hidden from new reports)}';

    protected $description = 'Create or update a report reason (reasons-as-data)';

    public function handle(): int
    {
        $key = $this->argument('key');

        if (! is_string($key) || trim($key) === '') {
            $this->error('A non-empty reason key is required.');

            return self::FAILURE;
        }

        $key = trim($key);

        $label = $this->argument('label');
        $label = is_string($label) && trim($label) !== '' ? trim($label) : Str::headline($key);

        $category = $this->option('category');
        $category = is_string($category) && trim($category) !== '' ? trim($category) : null;

        $active = $this->option('inactive') !== true;

        /** @var class-string<Reason> $class */
        $class = ModelRegistry::classFor('reason');

        $existed = $class::query()->where('key', $key)->exists();

        $class::query()->updateOrCreate(
            ['key' => $key],
            ['label' => $label, 'category' => $category, 'is_active' => $active],
        );

        $verb = $existed ? 'Updated' : 'Created';
        $state = $active ? 'active' : 'inactive';

        $this->info("{$verb} {$state} reason [{$key}] — \"{$label}\".");

        return self::SUCCESS;
    }
}
