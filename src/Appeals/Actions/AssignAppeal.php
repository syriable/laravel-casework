<?php

declare(strict_types=1);

namespace Syriable\Casework\Appeals\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Syriable\Casework\Appeals\AppealState;
use Syriable\Casework\Appeals\Concerns\GuardsReviewerIndependence;
use Syriable\Casework\Appeals\Events\AppealAssigned;
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Exceptions\InvalidTransition;
use Syriable\Casework\Exceptions\ReviewerNotIndependent;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;

/**
 * Assign an appeal to a reviewer. A non-transition operation:
 * the appeal's state never moves — only the reviewer reference. The
 * independence guard runs here and again at startReview, so a
 * post-assignment config flip cannot smuggle a dependent reviewer in.
 */
class AssignAppeal
{
    use AuthorizesActions;
    use GuardsReviewerIndependence;

    public function __construct(
        private readonly Recorder $recorder,
    ) {}

    /**
     * @throws ReviewerNotIndependent
     */
    public function execute(Appeal $appeal, Model $reviewer, ActorRef $by): Appeal
    {
        $this->authorize($by, 'assign', $appeal);
        $this->guardOpen($appeal);
        $this->guardReviewer($appeal, $reviewer);

        return DB::transaction(function () use ($appeal, $reviewer, $by): Appeal {
            $appeal->update([
                'reviewer_type' => $reviewer->getMorphClass(),
                'reviewer_id' => $reviewer->getKey(),
            ]);

            $this->recorder->record($by, 'appeal.assigned', $appeal, [
                'reviewer_type' => $reviewer->getMorphClass(),
                'reviewer_id' => $reviewer->getKey(),
            ]);

            event(new AppealAssigned($appeal, $reviewer, $by));

            return $appeal;
        });
    }

    private function guardOpen(Appeal $appeal): void
    {
        $open = [AppealState::Submitted->value, AppealState::UnderReview->value];

        if (! in_array(Workflow::stateOf($appeal), $open, true)) {
            throw InvalidTransition::withReason(
                $appeal,
                'assign',
                Workflow::stateOf($appeal),
                'resolved appeals cannot be reassigned',
            );
        }
    }
}
