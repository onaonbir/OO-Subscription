<?php

namespace OnaOnbir\Subscription\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use OnaOnbir\Subscription\Enums\BillingInterval;
use OnaOnbir\Subscription\Models\Plan;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => ['en' => fake()->unique()->slug(2), 'tr' => fake()->unique()->slug(2)],
            'name' => ['en' => fake()->words(2, true), 'tr' => fake()->words(2, true)],
            'description' => ['en' => fake()->sentence(), 'tr' => fake()->sentence()],
            'prices' => ['TRY' => fake()->numberBetween(1000, 50000), 'USD' => fake()->numberBetween(100, 5000)],
            'billing_interval' => BillingInterval::Monthly,
            'trial_days' => 0,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function yearly(): static
    {
        return $this->state(['billing_interval' => BillingInterval::Yearly]);
    }

    public function lifetime(): static
    {
        return $this->state(['billing_interval' => BillingInterval::Lifetime]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withTrial(int $days = 14): static
    {
        return $this->state(['trial_days' => $days]);
    }
}
