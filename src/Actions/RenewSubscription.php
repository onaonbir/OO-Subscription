<?php

namespace OnaOnbir\Subscription\Actions;

use OnaOnbir\Subscription\Enums\SubscriptionStatus;
use OnaOnbir\Subscription\Events\SubscriptionExpired;
use OnaOnbir\Subscription\Events\SubscriptionRenewed;
use OnaOnbir\Subscription\Exceptions\InvalidSubscriptionStateException;
use OnaOnbir\Subscription\Models\Subscription;
use OnaOnbir\Subscription\Support\ModelResolver;
use OnaOnbir\Subscription\Support\PlanSnapshotBuilder;

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
        $currency = $subscription->resolveCurrency($currency);
        $snapshot = $this->snapshotBuilder->build($plan, $currency);

        $startsAt = $subscription->ends_at ?? now();
        $endsAt = $plan->billing_interval->addToDate($startsAt);

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
