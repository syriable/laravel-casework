<?php

declare(strict_types=1);

namespace Syriable\Casework\Appeals\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Syriable\Casework\Appeals\AppealState;
use Syriable\Casework\Contracts\Stateful;
use Syriable\Casework\Database\Factories\AppealFactory;
use Syriable\Casework\Support\Concerns\GuardsStateColumn;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;
use Syriable\Casework\Support\ModelRegistry;
use Syriable\Casework\Support\Origin;

/**
 * A request to re-examine a decision or restriction (domain model E9).
 */
class Appeal extends Model implements Stateful
{
    use GuardsStateColumn;

    /** @use HasFactory<AppealFactory> */
    use HasFactory;

    use HasPrefixedTable;

    // Written only through the package's audited actions; never bind
    // request input to these models directly (ADR-0018). The state
    // column is separately immutable (GuardsStateColumn, I-03).
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'origin' => Origin::class,
        ];
    }

    protected function tableSuffix(): string
    {
        return 'appeals';
    }

    /**
     * A Decision or a Restriction (FR-501).
     *
     * @return MorphTo<Model, $this>
     */
    public function appealed(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function appellant(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function reviewer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Set on overturn (FR-504).
     *
     * @return BelongsTo<Model, $this>
     */
    public function resultingDecision(): BelongsTo
    {
        return $this->belongsTo(ModelRegistry::classFor('decision'), 'resulting_decision_id');
    }

    /** @param Builder<static> $query */
    public function scopeWhereState(Builder $query, AppealState|string $state): void
    {
        $query->where('state', $state instanceof AppealState ? $state->value : $state);
    }

    /** @param Builder<static> $query */
    public function scopeSubmitted(Builder $query): void
    {
        $query->where('state', AppealState::Submitted->value);
    }

    /** @param Builder<static> $query */
    public function scopeForTarget(Builder $query, Model $target): void
    {
        $query->where('appealed_type', $target->getMorphClass())
            ->where('appealed_id', $target->getKey());
    }

    /** @param Builder<static> $query */
    public function scopeByAppellant(Builder $query, Model $appellant): void
    {
        $query->where('appellant_type', $appellant->getMorphClass())
            ->where('appellant_id', $appellant->getKey());
    }
}
