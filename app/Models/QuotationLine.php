<?php

namespace App\Models;

use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quotation_id',
    'sales_request_line_id',
    'product_id',
    'product_name_ar',
    'quantity',
    'supplier_cost',
    'margin_amount',
    'selling_price_vat_inclusive',
    'sort_order',
])]
class QuotationLine extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'supplier_cost' => 'decimal:2',
            'margin_amount' => 'decimal:2',
            'selling_price_vat_inclusive' => 'decimal:2',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function salesRequestLine(): BelongsTo
    {
        return $this->belongsTo(SalesRequestLine::class);
    }

    public function lineTotalVatInclusive(): string
    {
        return Money::round2((float) $this->selling_price_vat_inclusive * (float) $this->quantity);
    }

    public function vatPortionUnit(): string
    {
        return Money::vatFromInclusive($this->selling_price_vat_inclusive);
    }
}
