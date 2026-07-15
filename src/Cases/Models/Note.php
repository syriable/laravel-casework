<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Syriable\Casework\Database\Factories\NoteFactory;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;
use Syriable\Casework\Support\Concerns\PreventsMutation;
use Syriable\Casework\Support\ModelRegistry;
use Syriable\Casework\Support\Origin;

/**
 * An immutable investigation note (domain model E4, FR-254).
 */
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    use HasPrefixedTable;
    use PreventsMutation;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'origin' => Origin::class,
        ];
    }

    protected function tableSuffix(): string
    {
        return 'case_notes';
    }

    /** @return BelongsTo<Model, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ModelRegistry::classFor('case'), 'case_id');
    }

    /** @return MorphTo<Model, $this> */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }
}
