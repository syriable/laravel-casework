<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Cases\CaseWorkflow;
use Syriable\Casework\Cases\Events\CaseOpened;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Exceptions\InvalidConfiguration;
use Syriable\Casework\Reporting\Actions\AttachReportToCase;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;
use Syriable\Casework\Support\ModelRegistry;

/**
 * Open a case about a primary subject, fixed at creation.
 */
class OpenCase
{
    use AuthorizesActions;

    public function __construct(
        private readonly Recorder $recorder,
        private readonly CaseWorkflow $workflow,
        private readonly AttachReportToCase $attach,
    ) {}

    /**
     * @param  list<Report>  $reports
     */
    public function execute(Model $subject, ActorRef $by, ?string $priority = null, array $reports = []): CaseFile
    {
        $this->authorizeInScope($by, 'open', ModelRegistry::classFor('case'), $subject);

        $priority ??= $this->defaultPriority();
        $this->guardPriority($priority);

        return DB::transaction(function () use ($subject, $by, $priority, $reports): CaseFile {
            $class = ModelRegistry::classFor('case');

            /** @var CaseFile $case */
            $case = new $class([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'priority' => $priority,
            ]);

            (new Workflow($this->workflow))->initialize($case, 'open', $by);

            $case->save();

            $this->recorder->record($by, 'case.opened', $case, ['priority' => $priority]);

            event(new CaseOpened($case, $by));

            foreach ($reports as $report) {
                $this->attach->execute($report, $case, $by);
            }

            return $case;
        });
    }

    private function defaultPriority(): string
    {
        $default = config('casework.cases.default_priority');

        return is_string($default) ? $default : 'normal';
    }

    private function guardPriority(string $priority): void
    {
        $priorities = config('casework.cases.priorities');

        if (! is_array($priorities) || ! in_array($priority, $priorities, true)) {
            throw InvalidConfiguration::forKey(
                'cases.priorities',
                "priority [{$priority}] is not in the configured set",
            );
        }
    }
}
