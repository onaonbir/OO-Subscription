<?php

namespace OnaOnbir\Subscription\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BillingCycleCompleted
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, int>|null  $prices
     */
    public function __construct(
        public readonly Model $subscribable,
        public readonly string $featureCode,
        public readonly int $used,
        public readonly array $period,
        public readonly ?array $prices = null,
    ) {}
}
