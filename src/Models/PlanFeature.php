<?php

namespace OnaOnbir\Subscription\Models;

use OnaOnbir\Subscription\Support\ModelResolver;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PlanFeature extends Pivot
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'overage_prices' => 'array',
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('subscription.tables.plan_features', 'plan_features');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::plan());
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::feature());
    }
}
