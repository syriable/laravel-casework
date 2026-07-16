<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Syriable\Casework\Database\Factories\DecisionFactory;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;
use Syriable\Casework\Support\Concerns\PreventsMutation;
use Syriable\Casework\Support\ModelRegistry;
use Syriable\Casework\Support\Origin;

/**
 * An immutable case resolution (domain model E6, FR-304). Amendments and
 * reversals are new decisions referencing the original.
 */
class Decision extends Model
{
    /** @use HasFactory<DecisionFactory> */
    use HasFactory;

    use HasPrefixedTable;
    use PreventsMutation;

    public const UPDATED_AT = null;

    // Written only through the package's audited actions; never bind
    // request input to these models directly (ADR-0018). Records are
    // immutable after creation (PreventsMutation, I-07).
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'origin' => Origin::class,
        ];
    }

    protected function tableSuffix(): string
    {
        return 'decisions';
    }

    /** @return BelongsTo<Model, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ModelRegistry::classFor('case'), 'case_id');
    }

    /** @return MorphTo<Model, $this> */
    public function decider(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Model, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(ModelRegistry::classFor('decision'), 'supersedes_id');
    }

    /**
     * Enforcement applied with this decision (FR-303).
     *
     * @return HasMany<Model, $this>
     */
    public function restrictions(): HasMany
    {
        return $this->hasMany(ModelRegistry::classFor('restriction'), 'decision_id');
    }

    /** @return HasMany<Model, $this> */
    public function warnings(): HasMany
    {
        return $this->hasMany(ModelRegistry::classFor('warning'), 'decision_id');
    }

    /** @param Builder<static> $query */
    public function scopeWithOutcome(Builder $query, string $outcome): void
    {
        $query->where('outcome', $outcome);
    }
}
