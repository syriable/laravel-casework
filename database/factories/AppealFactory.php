<?php

declare(strict_types=1);

namespace Syriable\Casework\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Appeals\AppealState;
use Syriable\Casework\Appeals\Models\Appeal;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Support\Origin;

/**
 * Creation-state factory: appeals start `submitted`.
 *
 * @extends Factory<Appeal>
 */
class AppealFactory extends Factory
{
    protected $model = Appeal::class;

    public function definition(): array
    {
        return [
            'appealed_type' => (new Restriction)->getMorphClass(),
            'appealed_id' => RestrictionFactory::new(),
            'appellant_type' => null,
            'appellant_id' => null,
            'origin' => Origin::System,
            'statement' => null,
            'state' => AppealState::Submitted->value,
            'reviewer_type' => null,
            'reviewer_id' => null,
            'resulting_decision_id' => null,
        ];
    }

    public function against(Model $target): static
    {
        return $this->state([
            'appealed_type' => $target->getMorphClass(),
            'appealed_id' => $target->getKey(),
        ]);
    }

    public function by(Model $appellant): static
    {
        return $this->state([
            'appellant_type' => $appellant->getMorphClass(),
            'appellant_id' => $appellant->getKey(),
            'origin' => Origin::Model,
        ]);
    }
}
