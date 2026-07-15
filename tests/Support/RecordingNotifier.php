<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests\Support;

use Syriable\Casework\Contracts\Notifier;

/**
 * Test notifier: records every event it receives, tagged with the
 * receiving class so ordered multi-notifier runs are observable. The
 * log is shared down the class hierarchy on purpose.
 */
class RecordingNotifier implements Notifier
{
    /** @var list<string> */
    public static array $received = [];

    public function notify(object $event): void
    {
        self::$received[] = static::class.':'.$event::class;
    }
}
