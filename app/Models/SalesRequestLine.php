<?php

namespace App\Models;

use App\Support\SalesRequests\LineAvailability;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'sales_request_id',
    'product_id',
    'quantity',
    'availability',
    'supplier_cost',
    'sort_order',
])]
class SalesRequestLine extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'availability' => LineAvailability::class,
            'supplier_cost' => 'decimal:2',
        ];
    }

    public function salesRequest(): BelongsTo
    {
        return $this->belongsTo(SalesRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function procurementTask(): HasOne
    {
        return $this->hasOne(ProcurementTask::class);
    }

    public function needsProcurement(): bool
    {
        return $this->availability === LineAvailability::NeedsProcurement;
    }
}
