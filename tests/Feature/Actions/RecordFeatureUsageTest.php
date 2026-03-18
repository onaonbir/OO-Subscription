<?php

use App\Models\User;
use App\Subscription\Actions\CreateSubscription;
use App\Subscription\Actions\RecordFeatureUsage;
use App\Subscription\Concerns\HasSubscriptions;
use App\Subscription\Events\FeatureLimitReached;
use App\Subscription\Events\UsageRecorded;
use App\Subscription\Exceptions\FeatureLimitExceededException;
use App\Subscription\Models\Feature;
use App\Subscription\Models\FeatureUsage;
use App\Subscription\Models\Plan;
use App\Subscription\Models\SubscribableFeature;
use App\Subscription\Support\PlanSnapshotBuilder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->action = new RecordFeatureUsage;
    $this->user = User::factory()->create();
    $this->feature = Feature::factory()->quantity()->create([
        'code' => 'api-requests',
        'resettable' => true,
    ]);
    $this->plan = Plan::factory()->create(['prices' => ['TRY' => 9900]]);
    $this->plan->features()->attach($this->feature, ['value' => '100']);

    (new CreateSubscription(new PlanSnapshotBuilder))
        ->handle($this->user, $this->plan, 'TRY');
});

it('records usage and increments counter', function () {
    $usage = $this->action->handle($this->user, 'api-requests', 5);

    expect($usage->used)->toBe(5)
        ->and($usage->feature_code)->toBe('api-requests');
});

it('creates a usage record for audit trail', function () {
    $this->action->handle($this->user, 'api-requests', 5, ['endpoint' => '/api/data']);

    $record = $this->user->morphMany(\App\Subscription\Models\UsageRecord::class, 'subscribable')->first();

    expect($record)->not->toBeNull()
        ->and($record->amount)->toBe(5)
        ->and($record->feature_code)->toBe('api-requests')
        ->and($record->metadata)->toBe(['endpoint' => '/api/data']);
});

it('dispatches UsageRecorded event', function () {
    Event::fake([UsageRecorded::class]);

    $this->action->handle($this->user, 'api-requests', 3);

    Event::assertDispatched(UsageRecorded::class, function ($event) {
        return $event->featureCode === 'api-requests' && $event->amount === 3;
    });
});

it('increments atomically on multiple calls', function () {
    $this->action->handle($this->user, 'api-requests', 10);
    $this->action->handle($this->user, 'api-requests', 20);
    $this->action->handle($this->user, 'api-requests', 5);

    $usage = FeatureUsage::query()
        ->where('subscribable_id', $this->user->id)
        ->where('feature_code', 'api-requests')
        ->first();

    expect($usage->used)->toBe(35);
});

it('throws exception when limit exceeded without overage pricing', function () {
    $this->action->handle($this->user, 'api-requests', 90);

    $this->action->handle($this->user, 'api-requests', 15);
})->throws(FeatureLimitExceededException::class);

it('dispatches FeatureLimitReached when limit is hit', function () {
    Event::fake([FeatureLimitReached::class]);

    $this->action->handle($this->user, 'api-requests', 100);

    Event::assertDispatched(FeatureLimitReached::class, function ($event) {
        return $event->featureCode === 'api-requests' && $event->limit === 100;
    });
});

it('allows overage when overage pricing exists', function () {
    $plan = Plan::factory()->create(['prices' => ['TRY' => 9900]]);
    $plan->features()->attach($this->feature, [
        'value' => '50',
        'overage_prices' => ['TRY' => 10],
    ]);

    $user = User::factory()->create();
    (new CreateSubscription(new PlanSnapshotBuilder))->handle($user, $plan, 'TRY');

    $this->action->handle($user, 'api-requests', 40);
    $usage = $this->action->handle($user, 'api-requests', 20);

    expect($usage->used)->toBe(60);
});

it('combines plan and direct feature limits', function () {
    $directFeature = SubscribableFeature::create([
        'subscribable_type' => $this->user->getMorphClass(),
        'subscribable_id' => $this->user->id,
        'feature_id' => $this->feature->id,
        'value' => '50',
        'valid_from' => now(),
    ]);

    $this->action->handle($this->user, 'api-requests', 140);

    $usage = FeatureUsage::query()
        ->where('subscribable_id', $this->user->id)
        ->where('feature_code', 'api-requests')
        ->first();

    expect($usage->used)->toBe(140);
});

it('resets usage when period expires', function () {
    Event::fake([\App\Subscription\Events\BillingCycleCompleted::class]);

    $usage = FeatureUsage::create([
        'subscribable_type' => $this->user->getMorphClass(),
        'subscribable_id' => $this->user->id,
        'feature_code' => 'api-requests',
        'used' => 80,
        'resets_at' => now()->subDay(),
    ]);

    $result = $this->action->handle($this->user, 'api-requests', 5);

    expect($result->used)->toBe(5);

    Event::assertDispatched(\App\Subscription\Events\BillingCycleCompleted::class);
});

it('throws exception for negative amount', function () {
    $this->action->handle($this->user, 'api-requests', -5);
})->throws(\InvalidArgumentException::class, 'Usage amount must be at least 1.');

it('throws exception for zero amount', function () {
    $this->action->handle($this->user, 'api-requests', 0);
})->throws(\InvalidArgumentException::class, 'Usage amount must be at least 1.');

it('throws exception for boolean feature usage', function () {
    $boolFeature = Feature::factory()->boolean()->create(['code' => 'priority-support']);
    $this->plan->features()->attach($boolFeature, ['value' => 'true']);

    $this->action->handle($this->user, 'priority-support', 1);
})->throws(\InvalidArgumentException::class, 'Cannot record usage for boolean feature [priority-support].');

it('throws exception for non-existent feature', function () {
    $this->action->handle($this->user, 'nonexistent-feature', 1);
})->throws(\InvalidArgumentException::class, 'Feature [nonexistent-feature] not found.');

it('handles concurrent usage recording atomically', function () {
    $this->action->handle($this->user, 'api-requests', 95);

    try {
        \Illuminate\Support\Facades\DB::transaction(function () {
            $this->action->handle($this->user, 'api-requests', 10);
        });
    } catch (FeatureLimitExceededException) {
        // expected
    }

    $usage = FeatureUsage::query()
        ->where('subscribable_id', $this->user->id)
        ->where('feature_code', 'api-requests')
        ->first();

    expect($usage->used)->toBe(95);
});
