<?php

namespace OnaOnbir\Subscription\Console;

use Illuminate\Console\Command;
use OnaOnbir\Subscription\Actions\CancelSubscription;
use OnaOnbir\Subscription\Enums\SubscriptionStatus;
use OnaOnbir\Subscription\Events\BillingCycleCompleted;
use OnaOnbir\Subscription\Events\SubscriptionActivated;
use OnaOnbir\Subscription\Events\SubscriptionExpired;
use OnaOnbir\Subscription\Support\ModelResolver;
use OnaOnbir\Subscription\Support\ResetDateCalculator;

class ProcessSubscriptionsCommand extends Command
{
    protected $signature = 'subscription:process
        {--expired : Process expired active subscriptions}
        {--trials : Process ended trials}
        {--grace : Process expired grace periods}
        {--cancellations : Process scheduled cancellations}
        {--usage-reset : Process usage cycle resets}
        {--dry-run : Report only, do not modify records}';

    protected $description = 'Process subscription lifecycle events';

    private int $processed = 0;

    public function handle(): int
    {
        $runAll = ! $this->option('expired')
            && ! $this->option('trials')
            && ! $this->option('grace')
            && ! $this->option('cancellations')
            && ! $this->option('usage-reset');

        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->components->warn('Dry run mode - no records will be modified.');
        }

        if ($runAll || $this->option('expired')) {
            $this->processExpired($isDryRun);
        }

        if ($runAll || $this->option('trials')) {
            $this->processTrials($isDryRun);
        }

        if ($runAll || $this->option('grace')) {
            $this->processGrace($isDryRun);
        }

        if ($runAll || $this->option('cancellations')) {
            $this->processCancellations($isDryRun);
        }

        if ($runAll || $this->option('usage-reset')) {
            $this->processUsageResets($isDryRun);
        }

        $this->components->info("Processed {$this->processed} record(s).");

        return self::SUCCESS;
    }

    private function processExpired(bool $isDryRun): void
    {
        $subscriptionClass = ModelResolver::subscription();

        $subscriptionClass::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->chunkById(100, function ($subscriptions) use ($isDryRun) {
                foreach ($subscriptions as $subscription) {
                    $this->processed++;

                    if ($isDryRun) {
                        $this->components->twoColumnDetail(
                            "Would expire subscription [{$subscription->id}]",
                            "ends_at: {$subscription->ends_at}"
                        );

                        continue;
                    }

                    $subscription->update(['status' => SubscriptionStatus::Expired]);
                    SubscriptionExpired::dispatch($subscription);
                }
            });
    }

    private function processTrials(bool $isDryRun): void
    {
        $subscriptionClass = ModelResolver::subscription();

        $subscriptionClass::query()
            ->where('status', SubscriptionStatus::Trialing)
            ->where('trial_ends_at', '<', now())
            ->chunkById(100, function ($subscriptions) use ($isDryRun) {
                foreach ($subscriptions as $subscription) {
                    $this->processed++;

                    if ($isDryRun) {
                        $this->components->twoColumnDetail(
                            "Would activate subscription [{$subscription->id}]",
                            "trial_ends_at: {$subscription->trial_ends_at}"
                        );

                        continue;
                    }

                    $subscription->update(['status' => SubscriptionStatus::Active]);
                    SubscriptionActivated::dispatch($subscription);
                }
            });
    }

    private function processGrace(bool $isDryRun): void
    {
        $subscriptionClass = ModelResolver::subscription();

        $subscriptionClass::query()
            ->where('status', SubscriptionStatus::PastDue)
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<', now())
            ->chunkById(100, function ($subscriptions) use ($isDryRun) {
                foreach ($subscriptions as $subscription) {
                    $this->processed++;

                    if ($isDryRun) {
                        $this->components->twoColumnDetail(
                            "Would expire past-due subscription [{$subscription->id}]",
                            "grace_ends_at: {$subscription->grace_ends_at}"
                        );

                        continue;
                    }

                    $subscription->update(['status' => SubscriptionStatus::Expired]);
                    SubscriptionExpired::dispatch($subscription);
                }
            });
    }

    private function processCancellations(bool $isDryRun): void
    {
        $subscriptionClass = ModelResolver::subscription();

        $subscriptionClass::query()
            ->whereNotNull('cancels_at')
            ->where('cancels_at', '<', now())
            ->whereNull('canceled_at')
            ->chunkById(100, function ($subscriptions) use ($isDryRun) {
                foreach ($subscriptions as $subscription) {
                    $this->processed++;

                    if ($isDryRun) {
                        $this->components->twoColumnDetail(
                            "Would cancel subscription [{$subscription->id}]",
                            "cancels_at: {$subscription->cancels_at}"
                        );

                        continue;
                    }

                    app(CancelSubscription::class)->handle(
                        $subscription,
                        immediately: true,
                        reason: 'scheduled',
                    );
                }
            });
    }

    private function processUsageResets(bool $isDryRun): void
    {
        $featureUsageClass = ModelResolver::featureUsage();

        $featureUsageClass::query()
            ->with('subscribable')
            ->whereNotNull('resets_at')
            ->where('resets_at', '<', now())
            ->where('used', '>', 0)
            ->chunkById(100, function ($usages) use ($isDryRun) {
                $featureCodes = $usages->pluck('feature_code')->unique();
                $features = ModelResolver::feature()::query()
                    ->whereIn('code', $featureCodes)
                    ->get()
                    ->keyBy('code');

                foreach ($usages as $usage) {
                    $this->processed++;

                    if ($isDryRun) {
                        $this->components->twoColumnDetail(
                            "Would reset usage [{$usage->feature_code}] for {$usage->subscribable_type}:{$usage->subscribable_id}",
                            "used: {$usage->used}, resets_at: {$usage->resets_at}"
                        );

                        continue;
                    }

                    $subscribable = $usage->subscribable;

                    if (! $subscribable) {
                        $usage->delete();

                        continue;
                    }

                    $feature = $features->get($usage->feature_code);
                    $newResetsAt = $feature
                        ? ResetDateCalculator::calculate($subscribable, $feature)
                        : null;

                    $usedBeforeReset = $usage->used;

                    $usage->update([
                        'used' => 0,
                        'resets_at' => $newResetsAt,
                    ]);

                    BillingCycleCompleted::dispatch(
                        $subscribable,
                        $usage->feature_code,
                        $usedBeforeReset,
                        [
                            'ended_at' => $usage->resets_at->toIso8601String(),
                            'reset_at' => now()->toIso8601String(),
                        ],
                    );
                }
            });
    }
}
