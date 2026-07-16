<?php

declare(strict_types=1);

namespace Syriable\Casework\Reporting;

/**
 * Core report lifecycle states (docs/guide/workflows.md). The state
 * column stays a plain string rather than a DB-level enum, so the
 * workflow engine — not the schema — is the single source of truth;
 * these are the canonical, non-removable values.
 */
enum ReportState: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case AttachedToCase = 'attached_to_case';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
