<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting;

/**
 * Core report lifecycle states (docs/workflows/report.md). The state
 * column stays a plain string so applications can add custom states
 * (ADR-0013); these are the canonical, non-removable values.
 */
enum ReportState: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case AttachedToCase = 'attached_to_case';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
