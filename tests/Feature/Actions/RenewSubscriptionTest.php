<?php

use App\Models\User;
use OnaOnbir\Subscription\Actions\CreateSubscription;
use OnaOnbir\Subscription\Actions\RenewSubscription;
use OnaOnbir\Subscription\Enums\SubscriptionStatus;
use OnaOnbir\Subscription\Events\SubscriptionExpired;
use OnaOnbir\Subscription\Events\SubscriptionRenewed;
use OnaOnbir\Subscription\Models\Plan;
use OnaOnbir\Subscription\Support\PlanSnapshotBuilder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->snapshotBuilder = new PlanSnapshotBuilder;
    $this->renewAction = new RenewSubscription($this->snapshotBuilder);
    $this->user = User::factory()->create();
    $this->plan = Plan::factory()->create(['prices' => ['TRY' => 9900]]);
    $this->subscription = (new CreateSubscription($this->snapshotBuilder))
        ->handle($this->user, $this->plan, 'TRY');
});

it('renews by creating new row and expiring old', function () {
    Event::fake();

    $oldId = $this->subscription->id;
    $newSubscription = $this->renewAction->handle($this->subscription);

    $this->subscription->refresh();

    expect($this->subscription->status)->toBe(SubscriptionStatus::Expired)
        ->and($newSubscription->id)->not->toBe($oldId)
        ->and($newSubscription->status)->toBe(SubscriptionStatus::Active)
        ->and($newSubscription->plan_id)->toBe($this->plan->id)
        ->and($newSubscription->subscribable_id)->toBe($this->user->id);

    Event::assertDispatched(SubscriptionExpired::class);
    Event::assertDispatched(SubscriptionRenewed::class);
});

it('starts new period from old ends_at', function () {
    $newSubscription = $this->renewAction->handle($this->subscription);

    expect($newSubscription->starts_at->toDateTimeString())
        ->toBe($this->subscription->ends_at->toDateTimeString());
});

it('preserves gateway information', function () {
    $user = User::factory()->create();
    $subscription = (new CreateSubscription($this->snapshotBuilder))
        ->handle($user, $this->plan, 'TRY', 'stripe', 'sub_123');

    $newSubscription = $this->renewAction->handle($subscription);

    expect($newSubscription->gateway)->toBe('stripe')
        ->and($newSubscription->gateway_subscription_id)->toBe('sub_123');
});

it('creates fresh snapshot on renewal', function () {
    $this->travel(1)->minutes();

    $newSubscription = $this->renewAction->handle($this->subscription);

    expect($newSubscription->plan_snapshot)->toHaveKeys(['plan', 'price', 'features', 'captured_at'])
        ->and($newSubscription->plan_snapshot['captured_at'])
        ->not->toBe($this->subscription->plan_snapshot['captured_at']);
});

it('throws exception when renewing expired subscription', function () {
    $this->subscription->update(['status' => SubscriptionStatus::Expired]);

    $this->renewAction->handle($this->subscription);
})->throws(\OnaOnbir\Subscription\Exceptions\InvalidSubscriptionStateException::class, 'Cannot renew subscription with status: expired');

it('throws exception when renewing canceled subscription', function () {
    $this->subscription->update(['status' => SubscriptionStatus::Canceled, 'canceled_at' => now()]);

    $this->renewAction->handle($this->subscription);
})->throws(\OnaOnbir\Subscription\Exceptions\InvalidSubscriptionStateException::class, 'Cannot renew subscription with status: canceled');

it('handles lifetime subscription renewal correctly', function () {
    $lifetimePlan = Plan::factory()->lifetime()->create(['prices' => ['TRY' => 299900]]);
    $user = User::factory()->create();
    $subscription = (new CreateSubscription($this->snapshotBuilder))->handle($user, $lifetimePlan, 'TRY');

    $newSubscription = $this->renewAction->handle($subscription);

    expect($newSubscription->ends_at)->toBeNull()
        ->and($newSubscription->status)->toBe(SubscriptionStatus::Active);
});
