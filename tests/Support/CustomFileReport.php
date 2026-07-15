<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Reporting\Actions\FileReport;
use Syriable\Casework\Reporting\Models\Reason;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Support\ActorRef;

/**
 * Test action decoration (X11): calls the parent rather than copying
 * internals (extension guarantee #3).
 */
class CustomFileReport extends FileReport
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function execute(
        Model $subject,
        ActorRef $by,
        Reason|string $reason,
        ?string $comment = null,
        array $metadata = [],
    ): Report {
        $metadata['decorated'] = true;

        return parent::execute($subject, $by, $reason, $comment, $metadata);
    }
}
