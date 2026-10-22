<?php

namespace App\Http\Requests\Sales;

use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SalesOrder $order */
        $order = $this->route('sales_order');
        $decision = $this->input('decision');

        if ($decision === 'approve') {
            return $this->user()?->can('approve', $order) ?? false;
        }

        if ($decision === 'reject') {
            return $this->user()?->can('reject', $order) ?? false;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
