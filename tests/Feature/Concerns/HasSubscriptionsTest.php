<?php

use App\Models\User;
use OnaOnbir\Subscription\Concerns\HasSubscriptions;
use OnaOnbir\Subscription\Enums\BillingInterval;
use OnaOnbir\Subscription\Enums\SubscriptionStatus;
use OnaOnbir\Subscription\Models\Feature;
use OnaOnbir\Subscription\Models\Plan;
use OnaOnbir\Subscription\Models\SubscribableFeature;
use OnaOnbir\Subscription\Models\Subscription;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->plan = Plan::factory()->create([
        'prices' => ['TRY' => 9900],
        'billing_interval' => BillingInterval::Monthly,
    ]);
});

it('subscribes a user to a plan', function () {
    $subscription = $this->user->subscribe($this->plan, 'TRY');

    expect($subscription)->toBeInstanceOf(Subscription::class)
        ->and($subscription->isActive())->toBeTrue()
        ->and($this->user->subscribed())->toBeTrue();
});

it('checks if subscribed to a specific plan', function () {
    $this->user->subscribe($this->plan, 'TRY');
    $otherPlan = Plan::factory()->create(['prices' => ['TRY' => 4900]]);

    expect($this->user->subscribedTo($this->plan))->toBeTrue()
        ->and($this->user->subscribedTo($otherPlan))->toBeFalse();
});

it('returns active subscriptions', function () {
    $this->user->subscribe($this->plan, 'TRY');
    $plan2 = Plan::factory()->create(['prices' => ['TRY' => 4900]]);
    $this->user->subscribe($plan2, 'TRY');

    expect($this->user->activeSubscriptions())->toHaveCount(2);
});

it('detects trial status', function () {
    $trialPlan = Plan::factory()->withTrial(14)->create(['prices' => ['TRY' => 9900]]);
    $this->user->subscribe($trialPlan, 'TRY');

    expect($this->user->onTrial())->toBeTrue();
});

it('detects grace period', function () {
    Subscription::factory()->for($this->user, 'subscribable')->pastDue()->create([
        'plan_id' => $this->plan->id,
    ]);

    expect($this->user->onGracePeriod())->toBeTrue();
});

it('returns subscription history including expired and canceled', function () {
    $this->user->subscribe($this->plan, 'TRY');

    Subscription::factory()->for($this->user, 'subscribable')->expired()->create([
        'plan_id' => $this->plan->id,
    ]);
    Subscription::factory()->for($this->user, 'subscribable')->canceled()->create([
        'plan_id' => $this->plan->id,
    ]);

    expect($this->user->subscriptionHistory())->toHaveCount(3);
});

it('checks hasFeature from plan subscription', function () {
    $feature = Feature::factory()->boolean()->create(['code' => 'priority-support']);
    $this->plan->features()->attach($feature, ['value' => 'true']);

    $this->user->subscribe($this->plan, 'TRY');

    expect($this->user->hasFeature('priority-support'))->toBeTrue()
        ->and($this->user->hasFeature('nonexistent'))->toBeFalse();
});

it('checks hasFeature from direct assignment', function () {
    $feature = Feature::factory()->boolean()->create(['code' => 'custom-domain']);

    SubscribableFeature::create([
        'subscribable_type' => $this->user->getMorphClass(),
        'subscribable_id' => $this->user->id,
        'feature_id' => $feature->id,
        'value' => 'true',
        'valid_from' => now(),
    ]);

    expect($this->user->hasFeature('custom-domain'))->toBeTrue();
});

it('respects valid_until on direct features', function () {
    $feature = Feature::factory()->boolean()->create(['code' => 'temp-feature']);

    SubscribableFeature::create([
        'subscribable_type' => $this->user->getMorphClass(),
        'subscribable_id' => $this->user->id,
        'feature_id' => $feature->id,
        'value' => 'true',
        'valid_from' => now()->subDays(10),
        'valid_until' => now()->subDay(),
    ]);

    expect($this->user->hasFeature('temp-feature'))->toBeFalse();
});

