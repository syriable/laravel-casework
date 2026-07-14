<?php

declare(strict_types=1);

namespace Syriable\Casework\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Enforcement\Models\Warning;
use Syriable\Casework\Support\Origin;
use Workbench\App\Models\Post;

/**
 * @extends Factory<Warning>
 */
class WarningFactory extends Factory
{
    protected $model = Warning::class;

    public function definition(): array
    {
        return [
            'subject_type' => (new Post)->getMorphClass(),
            'subject_id' => Post::factory(),
            'issuer_type' => null,
            'issuer_id' => null,
            'origin' => Origin::System,
            'decision_id' => null,
            'reason' => $this->faker->sentence(),
            'expires_at' => null,
        ];
    }

    public function about(Model $subject): static
    {
        return $this->state([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
