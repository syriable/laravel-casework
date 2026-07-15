<?php

declare(strict_types=1);

namespace Syriable\Casework\Contracts;

/**
 * Marks an Eloquent model as a valid restriction subject (FR-401). Pair
 * with the Concerns\InteractsWithRestrictions trait.
 */
interface Restrictable {}
