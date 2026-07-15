<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\UlidPost;

/**
 * @extends Factory<UlidPost>
 */
class UlidPostFactory extends Factory
{
    protected $model = UlidPost::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
        ];
    }
}
