<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Reporting\Events\ReporterBlocked;
use Syriable\Casework\Reporting\Events\ReporterReputationChanged;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Reporting\Models\ReporterReputation;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;
use Syriable\Casework\Support\ModelRegistry;

/**
 * The single reputation write-path (ADR-0020, extension point X14):
 * every score change — automatic or manual — goes through here, so
 * there is exactly one place that writes the audit entry and dispatches
 * the change events. System attribution (the automatic listener) always
 * passes authorization; a model actor needs a granted 'adjust' ability.
 */
class AdjustReporterReputation
{
    use AuthorizesActions;

    public function __construct(
        private readonly Recorder $recorder,
    ) {}

    public function execute(Model $reporter, int $delta, string $reason, ActorRef $by, ?Report $report = null): ReporterReputation
    {
        $this->authorize($by, 'adjust', ModelRegistry::classFor('reporter_reputation'));

        return DB::transaction(function () use ($reporter, $delta, $reason, $by, $report): ReporterReputation {
            $reputation = $this->resolveReputation($reporter);
            $before = $reputation->score;
            $wasBlocked = $reputation->isBlocked();

            // Atomic UPDATE ... SET score = score + ? — race-safe under
            // concurrent adjustments for the same reporter, the same
            // discipline as the post-1.0 concurrency hardening elsewhere.
            if ($delta !== 0) {
                $reputation->increment('score', $delta);
            }

            $reputation->refresh();
            $after = $reputation->score;

            $this->recorder->record($by, 'reporter.reputation_adjusted', $reputation, [
                'reporter_type' => $reporter->getMorphClass(),
                'reporter_id' => $reporter->getKey(),
                'delta' => $delta,
                'before' => $before,
                'after' => $after,
                'reason' => $reason,
                'report_id' => $report?->getKey(),
            ]);

            event(new ReporterReputationChanged($reputation, $reporter, $before, $after, $reason, $report, $by));

            if (! $wasBlocked && $reputation->isBlocked()) {
                $this->recorder->record($by, 'reporter.blocked', $reputation, [
                    'score' => $after,
                ]);

                event(new ReporterBlocked($reputation, $reporter, $after, $by));
            }

            return $reputation;
        });
    }

    /**
     * firstOrCreate races when two adjustments for a brand-new reporter
     * land concurrently — the loser's insert hits the unique index and
     * throws; re-fetching the winner's row keeps this race-safe.
     */
    private function resolveReputation(Model $reporter): ReporterReputation
    {
        $class = ModelRegistry::classFor('reporter_reputation');

        $attributes = [
            'reporter_type' => $reporter->getMorphClass(),
            'reporter_id' => $reporter->getKey(),
        ];

        try {
            /** @var ReporterReputation */
            return $class::query()->firstOrCreate($attributes, ['score' => 0]);
        } catch (QueryException $exception) {
            /** @var ReporterReputation|null $existing */
            $existing = $class::query()->where($attributes)->first();

            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }
}
