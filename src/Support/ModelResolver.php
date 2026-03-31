<?php

namespace OnaOnbir\Subscription\Support;

use OnaOnbir\Subscription\Models\Feature;
use OnaOnbir\Subscription\Models\FeatureUsage;
use OnaOnbir\Subscription\Models\Plan;
use OnaOnbir\Subscription\Models\PlanFeature;
use OnaOnbir\Subscription\Models\SubscribableFeature;
use OnaOnbir\Subscription\Models\Subscription;
use OnaOnbir\Subscription\Models\UsageRecord;

class ModelResolver
{
    public static function plan(): string
    {
        return config('subscription.models.plan', Plan::class);
    }

    public static function feature(): string
    {
        return config('subscription.models.feature', Feature::class);
    }

    public static function subscription(): string
    {
        return config('subscription.models.subscription', Subscription::class);
    }

    public static function planFeature(): string
    {
        return config('subscription.models.plan_feature', PlanFeature::class);
    }

    public static function subscribableFeature(): string
    {
        return config('subscription.models.subscribable_feature', SubscribableFeature::class);
    }

    public static function featureUsage(): string
    {
        return config('subscription.models.feature_usage', FeatureUsage::class);
    }

    public static function usageRecord(): string
    {
        return config('subscription.models.usage_record', UsageRecord::class);
    }
}
