<?php

declare(strict_types=1);

namespace Syriable\Casework\Contracts;

/**
 * Single-entry notification hook (FR-803, extension point X8). Classes
 * listed in config('casework.notifiers') receive every catalog event,
 * in order, after the dispatching transaction commits (ADR-0015).
 *
 * The package never sends notifications itself.
 */
interface Notifier
{
    public function notify(object $event): void;
}
