# Laravel Subscription Package

A backend-only subscription management package for Laravel 12. Supports polymorphic subscribables, immutable subscription rows, multi-language content, multi-currency pricing, feature usage tracking with overage billing, and a pluggable payment gateway contract.

- **PHP** 8.4+
- **Laravel** 12
- **Primary Keys**: ULIDs on all models
- **Polymorphic**: Works with any model via `morphTo` (User, Team, etc.)
- **Immutable Rows**: Renewals and plan changes create new subscription rows
- **Multi-language**: JSON fields for slug, name, and description
- **Multi-currency**: JSON `prices` field on plans, per-currency overage pricing

---

## Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Models](#models)
  - [Plan](#plan)
  - [Feature](#feature)
  - [Subscription](#subscription)
  - [PlanFeature (Pivot)](#planfeature-pivot)
  - [SubscribableFeature](#subscribablefeature)
  - [FeatureUsage](#featureusage)
  - [UsageRecord](#usagerecord)
- [Enums](#enums)
- [HasSubscriptions Trait](#hassubscriptions-trait)
- [Actions](#actions)
  - [CreateSubscription](#createsubscription)
  - [CancelSubscription](#cancelsubscription)
  - [RenewSubscription](#renewsubscription)
  - [ChangePlan](#changeplan)
  - [RecordFeatureUsage](#recordfeatureusage)
- [Events](#events)
- [Feature Types](#feature-types)
- [Plan Snapshots](#plan-snapshots)
- [Overage Pricing](#overage-pricing)
- [Direct Feature Assignments](#direct-feature-assignments)
- [Usage Cycle Reset](#usage-cycle-reset)
- [Period-Based Usage & Billing](#period-based-usage--billing)
- [Payment Gateway Integration](#payment-gateway-integration)
  - [PaymentGateway Contract](#paymentgateway-contract)
  - [Implementing a Gateway](#implementing-a-gateway)
  - [Handling Webhooks](#handling-webhooks)
- [Model Customization](#model-customization)
- [Table Customization](#table-customization)
- [Scheduled Commands](#scheduled-commands)
- [Testing](#testing)
- [Quick Start](#quick-start)
- [License](#license)

---

## Installation

Add the package as a path repository in your root `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "packages/subscription"
        }
    ],
    "require": {
        "app/subscription": "*"
    }
}
```

Then install:

```bash
composer require app/subscription
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --tag=subscription-config
php artisan vendor:publish --tag=subscription-migrations
php artisan migrate
```

The service provider (`App\Subscription\SubscriptionServiceProvider`) is auto-discovered.

---

## Configuration

The config file is published to `config/subscription.php`.

| Key | Description | Default |
|-----|-------------|---------|
| `default_currency` | Default currency code for pricing | `'TRY'` |
| `grace_period_days` | Default grace period after expiration | `3` |
| `models` | Override any of the 7 model classes | Base model classes |
| `tables` | Override any of the 7 table names | Default table names |
| `gateway.driver` | Active gateway driver name | `null` |
| `gateway.handler` | PaymentGateway implementation class | `null` |

---

## Models

The package provides 7 models. All use ULIDs as primary keys.

### Plan

Represents a subscription plan with multi-language fields and multi-currency pricing.

| Field | Type | Description |
|-------|------|-------------|
| `slug` | json | Localized slugs (`{"en": "pro", "tr": "pro"}`) |
| `name` | json | Localized names |
| `description` | json | Localized descriptions |
| `prices` | json | Currency-keyed prices in cents (`{"USD": 1490, "TRY": 14900}`) |
| `billing_interval` | enum | `monthly`, `yearly`, or `lifetime` |
| `trial_days` | int | Number of trial days (0 for none) |
| `grace_period_days` | int | Grace period after expiration |
| `sort_order` | int | Display order |
| `is_active` | bool | Whether the plan is available |
| `metadata` | json | Arbitrary metadata |

**Relationships:** `features()` (BelongsToMany), `planFeatures()` (HasMany), `subscriptions()` (HasMany)

**Methods:**

- `getPrice(?string $currency, ?array $prices): mixed` -- Resolve price for a currency.
- `getTranslation(string $field, ?string $locale): string` -- Get a localized field value.
- `getGracePeriodDays(): int` -- Grace period (falls back to config).

Uses `SoftDeletes`.

### Feature

Represents a capability that can be attached to plans or assigned directly.

| Field | Type | Description |
|-------|------|-------------|
| `code` | string | Unique identifier (e.g., `api-requests`) |
| `slug` | json | Localized slugs |
| `name` | json | Localized names |
| `description` | json | Localized descriptions |
| `type` | enum | `boolean`, `quantity`, or `metered` |
| `resettable` | bool | Whether usage resets each billing cycle |
| `metadata` | json | Arbitrary metadata |

**Relationships:** `plans()` (BelongsToMany)

**Methods:** `getTranslation(string $field, ?string $locale): string`

Uses `SoftDeletes`.

### Subscription

An immutable record of a subscribable's subscription to a plan.

| Field | Type | Description |
|-------|------|-------------|
| `subscribable` | morph | Polymorphic owner (User, Team, etc.) |
| `plan_id` | foreign key | Associated plan |
| `plan_snapshot` | json | Immutable snapshot of plan at creation |
| `gateway` | string | Payment gateway identifier |
| `gateway_subscription_id` | string | External subscription ID |
| `status` | enum | `active`, `trialing`, `past_due`, `canceled`, `expired` |
| `trial_ends_at` | datetime | Trial end date |
| `starts_at` | datetime | Subscription start |
| `ends_at` | datetime | Subscription end / next renewal |
| `cancels_at` | datetime | Scheduled cancellation date |
| `canceled_at` | datetime | Actual cancellation timestamp |
| `canceled_reason` | string | Reason for cancellation |
| `grace_ends_at` | datetime | Grace period end |
| `metadata` | json | Arbitrary metadata |

**Relationships:** `subscribable()` (MorphTo), `plan()` (BelongsTo)

**Methods:**

| Method | Returns | Description |
|--------|---------|-------------|
| `isActive()` | `bool` | Status is Active |
| `isTrialing()` | `bool` | Status is Trialing |
| `isPastDue()` | `bool` | Status is PastDue |
| `isCanceled()` | `bool` | Status is Canceled |
| `isExpired()` | `bool` | Status is Expired |
| `isValid()` | `bool` | Active, Trialing, or PastDue |
| `onTrial()` | `bool` | Trialing and trial has not ended |
| `onGracePeriod()` | `bool` | Within grace period |
| `hasCancelScheduled()` | `bool` | Has a future `cancels_at` date |
| `isLifetime()` | `bool` | Billing interval is Lifetime |

### PlanFeature (Pivot)

Pivot model connecting plans to features with plan-specific values.

| Field | Type | Description |
|-------|------|-------------|
| `plan_id` | foreign key | Plan |
| `feature_id` | foreign key | Feature |
| `value` | string | Limit value (e.g., `'1000'`, `'true'`) |
| `overage_prices` | json | Per-currency overage rates |
| `metadata` | json | Arbitrary metadata |

**Relationships:** `plan()`, `feature()`

### SubscribableFeature

Direct feature assignment to a subscribable, independent of any plan.

| Field | Type | Description |
|-------|------|-------------|
| `subscribable` | morph | Polymorphic owner |
| `feature_id` | foreign key | Feature |
| `value` | string | Limit value |
| `overage_prices` | json | Per-currency overage rates |
| `billing_interval` | enum | Billing interval for this assignment |
| `valid_from` | datetime | Start of validity |
| `valid_until` | datetime | End of validity (null = indefinite) |
| `metadata` | json | Arbitrary metadata |

**Methods:** `isActive(): bool`

### FeatureUsage

Tracks current usage of a feature within a billing cycle.

| Field | Type | Description |
|-------|------|-------------|
| `subscribable` | morph | Polymorphic owner |
| `feature_code` | string | Feature code |
| `used` | int | Current usage count |
| `resets_at` | datetime | Next reset date |

**Methods:** `isExpired(): bool`

### UsageRecord

Immutable audit trail of individual usage events.

| Field | Type | Description |
|-------|------|-------------|
| `subscribable` | morph | Polymorphic owner |
| `feature_code` | string | Feature code |
| `amount` | int | Amount recorded |
| `metadata` | json | Context metadata |
| `recorded_at` | datetime | Timestamp of the record |

---

## Enums

### BillingInterval

| Key | Value |
|-----|-------|
| `Monthly` | `'monthly'` |
| `Yearly` | `'yearly'` |
| `Lifetime` | `'lifetime'` |

### SubscriptionStatus

| Key | Value |
|-----|-------|
| `Active` | `'active'` |
| `Trialing` | `'trialing'` |
| `PastDue` | `'past_due'` |
| `Canceled` | `'canceled'` |
| `Expired` | `'expired'` |

### FeatureType

| Key | Value |
|-----|-------|
| `Boolean` | `'boolean'` |
| `Quantity` | `'quantity'` |
| `Metered` | `'metered'` |

---

## HasSubscriptions Trait

Add to any Eloquent model to make it subscribable:

```php
use App\Subscription\Concerns\HasSubscriptions;

class User extends Authenticatable
{
    use HasSubscriptions;
}
```

### Subscription Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `subscribe(Plan $plan, ?string $currency, ?string $gateway, ?string $gatewaySubscriptionId)` | `Subscription` | Create a new subscription |
| `subscriptions()` | `MorphMany` | All subscriptions |
| `activeSubscriptions()` | `Collection` | Active + Trialing + PastDue subscriptions |
| `subscription(?Plan $plan)` | `?Subscription` | Latest active subscription (optionally for a specific plan) |
| `subscribed()` | `bool` | Has any active subscription |
| `subscribedTo(Plan $plan)` | `bool` | Subscribed to a specific plan |
| `onTrial()` | `bool` | Currently on a trial |
| `onGracePeriod()` | `bool` | Currently in a grace period |
| `subscriptionHistory()` | `Collection` | All subscriptions ordered by creation date |

### Feature Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `subscribableFeatures()` | `MorphMany` | Direct feature assignments |
| `hasFeature(string $code)` | `bool` | Has feature via plan or direct assignment |
| `canUseFeature(string $code)` | `bool` | Has feature and has remaining quota |
| `remainingUsage(string $code)` | `?int` | Remaining usage (null = unlimited) |
| `recordUsage(string $code, int $amount = 1, ?array $metadata = null)` | `FeatureUsage` | Record feature usage |
| `featureUsages()` | `MorphMany` | All feature usage records |

---

## Actions

All actions are resolved from the container and follow an immutable pattern.

### CreateSubscription

```php
app(CreateSubscription::class)->handle($user, $plan, 'USD', 'stripe', 'sub_xxx');
```

- Creates a subscription with an immutable plan snapshot.
- Sets status to `Trialing` if `plan.trial_days > 0`, otherwise `Active`.
- Calculates `ends_at` based on the plan's billing interval.
- Dispatches `SubscriptionCreated`. Also dispatches `SubscriptionActivated` if there is no trial.

### CancelSubscription

```php
// Immediate cancellation
app(CancelSubscription::class)->handle($subscription, immediately: true, reason: 'user_request');

// Schedule cancellation at period end
app(CancelSubscription::class)->handle($subscription, immediately: false);

// Resume a scheduled cancellation
app(CancelSubscription::class)->resume($subscription);
```

- Dispatches `SubscriptionCanceled`.

### RenewSubscription

```php
$newSubscription = app(RenewSubscription::class)->handle($subscription, 'USD');
```

- Creates a **new** subscription row (immutable pattern).
- Marks the old subscription as `Expired`.
- New subscription starts where the old one ended.
- Preserves gateway information.
- Dispatches `SubscriptionExpired` and `SubscriptionRenewed`.

### ChangePlan

```php
$newSubscription = app(ChangePlan::class)->handle($subscription, $newPlan, 'USD');
```

- Cancels the old subscription (reason: `'plan_changed'`).
- Creates a new subscription with the new plan.
- Dispatches `SubscriptionCanceled` and `PlanChanged`.

### RecordFeatureUsage

```php
$usage = app(RecordFeatureUsage::class)->handle($user, 'api-requests', 5, ['endpoint' => '/api/users']);

// Or via the trait:
$user->recordUsage('api-requests', 5);
```

- Creates or updates the `FeatureUsage` record for the current cycle.
- Handles automatic cycle reset when the billing period expires.
- Combines plan limits and direct feature limits (additive).
- **With overage pricing**: allows exceeding the limit; billed at the overage rate.
- **Without overage pricing**: throws `FeatureLimitExceededException`.
- Creates a `UsageRecord` entry for the audit trail.
- Dispatches `UsageRecorded`. Also dispatches `FeatureLimitReached` or `BillingCycleCompleted` when applicable.

---

## Events

| Event | Properties | Dispatched When |
|-------|------------|-----------------|
| `SubscriptionCreated` | `Subscription $subscription` | New subscription created |
| `SubscriptionActivated` | `Subscription $subscription` | Subscription becomes active (after trial or immediately) |
| `SubscriptionCanceled` | `Subscription $subscription` | Subscription canceled |
| `SubscriptionExpired` | `Subscription $subscription` | Subscription expired (during renewal, or via `subscription:process --expired` / `--grace`) |
| `SubscriptionRenewed` | `Subscription $old, Subscription $new` | Subscription renewed |
| `PlanChanged` | `Subscription $old, Subscription $new, Plan $oldPlan, Plan $newPlan` | Plan changed |
| `FeatureLimitReached` | `Model $subscribable, string $featureCode, int $currentUsage, int $limit` | Usage limit reached |
| `UsageRecorded` | `Model $subscribable, UsageRecord $record, string $featureCode, int $amount` | Usage recorded |
| `BillingCycleCompleted` | `Model $subscribable, string $featureCode, int $used, array $period, ?array $prices` | Billing cycle reset |

---

## Feature Types

### Boolean

The subscribable either has the feature or does not. No usage tracking.

- **Value**: `'true'` or `'false'`
- **Example**: "Priority Support", "Custom Branding"

### Quantity

A limited number of items per billing cycle.

- **Value**: Numeric string (e.g., `'100'`). `null` = unlimited.
- **Resettable**: If `true`, usage counter resets each billing cycle.
- **Usage**: Tracked via `FeatureUsage`.
- **Example**: "100 Projects", "5 Team Members"

### Metered

Pay-as-you-go with an optional included amount.

- **Value**: Numeric string (included amount). `null` = unlimited included.
- **Overage**: When `overage_prices` is set, usage beyond the included amount is allowed and billed per unit.
- **Usage**: Tracked via `FeatureUsage`.
- **Example**: "10,000 API Requests (then $0.01/request)"

---

## Plan Snapshots

Each subscription stores an immutable snapshot of the plan at creation time. This ensures that plan changes never retroactively affect existing subscriptions.

```json
{
    "plan": {
        "id": "01abc...",
        "slug": {"en": "pro"},
        "name": {"en": "Pro", "tr": "Profesyonel"},
        "billing_interval": "monthly"
    },
    "price": {
        "amount": 1490,
        "currency": "USD"
    },
    "features": [
        {
            "code": "api-requests",
            "type": "quantity",
            "value": "10000",
            "resettable": true,
            "overage_prices": {"TRY": 10, "USD": 1}
        }
    ],
    "captured_at": "2026-03-05T12:00:00+00:00"
}
```

---

## Overage Pricing

Define per-currency overage prices when attaching features to plans:

```php
$plan->features()->attach($feature->id, [
    'value' => '1000',
    'overage_prices' => [
        'TRY' => 10,   // per unit overage in kuruş/cents
        'USD' => 1,
    ],
]);
```

When usage exceeds the limit:

- **With overage pricing**: Usage is allowed and billed at the overage rate.
- **Without overage pricing**: A `FeatureLimitExceededException` is thrown.

**Overage pricing resolution** checks both plan features and direct feature assignments. If either source defines `overage_prices` for a feature, overage is allowed. The first non-empty `overage_prices` found (plan checked first, then direct) is used for billing.

**Limit combination**: Plan limits and direct feature limits are **additive**. If a plan grants 1000 and a direct assignment grants 50, the total limit is 1050. Overage is calculated against this combined total.

---

## Direct Feature Assignments

Assign features directly to a subscribable without requiring a plan:

```php
$user->subscribableFeatures()->create([
    'feature_id' => $feature->id,
    'value' => '50',
    'overage_prices' => ['TRY' => 5, 'USD' => 1], // optional per-currency overage rates
    'valid_from' => now(),
    'valid_until' => now()->addMonth(), // null for indefinite
]);
```

Direct feature assignments support the same `overage_prices` field as plan features. When a direct feature has overage pricing, usage beyond the limit is allowed and billed at the specified rate.

Direct feature limits are **additive** with plan limits. If a plan grants 1000 and a direct assignment grants 50, the total limit is 1050.

---

## Usage Cycle Reset

For resettable features:

- The reset date is based on `subscription.ends_at` (or `now + 1 month` if there is no subscription).
- When usage is recorded after the reset date, the `used` counter resets to 0.
- A `BillingCycleCompleted` event is dispatched on reset, including the previous period's usage and overage pricing data.

---

## Period-Based Usage & Billing

Invoice calculation uses **period-based billing**, which separates usage into distinct time windows based on `UsageRecord.recorded_at`:

### Pre-Subscription Usage

Usage recorded **before** the subscription's `starts_at` is evaluated against **direct feature limits only**. If a direct feature has `overage_prices`, any usage exceeding the direct limit generates a `pre_subscription_overage` invoice line.

```
Timeline:   |--- direct feature valid_from ---|--- subscription starts_at ---|--- ends_at ---|
Usage here: ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^    (billed as pre_subscription_overage)
Usage here:                                    ^^^^^^^^^^^^^^^^^^^^^^^^^^^^   (billed as overage)
```

### Current Period Usage

Usage recorded **during** the subscription period (`starts_at` to now) is evaluated against the **combined limit** (plan + direct features). If overage pricing exists on either source, excess usage generates an `overage` invoice line.

### Helper Methods (Application-Level)

The dev subscription controller demonstrates two helper patterns:

- `usageInPeriod($user, $code, $from, $until)` — Sums `UsageRecord.amount` within a date range.
- `periodUsage($user, $code, $periodStart)` — Sums usage from `$periodStart` onward; falls back to `FeatureUsage.used` if no period is defined.

### Invoice Line Types

| Type | Description |
|------|-------------|
| `base` | Plan base price |
| `overage` | Usage exceeding combined limit during the subscription period |
| `pre_subscription_overage` | Usage exceeding direct feature limit before subscription started |

---

## Payment Gateway Integration

This package manages subscription state -- it does **not** process payments. You implement the payment gateway in your application, using the package's Actions and Events to keep subscription state in sync.

### PaymentGateway Contract

The package provides an optional contract your gateway can implement:

```php
namespace App\Subscription\Contracts;

interface PaymentGateway
{
    public function create(Subscription $subscription): array;
    public function cancel(Subscription $subscription): bool;
    public function renew(Subscription $subscription): array;
    public function changePlan(Subscription $subscription, array $newPlanData): array;
}
```

Register your implementation in `config/subscription.php`:

```php
'gateway' => [
    'driver' => 'stripe',
    'handler' => App\Gateways\StripeGateway::class,
],
```

Resolve it from the container:

```php
$gateway = app(PaymentGateway::class);
$result = $gateway->create($subscription);
```

### Implementing a Gateway

```php
use App\Subscription\Contracts\PaymentGateway;

class StripeGateway implements PaymentGateway
{
    public function create(Subscription $subscription): array
    {
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        $stripeSubscription = $stripe->subscriptions->create([
            'customer' => $subscription->subscribable->stripe_customer_id,
            'items' => [['price' => $this->resolvePriceId($subscription)]],
        ]);

        // Update subscription with gateway reference
        $subscription->update([
            'gateway' => 'stripe',
            'gateway_subscription_id' => $stripeSubscription->id,
        ]);

        return [
            'gateway_subscription_id' => $stripeSubscription->id,
            'status' => $stripeSubscription->status,
        ];
    }

    public function cancel(Subscription $subscription): bool
    {
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
        $stripe->subscriptions->cancel($subscription->gateway_subscription_id);

        return true;
    }

    public function renew(Subscription $subscription): array
    {
        // Stripe handles renewals automatically via webhooks.
        // This method can be used for manual renewal if needed.
        return [];
    }

    public function changePlan(Subscription $subscription, array $newPlanData): array
    {
        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

        $stripeSubscription = $stripe->subscriptions->update(
            $subscription->gateway_subscription_id,
            ['items' => [['price' => $newPlanData['stripe_price_id']]]],
        );

        return ['status' => $stripeSubscription->status];
    }
}
```

### Handling Webhooks

Webhook handling is **your responsibility**. This package intentionally does not register webhook routes or parse gateway-specific payloads -- every gateway (Stripe, Iyzico, Paddle, etc.) has a completely different signature verification and payload format.

Instead, create your own webhook controller and call the package's Actions directly:

```php
use App\Subscription\Actions\CancelSubscription;
use App\Subscription\Actions\RenewSubscription;
use App\Subscription\Enums\SubscriptionStatus;
use App\Subscription\Models\Subscription;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // 1. Verify signature (gateway-specific)
        try {
            $event = \Stripe\Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                config('services.stripe.webhook_secret'),
            );
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // 2. Find subscription
        $gatewayId = $event->data->object->subscription
            ?? $event->data->object->id;

        $subscription = Subscription::query()
            ->where('gateway_subscription_id', $gatewayId)
            ->whereIn('status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::Trialing,
                SubscriptionStatus::PastDue,
            ])
            ->latest()
            ->first();

        if (! $subscription) {
            return response()->json(['status' => 'skipped']);
        }

        // 3. Call the appropriate Action
        match ($event->type) {
            'invoice.paid' => app(RenewSubscription::class)->handle($subscription),

            'customer.subscription.deleted' => app(CancelSubscription::class)
                ->handle($subscription, immediately: true, reason: 'gateway_canceled'),

            'invoice.payment_failed' => $subscription->update([
                'status' => SubscriptionStatus::PastDue,
                'grace_ends_at' => now()->addDays($subscription->plan->getGracePeriodDays()),
            ]),

            'customer.subscription.trial_will_end' => null, // send reminder email

            default => null, // log unhandled events
        };

        return response()->json(['status' => 'handled']);
    }
}
```

Register the route yourself (with CSRF disabled):

```php
// routes/api.php (no CSRF by default in Laravel 12)
Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle']);

// Or in routes/web.php with CSRF excluded via bootstrap/app.php:
// ->withMiddleware(function (Middleware $middleware) {
//     $middleware->validateCsrfTokens(except: ['webhooks/*']);
// })
```

> **Why this approach?** The package manages subscription state. Payment processing, webhook verification, and HTTP routing are application concerns. This separation keeps the package focused and gives you full control over your payment integration.

---

## Model Customization

Swap any model by extending the base class and updating the config:

```php
// app/Models/CustomPlan.php
use App\Subscription\Models\Plan as BasePlan;

class CustomPlan extends BasePlan
{
    // Add custom methods, scopes, relationships...
}
```

```php
// config/subscription.php
'models' => [
    'plan' => App\Models\CustomPlan::class,
    // other models keep their defaults
],
```

All package internals use `ModelResolver` to look up model classes, so your custom models are used throughout.

---

## Table Customization

Override table names in the config:

```php
// config/subscription.php
'tables' => [
    'plans' => 'sub_plans',
    'subscriptions' => 'sub_subscriptions',
    // ...
],
```

All models read their table name from config. If you publish and customize migrations, update the table names there as well.

---

## Scheduled Commands

The package provides two Artisan commands for automated subscription lifecycle management.

### `subscription:process`

Processes all pending lifecycle transitions. Run via Laravel Scheduler:

```php
// routes/console.php
Schedule::command('subscription:process')->everyFiveMinutes();
```

**Operations performed:**

| Operation | Condition | Result | Event |
|-----------|-----------|--------|-------|
| Expire subscriptions | `status=active`, `ends_at < now()` | status -> `expired` | `SubscriptionExpired` |
| Activate after trial | `status=trialing`, `trial_ends_at < now()` | status -> `active` | `SubscriptionActivated` |
| Expire grace period | `status=past_due`, `grace_ends_at < now()` | status -> `expired` | `SubscriptionExpired` |
| Execute scheduled cancels | `cancels_at < now()`, `canceled_at = null` | status -> `canceled` | `SubscriptionCanceled` |
| Reset usage cycles | `resets_at < now()`, `used > 0` | `used = 0`, new `resets_at` | `BillingCycleCompleted` |

**Flags:**

```bash
php artisan subscription:process              # Run all operations
php artisan subscription:process --expired     # Only expire active subscriptions
php artisan subscription:process --trials      # Only activate ended trials
php artisan subscription:process --grace       # Only expire past-due grace periods
php artisan subscription:process --cancellations # Only execute scheduled cancellations
php artisan subscription:process --usage-reset # Only reset usage cycles
php artisan subscription:process --dry-run     # Report only, do not modify records
```

### `subscription:status`

Displays a status report with subscription counts and pending warnings:

```bash
php artisan subscription:status
```

---

## Testing

The package includes a comprehensive Pest test suite:

```bash
php artisan test --compact --testsuite=Subscription
```

Test coverage includes:

- **PlanSnapshotBuilder** -- snapshot integrity and structure
- **CreateSubscription** -- monthly, yearly, lifetime, trials, gateways
- **CancelSubscription** -- immediate, scheduled, resume
- **RenewSubscription** -- immutable rows, status transitions, gateways
- **ChangePlan** -- old subscription canceled, new created, feature comparison
- **RecordFeatureUsage** -- limits, overage, cycle reset, combined plan + direct limits
- **HasSubscriptions** -- all trait methods
- **ProcessSubscriptionsCommand** -- expire, trial activation, grace period, cancellations, usage reset, dry-run, flags
- **SubscriptionStatusCommand** -- status counts, pending warnings

Application-level integration tests (in `tests/Feature/DevSubscription/`):

- **DirectFeatureControllerTest** -- direct feature CRUD operations
- **InvoiceSimulationTest** -- period-based billing, overage calculation, pre-subscription overage, combined limits, dry-run usage overrides

---

## Quick Start

```php
use App\Subscription\Models\Plan;
use App\Subscription\Models\Feature;
use App\Subscription\Enums\BillingInterval;
use App\Subscription\Enums\FeatureType;
use App\Subscription\Actions\CancelSubscription;
use App\Subscription\Actions\RenewSubscription;
use App\Subscription\Actions\ChangePlan;

// 1. Create a plan
$plan = Plan::create([
    'slug' => ['en' => 'pro'],
    'name' => ['en' => 'Pro', 'tr' => 'Profesyonel'],
    'prices' => ['USD' => 1490, 'TRY' => 14900],
    'billing_interval' => BillingInterval::Monthly,
    'trial_days' => 14,
    'is_active' => true,
]);

// 2. Create a feature
$feature = Feature::create([
    'code' => 'api-requests',
    'slug' => ['en' => 'api-requests'],
    'name' => ['en' => 'API Requests'],
    'type' => FeatureType::Quantity,
    'resettable' => true,
]);

// 3. Attach feature to plan with a limit and overage pricing
$plan->features()->attach($feature->id, [
    'value' => '10000',
    'overage_prices' => ['USD' => 1],
]);

// 4. Subscribe a user
$subscription = $user->subscribe($plan, 'USD');

// 5. Check features
$user->hasFeature('api-requests');     // true
$user->canUseFeature('api-requests');  // true
$user->remainingUsage('api-requests'); // 10000

// 6. Record usage
$user->recordUsage('api-requests', 100);
$user->remainingUsage('api-requests'); // 9900

// 7. Cancel at period end
app(CancelSubscription::class)->handle($subscription, immediately: false);

// 8. Renew
$newSubscription = app(RenewSubscription::class)->handle($subscription);

// 9. Change plan
$newSubscription = app(ChangePlan::class)->handle($subscription, $enterprisePlan, 'USD');
```

---

## License

MIT
