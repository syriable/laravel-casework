<?php

declare(strict_types=1);

namespace Syriable\Casework\Audit\Models;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Support\Concerns\HasPrefixedTable;

/**
 * An append-only record of a domain action (domain model E10).
 * Immutability enforcement (ADR-0003) lands in M2.
 *
 * Scaffold (milestone M1).
 */
class AuditEntry extends Model
{
    use HasPrefixedTable;

    protected function tableSuffix(): string
    {
        return 'audit_entries';
    }
}
