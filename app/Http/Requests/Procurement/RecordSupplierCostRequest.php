<?php

namespace App\Http\Requests\Procurement;

use App\Models\ProcurementTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class RecordSupplierCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProcurementTask $task */
        $task = $this->route('task');

        return $this->user()?->can('recordCost', $task) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('attachments.max_kilobytes', 5120);

        return [
            'supplier_cost' => ['required', 'numeric', 'gte:0', 'decimal:0,2'],
            'supplier_name_ar' => ['nullable', 'string', 'max:255'],
            'notes_ar' => ['nullable', 'string', 'max:1000'],
            'supplier_quote_pdf' => ['required', 'file', 'max:'.$maxKb, 'extensions:pdf', 'mimes:pdf'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supplier_cost' => 'تكلفة المورد',
            'supplier_name_ar' => 'اسم المورد',
            'notes_ar' => 'ملاحظات',
            'supplier_quote_pdf' => 'عرض سعر المورد (PDF)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_quote_pdf.required' => 'يجب إرفاق عرض سعر المورد بصيغة PDF.',
            'supplier_quote_pdf.extensions' => 'عرض سعر المورد يجب أن يكون PDF فقط.',
            'supplier_quote_pdf.mimes' => 'عرض سعر المورد يجب أن يكون PDF فقط.',
        ];
    }

    /**
     * @return array{
     *     supplier_cost: string,
     *     supplier_name_ar: string|null,
     *     notes_ar: string|null,
     *     supplier_quote_pdf: UploadedFile
     * }
     */
    public function payload(): array
    {
        /** @var UploadedFile $pdf */
        $pdf = $this->file('supplier_quote_pdf');

        return [
            'supplier_cost' => number_format((float) $this->validated('supplier_cost'), 2, '.', ''),
            'supplier_name_ar' => $this->validated('supplier_name_ar'),
            'notes_ar' => $this->validated('notes_ar'),
            'supplier_quote_pdf' => $pdf,
        ];
    }
}
