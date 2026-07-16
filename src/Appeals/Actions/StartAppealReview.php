<?php

declare(strict_types=1);

namespace Syriable\Casework\Appeals\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Syriable\Casework\Appeals\AppealWorkflow;
use Syriable\Casework\Appeals\Concerns\GuardsReviewerIndependence;
use Syriable\Casework\Appeals\Events\AppealReviewStarted;
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Exceptions\ReviewerNotIndependent;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;

/**
 * Move a submitted appeal under review. The acting model actor is
 * vetted for independence and recorded as the reviewer when no
 * assignment happened beforehand.
 */
class StartAppealReview
{
    use AuthorizesActions;
    use GuardsReviewerIndependence;

    public function __construct(
        private readonly Recorder $recorder,
        private readonly AppealWorkflow $workflow,
    ) {}

    /**
     * @throws ReviewerNotIndependent
     */
    public function execute(Appeal $appeal, ActorRef $by): Appeal
    {
        $this->authorize($by, 'review', $appeal);

        if ($by->actor instanceof Model) {
            $this->guardReviewer($appeal, $by->actor);
        }

        return DB::transaction(function () use ($appeal, $by): Appeal {
            $from = Workflow::stateOf($appeal);

            (new Workflow($this->workflow))->transition($appeal, 'startReview', $by);

            if ($by->actor instanceof Model && $appeal->getAttribute('reviewer_type') === null) {
                $appeal->update([
                    'reviewer_type' => $by->actor->getMorphClass(),
                    'reviewer_id' => $by->actor->getKey(),
                ]);
            }

            $this->recorder->record($by, 'appeal.review_started', $appeal);

            event(new AppealReviewStarted($appeal, $from, Workflow::stateOf($appeal), $by));

            return $appeal;
        });
    }
}
