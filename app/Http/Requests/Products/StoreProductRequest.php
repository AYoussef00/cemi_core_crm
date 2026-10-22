<?php

namespace App\Http\Requests\Products;

use App\Models\Product;
use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $activeUnitIds = UnitOfMeasure::query()->active()->pluck('id')->all();

        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'unit_id' => ['required', 'integer', Rule::in($activeUnitIds)],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'density_value' => ['nullable', 'string', 'max:255'],
            'density_unit_ar' => ['nullable', 'string', 'max:64'],
            'specifications' => ['nullable', 'array', 'max:50'],
            'specifications.*.label_ar' => ['nullable', 'string', 'max:255'],
            'specifications.*.value' => ['nullable', 'string', 'max:255'],
            'specifications.*.unit_ar' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (filled($this->input('density_unit_ar')) && blank($this->input('density_value'))) {
                $validator->errors()->add('density_value', 'قيمة الكثافة مطلوبة عند تحديد وحدة الكثافة.');
            }

            foreach ((array) $this->input('specifications', []) as $index => $row) {
                $label = trim((string) ($row['label_ar'] ?? ''));
                $value = trim((string) ($row['value'] ?? ''));
                if (($label !== '' && $value === '') || ($label === '' && $value !== '')) {
                    $validator->errors()->add(
                        "specifications.{$index}.label_ar",
                        'كل مواصفة تحتاج تسمية وقيمة معًا، أو تُترك فارغة.'
                    );
                }
                if ($label === Product::DENSITY_LABEL_AR) {
                    $validator->errors()->add(
                        "specifications.{$index}.label_ar",
                        'استخدم حقل الكثافة المخصص بدل تكرار تسمية الكثافة.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name_ar' => 'اسم المنتج',
            'unit_id' => 'وحدة القياس',
            'purchase_cost' => 'تكلفة الشراء',
            'density_value' => 'الكثافة',
            'density_unit_ar' => 'وحدة الكثافة',
        ];
    }

    /**
     * @return array{name_ar: string, unit_id: int, purchase_cost: string|null, specifications: list<array{label_ar: string, value: string, unit_ar: string|null}>}
     */
    public function productPayload(): array
    {
        $specs = [];

        if (filled($this->validated('density_value'))) {
            $specs[] = [
                'label_ar' => Product::DENSITY_LABEL_AR,
                'value' => (string) $this->validated('density_value'),
                'unit_ar' => $this->validated('density_unit_ar') ?? null,
            ];
        }

        foreach ((array) ($this->validated('specifications') ?? []) as $row) {
            $label = trim((string) ($row['label_ar'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }
            $specs[] = [
                'label_ar' => $label,
                'value' => $value,
                'unit_ar' => filled($row['unit_ar'] ?? null) ? trim((string) $row['unit_ar']) : null,
            ];
        }

        $cost = $this->validated('purchase_cost');

        return [
            'name_ar' => (string) $this->validated('name_ar'),
            'unit_id' => (int) $this->validated('unit_id'),
            'purchase_cost' => $cost !== null && $cost !== '' ? (string) $cost : null,
            'specifications' => $specs,
        ];
    }
}
