<?php

declare(strict_types=1);

namespace Syriable\Casework\Enforcement\Models;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;

/**
 * A typed, scoped limitation on a subject (domain model E7).
 *
 * Scaffold (milestone M1) — completed in M2.
 */
class Restriction extends Model
{
    use HasPrefixedTable;

    protected function tableSuffix(): string
    {
        return 'restrictions';
    }
}
