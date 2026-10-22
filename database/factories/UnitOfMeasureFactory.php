<?php

namespace Database\Factories;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitOfMeasure>
 */
class UnitOfMeasureFactory extends Factory
{
    protected $model = UnitOfMeasure::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => 'وحدة '.fake()->unique()->numerify('###'),
            'symbol_ar' => fake()->optional()->lexify('??'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
