<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Support\Warehouse\DeliveryStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sales_order_id' => SalesOrder::factory(),
            'customer_id' => Customer::factory(),
            'created_by_user_id' => User::factory()->withRole(RoleSlug::Warehouse),
            'status' => DeliveryStatus::Draft,
        ];
    }
}
