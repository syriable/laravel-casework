<?php

declare(strict_types=1);

namespace Syriable\Casework\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Syriable\Casework\Audit\Models\AuditEntry;
use Syriable\Casework\Support\Origin;
use Workbench\App\Models\Post;

/**
 * @extends Factory<AuditEntry>
 */
class AuditEntryFactory extends Factory
{
    protected $model = AuditEntry::class;

    public function definition(): array
    {
        return [
            'actor_type' => null,
            'actor_id' => null,
            'origin' => Origin::System,
            'action' => 'report.filed',
            'auditable_type' => (new Post)->getMorphClass(),
            'auditable_id' => Post::factory(),
            'payload' => null,
        ];
    }

    public function on(Model $auditable): static
    {
        return $this->state([
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
        ]);
    }

    public function by(Model $actor): static
    {
        return $this->state([
            'actor_type' => $actor->getMorphClass(),
            'actor_id' => $actor->getKey(),
            'origin' => Origin::Model,
        ]);
    }

    public function action(string $action): static
    {
        return $this->state(['action' => $action]);
    }
}
