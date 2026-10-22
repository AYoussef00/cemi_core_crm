<?php

namespace App\Http\Requests\Procurement;

use App\Models\SupplierPurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class MarkSupplierPurchaseOrderOrderedRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SupplierPurchaseOrder $supplier_po */
        $supplier_po = $this->route('supplier_po');

        return $this->user()?->can('markOrdered', $supplier_po) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('attachments.max_kilobytes', 5120);

        return [
            'expected_at' => ['required', 'date'],
            'supplier_name_ar' => ['nullable', 'string', 'max:255'],
            'notes_ar' => ['nullable', 'string', 'max:2000'],
            'po_document' => ['nullable', 'file', 'max:'.$maxKb, 'extensions:pdf', 'mimes:pdf'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'expected_at' => 'موعد الوصول المتوقع',
            'supplier_name_ar' => 'اسم المورد',
            'notes_ar' => 'ملاحظات',
            'po_document' => 'نسخة أمر الشراء (PDF)',
        ];
    }

    /**
     * @return array{
     *     expected_at: string,
     *     supplier_name_ar: string|null,
     *     notes_ar: string|null,
     *     po_document: UploadedFile|null
     * }
     */
    public function payload(): array
    {
        return [
            'expected_at' => (string) $this->validated('expected_at'),
            'supplier_name_ar' => $this->validated('supplier_name_ar'),
            'notes_ar' => $this->validated('notes_ar'),
            'po_document' => $this->file('po_document'),
        ];
    }
}
