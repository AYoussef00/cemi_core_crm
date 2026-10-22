<?php

namespace App\Http\Requests\Credit;

use App\Models\CreditLimitRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideCreditLimitRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CreditLimitRequest $creditRequest */
        $creditRequest = $this->route('credit_request');

        return $this->user()?->can('decideFinal', $creditRequest) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'decision' => 'قرار الاعتماد',
            'notes' => 'ملاحظات الاعتماد',
        ];
    }
}
