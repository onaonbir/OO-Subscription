<?php

namespace OnaOnbir\Subscription\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use OnaOnbir\Subscription\Enums\FeatureType;
use OnaOnbir\Subscription\Events\BillingCycleCompleted;
use OnaOnbir\Subscription\Events\FeatureLimitReached;
use OnaOnbir\Subscription\Events\UsageRecorded;
use OnaOnbir\Subscription\Exceptions\FeatureLimitExceededException;
use OnaOnbir\Subscription\Models\Feature;
use OnaOnbir\Subscription\Models\FeatureUsage;
use OnaOnbir\Subscription\Support\FeatureLimitCalculator;
use OnaOnbir\Subscription\Support\ModelResolver;
use OnaOnbir\Subscription\Support\ResetDateCalculator;

class RecordFeatureUsage
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(
        Model $subscribable,
        string $featureCode,
        int $amount = 1,
        ?array $metadata = null,
    ): FeatureUsage {
        if ($amount < 1) {
            throw new \InvalidArgumentException('Usage amount must be at least 1.');
        }

        $feature = ModelResolver::feature()::query()->where('code', $featureCode)->first();

        if (! $feature) {
            throw new \InvalidArgumentException("Feature [{$featureCode}] not found.");
        }

        if ($feature->type === FeatureType::Boolean) {
            throw new \InvalidArgumentException("Cannot record usage for boolean feature [{$featureCode}].");
        }

        return DB::transaction(function () use ($subscribable, $featureCode, $amount, $metadata, $feature) {
            $usage = ModelResolver::featureUsage()::query()
                ->lockForUpdate()
                ->firstOrCreate([
                    'subscribable_type' => $subscribable->getMorphClass(),
                    'subscribable_id' => $subscribable->getKey(),
                    'feature_code' => $featureCode,
                ], [
                    'used' => 0,
                    'resets_at' => ResetDateCalculator::calculate($subscribable, $feature),
                ]);

            if ($usage->isExpired()) {
                $this->handleCycleReset($usage, $subscribable, $feature);
            }

            $totalLimit = FeatureLimitCalculator::totalLimit($subscribable, $featureCode);

            if ($totalLimit !== null) {
                $remaining = $totalLimit - $usage->used;

                if ($amount > $remaining) {
                    $hasOveragePricing = FeatureLimitCalculator::hasOveragePricing($subscribable, $featureCode);

                    if (! $hasOveragePricing) {
                        FeatureLimitReached::dispatch($subscribable, $featureCode, $usage->used, $totalLimit);

                        throw new FeatureLimitExceededException($featureCode, $usage->used, $totalLimit, $amount);
                    }
                }

                if ($usage->used + $amount >= $totalLimit) {
                    FeatureLimitReached::dispatch($subscribable, $featureCode, $usage->used + $amount, $totalLimit);
                }
            }

            ModelResolver::featureUsage()::query()
                ->where('id', $usage->id)
                ->update(['used' => DB::raw('used + '.(int) $amount)]);

            $usage->refresh();

            $usageRecord = ModelResolver::usageRecord()::query()->create([
                'subscribable_type' => $subscribable->getMorphClass(),
                'subscribable_id' => $subscribable->getKey(),
                'feature_code' => $featureCode,
                'amount' => $amount,
                'metadata' => $metadata,
                'recorded_at' => now(),
            ]);

            UsageRecorded::dispatch($subscribable, $usageRecord, $featureCode, $amount);

            return $usage;
        });
    }

    private function handleCycleReset(FeatureUsage $usage, Model $subscribable, Feature $feature): void
    {
        $usedBeforeReset = $usage->used;

        $usage->update([
            'used' => 0,
            'resets_at' => ResetDateCalculator::calculate($subscribable, $feature),
        ]);

        BillingCycleCompleted::dispatch(
            $subscribable,
            $feature->code,
            $usedBeforeReset,
            [
                'ended_at' => $usage->resets_at?->toIso8601String(),
                'reset_at' => now()->toIso8601String(),
            ],
        );
    }
}
