<?php

declare(strict_types=1);

namespace Syriable\Casework\Appeals;

/**
 * Core appeal lifecycle states (docs/guide/workflows.md).
 */
enum AppealState: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Upheld = 'upheld';
    case Overturned = 'overturned';
    case Rejected = 'rejected';
}
