<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Cases\Events\CaseAssigned;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Exceptions\InvalidTransition;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;

/**
 * Assign or reassign a case to a moderator. A recorded,
 * evented operation that is not a state transition (workflow doc).
 */
class AssignCase
{
    use AuthorizesActions;

    public function __construct(
        private readonly Recorder $recorder,
    ) {}

    public function execute(CaseFile $case, Model $assignee, ActorRef $by): CaseFile
    {
        $this->authorizeInScope($by, 'assign', $case, $case->subject()->first());

        if (in_array(Workflow::stateOf($case), ['decided', 'closed'], true)) {
            throw InvalidTransition::withReason($case, 'assign', Workflow::stateOf($case), 'the case is no longer workable');
        }

        return DB::transaction(function () use ($case, $assignee, $by): CaseFile {
            // getResults() on a fresh relation instance: it re-reads the
            // current morph columns (attribute access would return a stale
            // cached value on reassignment) and guards null keys, where
            // MorphTo::first() builds invalid SQL on pgsql/MySQL.
            $previous = $case->assignee()->getResults();
            $previous = $previous instanceof Model ? $previous : null;

            $case->update([
                'assignee_type' => $assignee->getMorphClass(),
                'assignee_id' => $assignee->getKey(),
            ]);

            $this->recorder->record($by, 'case.assigned', $case, [
                'assignee_type' => $assignee->getMorphClass(),
                'assignee_id' => $assignee->getKey(),
            ]);

            event(new CaseAssigned($case, $assignee, $previous, $by));

            return $case;
        });
    }
}
