<?php

declare(strict_types=1);

namespace Syriable\Casework\Appeals\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Syriable\Casework\Appeals\AppealWorkflow;
use Syriable\Casework\Appeals\Events\AppealSubmitted;
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Exceptions\AppealLimitReached;
use Syriable\Casework\Exceptions\AppealWindowClosed;
use Syriable\Casework\States\Workflow;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;
use Syriable\Casework\Support\ModelRegistry;

/**
 * Submit an appeal against a decision or restriction (FR-501–503,
 * FR-506). Guard-level rejections never create a record — they throw
 * (invariant I-11). Pipeline: authorize → guard → transact → transition
 * → audit → events (ADR-0005).
 */
class SubmitAppeal
{
    use AuthorizesActions;

    public function __construct(
        private readonly Recorder $recorder,
        private readonly AppealWorkflow $workflow,
    ) {}

    /**
     * @throws AuthorizationException|AppealWindowClosed|AppealLimitReached
     */
    public function execute(Model $target, ActorRef $by, ?string $statement = null): Appeal
    {
        $this->guardAppealable($target);
        $this->authorize($by, 'submit', ModelRegistry::classFor('appeal'));
        $this->guardWindow($target);

        return DB::transaction(function () use ($target, $by, $statement): Appeal {
            // The per-target limit (FR-503) is a count-then-insert check,
            // so it must be serialized: a row lock on the appealed target
            // makes concurrent submissions queue, and each then sees the
            // committed count of the ones before it (Phase 18 review).
            $this->lockTarget($target);
            $this->guardLimit($target);

            $class = ModelRegistry::classFor('appeal');

            /** @var Appeal $appeal */
            $appeal = new $class([
                'appealed_type' => $target->getMorphClass(),
                'appealed_id' => $target->getKey(),
                'appellant_type' => $by->actor?->getMorphClass(),
                'appellant_id' => $by->actor?->getKey(),
                'origin' => $by->origin,
                'statement' => $statement,
            ]);

            (new Workflow($this->workflow))->initialize($appeal, 'submit', $by);

            $appeal->save();

            $this->recorder->record($by, 'appeal.submitted', $appeal, [
                'appealed_type' => $target->getMorphClass(),
                'appealed_id' => $target->getKey(),
            ]);

            event(new AppealSubmitted($appeal, $by));

            return $appeal;
        });
    }

    /**
     * Only decisions and restrictions are appealable (FR-501).
     */
    private function guardAppealable(Model $target): void
    {
        $decision = ModelRegistry::classFor('decision');
        $restriction = ModelRegistry::classFor('restriction');

        if (! $target instanceof $decision && ! $target instanceof $restriction) {
            throw new InvalidArgumentException(sprintf(
                'Only decisions and restrictions are appealable (FR-501); %s given.',
                $target::class,
            ));
        }
    }

    /**
     * FR-506: submission is allowed through the exact end of the window
     * — day count from the target's creation — and refused strictly
     * after it. A null window disables the check.
     */
    private function guardWindow(Model $target): void
    {
        $window = config('casework.appeals.window_days');

        if (! is_int($window)) {
            return;
        }

        $createdAt = $target->getAttribute('created_at');

        if (! $createdAt instanceof Carbon) {
            return;
        }

        if (now()->gt($createdAt->clone()->addDays($window))) {
            throw AppealWindowClosed::for($target, $window);
        }
    }

    /**
     * Serialize concurrent submissions against the same target by taking
     * a row lock on it. The target (a decision or restriction) always
     * exists, so the lock holds even for the first appeal — closing the
     * gap a lock on the (possibly empty) appeals set would leave. On
     * SQLite this is a no-op, but SQLite already serializes writers.
     */
    private function lockTarget(Model $target): void
    {
        $target->newQuery()
            ->whereKey($target->getKey())
            ->lockForUpdate()
            ->first();
    }

    /**
     * FR-503: appeals per target are counted regardless of outcome — a
     * rejected appeal still consumes the allowance.
     */
    private function guardLimit(Model $target): void
    {
        $limit = config('casework.appeals.limit_per_target');
        $limit = is_int($limit) ? $limit : 1;

        $class = ModelRegistry::classFor('appeal');

        $existing = $class::query()
            ->where('appealed_type', $target->getMorphClass())
            ->where('appealed_id', $target->getKey())
            ->count();

        if ($existing >= $limit) {
            throw AppealLimitReached::for($target, $limit);
        }
    }
}
