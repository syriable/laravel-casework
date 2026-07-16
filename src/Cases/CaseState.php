<?php

declare(strict_types=1);

namespace Syriable\Casework\Cases;

/**
 * Core case lifecycle states (docs/guide/workflows.md).
 */
enum CaseState: string
{
    case Open = 'open';
    case UnderInvestigation = 'under_investigation';
    case AwaitingDecision = 'awaiting_decision';
    case Decided = 'decided';
    case Closed = 'closed';
}
