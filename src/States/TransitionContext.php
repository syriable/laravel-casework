<?php

declare(strict_types=1);

namespace Syriable\Casework\States;

use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Support\ActorRef;

/**
 * Everything a guard may inspect (ADR-0012).
 */
final readonly class TransitionContext
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public Model $record,
        public TransitionDefinition $transition,
        public ActorRef $by,
        public array $context = [],
    ) {}
}