it('calculates canUseFeature with quantity limits', function () {
    $feature = Feature::factory()->quantity()->create(['code' => 'api-requests']);
    $this->plan->features()->attach($feature, ['value' => '100']);

    $this->user->subscribe($this->plan, 'TRY');

    expect($this->user->canUseFeature('api-requests'))->toBeTrue();

    $this->user->recordUsage('api-requests', 100);

    expect($this->user->canUseFeature('api-requests'))->toBeFalse();
});

it('calculates remainingUsage from all sources', function () {
    $feature = Feature::factory()->quantity()->create(['code' => 'api-requests']);
    $this->plan->features()->attach($feature, ['value' => '100']);

    $this->user->subscribe($this->plan, 'TRY');

    SubscribableFeature::create([
        'subscribable_type' => $this->user->getMorphClass(),
        'subscribable_id' => $this->user->id,
        'feature_id' => $feature->id,
        'value' => '50',
        'valid_from' => now(),
    ]);

    expect($this->user->remainingUsage('api-requests'))->toBe(150);

    $this->user->recordUsage('api-requests', 30);

    expect($this->user->remainingUsage('api-requests'))->toBe(120);
});

it('returns null remainingUsage for metered features', function () {
    $feature = Feature::factory()->metered()->create(['code' => 'metered-calls']);
    $this->plan->features()->attach($feature, ['value' => null]);

    $this->user->subscribe($this->plan, 'TRY');

    expect($this->user->remainingUsage('metered-calls'))->toBeNull();
});

it('canUseFeature returns true when overage pricing exists and limit exceeded', function () {
    $feature = Feature::factory()->quantity()->create(['code' => 'api-requests']);
    $this->plan->features()->attach($feature, ['value' => '100']);

    $this->user->subscribe($this->plan, 'TRY');

    SubscribableFeature::create([
        'subscribable_type' => $this->user->getMorphClass(),
        'subscribable_id' => $this->user->id,
        'feature_id' => $feature->id,
        'value' => '50',
        'overage_prices' => ['TRY' => 10],
        'valid_from' => now(),
    ]);

    $this->user->recordUsage('api-requests', 150);

    expect($this->user->canUseFeature('api-requests'))->toBeTrue();
});

it('remainingUsage returns negative when overage pricing exists and limit exceeded', function () {
    $feature = Feature::factory()->quantity()->create(['code' => 'api-requests']);
    $this->plan->features()->attach($feature, ['value' => '100']);

    $this->user->subscribe($this->plan, 'TRY');

    SubscribableFeature::create([
        'subscribable_type' => $this->user->getMorphClass(),
        'subscribable_id' => $this->user->id,
        'feature_id' => $feature->id,
        'value' => '50',
        'overage_prices' => ['TRY' => 10],
        'valid_from' => now(),
    ]);

    $this->user->recordUsage('api-requests', 155);

    expect($this->user->remainingUsage('api-requests'))->toBe(-5);
});

it('canUseFeature returns true when plan snapshot has overage pricing and limit exceeded', function () {
    $feature = Feature::factory()->quantity()->create(['code' => 'api-requests']);
    $this->plan->features()->attach($feature, [
        'value' => '100',
        'overage_prices' => ['TRY' => 10, 'USD' => 1],
    ]);

    $this->user->subscribe($this->plan, 'TRY');
    $this->user->recordUsage('api-requests', 100);

    expect($this->user->canUseFeature('api-requests'))->toBeTrue();
});

it('records usage via trait method', function () {
    $feature = Feature::factory()->quantity()->create(['code' => 'api-requests']);
    $this->plan->features()->attach($feature, ['value' => '1000']);

    $this->user->subscribe($this->plan, 'TRY');

    $usage = $this->user->recordUsage('api-requests', 25);

    expect($usage->used)->toBe(25)
        ->and($this->user->featureUsages()->count())->toBe(1);
});

it('returns false for hasFeature with expired direct feature', function () {
    $feature = Feature::factory()->boolean()->create(['code' => 'expired-feature']);

    SubscribableFeature::create([
        'subscribable_type' => $this->user->getMorphClass(),
        'subscribable_id' => $this->user->id,
        'feature_id' => $feature->id,
        'value' => 'true',
        'valid_from' => now()->subDays(30),
        'valid_until' => now()->subDay(),
    ]);

    expect($this->user->hasFeature('expired-feature'))->toBeFalse();
});
