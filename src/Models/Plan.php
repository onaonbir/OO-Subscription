<?php

namespace App\Subscription\Models;

use App\Subscription\Database\Factories\PlanFactory;
use App\Subscription\Enums\BillingInterval;
use App\Subscription\Support\ModelResolver;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected static function newFactory(): PlanFactory
    {
        return PlanFactory::new();
    }

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slug' => 'array',
            'name' => 'array',
            'description' => 'array',
            'prices' => 'array',
            'billing_interval' => BillingInterval::class,
            'trial_days' => 'integer',
            'grace_period_days' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('subscription.tables.plans', 'plans');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(
            ModelResolver::feature(),
            config('subscription.tables.plan_features', 'plan_features'),
            'plan_id',
            'feature_id'
        )->using(ModelResolver::planFeature())->withPivot(['id', 'value', 'overage_prices', 'metadata'])->withTimestamps();
    }

    public function planFeatures(): HasMany
    {
        return $this->hasMany(ModelResolver::planFeature());
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ModelResolver::subscription());
    }

    /**
     * @param  array<string, int>|null  $prices
     */
    public function getPrice(?string $currency = null, ?array $prices = null): ?int
    {
        $currency = $currency ?? config('subscription.default_currency', 'TRY');
        $prices = $prices ?? $this->prices;

        return $prices[$currency] ?? null;
    }

    public function getTranslation(string $field, ?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $values = $this->{$field};

        if (! is_array($values)) {
            return null;
        }

        return $values[$locale] ?? $values[config('app.fallback_locale', 'en')] ?? array_values($values)[0] ?? null;
    }

    public function getGracePeriodDays(): int
    {
        return $this->grace_period_days ?? config('subscription.grace_period_days', 3);
    }
}
