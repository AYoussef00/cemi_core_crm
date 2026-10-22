<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sales_order_id',
    'customer_purchase_order_line_id',
    'product_id',
    'quantity',
    'sort_order',
])]
class SalesOrderLine extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function customerPurchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(CustomerPurchaseOrderLine::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
