<?php

namespace App\Subscription\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeatureLimitReached
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Model $subscribable,
        public readonly string $featureCode,
        public readonly int $currentUsage,
        public readonly int $limit,
    ) {}
}
