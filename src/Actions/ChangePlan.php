<?php

namespace App\Subscription\Actions;

use App\Subscription\Enums\BillingInterval;
use App\Subscription\Enums\SubscriptionStatus;
use App\Subscription\Events\PlanChanged;
use App\Subscription\Events\SubscriptionCanceled;
use App\Subscription\Exceptions\InvalidSubscriptionStateException;
use App\Subscription\Models\Plan;
use App\Subscription\Models\Subscription;
use App\Subscription\Support\ModelResolver;
use App\Subscription\Support\PlanSnapshotBuilder;

class ChangePlan
{
    public function __construct(
        private readonly PlanSnapshotBuilder $snapshotBuilder,
    ) {}

    public function handle(
        Subscription $subscription,
        Plan $newPlan,
        ?string $currency = null,
        ?string $gateway = null,
        ?string $gatewaySubscriptionId = null,
    ): Subscription {
        if (! $subscription->isValid()) {
            throw new InvalidSubscriptionStateException('change plan on', $subscription->status->value);
        }

        $oldPlan = $subscription->plan;
        $currency = $currency ?? $subscription->plan_snapshot['price']['currency'] ?? config('subscription.default_currency', 'TRY');
        $snapshot = $this->snapshotBuilder->build($newPlan, $currency);

        $now = now();

        $endsAt = match ($newPlan->billing_interval) {
            BillingInterval::Monthly => $now->copy()->addMonth(),
            BillingInterval::Yearly => $now->copy()->addYear(),
            BillingInterval::Lifetime => null,
        };

        $subscription->update([
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => $now,
            'canceled_reason' => 'plan_changed',
        ]);

        SubscriptionCanceled::dispatch($subscription);

        $subscriptionClass = ModelResolver::subscription();
        $newSubscription = $subscriptionClass::query()->create([
            'subscribable_type' => $subscription->subscribable_type,
            'subscribable_id' => $subscription->subscribable_id,
            'plan_id' => $newPlan->id,
            'plan_snapshot' => $snapshot,
            'gateway' => $gateway ?? $subscription->gateway,
            'gateway_subscription_id' => $gatewaySubscriptionId ?? $subscription->gateway_subscription_id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => $now,
            'ends_at' => $endsAt,
        ]);

        PlanChanged::dispatch($subscription, $newSubscription, $oldPlan, $newPlan);

        return $newSubscription;
    }
}
