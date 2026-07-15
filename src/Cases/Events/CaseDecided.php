<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Collection;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Contracts\StateTransitionEvent;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Enforcement\Models\Warning;
use Syriable\Casework\Support\ActorRef;

/**
 * Audit key: case.decided. The summarizing event of a decision — its
 * enforcement events dispatch first (occurrence order, ADR-0015).
 *
 * @phpstan-type TRestrictions Collection<int, \Syriable\Casework\Enforcement\Models\Restriction>
 * @phpstan-type TWarnings Collection<int, \Syriable\Casework\Enforcement\Models\Warning>
 */
final readonly class CaseDecided implements ShouldDispatchAfterCommit, StateTransitionEvent
{
    /**
     * @param  Collection<int, Restriction>  $restrictions
     * @param  Collection<int, Warning>  $warnings
     */
    public function __construct(
        public CaseFile $case,
        public Decision $decision,
        public Collection $restrictions,
        public Collection $warnings,
        public string $from,
        public string $to,
        public ActorRef $by,
    ) {}
}
