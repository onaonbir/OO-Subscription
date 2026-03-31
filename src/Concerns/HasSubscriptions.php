<?php

namespace OnaOnbir\Subscription\Concerns;

use OnaOnbir\Subscription\Actions\CreateSubscription;
use OnaOnbir\Subscription\Actions\RecordFeatureUsage;
use OnaOnbir\Subscription\Enums\FeatureType;
use OnaOnbir\Subscription\Enums\SubscriptionStatus;
use OnaOnbir\Subscription\Models\FeatureUsage;
use OnaOnbir\Subscription\Models\Plan;
use OnaOnbir\Subscription\Models\SubscribableFeature;
use OnaOnbir\Subscription\Models\Subscription;
use OnaOnbir\Subscription\Support\FeatureLimitCalculator;
use OnaOnbir\Subscription\Support\ModelResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasSubscriptions
{
    /** @var Collection<int, Subscription>|null */
    private ?Collection $cachedActiveSubscriptions = null;

    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscribable');
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function activeSubscriptions(): Collection
    {
        return $this->cachedActiveSubscriptions ??= $this->subscriptions()
            ->whereIn('status', SubscriptionStatus::activeStatuses())
            ->get();
    }

    public function clearSubscriptionCache(): void
    {
        $this->cachedActiveSubscriptions = null;
    }

    public function subscription(?Plan $plan = null): ?Subscription
    {
        $query = $this->subscriptions()
            ->whereIn('status', SubscriptionStatus::activeStatuses());

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
        $subscription = app(CreateSubscription::class)->handle(
            $this,
            $plan,
            $currency,
            $gateway,
            $gatewaySubscriptionId,
        );

        $this->clearSubscriptionCache();

        return $subscription;
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

        return $this->subscribableFeatures()
            ->whereHas('feature', fn ($query) => $query->where('code', $code))
            ->currentlyValid()
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
