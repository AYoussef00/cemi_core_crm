<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerPurchaseOrder;
use App\Models\Deal;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Support\Sales\DealStatus;
use App\Support\Sales\SalesOrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'created_by_user_id' => User::factory()->withRole(RoleSlug::SalesRepresentative),
            'status' => SalesOrderStatus::Draft,
            'customer_purchase_order_id' => null,
            'deal_id' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (SalesOrder $order): void {
            if ($order->customer_purchase_order_id && $order->deal_id) {
                return;
            }

            $customerId = $order->customer_id ?? Customer::factory()->create()->id;
            $po = CustomerPurchaseOrder::factory()->create(['customer_id' => $customerId]);
            $deal = Deal::query()->firstOrCreate(
                ['customer_purchase_order_id' => $po->id],
                [
                    'customer_id' => $customerId,
                    'status' => DealStatus::Open,
                ],
            );

            $order->customer_id = $customerId;
            $order->customer_purchase_order_id = $po->id;
            $order->deal_id = $deal->id;
        });
    }
}
