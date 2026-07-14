<?php

declare(strict_types=1);

namespace Syriable\Casework\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Reporting\Models\Report;
use Syriable\Casework\Reporting\ReportState;
use Syriable\Casework\Support\Origin;
use Workbench\App\Models\Post;

/**
 * Creation-state factory (implementation plan M2): lifecycle states
 * beyond `pending` are reached through actions, never fabricated.
 *
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'subject_type' => (new Post)->getMorphClass(),
            'subject_id' => Post::factory(),
            'reporter_type' => null,
            'reporter_id' => null,
            'origin' => Origin::Anonymous,
            'reason_id' => ReasonFactory::new(),
            'comment' => null,
            'metadata' => null,
            'state' => ReportState::Pending->value,
            'case_id' => null,
            'decision_id' => null,
        ];
    }

    public function about(Model $subject): static
    {
        return $this->state([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ]);
    }

    public function by(Model $reporter): static
    {
        return $this->state([
            'reporter_type' => $reporter->getMorphClass(),
            'reporter_id' => $reporter->getKey(),
            'origin' => Origin::Model,
        ]);
    }

    public function fromSystem(): static
    {
        return $this->state([
            'reporter_type' => null,
            'reporter_id' => null,
            'origin' => Origin::System,
        ]);
    }
}
