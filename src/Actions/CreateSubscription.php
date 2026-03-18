<?php

namespace App\Subscription\Actions;

use App\Subscription\Enums\BillingInterval;
use App\Subscription\Enums\SubscriptionStatus;
use App\Subscription\Events\SubscriptionActivated;
use App\Subscription\Events\SubscriptionCreated;
use App\Subscription\Exceptions\DuplicateSubscriptionException;
use App\Subscription\Models\Plan;
use App\Subscription\Models\Subscription;
use App\Subscription\Support\ModelResolver;
use App\Subscription\Support\PlanSnapshotBuilder;
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
            ->whereIn('status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::Trialing,
                SubscriptionStatus::PastDue,
            ])
            ->exists();

        if ($existingActive) {
            throw new DuplicateSubscriptionException($plan);
        }

        $currency = $currency ?? config('subscription.default_currency', 'TRY');
        $snapshot = $this->snapshotBuilder->build($plan, $currency);

        $now = now();
        $hasTrial = $plan->trial_days > 0;

        $endsAt = match ($plan->billing_interval) {
            BillingInterval::Monthly => $hasTrial
                ? $now->copy()->addDays($plan->trial_days)->addMonth()
                : $now->copy()->addMonth(),
            BillingInterval::Yearly => $hasTrial
                ? $now->copy()->addDays($plan->trial_days)->addYear()
                : $now->copy()->addYear(),
            BillingInterval::Lifetime => null,
        };

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
