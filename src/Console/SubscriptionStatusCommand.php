<?php

namespace App\Subscription\Console;

use App\Subscription\Enums\SubscriptionStatus;
use App\Subscription\Support\ModelResolver;
use Illuminate\Console\Command;

class SubscriptionStatusCommand extends Command
{
    protected $signature = 'subscription:status';

    protected $description = 'Display subscription status report';

    public function handle(): int
    {
        $subscriptionClass = ModelResolver::subscription();
        $featureUsageClass = ModelResolver::featureUsage();

        $this->components->info('Subscription Status Report');

        $this->displayStatusCounts($subscriptionClass);
        $this->displayWarnings($subscriptionClass, $featureUsageClass);

        return self::SUCCESS;
    }

    private function displayStatusCounts(string $subscriptionClass): void
    {
        $counts = [];

        foreach (SubscriptionStatus::cases() as $status) {
            $counts[] = [
                $status->value,
                $subscriptionClass::query()->where('status', $status)->count(),
            ];
        }

        $this->table(['Status', 'Count'], $counts);
    }

    private function displayWarnings(string $subscriptionClass, string $featureUsageClass): void
    {
        $now = now();

        $expiringTrials = $subscriptionClass::query()
            ->where('status', SubscriptionStatus::Trialing)
            ->whereBetween('trial_ends_at', [$now, $now->copy()->addDays(3)])
            ->count();

        $overdueRenewals = $subscriptionClass::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->count();

        $pendingCancellations = $subscriptionClass::query()
            ->whereNotNull('cancels_at')
            ->where('cancels_at', '<', $now)
            ->whereNull('canceled_at')
            ->count();

        $pendingUsageResets = $featureUsageClass::query()
            ->whereNotNull('resets_at')
            ->where('resets_at', '<', $now)
            ->where('used', '>', 0)
            ->count();

        $warnings = [
            ['Trials ending within 3 days', $expiringTrials],
            ['Overdue renewals (active past ends_at)', $overdueRenewals],
            ['Pending scheduled cancellations', $pendingCancellations],
            ['Pending usage resets', $pendingUsageResets],
        ];

        $this->table(['Warning', 'Count'], $warnings);
    }
}
