<?php

namespace OnaOnbir\Subscription\Models;

use OnaOnbir\Subscription\Enums\BillingInterval;
use OnaOnbir\Subscription\Support\ModelResolver;
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

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     */
    public function scopeCurrentlyValid(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $now = now();
        $query->where('valid_from', '<=', $now)
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', $now));
    }

    public function isActive(): bool
    {
        $now = now();

        return $this->valid_from <= $now
            && ($this->valid_until === null || $this->valid_until > $now);
    }
}
