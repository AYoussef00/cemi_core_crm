<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => 'عميل '.fake()->unique()->numerify('###'),
            'assigned_sales_rep_id' => null,
        ];
    }

    public function assignedTo(User $rep): static
    {
        return $this->state(fn () => [
            'assigned_sales_rep_id' => $rep->id,
        ]);
    }
}
