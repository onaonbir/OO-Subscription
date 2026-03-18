<?php

use App\Models\User;
use App\Subscription\Enums\BillingInterval;
use App\Subscription\Enums\SubscriptionStatus;
use App\Subscription\Events\BillingCycleCompleted;
use App\Subscription\Events\SubscriptionActivated;
use App\Subscription\Events\SubscriptionCanceled;
use App\Subscription\Events\SubscriptionExpired;
use App\Subscription\Models\Feature;
use App\Subscription\Models\FeatureUsage;
use App\Subscription\Models\Plan;
use App\Subscription\Models\Subscription;
use Illuminate\Support\Facades\Event;

it('expires active subscriptions past ends_at', function () {
    Event::fake();

    $subscription = Subscription::factory()->create([
        'status' => SubscriptionStatus::Active,
        'ends_at' => now()->subDay(),
    ]);

    $this->artisan('subscription:process', ['--expired' => true])
        ->assertSuccessful();

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Expired);

    Event::assertDispatched(SubscriptionExpired::class, function ($event) use ($subscription) {
        return $event->subscription->id === $subscription->id;
    });
});

it('does not expire lifetime subscriptions', function () {
    Event::fake();

    $subscription = Subscription::factory()->create([
        'status' => SubscriptionStatus::Active,
        'ends_at' => null,
    ]);

    $this->artisan('subscription:process', ['--expired' => true])
        ->assertSuccessful();

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Active);

    Event::assertNotDispatched(SubscriptionExpired::class);
});

it('activates trialing subscriptions past trial_ends_at', function () {
    Event::fake();

    $subscription = Subscription::factory()->trialing()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    $this->artisan('subscription:process', ['--trials' => true])
        ->assertSuccessful();

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Active);

    Event::assertDispatched(SubscriptionActivated::class, function ($event) use ($subscription) {
        return $event->subscription->id === $subscription->id;
    });
});

it('does not activate trialing subscriptions with future trial_ends_at', function () {
    Event::fake();

    $subscription = Subscription::factory()->trialing()->create([
        'trial_ends_at' => now()->addDays(5),
    ]);

    $this->artisan('subscription:process', ['--trials' => true])
        ->assertSuccessful();

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Trialing);

    Event::assertNotDispatched(SubscriptionActivated::class);
});

it('expires past_due subscriptions past grace_ends_at', function () {
    Event::fake();

    $subscription = Subscription::factory()->pastDue()->create([
        'grace_ends_at' => now()->subDay(),
    ]);

    $this->artisan('subscription:process', ['--grace' => true])
        ->assertSuccessful();

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Expired);

    Event::assertDispatched(SubscriptionExpired::class, function ($event) use ($subscription) {
        return $event->subscription->id === $subscription->id;
    });
});

it('executes scheduled cancellations past cancels_at', function () {
    Event::fake();

    $subscription = Subscription::factory()->create([
        'status' => SubscriptionStatus::Active,
        'cancels_at' => now()->subDay(),
        'canceled_at' => null,
    ]);

    $this->artisan('subscription:process', ['--cancellations' => true])
        ->assertSuccessful();

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Canceled)
        ->and($subscription->canceled_at)->not->toBeNull()
        ->and($subscription->canceled_reason)->toBe('scheduled');

    Event::assertDispatched(SubscriptionCanceled::class);
});

it('does not cancel already canceled subscriptions', function () {
    Event::fake();

    $subscription = Subscription::factory()->create([
        'status' => SubscriptionStatus::Active,
        'cancels_at' => now()->subDay(),
        'canceled_at' => now()->subHour(),
    ]);

    $this->artisan('subscription:process', ['--cancellations' => true])
        ->assertSuccessful();

    Event::assertNotDispatched(SubscriptionCanceled::class);
});

it('resets usage cycles past resets_at', function () {
    Event::fake();

    $user = User::factory()->create();
    $feature = Feature::factory()->create(['code' => 'api-calls', 'resettable' => true]);

    $usage = FeatureUsage::create([
        'subscribable_type' => $user->getMorphClass(),
        'subscribable_id' => $user->id,
        'feature_code' => 'api-calls',
        'used' => 50,
        'resets_at' => now()->subDay(),
    ]);

    $this->artisan('subscription:process', ['--usage-reset' => true])
        ->assertSuccessful();

    $usage->refresh();

    expect($usage->used)->toBe(0)
        ->and($usage->resets_at)->not->toBeNull()
        ->and($usage->resets_at->isFuture())->toBeTrue();

    Event::assertDispatched(BillingCycleCompleted::class, function ($event) {
        return $event->featureCode === 'api-calls' && $event->used === 50;
    });
});

it('does not reset usage with zero used', function () {
    Event::fake();

    $user = User::factory()->create();

    FeatureUsage::create([
        'subscribable_type' => $user->getMorphClass(),
        'subscribable_id' => $user->id,
        'feature_code' => 'api-calls',
        'used' => 0,
        'resets_at' => now()->subDay(),
    ]);

    $this->artisan('subscription:process', ['--usage-reset' => true])
        ->assertSuccessful();

    Event::assertNotDispatched(BillingCycleCompleted::class);
});

it('dry run does not modify records', function () {
    Event::fake();

    $subscription = Subscription::factory()->create([
        'status' => SubscriptionStatus::Active,
        'ends_at' => now()->subDay(),
    ]);

    $this->artisan('subscription:process', ['--expired' => true, '--dry-run' => true])
        ->assertSuccessful();

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Active);

    Event::assertNotDispatched(SubscriptionExpired::class);
});

it('processes all when no flags given', function () {
    Event::fake();

    $expired = Subscription::factory()->create([
        'status' => SubscriptionStatus::Active,
        'ends_at' => now()->subDay(),
    ]);

    $trialing = Subscription::factory()->trialing()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    $pastDue = Subscription::factory()->pastDue()->create([
        'grace_ends_at' => now()->subDay(),
    ]);

    $scheduled = Subscription::factory()->create([
        'status' => SubscriptionStatus::Active,
        'cancels_at' => now()->subDay(),
        'canceled_at' => null,
    ]);

    $this->artisan('subscription:process')
        ->assertSuccessful();

    expect($expired->refresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($trialing->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($pastDue->refresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($scheduled->refresh()->status)->toBe(SubscriptionStatus::Canceled);

    Event::assertDispatched(SubscriptionExpired::class);
    Event::assertDispatched(SubscriptionActivated::class);
    Event::assertDispatched(SubscriptionCanceled::class);
});

it('respects individual flags', function () {
    Event::fake();

    $expired = Subscription::factory()->create([
        'status' => SubscriptionStatus::Active,
        'ends_at' => now()->subDay(),
    ]);

    $trialing = Subscription::factory()->trialing()->create([
        'trial_ends_at' => now()->subDay(),
    ]);

    $this->artisan('subscription:process', ['--expired' => true])
        ->assertSuccessful();

    expect($expired->refresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($trialing->refresh()->status)->toBe(SubscriptionStatus::Trialing);

    Event::assertDispatched(SubscriptionExpired::class);
    Event::assertNotDispatched(SubscriptionActivated::class);
});
