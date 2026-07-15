<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Audit\Models\AuditEntry;

/**
 * Audit assertion helper (implementation plan M4): later milestones
 * assert one entry per domain action with it.
 */
trait AssertsAudit
{
    protected function assertAuditRecorded(string $action, Model $auditable, ?Model $actor = null): AuditEntry
    {
        $query = AuditEntry::query()->action($action)->forAuditable($auditable);

        if ($actor instanceof Model) {
            $query->byActor($actor);
        }

        $entry = $query->latest('id')->first();

        expect($entry)->not->toBeNull(
            "Expected an audit entry [{$action}] for ".$auditable::class." #{$auditable->getKey()}",
        );

        /** @var AuditEntry $entry */
        return $entry;
    }

    protected function assertNoAuditRecorded(string $action, Model $auditable): void
    {
        expect(
            AuditEntry::query()->action($action)->forAuditable($auditable)->exists(),
        )->toBeFalse("Expected no audit entry [{$action}] for ".$auditable::class);
    }
}
