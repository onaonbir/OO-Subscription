<?php

namespace App\Subscription\Support;

class ModelResolver
{
    public static function plan(): string
    {
        return config('subscription.models.plan', \App\Subscription\Models\Plan::class);
    }

    public static function feature(): string
    {
        return config('subscription.models.feature', \App\Subscription\Models\Feature::class);
    }

    public static function subscription(): string
    {
        return config('subscription.models.subscription', \App\Subscription\Models\Subscription::class);
    }

    public static function planFeature(): string
    {
        return config('subscription.models.plan_feature', \App\Subscription\Models\PlanFeature::class);
    }

    public static function subscribableFeature(): string
    {
        return config('subscription.models.subscribable_feature', \App\Subscription\Models\SubscribableFeature::class);
    }

    public static function featureUsage(): string
    {
        return config('subscription.models.feature_usage', \App\Subscription\Models\FeatureUsage::class);
    }

    public static function usageRecord(): string
    {
        return config('subscription.models.usage_record', \App\Subscription\Models\UsageRecord::class);
    }
}
