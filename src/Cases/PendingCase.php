<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Cases\Actions\OpenCase;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Exceptions\IncompleteBuilder;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Support\ActorRef;

/**
 * Pending-operation builder for opening cases (ADR-0009; Phase 5 §3).
 */
final class PendingCase
{
    private ?ActorRef $by = null;

    /** @var list<Report> */
    private array $reports = [];

    private ?string $priority = null;

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

    /** @param list<Report> $reports */
    public function withReports(array $reports): self
    {
        $clone = clone $this;
        $clone->reports = $reports;

        return $clone;
    }

    public function priority(string $priority): self
    {
        $clone = clone $this;
        $clone->priority = $priority;

        return $clone;
    }

    public function open(): CaseFile
    {
        $by = $this->by ?? throw IncompleteBuilder::missing(self::class, 'an actor (by / bySystem)');

        return app(OpenCase::class)->execute($this->subject, $by, $this->priority, $this->reports);
    }
}
