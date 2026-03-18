<?php

namespace App\Subscription\Support;

use Illuminate\Database\Eloquent\Model;

class FeatureLimitCalculator
{
    public static function totalLimit(Model $subscribable, string $code): ?int
    {
        $planLimit = static::planFeatureLimit($subscribable, $code);
        $directLimit = static::directFeatureLimit($subscribable, $code);

        if ($planLimit === null && $directLimit === null) {
            return null;
        }

        return ($planLimit ?? 0) + ($directLimit ?? 0);
    }

    public static function planFeatureLimit(Model $subscribable, string $code): ?int
    {
        if (! method_exists($subscribable, 'activeSubscriptions')) {
            return null;
        }

        $total = null;

        foreach ($subscribable->activeSubscriptions() as $subscription) {
            $features = $subscription->plan_snapshot['features'] ?? [];

            foreach ($features as $feature) {
                if ($feature['code'] === $code && $feature['value'] !== null) {
                    $total = ($total ?? 0) + (int) $feature['value'];
                }
            }
        }

        return $total;
    }

    public static function directFeatureLimit(Model $subscribable, string $code): ?int
    {
        if (! method_exists($subscribable, 'subscribableFeatures')) {
            return null;
        }

        $total = null;
        $now = now();

        $directFeatures = $subscribable->subscribableFeatures()
            ->whereHas('feature', fn ($query) => $query->where('code', $code))
            ->where('valid_from', '<=', $now)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>', $now))
            ->get();

        foreach ($directFeatures as $directFeature) {
            if ($directFeature->value !== null) {
                $total = ($total ?? 0) + (int) $directFeature->value;
            }
        }

        return $total;
    }

    public static function hasOveragePricing(Model $subscribable, string $code): bool
    {
        if (method_exists($subscribable, 'activeSubscriptions')) {
            foreach ($subscribable->activeSubscriptions() as $subscription) {
                $features = $subscription->plan_snapshot['features'] ?? [];

                foreach ($features as $feature) {
                    if ($feature['code'] === $code && ! empty($feature['overage_prices'])) {
                        return true;
                    }
                }
            }
        }

        if (method_exists($subscribable, 'subscribableFeatures')) {
            $now = now();

            $hasOverage = $subscribable->subscribableFeatures()
                ->whereHas('feature', fn ($query) => $query->where('code', $code))
                ->where('valid_from', '<=', $now)
                ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>', $now))
                ->whereNotNull('overage_prices')
                ->exists();

            if ($hasOverage) {
                return true;
            }
        }

        return false;
    }
}
