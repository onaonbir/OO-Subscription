<?php

namespace App\Subscription\Support;

use App\Subscription\Models\Plan;

class PlanSnapshotBuilder
{
    public function build(Plan $plan, string $currency): array
    {
        $plan->loadMissing('features');

        return [
            'plan' => [
                'id' => $plan->id,
                'slug' => $plan->slug,
                'name' => $plan->name,
                'billing_interval' => $plan->billing_interval->value,
            ],
            'price' => [
                'amount' => $plan->getPrice($currency),
                'currency' => $currency,
            ],
            'features' => $plan->features->map(fn ($feature) => [
                'code' => $feature->code,
                'type' => $feature->type->value,
                'value' => $feature->pivot->value,
                'resettable' => $feature->resettable,
                'overage_prices' => $feature->pivot->overage_prices,
            ])->values()->all(),
            'captured_at' => now()->toIso8601String(),
        ];
    }
}
