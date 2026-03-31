<?php

use App\Models\User;
use OnaOnbir\Subscription\Actions\CancelSubscription;
use OnaOnbir\Subscription\Actions\CreateSubscription;
use OnaOnbir\Subscription\Enums\SubscriptionStatus;
use OnaOnbir\Subscription\Events\SubscriptionCanceled;
use OnaOnbir\Subscription\Models\Plan;
use OnaOnbir\Subscription\Support\PlanSnapshotBuilder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->cancelAction = new CancelSubscription;
    $this->user = User::factory()->create();
    $this->plan = Plan::factory()->create(['prices' => ['TRY' => 9900]]);
    $this->subscription = (new CreateSubscription(new PlanSnapshotBuilder))
        ->handle($this->user, $this->plan, 'TRY');
});

it('cancels immediately', function () {
    Event::fake();

    $result = $this->cancelAction->handle($this->subscription, immediately: true, reason: 'user_request');

    expect($result->status)->toBe(SubscriptionStatus::Canceled)
        ->and($result->canceled_at)->not->toBeNull()
        ->and($result->canceled_reason)->toBe('user_request');

    Event::assertDispatched(SubscriptionCanceled::class);
});

it('schedules cancellation at end of period', function () {
    Event::fake();

    $result = $this->cancelAction->handle($this->subscription, immediately: false, reason: 'too_expensive');

    expect($result->status)->toBe(SubscriptionStatus::Active)
        ->and($result->cancels_at)->not->toBeNull()
        ->and($result->cancels_at->toDateTimeString())->toBe($this->subscription->ends_at->toDateTimeString())
        ->and($result->canceled_at)->toBeNull()
        ->and($result->hasCancelScheduled())->toBeTrue();

    Event::assertDispatched(SubscriptionCanceled::class);
});

it('resumes a scheduled cancellation', function () {
    $this->cancelAction->handle($this->subscription, immediately: false);

    expect($this->subscription->hasCancelScheduled())->toBeTrue();

    $result = $this->cancelAction->resume($this->subscription);

    expect($result->cancels_at)->toBeNull()
        ->and($result->canceled_reason)->toBeNull()
        ->and($result->hasCancelScheduled())->toBeFalse();
});

it('throws exception when canceling already canceled subscription', function () {
    $this->subscription->update(['status' => SubscriptionStatus::Canceled, 'canceled_at' => now()]);

    $this->cancelAction->handle($this->subscription, immediately: true);
})->throws(\OnaOnbir\Subscription\Exceptions\InvalidSubscriptionStateException::class, 'Cannot cancel subscription with status: canceled');

it('throws exception when canceling expired subscription', function () {
    $this->subscription->update(['status' => SubscriptionStatus::Expired]);

    $this->cancelAction->handle($this->subscription, immediately: true);
})->throws(\OnaOnbir\Subscription\Exceptions\InvalidSubscriptionStateException::class, 'Cannot cancel subscription with status: expired');
