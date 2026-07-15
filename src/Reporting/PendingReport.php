<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Exceptions\IncompleteBuilder;
use Syriable\Casework\Reporting\Actions\FileReport;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Support\ActorRef;

/**
 * Pending-operation builder for filing reports (ADR-0009): an immutable
 * intent, inert until file(). Phase 5 §2 surface.
 */
final class PendingReport
{
    private ?ActorRef $by = null;

    private Reason|string|null $reason = null;

    private ?string $comment = null;

    /** @var array<string, mixed> */
    private array $metadata = [];

    public function __construct(
        private readonly Model $subject,
    ) {}

    public function by(Model $reporter): self
    {
        $clone = clone $this;
        $clone->by = ActorRef::model($reporter);

        return $clone;
    }

    public function anonymously(): self
    {
        $clone = clone $this;
        $clone->by = ActorRef::anonymous();

        return $clone;
    }

    public function bySystem(): self
    {
        $clone = clone $this;
        $clone->by = ActorRef::system();

        return $clone;
    }

    public function because(Reason|string $reason): self
    {
        $clone = clone $this;
        $clone->reason = $reason;

        return $clone;
    }

    public function comment(string $comment): self
    {
        $clone = clone $this;
        $clone->comment = $comment;

        return $clone;
    }

    /** @param array<string, mixed> $metadata */
    public function withMetadata(array $metadata): self
    {
        $clone = clone $this;
        $clone->metadata = $metadata;

        return $clone;
    }

    /**
     * Terminal verb: validates and delegates to the FileReport action
     * (builders are sugar, never a second implementation — ADR-0005).
     */
    public function file(): Report
    {
        $by = $this->by ?? throw IncompleteBuilder::missing(self::class, 'a reporter (by / anonymously / bySystem)');
        $reason = $this->reason ?? throw IncompleteBuilder::missing(self::class, 'a reason (because)');

        return app(FileReport::class)->execute(
            $this->subject,
            $by,
            $reason,
            $this->comment,
            $this->metadata,
        );
    }
}
