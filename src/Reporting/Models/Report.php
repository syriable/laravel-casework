<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting\Models;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;

/**
 * A reporter's claim about a subject (domain model E1).
 *
 * Scaffold (milestone M1) — relations, query scopes, and state handling
 * land in M2 per docs/implementation-plan.md.
 */
class Report extends Model
{
    use HasPrefixedTable;

    protected function tableSuffix(): string
    {
        return 'reports';
    }
}
