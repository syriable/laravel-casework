<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Models;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;

/**
 * An immutable evidence record (domain model E5). Immutability
 * enforcement (ADR-0003) lands in M2.
 *
 * Scaffold (milestone M1).
 */
class Evidence extends Model
{
    use HasPrefixedTable;

    protected function tableSuffix(): string
    {
        return 'case_evidence';
    }
}
