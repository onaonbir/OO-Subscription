<?php

use App\Models\User;
use App\Subscription\Actions\ChangePlan;
use App\Subscription\Actions\CreateSubscription;
use App\Subscription\Enums\BillingInterval;
use App\Subscription\Enums\SubscriptionStatus;
use App\Subscription\Events\PlanChanged;
use App\Subscription\Events\SubscriptionCanceled;
use App\Subscription\Models\Plan;
use App\Subscription\Support\PlanSnapshotBuilder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->snapshotBuilder = new PlanSnapshotBuilder;
    $this->changePlanAction = new ChangePlan($this->snapshotBuilder);
    $this->user = User::factory()->create();
    $this->oldPlan = Plan::factory()->create([
        'name' => ['en' => 'Basic'],
        'prices' => ['TRY' => 4900],
    ]);
    $this->newPlan = Plan::factory()->create([
        'name' => ['en' => 'Pro'],
        'prices' => ['TRY' => 9900],
    ]);
    $this->subscription = (new CreateSubscription($this->snapshotBuilder))
        ->handle($this->user, $this->oldPlan, 'TRY');
});

it('cancels old subscription and creates new one', function () {
    Event::fake();

    $oldId = $this->subscription->id;
    $newSubscription = $this->changePlanAction->handle($this->subscription, $this->newPlan);

    $this->subscription->refresh();

    expect($this->subscription->status)->toBe(SubscriptionStatus::Canceled)
        ->and($this->subscription->canceled_reason)->toBe('plan_changed')
        ->and($newSubscription->id)->not->toBe($oldId)
        ->and($newSubscription->status)->toBe(SubscriptionStatus::Active)
        ->and($newSubscription->plan_id)->toBe($this->newPlan->id);

    Event::assertDispatched(SubscriptionCanceled::class);
    Event::assertDispatched(PlanChanged::class, function ($event) use ($newSubscription) {
        return $event->oldPlan->id === $this->oldPlan->id
            && $event->newPlan->id === $this->newPlan->id
            && $event->newSubscription->id === $newSubscription->id;
    });
});

it('uses new plan snapshot', function () {
    $newSubscription = $this->changePlanAction->handle($this->subscription, $this->newPlan);

    expect($newSubscription->plan_snapshot['price']['amount'])->toBe(9900)
        ->and($newSubscription->plan_snapshot['plan']['name'])->toBe(['en' => 'Pro']);
});

it('changes to a different billing interval', function () {
    $yearlyPlan = Plan::factory()->yearly()->create(['prices' => ['TRY' => 49900]]);

    $newSubscription = $this->changePlanAction->handle($this->subscription, $yearlyPlan);

    expect((int) abs($newSubscription->ends_at->diffInDays($newSubscription->starts_at)))->toBeBetween(364, 366)
        ->and($newSubscription->plan_snapshot['plan']['billing_interval'])->toBe('yearly');
});

it('preserves gateway when not overridden', function () {
    $user = User::factory()->create();
    $subscription = (new CreateSubscription($this->snapshotBuilder))
        ->handle($user, $this->oldPlan, 'TRY', 'stripe', 'sub_old');

    $newSubscription = $this->changePlanAction->handle($subscription, $this->newPlan);

    expect($newSubscription->gateway)->toBe('stripe')
        ->and($newSubscription->gateway_subscription_id)->toBe('sub_old');
});

it('overrides gateway when provided', function () {
    $user = User::factory()->create();
    $subscription = (new CreateSubscription($this->snapshotBuilder))
        ->handle($user, $this->oldPlan, 'TRY', 'stripe', 'sub_old');

    $newSubscription = $this->changePlanAction->handle(
        $subscription,
        $this->newPlan,
        gateway: 'iyzico',
        gatewaySubscriptionId: 'sub_new',
    );

    expect($newSubscription->gateway)->toBe('iyzico')
        ->and($newSubscription->gateway_subscription_id)->toBe('sub_new');
});

it('throws exception when changing plan on canceled subscription', function () {
    $this->subscription->update(['status' => SubscriptionStatus::Canceled, 'canceled_at' => now()]);

    $this->changePlanAction->handle($this->subscription, $this->newPlan);
})->throws(\App\Subscription\Exceptions\InvalidSubscriptionStateException::class, 'Cannot change plan on subscription with status: canceled');

it('throws exception when changing plan on expired subscription', function () {
    $this->subscription->update(['status' => SubscriptionStatus::Expired]);

    $this->changePlanAction->handle($this->subscription, $this->newPlan);
})->throws(\App\Subscription\Exceptions\InvalidSubscriptionStateException::class, 'Cannot change plan on subscription with status: expired');
