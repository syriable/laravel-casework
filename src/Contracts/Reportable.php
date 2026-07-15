<?php

declare(strict_types=1);

namespace Syriable\Casework\Contracts;

/**
 * Marks an Eloquent model as a valid report subject (FR-101). Pair with
 * the Concerns\InteractsWithReports trait for the relation surface.
 */
interface Reportable {}
