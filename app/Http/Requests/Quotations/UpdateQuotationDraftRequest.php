<?php

namespace App\Http\Requests\Quotations;

use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQuotationDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Quotation $quotation */
        $quotation = $this->route('quotation');

        return $this->user()?->can('update', $quotation) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes_ar' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer', 'exists:quotation_lines,id'],
            'lines.*.margin_amount' => ['required', 'numeric', 'gte:0', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'notes_ar' => 'ملاحظات',
            'expires_at' => 'تاريخ الانتهاء',
            'lines.*.margin_amount' => 'الهامش',
        ];
    }

    /**
     * @return array{
     *     notes_ar: string|null,
     *     expires_at: string|null,
     *     lines: list<array{id: int, margin_amount: string}>
     * }
     */
    public function payload(): array
    {
        $lines = [];
        foreach ($this->validated('lines') as $line) {
            $lines[] = [
                'id' => (int) $line['id'],
                'margin_amount' => number_format((float) $line['margin_amount'], 2, '.', ''),
            ];
        }

        return [
            'notes_ar' => $this->validated('notes_ar'),
            'expires_at' => $this->validated('expires_at'),
            'lines' => $lines,
        ];
    }
}
