<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Syriable\Casework\Database\Factories\ReportFactory;
use Syriable\Casework\Reporting\ReportState;
use Syriable\Casework\Support\Concerns\GuardsStateColumn;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;
use Syriable\Casework\Support\ModelRegistry;
use Syriable\Casework\Support\Origin;

/**
 * A reporter's claim about a subject (domain model E1).
 */
class Report extends Model
{
    use GuardsStateColumn;

    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    use HasPrefixedTable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'origin' => Origin::class,
            'metadata' => 'array',
        ];
    }

    protected function tableSuffix(): string
    {
        return 'reports';
    }

    /**
     * The reported model (ADR-0001).
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Present only when origin is model (ADR-0002, I-01).
     *
     * @return MorphTo<Model, $this>
     */
    public function reporter(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Model, $this> */
    public function reason(): BelongsTo
    {
        return $this->belongsTo(ModelRegistry::classFor('reason'), 'reason_id');
    }

    /** @return BelongsTo<Model, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ModelRegistry::classFor('case'), 'case_id');
    }

    /**
     * Set when the report was resolved by a decision (FR-305).
     *
     * @return BelongsTo<Model, $this>
     */
    public function decision(): BelongsTo
    {
        return $this->belongsTo(ModelRegistry::classFor('decision'), 'decision_id');
    }

    /** @param Builder<static> $query */
    public function scopeWhereState(Builder $query, ReportState|string $state): void
    {
        $query->where('state', $state instanceof ReportState ? $state->value : $state);
    }

    /** @param Builder<static> $query */
    public function scopePending(Builder $query): void
    {
        $query->where('state', ReportState::Pending->value);
    }

    /**
     * Pending, under review, or attached — anything not yet terminal.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNotIn('state', [ReportState::Resolved->value, ReportState::Dismissed->value]);
    }

    /** @param Builder<static> $query */
    public function scopeForSubject(Builder $query, Model $subject): void
    {
        $query->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    /** @param Builder<static> $query */
    public function scopeByReporter(Builder $query, Model $reporter): void
    {
        $query->where('reporter_type', $reporter->getMorphClass())
            ->where('reporter_id', $reporter->getKey());
    }

    /** @param Builder<static> $query */
    public function scopeFromSystem(Builder $query): void
    {
        $query->where('origin', Origin::System->value);
    }

    /** @param Builder<static> $query */
    public function scopeAnonymous(Builder $query): void
    {
        $query->where('origin', Origin::Anonymous->value);
    }

    /** @param Builder<static> $query */
    public function scopeWithReason(Builder $query, Model|string $reason): void
    {
        if ($reason instanceof Model) {
            $query->where('reason_id', $reason->getKey());

            return;
        }

        $query->whereHas('reason', function (Builder $reasonQuery) use ($reason): void {
            $reasonQuery->where('key', $reason);
        });
    }
}
