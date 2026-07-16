<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Syriable\Casework\Database\Factories\ReporterReputationFactory;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;

/**
 * A reporter's accumulated report-quality signal (domain model E1a): one
 * row per reporter, adjusted by AdjustReporterReputation as reports the
 * reporter filed are dismissed or upheld. Only model-origin reporters
 * have a row — system and anonymous origins carry no reporter identity
 * to score.
 */
class ReporterReputation extends Model
{
    /** @use HasFactory<ReporterReputationFactory> */
    use HasFactory;

    use HasPrefixedTable;

    // Written only through AdjustReporterReputation (ADR-0020); never
    // bind request input to this model directly.
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
        ];
    }

    protected function tableSuffix(): string
    {
        return 'reporter_reputations';
    }

    /** @return MorphTo<Model, $this> */
    public function reporter(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Below the configured block threshold; null threshold means
     * tracking-only — nobody is ever blocked.
     */
    public function isBlocked(): bool
    {
        $threshold = config('casework.reporting.reputation.block_threshold');

        return is_int($threshold) && $this->getAttribute('score') <= $threshold;
    }
}
