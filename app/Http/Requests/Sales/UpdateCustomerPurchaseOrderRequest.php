<?php

namespace App\Http\Requests\Sales;

use App\Models\CustomerPurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CustomerPurchaseOrder $po */
        $po = $this->route('customer_po');

        return $this->user()?->can('update', $po) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference_ar' => ['nullable', 'string', 'max:255'],
            'notes_ar' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array{reference_ar: string|null, notes_ar: string|null}
     */
    public function payload(): array
    {
        return [
            'reference_ar' => $this->validated('reference_ar'),
            'notes_ar' => $this->validated('notes_ar'),
        ];
    }
}
