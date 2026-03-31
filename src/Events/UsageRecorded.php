<?php

namespace OnaOnbir\Subscription\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use OnaOnbir\Subscription\Models\UsageRecord;

class UsageRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Model $subscribable,
        public readonly UsageRecord $usageRecord,
        public readonly string $featureCode,
        public readonly int $amount,
    ) {}
}
