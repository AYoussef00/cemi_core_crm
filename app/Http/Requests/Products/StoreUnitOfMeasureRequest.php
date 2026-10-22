<?php

namespace App\Http\Requests\Products;

use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', UnitOfMeasure::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255', Rule::unique('units_of_measure', 'name_ar')],
            'symbol_ar' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name_ar' => 'اسم الوحدة',
            'symbol_ar' => 'الرمز',
            'is_active' => 'نشطة',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        } else {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }
}
