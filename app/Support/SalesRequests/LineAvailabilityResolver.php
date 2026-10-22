<?php

namespace App\Support\SalesRequests;

use App\Models\Product;
use App\Support\Inventory\InventoryService;

/**
 * Resolves line availability from warehouse available qty vs requested qty (SR-AUTO-STOCK).
 *
 * qty ≤ available → available; qty > available (or no balance/warehouse) → needs_procurement.
 */
final class LineAvailabilityResolver
{
    public function __construct(private InventoryService $inventory) {}

    public function resolve(int $productId, float|string $quantity): LineAvailability
    {
        $qty = (float) $quantity;
        if ($qty <= 0) {
            return LineAvailability::NeedsProcurement;
        }

        $product = Product::query()->find($productId);
        if ($product === null) {
            return LineAvailability::NeedsProcurement;
        }

        $available = (float) ($this->inventory->balanceFor($product)?->available() ?? 0);

        return $qty > $available + 1e-9
            ? LineAvailability::NeedsProcurement
            : LineAvailability::Available;
    }
}
