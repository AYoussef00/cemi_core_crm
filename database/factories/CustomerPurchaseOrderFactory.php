<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerPurchaseOrder;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Support\Sales\CustomerPurchaseOrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPurchaseOrder>
 */
class CustomerPurchaseOrderFactory extends Factory
{
    protected $model = CustomerPurchaseOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'quotation_id' => Quotation::factory(),
            'created_by_user_id' => User::factory()->withRole(RoleSlug::SalesRepresentative),
            'status' => CustomerPurchaseOrderStatus::Open,
        ];
    }
}
