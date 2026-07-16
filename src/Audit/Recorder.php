<?php

declare(strict_types=1);

namespace Syriable\Casework\Audit;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Audit\Models\AuditEntry;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\ModelRegistry;

/**
 * The single audit write-path (architecture §2, invariant I-04). Called
 * by actions inside their transaction — never by models or listeners.
 *
 * Deliberately final and unbound to any interface: audit integrity must
 * be unforgeable from the extension surface (ADR-0017, T10 review).
 */
final class Recorder
{
    /**
     * Append one audit entry. Action keys are the
     * dot-namespaced values fixed in the event catalog.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(ActorRef $by, string $action, Model $auditable, array $payload = []): AuditEntry
    {
        $class = ModelRegistry::classFor('audit_entry');

        /** @var AuditEntry $entry */
        $entry = new $class([
            'actor_type' => $by->actor?->getMorphClass(),
            'actor_id' => $by->actor?->getKey(),
            'origin' => $by->origin,
            'action' => $action,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'payload' => $payload === [] ? null : $payload,
        ]);

        $entry->save();

        return $entry;
    }
}
