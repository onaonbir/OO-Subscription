<?php

use App\Models\User;
use OnaOnbir\Subscription\Actions\CreateSubscription;
use OnaOnbir\Subscription\Enums\BillingInterval;
use OnaOnbir\Subscription\Enums\SubscriptionStatus;
use OnaOnbir\Subscription\Events\SubscriptionActivated;
use OnaOnbir\Subscription\Events\SubscriptionCreated;
use OnaOnbir\Subscription\Models\Feature;
use OnaOnbir\Subscription\Models\Plan;
use OnaOnbir\Subscription\Support\PlanSnapshotBuilder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->action = new CreateSubscription(new PlanSnapshotBuilder);
});

it('creates a monthly subscription', function () {
    Event::fake();

    $user = User::factory()->create();
    $plan = Plan::factory()->create([
        'billing_interval' => BillingInterval::Monthly,
        'prices' => ['TRY' => 9900],
        'trial_days' => 0,
    ]);

    $subscription = $this->action->handle($user, $plan, 'TRY');

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->plan_id)->toBe($plan->id)
        ->and($subscription->subscribable_id)->toBe($user->id)
        ->and($subscription->starts_at)->not->toBeNull()
        ->and((int) abs($subscription->ends_at->diffInDays($subscription->starts_at)))->toBeBetween(28, 31)
        ->and($subscription->trial_ends_at)->toBeNull()
        ->and($subscription->plan_snapshot['price']['amount'])->toBe(9900);

    Event::assertDispatched(SubscriptionCreated::class);
    Event::assertDispatched(SubscriptionActivated::class);
});

it('creates a yearly subscription', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->yearly()->create(['prices' => ['TRY' => 99900]]);

    $subscription = $this->action->handle($user, $plan, 'TRY');

    expect((int) abs($subscription->ends_at->diffInDays($subscription->starts_at)))->toBeBetween(364, 366);
});

it('creates a lifetime subscription with null ends_at', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->lifetime()->create(['prices' => ['TRY' => 299900]]);

    $subscription = $this->action->handle($user, $plan, 'TRY');

    expect($subscription->ends_at)->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Active);
});

it('creates a trialing subscription', function () {
    Event::fake();

    $user = User::factory()->create();
    $plan = Plan::factory()->withTrial(14)->create([
        'billing_interval' => BillingInterval::Monthly,
        'prices' => ['TRY' => 9900],
    ]);

    $subscription = $this->action->handle($user, $plan, 'TRY');

    expect($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->trial_ends_at)->not->toBeNull()
        ->and($subscription->onTrial())->toBeTrue();

    Event::assertDispatched(SubscriptionCreated::class);
    Event::assertNotDispatched(SubscriptionActivated::class);
});

it('stores gateway information', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['prices' => ['TRY' => 9900]]);

    $subscription = $this->action->handle($user, $plan, 'TRY', 'stripe', 'sub_123');

    expect($subscription->gateway)->toBe('stripe')
        ->and($subscription->gateway_subscription_id)->toBe('sub_123');
});

it('includes features in plan snapshot', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['prices' => ['TRY' => 9900]]);

    $feature = Feature::factory()->quantity()->create(['code' => 'api-requests']);
    $plan->features()->attach($feature, ['value' => '10000']);

    $subscription = $this->action->handle($user, $plan, 'TRY');

    expect($subscription->plan_snapshot['features'])->toHaveCount(1)
        ->and($subscription->plan_snapshot['features'][0]['code'])->toBe('api-requests');
});

it('allows multiple active subscriptions for different plans', function () {
    $user = User::factory()->create();
    $plan1 = Plan::factory()->create(['prices' => ['TRY' => 9900]]);
    $plan2 = Plan::factory()->create(['prices' => ['TRY' => 4900]]);

    $sub1 = $this->action->handle($user, $plan1, 'TRY');
    $sub2 = $this->action->handle($user, $plan2, 'TRY');

    expect($sub1->isActive())->toBeTrue()
        ->and($sub2->isActive())->toBeTrue()
        ->and($user->subscriptions()->count())->toBe(2);
});

it('throws exception when subscribing to same plan twice', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['prices' => ['TRY' => 9900]]);

    $this->action->handle($user, $plan, 'TRY');
    $this->action->handle($user, $plan, 'TRY');
})->throws(\OnaOnbir\Subscription\Exceptions\DuplicateSubscriptionException::class);
