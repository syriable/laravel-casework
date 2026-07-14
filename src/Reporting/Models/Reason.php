<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Models;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;

/**
 * A configured report classification (domain model E2).
 *
 * Scaffold (milestone M1) — completed in M2.
 */
class Reason extends Model
{
    use HasPrefixedTable;

    protected function tableSuffix(): string
    {
        return 'reasons';
    }
}
