<?php

namespace App\Http\Requests\Products;

use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var UnitOfMeasure $unit */
        $unit = $this->route('unit');

        return $this->user()?->can('update', $unit) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var UnitOfMeasure $unit */
        $unit = $this->route('unit');

        return [
            'name_ar' => [
                'required',
                'string',
                'max:255',
                Rule::unique('units_of_measure', 'name_ar')->ignore($unit->id),
            ],
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
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
