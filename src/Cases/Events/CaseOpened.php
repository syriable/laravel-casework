<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: case.opened.
 */
final readonly class CaseOpened implements ShouldDispatchAfterCommit
{
    public function __construct(
        public CaseFile $case,
        public ActorRef $by,
    ) {}
}
