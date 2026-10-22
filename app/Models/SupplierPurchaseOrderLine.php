<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'supplier_purchase_order_id',
    'product_id',
    'sales_order_line_id',
    'quantity',
    'received_quantity',
    'unit_cost',
    'sort_order',
])]
class SupplierPurchaseOrderLine extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'received_quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(SupplierPurchaseOrder::class, 'supplier_purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    public function remainingQuantity(): float
    {
        return max(0, (float) $this->quantity - (float) $this->received_quantity);
    }
}
