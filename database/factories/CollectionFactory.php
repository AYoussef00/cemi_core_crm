<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Support\Finance\CollectionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Collection>
 */
class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'created_by_user_id' => User::factory()->withRole(RoleSlug::Accounts),
            'status' => CollectionStatus::Draft,
            'amount_total' => '0.00',
        ];
    }
}
