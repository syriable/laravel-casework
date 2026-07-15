<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Syriable\Casework\Cases\Models\Evidence;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: case.evidence_attached.
 */
final readonly class CaseEvidenceAttached implements ShouldDispatchAfterCommit
{
    public function __construct(
        public Evidence $evidence,
        public ActorRef $by,
    ) {}
}
