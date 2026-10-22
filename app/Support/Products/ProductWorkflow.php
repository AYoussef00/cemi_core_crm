<?php

namespace App\Support\Products;

use App\Models\Product;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class ProductWorkflow
{
    public function __construct(
        private AuditLogger $audit,
        private ProductSpecificationSync $specifications,
    ) {}

    /**
     * @param  array{name_ar: string, unit_id: int, purchase_cost: string|null, specifications: list<array{label_ar: string, value: string, unit_ar: string|null}>}  $payload
     */
    public function create(array $payload, User $actor): Product
    {
        return DB::transaction(function () use ($payload, $actor): Product {
            $product = Product::query()->create([
                'name_ar' => $payload['name_ar'],
                'unit_id' => $payload['unit_id'],
                'purchase_cost' => $payload['purchase_cost'],
            ]);

            $specs = $this->specifications->sync($product, $payload['specifications']);

            $this->audit->record('product.created', $product, [
                'name_ar' => $product->name_ar,
                'unit_id' => $product->unit_id,
                'purchase_cost' => $product->purchase_cost,
                'specifications_count' => count($specs),
                'has_density' => collect($specs)->contains(
                    fn ($s) => $s->label_ar === Product::DENSITY_LABEL_AR
                ),
                'actor_user_id' => $actor->id,
            ]);

            return $product->fresh(['unit', 'specifications']);
        });
    }

    /**
     * @param  array{name_ar: string, unit_id: int, purchase_cost: string|null, specifications: list<array{label_ar: string, value: string, unit_ar: string|null}>}  $payload
     */
    public function update(Product $product, array $payload, User $actor): Product
    {
        return DB::transaction(function () use ($product, $payload, $actor): Product {
            $before = $product->only(['name_ar', 'unit_id', 'purchase_cost']);
            $beforeCost = $product->purchase_cost;

            $product->fill([
                'name_ar' => $payload['name_ar'],
                'unit_id' => $payload['unit_id'],
                'purchase_cost' => $payload['purchase_cost'],
            ])->save();

            $specs = $this->specifications->sync($product, $payload['specifications']);

            $changes = $this->audit->changes(
                $before,
                $product->only(['name_ar', 'unit_id', 'purchase_cost']),
                ['name_ar', 'unit_id', 'purchase_cost'],
            );

            $this->audit->record('product.updated', $product, [
                ...$changes,
                'specifications_count' => count($specs),
                'has_density' => collect($specs)->contains(
                    fn ($s) => $s->label_ar === Product::DENSITY_LABEL_AR
                ),
                'actor_user_id' => $actor->id,
            ]);

            if ((string) $beforeCost !== (string) $product->purchase_cost) {
                $this->audit->record('product.purchase_cost_updated', $product, [
                    'before' => ['purchase_cost' => $beforeCost],
                    'after' => ['purchase_cost' => $product->purchase_cost],
                    'via' => 'master_update',
                    'actor_user_id' => $actor->id,
                ]);
            }

            return $product->fresh(['unit', 'specifications']);
        });
    }

    public function updatePurchaseCost(Product $product, string $purchaseCost, User $actor): Product
    {
        return DB::transaction(function () use ($product, $purchaseCost, $actor): Product {
            $before = $product->purchase_cost;

            $product->fill(['purchase_cost' => $purchaseCost])->save();

            $this->audit->record('product.purchase_cost_updated', $product, [
                'before' => ['purchase_cost' => $before],
                'after' => ['purchase_cost' => $product->purchase_cost],
                'via' => 'cost_only',
                'actor_user_id' => $actor->id,
            ]);

            return $product->fresh(['unit', 'specifications']);
        });
    }
}
