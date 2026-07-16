<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Syriable\Casework\Database\Factories\ReasonFactory;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;
use Syriable\Casework\Support\ModelRegistry;

/**
 * A configured report classification (domain model E2). Deactivation
 * never invalidates historical reports.
 */
class Reason extends Model
{
    /** @use HasFactory<ReasonFactory> */
    use HasFactory;

    use HasPrefixedTable;

    // Written only through the package's audited actions; never bind
    // request input to these models directly (ADR-0018).
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
        ];
    }

    protected function tableSuffix(): string
    {
        return 'reasons';
    }

    /** @return HasMany<Model, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(ModelRegistry::classFor('report'), 'reason_id');
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
