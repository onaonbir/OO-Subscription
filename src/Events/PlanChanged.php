<?php

namespace OnaOnbir\Subscription\Events;

use OnaOnbir\Subscription\Models\Plan;
use OnaOnbir\Subscription\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlanChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Subscription $oldSubscription,
        public readonly Subscription $newSubscription,
        public readonly Plan $oldPlan,
        public readonly Plan $newPlan,
    ) {}
}
