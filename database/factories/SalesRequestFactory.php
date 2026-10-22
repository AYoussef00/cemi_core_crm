<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\SalesRequest;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Support\SalesRequests\SalesRequestStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesRequest>
 */
class SalesRequestFactory extends Factory
{
    protected $model = SalesRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'created_by_user_id' => User::factory()->withRole(RoleSlug::SalesRepresentative),
            'status' => SalesRequestStatus::Draft,
            'notes_ar' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => SalesRequestStatus::Draft]);
    }

    public function awaitingProcurement(): static
    {
        return $this->state(fn () => [
            'status' => SalesRequestStatus::AwaitingProcurement,
            'submitted_at' => now(),
        ]);
    }

    public function readyForPricing(): static
    {
        return $this->state(fn () => [
            'status' => SalesRequestStatus::ReadyForPricing,
            'submitted_at' => now(),
        ]);
    }
}
