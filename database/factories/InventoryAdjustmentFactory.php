<?php

namespace Database\Factories;

use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Support\Inventory\InventoryAdjustmentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryAdjustment>
 */
class InventoryAdjustmentFactory extends Factory
{
    protected $model = InventoryAdjustment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => 1,
            'product_id' => Product::factory(),
            'quantity_delta' => '5.000',
            'reason_ar' => 'تسوية اختبار',
            'status' => InventoryAdjustmentStatus::Pending,
            'recorded_by_user_id' => User::factory()->withRole(RoleSlug::Warehouse),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => InventoryAdjustmentStatus::Pending]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => InventoryAdjustmentStatus::Approved,
            'decided_at' => now(),
        ]);
    }
}
