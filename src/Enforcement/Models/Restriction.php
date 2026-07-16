<?php

declare(strict_types=1);

namespace Syriable\Casework\Enforcement\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Syriable\Casework\Contracts\Stateful;
use Syriable\Casework\Database\Factories\RestrictionFactory;
use Syriable\Casework\Enforcement\RestrictionState;
use Syriable\Casework\Support\Concerns\ExpiresInRealTime;
use Syriable\Casework\Support\Concerns\GuardsStateColumn;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;
use Syriable\Casework\Support\ModelRegistry;
use Syriable\Casework\Support\Origin;

/**
 * A typed, scoped limitation on a subject (domain model E7).
 *
 * The real-time rule (I-09): a stored state of `active` with expires_at
 * in the past evaluates as inactive everywhere — the scheduled expiry
 * command only formalizes events and audit, never correctness.
 *
 * @property Carbon|null $expires_at
 * @property Carbon|null $lifted_at
 */
class Restriction extends Model implements Stateful
{
    use ExpiresInRealTime;
    use GuardsStateColumn;

    /** @use HasFactory<RestrictionFactory> */
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
            'expires_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    protected function tableSuffix(): string
    {
        return 'restrictions';
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function issuer(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function liftedBy(): MorphTo
    {
        return $this->morphTo(null, 'lifted_by_type', 'lifted_by_id');
    }

    /**
     * Nullable — direct restrictions carry no decision.
     *
     * @return BelongsTo<Model, $this>
     */
    public function decision(): BelongsTo
    {
        return $this->belongsTo(ModelRegistry::classFor('decision'), 'decision_id');
    }

    /** @return BelongsTo<Model, $this> */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(ModelRegistry::classFor('restriction'), 'superseded_by_id');
    }

    /** Currently enforceable: state active AND not past expiry (I-09). */
    public function isActive(): bool
    {
        return $this->getAttribute('state') === RestrictionState::Active->value
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isPermanent(): bool
    {
        return $this->expires_at === null;
    }

    /**
     * The FR-405 hot path — state active AND not past expiry (I-09),
     * resolved inside the composite hot-path index.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('state', RestrictionState::Active->value)->notExpired();
    }

    /** @param Builder<static> $query */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /** @param Builder<static> $query */
    public function scopeInScope(Builder $query, string $scope): void
    {
        $query->where('scope', $scope);
    }

    /** @param Builder<static> $query */
    public function scopeForSubject(Builder $query, Model $subject): void
    {
        $query->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    /**
     * Due for the expiry command (FR-404).
     *
     * @param  Builder<static>  $query
     */
    public function scopeExpiringBefore(Builder $query, Carbon $moment): void
    {
        $query->where('state', RestrictionState::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $moment);
    }
}
