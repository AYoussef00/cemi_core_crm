<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductStockBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductStockBalance>
 */
class ProductStockBalanceFactory extends Factory
{
    protected $model = ProductStockBalance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => 1,
            'product_id' => Product::factory(),
            'on_hand' => '10.000',
            'reserved' => '0.000',
        ];
    }
}
