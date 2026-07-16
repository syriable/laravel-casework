<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\Support\ActorRef;

/**
 * Mutable pending-intake context handed through the intake pipeline
 * (FR-804, event catalog §Automation). Stages may adjust the opaque
 * fields and steer what happens after persistence; the identity of the
 * report — subject, reporter, reason — is fixed by the time the
 * pipeline runs (guards have already accepted it).
 */
final class ReportIntake
{
    private ?bool $caseDecision = null;

    private bool $dismiss = false;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly Model $subject,
        public readonly ActorRef $by,
        public readonly Reason $reason,
        public ?string $comment,
        public array $metadata,
    ) {}

    /**
     * Open/join a case regardless of the configured strategy.
     */
    public function forceCase(): void
    {
        $this->caseDecision = true;
    }

    /**
     * Skip case creation regardless of the configured strategy.
     */
    public function suppressCase(): void
    {
        $this->caseDecision = false;
    }

    /**
     * Persist the report, then immediately dismiss it with System
     * attribution — filed and dismissed both land in the audit trail.
     * Implies no case is opened.
     */
    public function autoDismiss(): void
    {
        $this->dismiss = true;
    }

    public function shouldForceCase(): bool
    {
        return $this->caseDecision === true;
    }

    public function shouldSuppressCase(): bool
    {
        return $this->caseDecision === false;
    }

    public function shouldDismiss(): bool
    {
        return $this->dismiss;
    }
}
