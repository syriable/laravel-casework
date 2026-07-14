<?php

declare(strict_types=1);

namespace Syriable\Casework;

/**
 * Facade root. Domain operations (report, openCase, decide, restrict,
 * appeal, …) land with their modules in milestones M5–M8 per
 * docs/implementation-plan.md; the facade delegates to actions and never
 * re-implements them (ADR-0005).
 */
final class Casework {}
