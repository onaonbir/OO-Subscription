<?php

namespace App\Subscription\Models;

use App\Subscription\Enums\BillingInterval;
use App\Subscription\Support\ModelResolver;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SubscribableFeature extends Model
{
    use HasUlids;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'overage_prices' => 'array',
            'billing_interval' => BillingInterval::class,
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('subscription.tables.subscribable_features', 'subscribable_features');
    }

    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::feature());
    }

    public function isActive(): bool
    {
        $now = now();

        return $this->valid_from <= $now
            && ($this->valid_until === null || $this->valid_until > $now);
    }
}
