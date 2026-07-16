<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests\Support;

use Syriable\Casework\Contracts\FiltersEvents;
use Syriable\Casework\Contracts\Notifier;
use Syriable\Casework\Reporting\Events\ReportFiled;

/**
 * Test notifier that subscribes to a single event class and records how
 * many times it was built, so a test can prove the dispatcher skips
 * resolution for events it did not subscribe to.
 */
class SelectiveNotifier implements FiltersEvents, Notifier
{
    /** @var list<string> */
    public static array $received = [];

    public static int $instantiations = 0;

    public function __construct()
    {
        self::$instantiations++;
    }

    public static function subscribesTo(): array
    {
        return [ReportFiled::class];
    }

    public function notify(object $event): void
    {
        self::$received[] = $event::class;
    }
}
