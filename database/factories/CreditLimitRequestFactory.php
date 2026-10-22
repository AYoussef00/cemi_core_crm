<?php

namespace Database\Factories;

use App\Models\CreditLimitRequest;
use App\Models\Customer;
use App\Models\User;
use App\Support\Credit\CreditLimitRequestStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditLimitRequest>
 */
class CreditLimitRequestFactory extends Factory
{
    protected $model = CreditLimitRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'requested_by_user_id' => User::factory(),
            'requested_credit_limit' => '10000.00',
            'requested_payment_term' => '30 يوم',
            'status' => CreditLimitRequestStatus::PendingReview,
            'request_notes' => null,
        ];
    }

    public function pendingReview(): static
    {
        return $this->state(fn () => [
            'status' => CreditLimitRequestStatus::PendingReview,
        ]);
    }

    public function pendingFinal(): static
    {
        return $this->state(fn () => [
            'status' => CreditLimitRequestStatus::PendingFinal,
            'reviewed_at' => now(),
        ]);
    }
}
