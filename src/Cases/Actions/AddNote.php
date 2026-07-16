<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Actions;

use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Cases\Events\CaseNoteAdded;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Cases\Models\Note;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;
use Syriable\Casework\Support\ModelRegistry;

/**
 * Attach an immutable, authored investigation note.
 */
class AddNote
{
    use AuthorizesActions;

    public function __construct(
        private readonly Recorder $recorder,
    ) {}

    public function execute(CaseFile $case, ActorRef $by, string $body): Note
    {
        $this->authorizeInScope($by, 'note', $case, $case->subject()->first());

        return DB::transaction(function () use ($case, $by, $body): Note {
            $class = ModelRegistry::classFor('note');

            /** @var Note $note */
            $note = new $class([
                'case_id' => $case->getKey(),
                'author_type' => $by->actor?->getMorphClass(),
                'author_id' => $by->actor?->getKey(),
                'origin' => $by->origin,
                'body' => $body,
            ]);

            $note->save();

            $this->recorder->record($by, 'case.note_added', $case, ['note_id' => $note->getKey()]);

            event(new CaseNoteAdded($note, $by));

            return $note;
        });
    }
}
