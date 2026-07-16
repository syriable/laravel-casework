<?php

declare(strict_types=1);

namespace Syriable\Casework\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Reporting\Models\ReporterReputation;
use Workbench\App\Models\Post;

/**
 * @extends Factory<ReporterReputation>
 */
class ReporterReputationFactory extends Factory
{
    protected $model = ReporterReputation::class;

    public function definition(): array
    {
        return [
            'reporter_type' => (new Post)->getMorphClass(),
            'reporter_id' => Post::factory(),
            'score' => 0,
        ];
    }

    public function forReporter(Model $reporter): static
    {
        return $this->state([
            'reporter_type' => $reporter->getMorphClass(),
            'reporter_id' => $reporter->getKey(),
        ]);
    }

    public function withScore(int $score): static
    {
        return $this->state(['score' => $score]);
    }
}
