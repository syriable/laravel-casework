<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Syriable\Casework\Cases\Actions\DecideCase;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Exceptions\IncompleteBuilder;
use Syriable\Casework\Support\ActorRef;
use Syriable\Casework\Support\RestrictionType;

/**
 * Pending-operation builder for deciding a case (ADR-0009; Phase 5 §4).
 * finalize() is atomic (I-06/I-08): case transition, report resolution,
 * and enforcement application in one transaction.
 *
 * @phpstan-type TEnforcement array{type: string, expires_at: Carbon|null, scope: string|null}
 */
final class PendingDecision
{
    private ?ActorRef $by = null;

    private ?string $outcome = null;

    private ?string $rationale = null;

    private ?Decision $supersedes = null;

    /** @var list<array{type: string, expires_at: Carbon|null, scope: string|null}> */
    private array $restrictions = [];

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(
        private readonly CaseFile $case,
    ) {}

    public function by(Model $actor): self
    {
        $clone = clone $this;
        $clone->by = ActorRef::model($actor);

        return $clone;
    }

    public function bySystem(): self
    {
        $clone = clone $this;
        $clone->by = ActorRef::system();

        return $clone;
    }

    public function outcome(string $outcome): self
    {
        $clone = clone $this;
        $clone->outcome = $outcome;

        return $clone;
    }

    public function rationale(string $rationale): self
    {
        $clone = clone $this;
        $clone->rationale = $rationale;

        return $clone;
    }

    public function supersedes(Decision $decision): self
    {
        $clone = clone $this;
        $clone->supersedes = $decision;

        return $clone;
    }

    public function withRestriction(string $type, bool $permanent = false, ?int $days = null, ?string $scope = null): self
    {
        $clone = clone $this;
        $clone->restrictions[] = [
            'type' => $type,
            'expires_at' => $permanent || $days === null ? null : now()->addDays($days),
            'scope' => $scope,
        ];

        return $clone;
    }

    public function withSuspension(?int $days = null): self
    {
        return $this->withRestriction(RestrictionType::SUSPENSION, permanent: $days === null, days: $days);
    }

    public function withWarning(string $reason): self
    {
        $clone = clone $this;
        $clone->warnings[] = $reason;

        return $clone;
    }

    public function finalize(): Decision
    {
        $by = $this->by ?? throw IncompleteBuilder::missing(self::class, 'a decider (by / bySystem)');
        $outcome = $this->outcome ?? throw IncompleteBuilder::missing(self::class, 'an outcome');

        return app(DecideCase::class)->execute(
            $this->case,
            $by,
            $outcome,
            $this->rationale,
            $this->restrictions,
            $this->warnings,
            $this->supersedes,
        );
    }
}
