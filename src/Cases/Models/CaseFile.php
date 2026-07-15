<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Syriable\Casework\Cases\CaseState;
use Syriable\Casework\Contracts\Stateful;
use Syriable\Casework\Database\Factories\CaseFileFactory;
use Syriable\Casework\Support\Concerns\GuardsStateColumn;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;
use Syriable\Casework\Support\ModelRegistry;

/**
 * The unit of moderation work (domain model E3). Named CaseFile because
 * `case` is a PHP reserved word (ADR-0008); the domain term is "case".
 */
class CaseFile extends Model implements Stateful
{
    use GuardsStateColumn;

    /** @use HasFactory<CaseFileFactory> */
    use HasFactory;

    use HasPrefixedTable;

    protected $guarded = [];

    protected function tableSuffix(): string
    {
        return 'cases';
    }

    /**
     * Primary subject, fixed at creation (I-05).
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function assignee(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<Model, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(ModelRegistry::classFor('report'), 'case_id');
    }

    /** @return HasMany<Model, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(ModelRegistry::classFor('note'), 'case_id');
    }

    /** @return HasMany<Model, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(ModelRegistry::classFor('evidence'), 'case_id');
    }

    /** @return HasMany<Model, $this> */
    public function decisions(): HasMany
    {
        return $this->hasMany(ModelRegistry::classFor('decision'), 'case_id');
    }

    /** @param Builder<static> $query */
    public function scopeWhereState(Builder $query, CaseState|string $state): void
    {
        $query->where('state', $state instanceof CaseState ? $state->value : $state);
    }

    /**
     * Any pre-decided, workable state.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNotIn('state', [CaseState::Decided->value, CaseState::Closed->value]);
    }

    /** @param Builder<static> $query */
    public function scopeDecided(Builder $query): void
    {
        $query->where('state', CaseState::Decided->value);
    }

    /** @param Builder<static> $query */
    public function scopeAssignedTo(Builder $query, Model $assignee): void
    {
        $query->where('assignee_type', $assignee->getMorphClass())
            ->where('assignee_id', $assignee->getKey());
    }

    /** @param Builder<static> $query */
    public function scopeWherePriority(Builder $query, string $priority): void
    {
        $query->where('priority', $priority);
    }

    /** @param Builder<static> $query */
    public function scopeForSubject(Builder $query, Model $subject): void
    {
        $query->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }
}
