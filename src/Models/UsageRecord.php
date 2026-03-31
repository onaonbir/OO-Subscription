<?php

namespace OnaOnbir\Subscription\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UsageRecord extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'metadata' => 'array',
            'recorded_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('subscription.tables.usage_records', 'usage_records');
    }

    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        static::creating(function (UsageRecord $record) {
            $record->created_at ??= now();
        });
    }
}
