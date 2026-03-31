<?php

namespace OnaOnbir\Subscription\Support;

use OnaOnbir\Subscription\Models\Feature;
use Illuminate\Database\Eloquent\Model;

class ResetDateCalculator
{
    public static function calculate(Model $subscribable, Feature $feature): ?\Carbon\Carbon
    {
        if (! $feature->resettable) {
            return null;
        }

        if (method_exists($subscribable, 'activeSubscriptions')) {
            $activeSubscription = $subscribable->activeSubscriptions()->first();

            if ($activeSubscription && $activeSubscription->ends_at) {
                return $activeSubscription->ends_at;
            }
        }

        return now()->addMonth();
    }
}
