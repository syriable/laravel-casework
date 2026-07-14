<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Models;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;

/**
 * The unit of moderation work (domain model E3). Named CaseFile because
 * `case` is a PHP reserved word (ADR-0008); the domain term is "case".
 *
 * Scaffold (milestone M1) — completed in M2.
 */
class CaseFile extends Model
{
    use HasPrefixedTable;

    protected function tableSuffix(): string
    {
        return 'cases';
    }
}
