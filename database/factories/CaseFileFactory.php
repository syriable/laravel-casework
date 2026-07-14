<?php

declare(strict_types=1);

namespace Syriable\Casework\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Cases\CaseState;
use Syriable\Casework\Cases\Models\CaseFile;
use Workbench\App\Models\Post;

/**
 * Creation-state factory: cases start `open` (implementation plan M2).
 *
 * @extends Factory<CaseFile>
 */
class CaseFileFactory extends Factory
{
    protected $model = CaseFile::class;

    public function definition(): array
    {
        return [
            'subject_type' => (new Post)->getMorphClass(),
            'subject_id' => Post::factory(),
            'state' => CaseState::Open->value,
            'priority' => 'normal',
            'assignee_type' => null,
            'assignee_id' => null,
        ];
    }

    public function about(Model $subject): static
    {
        return $this->state([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ]);
    }

    public function assignedTo(Model $assignee): static
    {
        return $this->state([
            'assignee_type' => $assignee->getMorphClass(),
            'assignee_id' => $assignee->getKey(),
        ]);
    }
}
