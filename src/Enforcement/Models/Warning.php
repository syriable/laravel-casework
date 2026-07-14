<?php

declare(strict_types=1);

namespace Syriable\Casework\Enforcement\Models;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;

/**
 * A formal recorded caution (domain model E8).
 *
 * Scaffold (milestone M1) — completed in M2.
 */
class Warning extends Model
{
    use HasPrefixedTable;

    protected function tableSuffix(): string
    {
        return 'warnings';
    }
}
