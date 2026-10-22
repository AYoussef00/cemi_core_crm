<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\SalesRequest;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Support\Quotations\QuotationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sales_request_id' => SalesRequest::factory()->readyForPricing(),
            'customer_id' => Customer::factory(),
            'created_by_user_id' => User::factory()->withRole(RoleSlug::SalesRepresentative),
            'version' => 1,
            'status' => QuotationStatus::Draft,
            'vat_rate' => '0.1500',
            'expires_at' => now()->addDays(14),
        ];
    }
}
