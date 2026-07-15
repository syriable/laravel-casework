<?php

declare(strict_types=1);

namespace Syriable\Casework\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Syriable\Casework\Cases\Models\Note;
use Syriable\Casework\Support\Origin;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    protected $model = Note::class;

    public function definition(): array
    {
        return [
            'case_id' => CaseFileFactory::new(),
            'author_type' => null,
            'author_id' => null,
            'origin' => Origin::System,
            'body' => $this->faker->sentence(),
        ];
    }
}
