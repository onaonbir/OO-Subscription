<?php

namespace App\Subscription\Models;

use App\Subscription\Database\Factories\FeatureFactory;
use App\Subscription\Enums\FeatureType;
use App\Subscription\Support\ModelResolver;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feature extends Model
{
    /** @use HasFactory<FeatureFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected static function newFactory(): FeatureFactory
    {
        return FeatureFactory::new();
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
            'type' => FeatureType::class,
            'resettable' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('subscription.tables.features', 'features');
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(
            ModelResolver::plan(),
            config('subscription.tables.plan_features', 'plan_features'),
            'feature_id',
            'plan_id'
        )->using(ModelResolver::planFeature())->withPivot(['id', 'value', 'overage_prices', 'metadata'])->withTimestamps();
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
}
