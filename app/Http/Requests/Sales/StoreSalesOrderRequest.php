<?php

namespace App\Http\Requests\Sales;

use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SalesOrder::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $lines = collect($this->input('lines', []))
            ->filter(fn ($line) => isset($line['quantity']) && (float) $line['quantity'] > 0)
            ->values()
            ->all();

        $this->merge(['lines' => $lines]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_purchase_order_id' => ['required', 'integer', 'exists:customer_purchase_orders,id'],
            'notes_ar' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.customer_purchase_order_line_id' => ['required', 'integer', 'exists:customer_purchase_order_lines,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_purchase_order_id' => 'أمر شراء العميل',
            'notes_ar' => 'ملاحظات',
            'lines.*.quantity' => 'الكمية',
        ];
    }

    /**
     * @return array{
     *     customer_purchase_order_id: int,
     *     notes_ar: string|null,
     *     lines: list<array{customer_purchase_order_line_id: int, quantity: string}>
     * }
     */
    public function payload(): array
    {
        $lines = [];
        foreach ($this->validated('lines') as $line) {
            $lines[] = [
                'customer_purchase_order_line_id' => (int) $line['customer_purchase_order_line_id'],
                'quantity' => number_format((float) $line['quantity'], 3, '.', ''),
            ];
        }

        return [
            'customer_purchase_order_id' => (int) $this->validated('customer_purchase_order_id'),
            'notes_ar' => $this->validated('notes_ar'),
            'lines' => $lines,
        ];
    }
}
