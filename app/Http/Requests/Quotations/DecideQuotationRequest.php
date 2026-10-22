<?php

namespace App\Http\Requests\Quotations;

use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;

class DecideQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Quotation $quotation */
        $quotation = $this->route('quotation');
        $decision = $this->input('decision');

        if ($decision === 'approve') {
            return $this->user()?->can('approve', $quotation) ?? false;
        }

        if ($decision === 'reject') {
            return $this->user()?->can('reject', $quotation) ?? false;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject'],
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'decision' => 'القرار',
            'decision_notes' => 'ملاحظات القرار',
        ];
    }
}
