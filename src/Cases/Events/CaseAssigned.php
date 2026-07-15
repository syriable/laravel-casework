<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: case.assigned. Not a state transition (workflow doc).
 */
final readonly class CaseAssigned implements ShouldDispatchAfterCommit
{
    public function __construct(
        public CaseFile $case,
        public Model $assignee,
        public ?Model $previousAssignee,
        public ActorRef $by,
    ) {}
}
