<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Support\Finance\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_id' => Delivery::factory(),
            'sales_order_id' => SalesOrder::factory(),
            'deal_id' => Deal::factory(),
            'customer_id' => Customer::factory(),
            'issued_by_user_id' => User::factory()->withRole(RoleSlug::Accounts),
            'status' => InvoiceStatus::Issued,
            'total_vat_inclusive' => '100.00',
            'issued_at' => now(),
            'due_at' => now()->addDays(30),
        ];
    }
}
