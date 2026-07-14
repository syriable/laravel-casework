<?php

declare(strict_types=1);

namespace Syriable\Casework\Audit\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Syriable\Casework\Database\Factories\AuditEntryFactory;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;
use Syriable\Casework\Support\Concerns\PreventsMutation;
use Syriable\Casework\Support\Origin;

/**
 * An append-only record of a domain action (domain model E10, FR-700).
 * No update or delete API exists (ADR-0011); pruning is a dedicated,
 * opt-in command.
 */
class AuditEntry extends Model
{
    /** @use HasFactory<AuditEntryFactory> */
    use HasFactory;

    use HasPrefixedTable;
    use PreventsMutation;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'origin' => Origin::class,
            'payload' => 'array',
        ];
    }

    protected function tableSuffix(): string
    {
        return 'audit_entries';
    }

    /**
     * Null when the origin is system or anonymous (ADR-0002).
     *
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @param Builder<static> $query */
    public function scopeForAuditable(Builder $query, Model $auditable): void
    {
        $query->where('auditable_type', $auditable->getMorphClass())
            ->where('auditable_id', $auditable->getKey());
    }

    /** @param Builder<static> $query */
    public function scopeByActor(Builder $query, Model $actor): void
    {
        $query->where('actor_type', $actor->getMorphClass())
            ->where('actor_id', $actor->getKey());
    }

    /**
     * Dot-namespaced action key, e.g. "case.decided".
     *
     * @param  Builder<static>  $query
     */
    public function scopeAction(Builder $query, string $action): void
    {
        $query->where('action', $action);
    }

    /** @param Builder<static> $query */
    public function scopeBetween(Builder $query, Carbon $from, Carbon $to): void
    {
        $query->whereBetween('created_at', [$from, $to]);
    }
}
