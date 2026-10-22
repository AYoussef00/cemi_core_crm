<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => 'منتج '.fake()->unique()->numerify('###'),
            'unit_id' => UnitOfMeasure::factory(),
            'purchase_cost' => '10.50',
        ];
    }
}
