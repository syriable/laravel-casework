<?php

declare(strict_types=1);

namespace Syriable\Casework\Support;

use Syriable\Casework\Contracts\Notifier;

/**
 * The FR-803 notifier loop (extension point X8): a wildcard listener on
 * the package namespace that hands every catalog event to the classes
 * listed in config('casework.notifiers'), in listed order. Events
 * dispatch after commit (ADR-0015), so notifiers observe committed
 * state. A notifier decides internally which events it cares about and
 * may queue its own jobs — notifiers observe; they cannot veto.
 */
final class NotifierDispatcher
{
    /**
     * Wildcard listener signature: the event name and its payload.
     *
     * @param  array<int, mixed>  $payload
     */
    public function handle(string $eventName, array $payload): void
    {
        $event = $payload[0] ?? null;

        if (! is_object($event)) {
            return;
        }

        $notifiers = config('casework.notifiers');

        if (! is_array($notifiers)) {
            return;
        }

        foreach ($notifiers as $class) {
            if (! is_string($class)) {
                continue;
            }

            $notifier = app($class);

            if ($notifier instanceof Notifier) {
                $notifier->notify($event);
            }
        }
    }
}
