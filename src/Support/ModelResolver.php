<?php

namespace OnaOnbir\Subscription\Support;

class ModelResolver
{
    public static function plan(): string
    {
        return config('subscription.models.plan', \OnaOnbir\Subscription\Models\Plan::class);
    }

    public static function feature(): string
    {
        return config('subscription.models.feature', \OnaOnbir\Subscription\Models\Feature::class);
    }

    public static function subscription(): string
    {
        return config('subscription.models.subscription', \OnaOnbir\Subscription\Models\Subscription::class);
    }

    public static function planFeature(): string
    {
        return config('subscription.models.plan_feature', \OnaOnbir\Subscription\Models\PlanFeature::class);
    }

    public static function subscribableFeature(): string
    {
        return config('subscription.models.subscribable_feature', \OnaOnbir\Subscription\Models\SubscribableFeature::class);
    }

    public static function featureUsage(): string
    {
        return config('subscription.models.feature_usage', \OnaOnbir\Subscription\Models\FeatureUsage::class);
    }

    public static function usageRecord(): string
    {
        return config('subscription.models.usage_record', \OnaOnbir\Subscription\Models\UsageRecord::class);
    }
}
