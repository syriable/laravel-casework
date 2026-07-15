<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Syriable\Casework\Cases\Models\Note;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: case.note_added.
 */
final readonly class CaseNoteAdded implements ShouldDispatchAfterCommit
{
    public function __construct(
        public Note $note,
        public ActorRef $by,
    ) {}
}
