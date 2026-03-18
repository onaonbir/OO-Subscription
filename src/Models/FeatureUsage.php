<?php

namespace App\Subscription\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FeatureUsage extends Model
{
    use HasUlids;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used' => 'integer',
            'resets_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('subscription.tables.feature_usages', 'feature_usages');
    }

    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->resets_at !== null && $this->resets_at->isPast();
    }
}
