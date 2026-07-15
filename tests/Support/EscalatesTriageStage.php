<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests\Support;

use Closure;
use Syriable\Casework\Cases\Models\CaseFile;
use Syriable\Casework\Contracts\CaseTriageStage;
use Syriable\Casework\Facades\Casework;
use Syriable\Casework\Support\ActorRef;

/**
 * Test triage stage: escalates every fresh case through the regular
 * package operation, as the System actor (FR-805).
 */
class EscalatesTriageStage implements CaseTriageStage
{
    public function handle(CaseFile $case, Closure $next): CaseFile
    {
        Casework::escalateCase($case, ActorRef::system(), 'urgent');

        return $next($case);
    }
}
