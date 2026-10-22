<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'supplier_goods_receipt_id',
    'supplier_purchase_order_line_id',
    'product_id',
    'quantity',
    'sort_order',
])]
class SupplierGoodsReceiptLine extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(SupplierGoodsReceipt::class, 'supplier_goods_receipt_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(SupplierPurchaseOrderLine::class, 'supplier_purchase_order_line_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
