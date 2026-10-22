<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\User;
use App\Support\Visits\VisitStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerVisit>
 */
class CustomerVisitFactory extends Factory
{
    protected $model = CustomerVisit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'assigned_user_id' => User::factory(),
            'created_by_user_id' => User::factory(),
            'scheduled_at' => now()->addDay(),
            'status' => VisitStatus::Scheduled,
            'notes' => null,
            'outcome' => null,
            'customer_needs' => null,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => VisitStatus::Scheduled,
            'completed_at' => null,
            'cancelled_at' => null,
            'outcome' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => VisitStatus::Completed,
            'completed_at' => now(),
            'outcome' => 'تمت الزيارة',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => VisitStatus::Cancelled,
            'cancelled_at' => now(),
            'outcome' => null,
        ]);
    }
}
