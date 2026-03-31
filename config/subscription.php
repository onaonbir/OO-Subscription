<?php

use OnaOnbir\Subscription\Models\Feature;
use OnaOnbir\Subscription\Models\FeatureUsage;
use OnaOnbir\Subscription\Models\Plan;
use OnaOnbir\Subscription\Models\PlanFeature;
use OnaOnbir\Subscription\Models\SubscribableFeature;
use OnaOnbir\Subscription\Models\Subscription;
use OnaOnbir\Subscription\Models\UsageRecord;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency code used when no currency is explicitly provided.
    |
    */
    'default_currency' => 'TRY',

    /*
    |--------------------------------------------------------------------------
    | Grace Period
    |--------------------------------------------------------------------------
    |
    | The default number of grace period days after a subscription expires
    | before it is fully deactivated. Plans can override this value.
    |
    */
    'grace_period_days' => 3,

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | You may swap the package's default Eloquent models with your own by
    | specifying their fully qualified class names below. Your custom models
    | should extend the corresponding package model.
    |
    */
    'models' => [
        'plan' => Plan::class,
        'feature' => Feature::class,
        'subscription' => Subscription::class,
        'plan_feature' => PlanFeature::class,
        'subscribable_feature' => SubscribableFeature::class,
        'feature_usage' => FeatureUsage::class,
        'usage_record' => UsageRecord::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Customize table names if you need to avoid conflicts or follow different
    | naming conventions. All migrations respect these values.
    |
    */
    'tables' => [
        'plans' => 'plans',
        'features' => 'features',
        'plan_features' => 'plan_features',
        'subscriptions' => 'subscriptions',
        'subscribable_features' => 'subscribable_features',
        'feature_usages' => 'feature_usages',
        'usage_records' => 'usage_records',
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway
    |--------------------------------------------------------------------------
    |
    | Configure your payment gateway integration. The 'driver' key selects
    | which gateway to use. The 'handler' must implement the PaymentGateway
    | contract. Set driver to null to disable gateway integration.
    |
    | You are responsible for implementing the gateway, webhook routes,
    | and webhook handling in your application. The package provides
    | Actions (CancelSubscription, RenewSubscription, etc.) that your
    | webhook controller should call.
    |
    */
    'gateway' => [
        'driver' => null,
        'handler' => null, // e.g. App\Gateways\StripeGateway::class
    ],

];
