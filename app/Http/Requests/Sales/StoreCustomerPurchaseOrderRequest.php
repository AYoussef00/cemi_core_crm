<?php

namespace App\Http\Requests\Sales;

use App\Models\CustomerPurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CustomerPurchaseOrder::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'quotation_id' => ['required', 'integer', 'exists:quotations,id'],
            'reference_ar' => ['nullable', 'string', 'max:255'],
            'notes_ar' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.quotation_line_id' => ['required', 'integer', 'exists:quotation_lines,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'المنشأة',
            'quotation_id' => 'عرض السعر',
            'reference_ar' => 'مرجع أمر الشراء',
            'notes_ar' => 'ملاحظات',
            'lines.*.quantity' => 'الكمية',
        ];
    }

    /**
     * @return array{
     *     customer_id: int,
     *     quotation_id: int,
     *     reference_ar: string|null,
     *     notes_ar: string|null,
     *     lines: list<array{quotation_line_id: int, quantity: string}>
     * }
     */
    public function payload(): array
    {
        $lines = [];
        foreach ($this->validated('lines') as $line) {
            $lines[] = [
                'quotation_line_id' => (int) $line['quotation_line_id'],
                'quantity' => number_format((float) $line['quantity'], 3, '.', ''),
            ];
        }

        return [
            'customer_id' => (int) $this->validated('customer_id'),
            'quotation_id' => (int) $this->validated('quotation_id'),
            'reference_ar' => $this->validated('reference_ar'),
            'notes_ar' => $this->validated('notes_ar'),
            'lines' => $lines,
        ];
    }
}
