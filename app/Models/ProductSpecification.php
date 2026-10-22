<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'label_ar', 'value', 'unit_ar', 'sort_order'])]
class ProductSpecification extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function displayValue(): string
    {
        if (filled($this->unit_ar)) {
            return $this->value.' '.$this->unit_ar;
        }

        return $this->value;
    }
}
