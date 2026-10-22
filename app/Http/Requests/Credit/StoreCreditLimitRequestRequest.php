<?php

namespace App\Http\Requests\Credit;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class StoreCreditLimitRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return $this->user()?->can('create', [\App\Models\CreditLimitRequest::class, $customer]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'requested_credit_limit' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999999.99'],
            'requested_payment_term' => ['nullable', 'string', 'max:255'],
            'request_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'requested_credit_limit' => 'حد الائتمان المطلوب',
            'requested_payment_term' => 'شروط الدفع المطلوبة',
            'request_notes' => 'ملاحظات الطلب',
        ];
    }
}
