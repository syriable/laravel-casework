<?php

declare(strict_types=1);

namespace Syriable\Casework\Enforcement\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Syriable\Casework\Audit\Recorder;
use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Enforcement\Events\WarningIssued;
use Syriable\Casework\Enforcement\Models\Warning;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\Concerns\AuthorizesActions;
use Syriable\Casework\Support\ModelRegistry;

/**
 * Issue a formal warning (FR-406). Not a state machine — activity is
 * time-derived (workflow doc).
 */
class IssueWarning
{
    use AuthorizesActions;

    public function __construct(
        private readonly Recorder $recorder,
    ) {}

    public function execute(
        Model $subject,
        ActorRef $by,
        string $reason,
        ?Carbon $expiresAt = null,
        ?Decision $decision = null,
    ): Warning {
        $this->authorizeInScope($by, 'issue', ModelRegistry::classFor('warning'), $subject);

        return DB::transaction(function () use ($subject, $by, $reason, $expiresAt, $decision): Warning {
            $class = ModelRegistry::classFor('warning');

            /** @var Warning $warning */
            $warning = new $class([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'issuer_type' => $by->actor?->getMorphClass(),
                'issuer_id' => $by->actor?->getKey(),
                'origin' => $by->origin,
                'decision_id' => $decision?->getKey(),
                'reason' => $reason,
                'expires_at' => $expiresAt,
            ]);

            $warning->save();

            $this->recorder->record($by, 'warning.issued', $warning, array_filter([
                'decision_id' => $decision?->getKey(),
            ]));

            event(new WarningIssued($warning, $by));

            return $warning;
        });
    }
}
