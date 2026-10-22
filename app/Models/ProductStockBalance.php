<?php

namespace App\Models;

use Database\Factories\ProductStockBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['warehouse_id', 'product_id', 'on_hand', 'reserved'])]
class ProductStockBalance extends Model
{
    /** @use HasFactory<ProductStockBalanceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'on_hand' => 'decimal:3',
            'reserved' => 'decimal:3',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Available = on_hand − reserved (computed; never stored).
     */
    public function available(): string
    {
        return $this->formatQty((float) $this->on_hand - (float) $this->reserved);
    }

    public function formatQty(float|string $qty): string
    {
        return number_format((float) $qty, 3, '.', '');
    }
}
