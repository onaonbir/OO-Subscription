# OO-Subscription

A backend-only subscription management package for Laravel. Built by [OnaOnbir](https://onaonbir.com).

Supports polymorphic subscribables, immutable subscription rows, multi-language content, multi-currency pricing, feature usage tracking with overage billing, and a pluggable payment gateway contract.

- **PHP** 8.2+
- **Laravel** 11 / 12
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
- [Enums](#enums)
- [HasSubscriptions Trait](#hassubscriptions-trait)
- [Actions](#actions)
- [State Guards](#state-guards)
- [Events](#events)
- [Feature Types](#feature-types)
- [Plan Snapshots](#plan-snapshots)
- [Overage Pricing](#overage-pricing)
- [Direct Feature Assignments](#direct-feature-assignments)
- [Usage Cycle Reset](#usage-cycle-reset)
- [Payment Gateway Integration](#payment-gateway-integration)
- [Model Customization](#model-customization)
- [Table Customization](#table-customization)
- [Scheduled Commands](#scheduled-commands)
- [Authorization](#authorization)
- [Testing](#testing)
- [Quick Start](#quick-start)
- [License](#license)

---

## Installation

```bash
composer require onaonbir/oo-subscription
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --tag=subscription-config
php artisan vendor:publish --tag=subscription-migrations
php artisan migrate
```

The service provider (`OnaOnbir\Subscription\SubscriptionServiceProvider`) is auto-discovered.

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

**Methods:** `isActive()`, `isTrialing()`, `isPastDue()`, `isCanceled()`, `isExpired()`, `isValid()`, `onTrial()`, `onGracePeriod()`, `hasCancelScheduled()`, `isLifetime()`, `resolveCurrency(?string $override): string`

### PlanFeature (Pivot)

Pivot model connecting plans to features with plan-specific values.

| Field | Type | Description |
|-------|------|-------------|
| `value` | string | Limit value (e.g., `'1000'`, `'true'`, `null` for unlimited) |
| `overage_prices` | json | Per-currency overage rates |
| `metadata` | json | Arbitrary metadata |

### SubscribableFeature

Direct feature assignment to a subscribable, independent of any plan.

| Field | Type | Description |
|-------|------|-------------|
| `subscribable` | morph | Polymorphic owner |
| `feature_id` | foreign key | Feature |
| `value` | string | Limit value |
| `overage_prices` | json | Per-currency overage rates |
| `valid_from` | datetime | Start of validity |
| `valid_until` | datetime | End of validity (null = indefinite) |

**Scopes:** `scopeCurrentlyValid()` -- filters to features within their validity window.

### FeatureUsage

Tracks current usage of a feature within a billing cycle.

### UsageRecord

Immutable audit trail of individual usage events.

---

## Enums

### BillingInterval

`Monthly`, `Yearly`, `Lifetime`

**Methods:** `addToDate(Carbon $date): ?Carbon` -- calculates the next period end date.

### SubscriptionStatus

`Active`, `Trialing`, `PastDue`, `Canceled`, `Expired`

**Methods:** `activeStatuses(): array` -- returns `[Active, Trialing, PastDue]`.

### FeatureType

`Boolean`, `Quantity`, `Metered`

---

## HasSubscriptions Trait

Add to any Eloquent model to make it subscribable:

```php
use OnaOnbir\Subscription\Concerns\HasSubscriptions;

class User extends Authenticatable
{
    use HasSubscriptions;
}
```

### Subscription Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `subscribe(Plan, ?currency, ?gateway, ?gatewayId)` | `Subscription` | Create a new subscription |
| `subscriptions()` | `MorphMany` | All subscriptions |
| `activeSubscriptions()` | `Collection` | Active + Trialing + PastDue (cached per request) |
| `clearSubscriptionCache()` | `void` | Clear the cached active subscriptions |
| `subscription(?Plan)` | `?Subscription` | Latest active subscription |
| `subscribed()` | `bool` | Has any active subscription |
| `subscribedTo(Plan)` | `bool` | Subscribed to a specific plan |
| `onTrial()` | `bool` | Currently on a trial |
| `onGracePeriod()` | `bool` | Currently in a grace period |
| `subscriptionHistory()` | `Collection` | All subscriptions ordered by date |

### Feature Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `hasFeature(string $code)` | `bool` | Has feature via plan or direct assignment |
| `canUseFeature(string $code)` | `bool` | Has feature and has remaining quota |
| `remainingUsage(string $code)` | `?int` | Remaining usage (null = unlimited) |
| `recordUsage(string $code, int $amount, ?array $metadata)` | `FeatureUsage` | Record feature usage |
| `subscribableFeatures()` | `MorphMany` | Direct feature assignments |
| `featureUsages()` | `MorphMany` | All feature usage records |

---

## Actions

All actions are resolved from the container and follow an immutable pattern.

### CreateSubscription

```php
use OnaOnbir\Subscription\Actions\CreateSubscription;

app(CreateSubscription::class)->handle($user, $plan, 'USD', 'stripe', 'sub_xxx');
```

- Creates a subscription with an immutable plan snapshot.
- Sets status to `Trialing` if `plan.trial_days > 0`, otherwise `Active`.
- **Guard:** Throws `DuplicateSubscriptionException` if the subscribable already has an active subscription for the same plan.
- Dispatches `SubscriptionCreated` and `SubscriptionActivated` (if no trial).

### CancelSubscription

```php
use OnaOnbir\Subscription\Actions\CancelSubscription;

// Immediate
app(CancelSubscription::class)->handle($subscription, immediately: true, reason: 'user_request');

// Schedule at period end
app(CancelSubscription::class)->handle($subscription, immediately: false);

// Resume
app(CancelSubscription::class)->resume($subscription);
```

- **Guard:** Throws `InvalidSubscriptionStateException` if subscription is already canceled or expired.
- Dispatches `SubscriptionCanceled`.

### RenewSubscription

```php
use OnaOnbir\Subscription\Actions\RenewSubscription;

$newSubscription = app(RenewSubscription::class)->handle($subscription, 'USD');
```

- Creates a **new** subscription row (immutable pattern).
- **Guard:** Throws `InvalidSubscriptionStateException` if subscription is not valid (canceled/expired).
- Dispatches `SubscriptionExpired` and `SubscriptionRenewed`.

### ChangePlan

```php
use OnaOnbir\Subscription\Actions\ChangePlan;

$newSubscription = app(ChangePlan::class)->handle($subscription, $newPlan, 'USD');
```

- **Guard:** Throws `InvalidSubscriptionStateException` if subscription is not valid.
- Dispatches `SubscriptionCanceled` and `PlanChanged`.

### RecordFeatureUsage

```php
use OnaOnbir\Subscription\Actions\RecordFeatureUsage;

$usage = app(RecordFeatureUsage::class)->handle($user, 'api-requests', 5, ['endpoint' => '/api/users']);

// Or via the trait:
$user->recordUsage('api-requests', 5);
```

- **Validation:** Amount must be >= 1, feature must exist, feature must not be boolean type.
- **Atomicity:** Uses `DB::transaction()` with `lockForUpdate()` to prevent race conditions.
- **With overage pricing**: allows exceeding the limit.
- **Without overage pricing**: throws `FeatureLimitExceededException`.
- Dispatches `UsageRecorded`, `FeatureLimitReached`, and `BillingCycleCompleted` when applicable.

---

## State Guards

Actions validate subscription state before executing. Invalid operations throw typed exceptions:

| Action | Allowed States | Exception |
|--------|---------------|-----------|
| `CreateSubscription` | (new) | `DuplicateSubscriptionException` if same plan already active |
| `CancelSubscription` | Active, Trialing, PastDue | `InvalidSubscriptionStateException` |
| `RenewSubscription` | Active, Trialing, PastDue | `InvalidSubscriptionStateException` |
| `ChangePlan` | Active, Trialing, PastDue | `InvalidSubscriptionStateException` |
| `RecordFeatureUsage` | (any) | `InvalidArgumentException` for bad input, `FeatureLimitExceededException` for limits |

---

## Events

| Event | Dispatched When |
|-------|-----------------|
| `SubscriptionCreated` | New subscription created |
| `SubscriptionActivated` | Subscription becomes active |
| `SubscriptionCanceled` | Subscription canceled |
| `SubscriptionExpired` | Subscription expired |
| `SubscriptionRenewed` | Subscription renewed |
| `PlanChanged` | Plan changed |
| `FeatureLimitReached` | Usage limit reached |
| `UsageRecorded` | Usage recorded |
| `BillingCycleCompleted` | Billing cycle reset |

---

## Feature Types

### Boolean

The subscribable either has the feature or does not. No usage tracking.

- **Value**: `'true'` or `'false'`
- **Example**: "Priority Support", "Code Editor"

### Quantity

A limited number of items per billing cycle.

- **Value**: Numeric string (e.g., `'100'`). `null` = unlimited.
- **Example**: "10 S3 Connections", "500 Monthly Transfers"

### Metered

Pay-as-you-go with an optional included amount.

- **Value**: Numeric string (included amount). `null` = unlimited included.
- **Overage**: When `overage_prices` is set, usage beyond the included amount is billed per unit.

### Modeling Protocols (Boolean + Quantity Pattern)

For capabilities with both an on/off toggle and a numeric limit, use two features:

```php
// Boolean: is the protocol enabled?
Feature::create(['code' => 'protocol-sftp', 'type' => FeatureType::Boolean]);

// Quantity: how many connections?
Feature::create(['code' => 'protocol-sftp-limit', 'type' => FeatureType::Quantity]);
```

Then in your plan:

```php
// Free plan: SFTP disabled
$freePlan->features()->attach([
    $sftp->id => ['value' => 'false'],
    $sftpLimit->id => ['value' => '0'],
]);

// Pro plan: SFTP enabled, 10 connections
$proPlan->features()->attach([
    $sftp->id => ['value' => 'true'],
    $sftpLimit->id => ['value' => '10'],
]);

// Unlimited plan: SFTP enabled, unlimited connections
$unlimitedPlan->features()->attach([
    $sftp->id => ['value' => 'true'],
    $sftpLimit->id => ['value' => null],  // null = unlimited
]);
```

Check in your application:

```php
$user->hasFeature('protocol-sftp');           // true or false
$user->remainingUsage('protocol-sftp-limit'); // 10, 0, or null (unlimited)
$user->canUseFeature('protocol-sftp-limit');  // true or false
```

---

## Plan Snapshots

Each subscription stores an immutable snapshot of the plan at creation time. Plan changes never retroactively affect existing subscriptions.

```json
{
    "plan": {
        "id": "01abc...",
        "slug": {"en": "pro"},
        "name": {"en": "Pro"},
        "billing_interval": "monthly"
    },
    "price": {"amount": 1490, "currency": "USD"},
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

```php
$plan->features()->attach($feature->id, [
    'value' => '1000',
    'overage_prices' => ['TRY' => 10, 'USD' => 1],
]);
```

- **With overage pricing**: Usage beyond the limit is allowed.
- **Without overage pricing**: `FeatureLimitExceededException` is thrown.
- Plan limits and direct feature limits are **additive**.

---

## Direct Feature Assignments

Assign features directly to a subscribable without requiring a plan:

```php
$user->subscribableFeatures()->create([
    'feature_id' => $feature->id,
    'value' => '50',
    'overage_prices' => ['TRY' => 5, 'USD' => 1],
    'valid_from' => now(),
    'valid_until' => now()->addMonth(),
]);
```

Direct feature limits are **additive** with plan limits.

---

## Usage Cycle Reset

For resettable features, the `used` counter resets to 0 when the billing period expires. A `BillingCycleCompleted` event is dispatched on reset.

---

## Payment Gateway Integration

This package manages subscription state -- it does **not** process payments. Implement the `PaymentGateway` contract in your application:

```php
use OnaOnbir\Subscription\Contracts\PaymentGateway;

class StripeGateway implements PaymentGateway
{
    public function create(Subscription $subscription): array { /* ... */ }
    public function cancel(Subscription $subscription): bool { /* ... */ }
    public function renew(Subscription $subscription): array { /* ... */ }
    public function changePlan(Subscription $subscription, array $newPlanData): array { /* ... */ }
}
```

Register in `config/subscription.php`:

```php
'gateway' => [
    'driver' => 'stripe',
    'handler' => App\Gateways\StripeGateway::class,
],
```

Webhook handling is **your responsibility**. Create your own webhook controller and call the package's Actions directly.

---

## Model Customization

Swap any model by extending the base class and updating the config:

```php
use OnaOnbir\Subscription\Models\Plan as BasePlan;

class CustomPlan extends BasePlan
{
    // Add custom methods, scopes, relationships...
}
```

```php
// config/subscription.php
'models' => ['plan' => App\Models\CustomPlan::class],
```

---

## Table Customization

Override table names in the config:

```php
'tables' => ['plans' => 'sub_plans', 'subscriptions' => 'sub_subscriptions'],
```

---

## Scheduled Commands

### `subscription:process`

Processes all pending lifecycle transitions:

```php
// routes/console.php
Schedule::command('subscription:process')->everyFiveMinutes();
```

| Operation | Result | Event |
|-----------|--------|-------|
| Expire active | status -> `expired` | `SubscriptionExpired` |
| Activate trials | status -> `active` | `SubscriptionActivated` |
| Expire grace | status -> `expired` | `SubscriptionExpired` |
| Execute cancels | status -> `canceled` | `SubscriptionCanceled` |
| Reset usage | `used = 0` | `BillingCycleCompleted` |

Supports `--expired`, `--trials`, `--grace`, `--cancellations`, `--usage-reset`, and `--dry-run` flags.

### `subscription:status`

Displays a status report with subscription counts and pending warnings.

---

## Authorization

This package does **not** include authorization policies. You are responsible for implementing gates, policies, or middleware to control who can subscribe, cancel, change plans, etc.

---

## Testing

```bash
php artisan test --compact --testsuite=Subscription
```

---

## Quick Start

```php
use OnaOnbir\Subscription\Models\Plan;
use OnaOnbir\Subscription\Models\Feature;
use OnaOnbir\Subscription\Enums\BillingInterval;
use OnaOnbir\Subscription\Enums\FeatureType;

// 1. Create a plan
$plan = Plan::create([
    'slug' => ['en' => 'pro'],
    'name' => ['en' => 'Pro'],
    'prices' => ['USD' => 1490, 'TRY' => 14900],
    'billing_interval' => BillingInterval::Monthly,
    'trial_days' => 14,
    'is_active' => true,
]);

// 2. Create features
$apiRequests = Feature::create([
    'code' => 'api-requests',
    'slug' => ['en' => 'api-requests'],
    'name' => ['en' => 'API Requests'],
    'type' => FeatureType::Quantity,
    'resettable' => true,
]);

// 3. Attach feature with limit and overage pricing
$plan->features()->attach($apiRequests->id, [
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

// 7. Cancel
app(CancelSubscription::class)->handle($subscription, immediately: false);

// 8. Renew
$newSubscription = app(RenewSubscription::class)->handle($subscription);

// 9. Change plan
$newSubscription = app(ChangePlan::class)->handle($subscription, $enterprisePlan, 'USD');
```

---

## License

MIT - See [LICENSE](LICENSE) for details.

Built with care by [OnaOnbir](https://onaonbir.com).
