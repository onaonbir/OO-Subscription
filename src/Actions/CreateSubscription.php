<?php

namespace OnaOnbir\Subscription\Actions;

use OnaOnbir\Subscription\Enums\SubscriptionStatus;
use OnaOnbir\Subscription\Events\SubscriptionActivated;
use OnaOnbir\Subscription\Events\SubscriptionCreated;
use OnaOnbir\Subscription\Exceptions\DuplicateSubscriptionException;
use OnaOnbir\Subscription\Models\Plan;
use OnaOnbir\Subscription\Models\Subscription;
use OnaOnbir\Subscription\Support\ModelResolver;
use OnaOnbir\Subscription\Support\PlanSnapshotBuilder;
use Illuminate\Database\Eloquent\Model;

class CreateSubscription
{
    public function __construct(
        private readonly PlanSnapshotBuilder $snapshotBuilder,
    ) {}

    public function handle(
        Model $subscribable,
        Plan $plan,
        ?string $currency = null,
        ?string $gateway = null,
        ?string $gatewaySubscriptionId = null,
    ): Subscription {
        $existingActive = ModelResolver::subscription()::query()
            ->where('subscribable_type', $subscribable->getMorphClass())
            ->where('subscribable_id', $subscribable->getKey())
            ->where('plan_id', $plan->id)
            ->whereIn('status', SubscriptionStatus::activeStatuses())
            ->exists();

        if ($existingActive) {
            throw new DuplicateSubscriptionException($plan);
        }

        $currency = $currency ?? config('subscription.default_currency', 'TRY');
        $snapshot = $this->snapshotBuilder->build($plan, $currency);

        $now = now();
        $hasTrial = $plan->trial_days > 0;
        $startsAt = $hasTrial ? $now->copy()->addDays($plan->trial_days) : $now;
        $endsAt = $plan->billing_interval->addToDate($startsAt);

        $subscriptionClass = ModelResolver::subscription();
        $subscription = $subscriptionClass::query()->create([
            'subscribable_type' => $subscribable->getMorphClass(),
            'subscribable_id' => $subscribable->getKey(),
            'plan_id' => $plan->id,
            'plan_snapshot' => $snapshot,
            'gateway' => $gateway,
            'gateway_subscription_id' => $gatewaySubscriptionId,
            'status' => $hasTrial ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
            'trial_ends_at' => $hasTrial ? $now->copy()->addDays($plan->trial_days) : null,
            'starts_at' => $now,
            'ends_at' => $endsAt,
        ]);

        SubscriptionCreated::dispatch($subscription);

        if (! $hasTrial) {
            SubscriptionActivated::dispatch($subscription);
        }

        return $subscription;
    }
}
