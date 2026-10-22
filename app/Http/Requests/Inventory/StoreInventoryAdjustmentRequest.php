<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryAdjustment;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryAdjustment::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'quantity_delta' => ['required', 'numeric', 'not_in:0', 'decimal:0,3'],
            'reason_ar' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_id' => 'المنتج',
            'quantity_delta' => 'كمية التسوية',
            'reason_ar' => 'سبب التسوية',
        ];
    }

    /**
     * @return array{product_id: int, quantity_delta: string, reason_ar: string}
     */
    public function payload(): array
    {
        return [
            'product_id' => (int) $this->validated('product_id'),
            'quantity_delta' => number_format((float) $this->validated('quantity_delta'), 3, '.', ''),
            'reason_ar' => (string) $this->validated('reason_ar'),
        ];
    }
}
