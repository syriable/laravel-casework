<?php

declare(strict_types=1);

namespace Syriable\Casework\Enforcement\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Syriable\Casework\Database\Factories\WarningFactory;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;
use Syriable\Casework\Support\ModelRegistry;
use Syriable\Casework\Support\Origin;

/**
 * A formal recorded caution (domain model E8, FR-406). Deliberately not
 * a state machine — activity is purely time-derived (workflows doc).
 *
 * @property Carbon|null $expires_at
 */
class Warning extends Model
{
    /** @use HasFactory<WarningFactory> */
    use HasFactory;

    use HasPrefixedTable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'origin' => Origin::class,
            'expires_at' => 'datetime',
        ];
    }

    protected function tableSuffix(): string
    {
        return 'warnings';
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

    /** @return BelongsTo<Model, $this> */
    public function decision(): BelongsTo
    {
        return $this->belongsTo(ModelRegistry::classFor('decision'), 'decision_id');
    }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where(function (Builder $expiry): void {
            $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /** @param Builder<static> $query */
    public function scopeForSubject(Builder $query, Model $subject): void
    {
        $query->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }
}
