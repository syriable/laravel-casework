<?php

declare(strict_types=1);

namespace Syriable\Casework\Support;

use Syriable\Casework\Contracts\FiltersEvents;
use Syriable\Casework\Contracts\Notifier;

/**
 * The FR-803 notifier loop (extension point X8): a wildcard listener on
 * the package namespace that hands every catalog event to the classes
 * listed in config('casework.notifiers'), in listed order. Events
 * dispatch after commit (ADR-0015), so notifiers observe committed
 * state. A notifier decides internally which events it cares about and
 * may queue its own jobs — notifiers observe; they cannot veto.
 *
 * A notifier implementing FiltersEvents narrows this: the dispatcher
 * consults its static subscription list first and skips resolving it
 * entirely for events it did not subscribe to (Phase 18 review).
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

            // Filtering happens before resolution: a notifier that opts
            // out of this event is never built.
            if (! $this->subscribes($class, $event)) {
                continue;
            }

            $notifier = app($class);

            if ($notifier instanceof Notifier) {
                $notifier->notify($event);
            }
        }
    }

    /**
     * Whether a notifier class wants this event. Notifiers that do not
     * implement FiltersEvents receive every event (BC).
     */
    private function subscribes(string $class, object $event): bool
    {
        if (! is_a($class, FiltersEvents::class, true)) {
            return true;
        }

        foreach ($class::subscribesTo() as $subscribed) {
            if ($event instanceof $subscribed) {
                return true;
            }
        }

        return false;
    }
}
