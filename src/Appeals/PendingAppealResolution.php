<?php

declare(strict_types=1);

namespace Syriable\Casework\Appeals;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Appeals\Actions\ResolveAppeal;
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Exceptions\IncompleteBuilder;
use Syriable\Casework\Support\ActorRef;

/**
 * Pending-operation builder for appeal resolution (ADR-0009; Phase 5
 * §6). The resolution verbs — uphold(), overturn(), reject() — are the
 * terminal calls; nothing happens until one of them.
 */
final class PendingAppealResolution
{
    private ?ActorRef $by = null;

    public function __construct(
        private readonly Appeal $appeal,
    ) {}

    public function by(Model $reviewer): self
    {
        $clone = clone $this;
        $clone->by = ActorRef::model($reviewer);

        return $clone;
    }

    public function bySystem(): self
    {
        $clone = clone $this;
        $clone->by = ActorRef::system();

        return $clone;
    }

    /**
     * The original decision/restriction stands.
     */
    public function uphold(?string $rationale = null): Appeal
    {
        return $this->resolve(ResolveAppeal::UPHOLD, $rationale);
    }

    /**
     * Reverse the original outcome: lifts associated active restrictions
     * and records a superseding decision, atomically (I-13, FR-504).
     */
    public function overturn(?string $rationale = null): Appeal
    {
        return $this->resolve(ResolveAppeal::OVERTURN, $rationale);
    }

    /**
     * Decline the appeal itself (also allowed from `submitted`, for
     * administrative rejection).
     */
    public function reject(?string $reason = null): Appeal
    {
        return $this->resolve(ResolveAppeal::REJECT, $reason);
    }

    private function resolve(string $resolution, ?string $rationale): Appeal
    {
        $by = $this->by ?? throw IncompleteBuilder::missing(self::class, 'a reviewer (by / bySystem)');

        return app(ResolveAppeal::class)->execute($this->appeal, $by, $resolution, $rationale);
    }
}
