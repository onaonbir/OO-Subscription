<?php

namespace OnaOnbir\Subscription\Database\Factories;

use OnaOnbir\Subscription\Enums\FeatureType;
use OnaOnbir\Subscription\Models\Feature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feature>
 */
class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->slug(2);

        return [
            'code' => $code,
            'slug' => ['en' => $code, 'tr' => $code],
            'name' => ['en' => fake()->words(2, true), 'tr' => fake()->words(2, true)],
            'description' => ['en' => fake()->sentence(), 'tr' => fake()->sentence()],
            'type' => FeatureType::Quantity,
            'resettable' => false,
        ];
    }

    public function boolean(): static
    {
        return $this->state([
            'type' => FeatureType::Boolean,
            'resettable' => false,
        ]);
    }

    public function quantity(): static
    {
        return $this->state([
            'type' => FeatureType::Quantity,
            'resettable' => true,
        ]);
    }

    public function metered(): static
    {
        return $this->state([
            'type' => FeatureType::Metered,
            'resettable' => true,
        ]);
    }
}
