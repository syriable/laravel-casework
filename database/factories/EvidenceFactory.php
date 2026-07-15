<?php

declare(strict_types=1);

namespace Syriable\Casework\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Syriable\Casework\Cases\Models\Evidence;
use Syriable\Casework\Support\Origin;

/**
 * @extends Factory<Evidence>
 */
class EvidenceFactory extends Factory
{
    protected $model = Evidence::class;

    public function definition(): array
    {
        return [
            'case_id' => CaseFileFactory::new(),
            'subject_type' => null,
            'subject_id' => null,
            'data' => ['note' => $this->faker->sentence()],
            'author_type' => null,
            'author_id' => null,
            'origin' => Origin::System,
        ];
    }
}
