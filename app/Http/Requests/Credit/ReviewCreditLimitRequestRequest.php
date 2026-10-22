<?php

namespace App\Http\Requests\Credit;

use App\Models\CreditLimitRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewCreditLimitRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CreditLimitRequest $creditRequest */
        $creditRequest = $this->route('credit_request');

        return $this->user()?->can('review', $creditRequest) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['forward', 'reject'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'decision' => 'قرار المراجعة',
            'notes' => 'ملاحظات المراجعة',
        ];
    }
}
