<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerPurchaseOrder;
use App\Models\Deal;
use App\Support\Sales\DealStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    protected $model = Deal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'customer_purchase_order_id' => CustomerPurchaseOrder::factory(),
            'status' => DealStatus::Open,
        ];
    }
}
