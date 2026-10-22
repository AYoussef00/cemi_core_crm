<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_purchase_order_id',
    'quotation_line_id',
    'product_id',
    'quantity',
    'sort_order',
])]
class CustomerPurchaseOrderLine extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function customerPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerPurchaseOrder::class);
    }

    public function quotationLine(): BelongsTo
    {
        return $this->belongsTo(QuotationLine::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function salesOrderLines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }
}
