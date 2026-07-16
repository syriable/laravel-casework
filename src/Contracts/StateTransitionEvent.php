<?php

declare(strict_types=1);

namespace Syriable\Casework\Contracts;

/**
 * Marks a domain event representing a state transition. By
 * catalog convention every implementation exposes public readonly
 * `string $from`, `string $to`, and `Support\ActorRef $by` properties.
 */
interface StateTransitionEvent {}
