<?php

namespace OnaOnbir\Subscription\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use OnaOnbir\Subscription\Enums\SubscriptionStatus;
use OnaOnbir\Subscription\Models\Plan;
use OnaOnbir\Subscription\Models\Subscription;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscribable_type' => (new User)->getMorphClass(),
            'subscribable_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'plan_snapshot' => [
                'plan' => ['id' => 'placeholder', 'slug' => ['en' => 'pro'], 'name' => ['en' => 'Pro'], 'billing_interval' => 'monthly'],
                'price' => ['amount' => 9900, 'currency' => 'TRY'],
                'features' => [],
                'captured_at' => now()->toIso8601String(),
            ],
            'status' => SubscriptionStatus::Active,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ];
    }

    public function trialing(int $days = 14): static
    {
        return $this->state([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays($days),
            'ends_at' => now()->addDays($days)->addMonth(),
        ]);
    }

    public function canceled(): static
    {
        return $this->state([
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => now(),
            'canceled_reason' => 'user_request',
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => SubscriptionStatus::Expired,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state([
            'status' => SubscriptionStatus::PastDue,
            'grace_ends_at' => now()->addDays(3),
        ]);
    }
}
