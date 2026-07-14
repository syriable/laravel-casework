<?php

declare(strict_types=1);

namespace Syriable\Casework\Exceptions;

/**
 * Marker interface implemented by every exception the package throws
 * (ADR-0006). Catch this to handle any package failure broadly.
 */
interface CaseworkException {}
