<?php

declare(strict_types=1);

namespace Syriable\Casework\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Syriable\Casework\Enforcement\Models\Restriction;
use Syriable\Casework\Enforcement\RestrictionState;
use Syriable\Casework\Support\Origin;
use Syriable\Casework\Support\RestrictionType;
use Workbench\App\Models\Post;

/**
 * Creation-state factory: restrictions start `active`. The expired()
 * state models the real-time rule (I-09) — a stale active row past its
 * expiry — not the formalized `expired` state, which only the expiry
 * transition may write.
 *
 * @extends Factory<Restriction>
 */
class RestrictionFactory extends Factory
{
    protected $model = Restriction::class;

    public function definition(): array
    {
        return [
            'subject_type' => (new Post)->getMorphClass(),
            'subject_id' => Post::factory(),
            'type' => RestrictionType::SUSPENSION,
            'scope' => null,
            'issuer_type' => null,
            'issuer_id' => null,
            'origin' => Origin::System,
            'decision_id' => null,
            'state' => RestrictionState::Active->value,
            'expires_at' => null,
            'lifted_at' => null,
            'lifted_by_type' => null,
            'lifted_by_id' => null,
            'lift_reason' => null,
            'superseded_by_id' => null,
            'rationale' => null,
        ];
    }

    public function about(Model $subject): static
    {
        return $this->state([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ]);
    }

    public function ofType(string $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function issuedBy(Model $issuer): static
    {
        return $this->state([
            'issuer_type' => $issuer->getMorphClass(),
            'issuer_id' => $issuer->getKey(),
            'origin' => Origin::Model,
        ]);
    }

    public function inScope(string $scope): static
    {
        return $this->state(['scope' => $scope]);
    }

    public function expiringAt(Carbon $moment): static
    {
        return $this->state(['expires_at' => $moment]);
    }

    /** Stale active row past its expiry — inactive under the real-time rule. */
    public function stalePastExpiry(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
