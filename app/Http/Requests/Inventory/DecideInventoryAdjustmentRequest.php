<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryAdjustment;
use Illuminate\Foundation\Http\FormRequest;

class DecideInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var InventoryAdjustment $adjustment */
        $adjustment = $this->route('adjustment');
        $decision = $this->input('decision');

        if ($decision === 'approve') {
            return $this->user()?->can('approve', $adjustment) ?? false;
        }

        if ($decision === 'reject') {
            return $this->user()?->can('reject', $adjustment) ?? false;
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
