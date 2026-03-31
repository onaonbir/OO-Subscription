<?php

use OnaOnbir\Subscription\Enums\BillingInterval;
use OnaOnbir\Subscription\Models\Feature;
use OnaOnbir\Subscription\Models\Plan;
use OnaOnbir\Subscription\Support\PlanSnapshotBuilder;

beforeEach(function () {
    $this->builder = new PlanSnapshotBuilder;
});

it('builds a snapshot with plan data', function () {
    $plan = Plan::factory()->create([
        'slug' => ['en' => 'pro-plan'],
        'name' => ['en' => 'Pro Plan'],
        'prices' => ['TRY' => 19900, 'USD' => 999],
        'billing_interval' => BillingInterval::Monthly,
    ]);

    $snapshot = $this->builder->build($plan, 'TRY');

    expect($snapshot)
        ->toHaveKeys(['plan', 'price', 'features', 'captured_at'])
        ->and($snapshot['plan']['id'])->toBe($plan->id)
        ->and($snapshot['plan']['slug'])->toBe(['en' => 'pro-plan'])
        ->and($snapshot['plan']['billing_interval'])->toBe('monthly')
        ->and($snapshot['price']['amount'])->toBe(19900)
        ->and($snapshot['price']['currency'])->toBe('TRY');
});

it('includes features from pivot', function () {
    $plan = Plan::factory()->create([
        'prices' => ['TRY' => 19900],
        'billing_interval' => BillingInterval::Monthly,
    ]);

    $feature = Feature::factory()->quantity()->create([
        'code' => 'api-requests',
        'resettable' => true,
    ]);

    $plan->features()->attach($feature, [
        'value' => '10000',
        'overage_prices' => ['TRY' => 10],
    ]);

    $snapshot = $this->builder->build($plan, 'TRY');

    expect($snapshot['features'])->toHaveCount(1)
        ->and($snapshot['features'][0])->toMatchArray([
            'code' => 'api-requests',
            'type' => 'quantity',
            'value' => '10000',
            'resettable' => true,
        ])
        ->and($snapshot['features'][0]['overage_prices'])->toBe(['TRY' => 10]);
});

it('builds snapshot with different currency', function () {
    $plan = Plan::factory()->create([
        'prices' => ['TRY' => 19900, 'USD' => 999],
    ]);

    $snapshot = $this->builder->build($plan, 'USD');

    expect($snapshot['price']['amount'])->toBe(999)
        ->and($snapshot['price']['currency'])->toBe('USD');
});

it('includes boolean features', function () {
    $plan = Plan::factory()->create(['prices' => ['TRY' => 9900]]);

    $feature = Feature::factory()->boolean()->create([
        'code' => 'priority-support',
    ]);

    $plan->features()->attach($feature, ['value' => 'true']);

    $snapshot = $this->builder->build($plan, 'TRY');

    expect($snapshot['features'][0])->toMatchArray([
        'code' => 'priority-support',
        'type' => 'boolean',
        'value' => 'true',
    ]);
});
