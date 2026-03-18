<?php

namespace App\Subscription\Events;

use App\Subscription\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCanceled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
    ) {}
}
