<?php

declare(strict_types=1);

namespace Syriable\Casework\Enforcement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Syriable\Casework\Enforcement\Actions\ApplyRestriction;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Exceptions\IncompleteBuilder;
use Syriable\Casework\Support\ActorRef;

/**
 * Pending-operation builder for restrictions (ADR-0009; Phase 5 §5).
 * Duration is explicit — for(), until(), or permanently() (Explicit >
 * Implicit).
 */
final class PendingRestriction
{
    private ?ActorRef $by = null;

    private ?Carbon $expiresAt = null;

    private bool $durationChosen = false;

    private ?string $scope = null;

    private ?string $rationale = null;

    public function __construct(
        private readonly Model $subject,
        private readonly string $type,
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

    public function for(int $days): self
    {
        $clone = clone $this;
        $clone->expiresAt = now()->addDays($days);
        $clone->durationChosen = true;

        return $clone;
    }

    public function until(Carbon $moment): self
    {
        $clone = clone $this;
        $clone->expiresAt = $moment;
        $clone->durationChosen = true;

        return $clone;
    }

    public function permanently(): self
    {
        $clone = clone $this;
        $clone->expiresAt = null;
        $clone->durationChosen = true;

        return $clone;
    }

    public function inScope(string $scope): self
    {
        $clone = clone $this;
        $clone->scope = $scope;

        return $clone;
    }

    public function because(string $rationale): self
    {
        $clone = clone $this;
        $clone->rationale = $rationale;

        return $clone;
    }

    public function apply(): Restriction
    {
        $by = $this->by ?? throw IncompleteBuilder::missing(self::class, 'an issuer (by / bySystem)');

        if (! $this->durationChosen) {
            throw IncompleteBuilder::missing(self::class, 'a duration (for / until / permanently)');
        }

        return app(ApplyRestriction::class)->execute(
            $this->subject,
            $by,
            $this->type,
            $this->expiresAt,
            $this->scope,
            $this->rationale,
        );
    }
}
