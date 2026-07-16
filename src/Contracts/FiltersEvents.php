<?php

declare(strict_types=1);

namespace Syriable\Casework\Contracts;

/**
 * An opt-in refinement of the Notifier hook (extension point X8): a
 * notifier that also implements this contract is only resolved and invoked
 * for the events it subscribes to, instead of for every catalog event.
 *
 * The method is static so the dispatcher can consult it without building
 * the notifier — the whole point is to skip instantiation for events a
 * notifier does not care about (Phase 18 review). A notifier that does not
 * implement this contract keeps the original behavior and receives every
 * event.
 */
interface FiltersEvents
{
    /**
     * Event classes this notifier handles. An event matches when it is an
     * instance of any listed class, so a base event class subscribes to
     * its subclasses too.
     *
     * @return list<class-string>
     */
    public static function subscribesTo(): array;
}
