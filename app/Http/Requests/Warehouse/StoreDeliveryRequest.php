<?php

namespace App\Http\Requests\Warehouse;

use App\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Delivery::class) ?? false;
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
            'sales_order_id' => ['required', 'integer', 'exists:sales_orders,id'],
            'notes_ar' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sales_order_line_id' => ['required', 'integer', 'exists:sales_order_lines,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
        ];
    }

    /**
     * @return array{
     *     sales_order_id: int,
     *     notes_ar: string|null,
     *     lines: list<array{sales_order_line_id: int, quantity: string}>
     * }
     */
    public function payload(): array
    {
        $lines = [];
        foreach ($this->validated('lines') as $line) {
            $lines[] = [
                'sales_order_line_id' => (int) $line['sales_order_line_id'],
                'quantity' => number_format((float) $line['quantity'], 3, '.', ''),
            ];
        }

        return [
            'sales_order_id' => (int) $this->validated('sales_order_id'),
            'notes_ar' => $this->validated('notes_ar'),
            'lines' => $lines,
        ];
    }
}
