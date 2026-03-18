<?php

namespace App\Subscription\Actions;

use App\Subscription\Enums\BillingInterval;
use App\Subscription\Enums\SubscriptionStatus;
use App\Subscription\Events\SubscriptionExpired;
use App\Subscription\Events\SubscriptionRenewed;
use App\Subscription\Exceptions\InvalidSubscriptionStateException;
use App\Subscription\Models\Subscription;
use App\Subscription\Support\ModelResolver;
use App\Subscription\Support\PlanSnapshotBuilder;

class RenewSubscription
{
    public function __construct(
        private readonly PlanSnapshotBuilder $snapshotBuilder,
    ) {}

    public function handle(Subscription $subscription, ?string $currency = null): Subscription
    {
        if (! $subscription->isValid()) {
            throw new InvalidSubscriptionStateException('renew', $subscription->status->value);
        }

        $plan = $subscription->plan;
        $currency = $currency ?? $subscription->plan_snapshot['price']['currency'] ?? config('subscription.default_currency', 'TRY');
        $snapshot = $this->snapshotBuilder->build($plan, $currency);

        $now = now();
        $startsAt = $subscription->ends_at ?? $now;

        $endsAt = match ($plan->billing_interval) {
            BillingInterval::Monthly => $startsAt->copy()->addMonth(),
            BillingInterval::Yearly => $startsAt->copy()->addYear(),
            BillingInterval::Lifetime => null,
        };

        $subscription->update(['status' => SubscriptionStatus::Expired]);
        SubscriptionExpired::dispatch($subscription);

        $subscriptionClass = ModelResolver::subscription();
        $newSubscription = $subscriptionClass::query()->create([
            'subscribable_type' => $subscription->subscribable_type,
            'subscribable_id' => $subscription->subscribable_id,
            'plan_id' => $plan->id,
            'plan_snapshot' => $snapshot,
            'gateway' => $subscription->gateway,
            'gateway_subscription_id' => $subscription->gateway_subscription_id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        SubscriptionRenewed::dispatch($subscription, $newSubscription);

        return $newSubscription;
    }
}
