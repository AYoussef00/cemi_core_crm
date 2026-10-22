<?php

namespace App\Http\Requests\Visits;

use App\Models\CustomerVisit;
use Illuminate\Foundation\Http\FormRequest;

class CompleteVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CustomerVisit $visit */
        $visit = $this->route('visit');

        return $this->user()?->can('complete', $visit) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['required', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'customer_needs' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'outcome' => 'نتيجة الزيارة',
            'notes' => 'ملاحظات',
            'customer_needs' => 'احتياجات العميل',
        ];
    }
}
