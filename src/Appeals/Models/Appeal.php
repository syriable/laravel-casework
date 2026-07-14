<?php

declare(strict_types=1);

namespace Syriable\Casework\Appeals\Models;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;

/**
 * A request to re-examine a decision or restriction (domain model E9).
 *
 * Scaffold (milestone M1) — completed in M2.
 */
class Appeal extends Model
{
    use HasPrefixedTable;

    protected function tableSuffix(): string
    {
        return 'appeals';
    }
}
