<?php

declare(strict_types=1);

namespace Syriable\Casework\Appeals;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Appeals\Actions\SubmitAppeal;
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Exceptions\IncompleteBuilder;
use Syriable\Casework\Support\ActorRef;

/**
 * Pending-operation builder for appeals (ADR-0009; Phase 5 §6): inert
 * and immutable until submit(). The target is a decision or a
 * restriction; the statement is optional and opaque.
 */
final class PendingAppeal
{
    private ?ActorRef $by = null;

    private ?string $statement = null;

    public function __construct(
        private readonly Model $target,
    ) {}

    public function by(Model $appellant): self
    {
        $clone = clone $this;
        $clone->by = ActorRef::model($appellant);

        return $clone;
    }

    public function bySystem(): self
    {
        $clone = clone $this;
        $clone->by = ActorRef::system();

        return $clone;
    }

    public function statement(string $statement): self
    {
        $clone = clone $this;
        $clone->statement = $statement;

        return $clone;
    }

    public function submit(): Appeal
    {
        $by = $this->by ?? throw IncompleteBuilder::missing(self::class, 'an appellant (by / bySystem)');

        return app(SubmitAppeal::class)->execute($this->target, $by, $this->statement);
    }
}
