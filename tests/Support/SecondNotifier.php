<?php

declare(strict_types=1);

namespace Syriable\Casework\Tests\Support;

/**
 * Test notifier: a second registration writing to the shared log, for
 * asserting listed-order execution.
 */
class SecondNotifier extends RecordingNotifier {}
