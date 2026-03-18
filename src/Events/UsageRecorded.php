<?php

namespace App\Subscription\Events;

use App\Subscription\Models\UsageRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

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
