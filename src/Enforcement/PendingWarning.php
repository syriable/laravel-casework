<?php

declare(strict_types=1);

namespace Syriable\Casework\Enforcement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Syriable\Casework\Enforcement\Actions\IssueWarning;
use Syriable\Casework\Enforcement\Models\Warning;
use Syriable\Casework\Exceptions\IncompleteBuilder;
use Syriable\Casework\Support\ActorRef;

/**
 * Pending-operation builder for warnings (ADR-0009; Phase 5 §5).
 */
final class PendingWarning
{
    private ?ActorRef $by = null;

    private ?string $reason = null;

    private ?Carbon $expiresAt = null;

    public function __construct(
        private readonly Model $subject,
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

    public function because(string $reason): self
    {
        $clone = clone $this;
        $clone->reason = $reason;

        return $clone;
    }

    public function expiring(Carbon $moment): self
    {
        $clone = clone $this;
        $clone->expiresAt = $moment;

        return $clone;
    }

    public function issue(): Warning
    {
        $by = $this->by ?? throw IncompleteBuilder::missing(self::class, 'an issuer (by / bySystem)');
        $reason = $this->reason ?? throw IncompleteBuilder::missing(self::class, 'a reason (because)');

        return app(IssueWarning::class)->execute($this->subject, $by, $reason, $this->expiresAt);
    }
}
