<?php

namespace App\Subscription\Actions;

use App\Subscription\Enums\SubscriptionStatus;
use App\Subscription\Events\SubscriptionCanceled;
use App\Subscription\Exceptions\InvalidSubscriptionStateException;
use App\Subscription\Models\Subscription;

class CancelSubscription
{
    public function handle(
        Subscription $subscription,
        bool $immediately = false,
        ?string $reason = null,
    ): Subscription {
        if ($subscription->isCanceled() || $subscription->isExpired()) {
            throw new InvalidSubscriptionStateException('cancel', $subscription->status->value);
        }

        if ($immediately) {
            $subscription->update([
                'status' => SubscriptionStatus::Canceled,
                'canceled_at' => now(),
                'canceled_reason' => $reason,
            ]);
        } else {
            $subscription->update([
                'cancels_at' => $subscription->ends_at,
                'canceled_reason' => $reason,
            ]);
        }

        SubscriptionCanceled::dispatch($subscription);

        return $subscription;
    }

    public function resume(Subscription $subscription): Subscription
    {
        $subscription->update([
            'cancels_at' => null,
            'canceled_reason' => null,
        ]);

        return $subscription;
    }
}
