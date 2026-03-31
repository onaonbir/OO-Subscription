<?php

namespace OnaOnbir\Subscription\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use OnaOnbir\Subscription\Models\Feature;

class ResetDateCalculator
{
    public static function calculate(Model $subscribable, Feature $feature): ?Carbon
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
