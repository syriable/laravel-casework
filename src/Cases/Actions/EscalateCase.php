<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Actions;

use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Cases\Events\CaseEscalated;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Exceptions\InvalidConfiguration;
use Syriable\Casework\Exceptions\InvalidTransition;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;

/**
 * Change a case's priority. A recorded, evented operation that
 * does not change lifecycle state (workflow doc).
 */
class EscalateCase
{
    use AuthorizesActions;

    public function __construct(
        private readonly Recorder $recorder,
    ) {}

    public function execute(CaseFile $case, ActorRef $by, string $priority): CaseFile
    {
        $this->authorizeInScope($by, 'escalate', $case, $case->subject()->first());

        $priorities = config('casework.cases.priorities');

        if (! is_array($priorities) || ! in_array($priority, $priorities, true)) {
            throw InvalidConfiguration::forKey(
                'cases.priorities',
                "priority [{$priority}] is not in the configured set",
            );
        }

        if (in_array(Workflow::stateOf($case), ['decided', 'closed'], true)) {
            throw InvalidTransition::withReason($case, 'escalate', Workflow::stateOf($case), 'the case is no longer workable');
        }

        return DB::transaction(function () use ($case, $by, $priority): CaseFile {
            $fromPriority = $case->getAttribute('priority');
            $fromPriority = is_string($fromPriority) ? $fromPriority : '';

            $case->update(['priority' => $priority]);

            $this->recorder->record($by, 'case.escalated', $case, [
                'from' => $fromPriority,
                'to' => $priority,
            ]);

            event(new CaseEscalated($case, $fromPriority, $priority, $by));

            return $case;
        });
    }
}
