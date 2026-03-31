<?php

use App\Models\User;
use OnaOnbir\Subscription\Enums\SubscriptionStatus;
use OnaOnbir\Subscription\Models\FeatureUsage;
use OnaOnbir\Subscription\Models\Subscription;

it('displays correct status counts', function () {
    Subscription::factory()->count(3)->create(['status' => SubscriptionStatus::Active]);
    Subscription::factory()->count(2)->trialing()->create();
    Subscription::factory()->count(1)->pastDue()->create();
    Subscription::factory()->count(1)->canceled()->create();
    Subscription::factory()->count(1)->expired()->create();

    $this->artisan('subscription:status')
        ->assertSuccessful();
});

it('shows pending warnings', function () {
    Subscription::factory()->create([
        'status' => SubscriptionStatus::Trialing,
        'trial_ends_at' => now()->addDays(2),
    ]);

    Subscription::factory()->create([
        'status' => SubscriptionStatus::Active,
        'ends_at' => now()->subDay(),
    ]);

    Subscription::factory()->create([
        'status' => SubscriptionStatus::Active,
        'cancels_at' => now()->subDay(),
        'canceled_at' => null,
    ]);

    $user = User::factory()->create();
    FeatureUsage::create([
        'subscribable_type' => $user->getMorphClass(),
        'subscribable_id' => $user->id,
        'feature_code' => 'api-calls',
        'used' => 10,
        'resets_at' => now()->subDay(),
    ]);

    $this->artisan('subscription:status')
        ->assertSuccessful();
});
