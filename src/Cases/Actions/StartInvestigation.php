<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Actions;

use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Cases\CaseWorkflow;
use Syriable\Casework\Cases\Events\CaseInvestigationStarted;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;

/**
 * Begin investigating an open case (workflow: startInvestigation).
 */
class StartInvestigation
{
    use AuthorizesActions;

    public function __construct(
        private readonly Recorder $recorder,
        private readonly CaseWorkflow $workflow,
    ) {}

    public function execute(CaseFile $case, ActorRef $by): CaseFile
    {
        $this->authorizeInScope($by, 'startInvestigation', $case, $case->subject()->first());

        return DB::transaction(function () use ($case, $by): CaseFile {
            $from = Workflow::stateOf($case);

            (new Workflow($this->workflow))->transition($case, 'startInvestigation', $by);

            $this->recorder->record($by, 'case.investigation_started', $case);

            event(new CaseInvestigationStarted($case, $from, Workflow::stateOf($case), $by));

            return $case;
        });
    }
}
