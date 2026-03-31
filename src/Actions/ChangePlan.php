<?php

namespace OnaOnbir\Subscription\Actions;

use OnaOnbir\Subscription\Enums\SubscriptionStatus;
use OnaOnbir\Subscription\Events\PlanChanged;
use OnaOnbir\Subscription\Events\SubscriptionCanceled;
use OnaOnbir\Subscription\Exceptions\InvalidSubscriptionStateException;
use OnaOnbir\Subscription\Models\Plan;
use OnaOnbir\Subscription\Models\Subscription;
use OnaOnbir\Subscription\Support\ModelResolver;
use OnaOnbir\Subscription\Support\PlanSnapshotBuilder;

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
        $currency = $subscription->resolveCurrency($currency);
        $snapshot = $this->snapshotBuilder->build($newPlan, $currency);

        $now = now();
        $endsAt = $newPlan->billing_interval->addToDate($now);

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
