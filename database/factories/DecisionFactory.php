<?php

declare(strict_types=1);

namespace Syriable\Casework\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Cases\Models\Decision;
use Syriable\Casework\Support\Origin;
use Syriable\Casework\Support\Outcome;

/**
 * @extends Factory<Decision>
 */
class DecisionFactory extends Factory
{
    protected $model = Decision::class;

    public function definition(): array
    {
        return [
            'case_id' => CaseFileFactory::new(),
            'decider_type' => null,
            'decider_id' => null,
            'origin' => Origin::System,
            'outcome' => Outcome::UPHOLD,
            'rationale' => null,
            'supersedes_id' => null,
        ];
    }

    public function withOutcome(string $outcome): static
    {
        return $this->state(['outcome' => $outcome]);
    }

    public function decidedBy(Model $decider): static
    {
        return $this->state([
            'decider_type' => $decider->getMorphClass(),
            'decider_id' => $decider->getKey(),
            'origin' => Origin::Model,
        ]);
    }
}
