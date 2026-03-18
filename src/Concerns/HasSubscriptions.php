<?php

namespace App\Subscription\Concerns;

use App\Subscription\Actions\CreateSubscription;
use App\Subscription\Actions\RecordFeatureUsage;
use App\Subscription\Enums\FeatureType;
use App\Subscription\Enums\SubscriptionStatus;
use App\Subscription\Models\FeatureUsage;
use App\Subscription\Models\Plan;
use App\Subscription\Models\SubscribableFeature;
use App\Subscription\Models\Subscription;
use App\Subscription\Support\FeatureLimitCalculator;
use App\Subscription\Support\ModelResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasSubscriptions
{
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscribable');
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function activeSubscriptions(): Collection
    {
        return $this->subscriptions()
            ->whereIn('status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::Trialing,
                SubscriptionStatus::PastDue,
            ])
            ->get();
    }

    public function subscription(?Plan $plan = null): ?Subscription
    {
        $query = $this->subscriptions()
            ->whereIn('status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::Trialing,
                SubscriptionStatus::PastDue,
            ]);

        if ($plan) {
            $query->where('plan_id', $plan->id);
        }

        return $query->latest()->first();
    }

    public function subscribed(): bool
    {
        return $this->activeSubscriptions()->isNotEmpty();
    }

    public function subscribedTo(Plan $plan): bool
    {
        return $this->subscription($plan) !== null;
    }

    public function onTrial(): bool
    {
        return $this->activeSubscriptions()->contains(fn (Subscription $sub) => $sub->onTrial());
    }

    public function onGracePeriod(): bool
    {
        return $this->subscriptions()
            ->where('status', SubscriptionStatus::PastDue)
            ->get()
            ->contains(fn (Subscription $sub) => $sub->onGracePeriod());
    }

    public function subscribe(
        Plan $plan,
        ?string $currency = null,
        ?string $gateway = null,
        ?string $gatewaySubscriptionId = null,
    ): Subscription {
        return app(CreateSubscription::class)->handle(
            $this,
            $plan,
            $currency,
            $gateway,
            $gatewaySubscriptionId,
        );
    }

    public function subscribableFeatures(): MorphMany
    {
        return $this->morphMany(SubscribableFeature::class, 'subscribable');
    }

    public function hasFeature(string $code): bool
    {
        foreach ($this->activeSubscriptions() as $subscription) {
            $features = $subscription->plan_snapshot['features'] ?? [];

            foreach ($features as $feature) {
                if ($feature['code'] === $code) {
                    if ($feature['type'] === FeatureType::Boolean->value) {
                        return $feature['value'] === 'true' || $feature['value'] === true;
                    }

                    return true;
                }
            }
        }

        $now = now();

        return $this->subscribableFeatures()
            ->whereHas('feature', fn ($query) => $query->where('code', $code))
            ->where('valid_from', '<=', $now)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>', $now))
            ->exists();
    }

    public function canUseFeature(string $code): bool
    {
        if (! $this->hasFeature($code)) {
            return false;
        }

        $feature = ModelResolver::feature()::query()->where('code', $code)->first();

        if (! $feature) {
            return false;
        }

        if ($feature->type === FeatureType::Boolean) {
            return true;
        }

        $totalLimit = FeatureLimitCalculator::totalLimit($this, $code);

        if ($totalLimit === null) {
            return true;
        }

        if (FeatureLimitCalculator::hasOveragePricing($this, $code)) {
            return true;
        }

        $usage = $this->featureUsages()
            ->where('feature_code', $code)
            ->first();

        $currentUsage = $usage?->used ?? 0;

        return $currentUsage < $totalLimit;
    }

    public function remainingUsage(string $code): ?int
    {
        $totalLimit = FeatureLimitCalculator::totalLimit($this, $code);

        if ($totalLimit === null) {
            return null;
        }

        $usage = $this->featureUsages()
            ->where('feature_code', $code)
            ->first();

        $currentUsage = $usage?->used ?? 0;
        $remaining = $totalLimit - $currentUsage;

        if (FeatureLimitCalculator::hasOveragePricing($this, $code)) {
            return $remaining;
        }

        return max(0, $remaining);
    }

    public function recordUsage(string $code, int $amount = 1, ?array $metadata = null): FeatureUsage
    {
        return app(RecordFeatureUsage::class)->handle($this, $code, $amount, $metadata);
    }

    public function featureUsages(): MorphMany
    {
        return $this->morphMany(FeatureUsage::class, 'subscribable');
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function subscriptionHistory(): Collection
    {
        return $this->subscriptions()->latest()->get();
    }
}
