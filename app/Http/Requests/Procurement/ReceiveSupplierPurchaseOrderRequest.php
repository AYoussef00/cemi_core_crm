<?php

namespace App\Http\Requests\Procurement;

use App\Models\SupplierPurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class ReceiveSupplierPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SupplierPurchaseOrder $supplier_po */
        $supplier_po = $this->route('supplier_po');

        return $this->user()?->can('receive', $supplier_po) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('attachments.max_kilobytes', 5120);

        return [
            'shipment_reference_ar' => ['nullable', 'string', 'max:255'],
            'notes_ar' => ['nullable', 'string', 'max:2000'],
            'delivery_note_pdf' => ['required', 'file', 'max:'.$maxKb, 'extensions:pdf', 'mimes:pdf'],
            'receipts' => ['required', 'array', 'min:1'],
            'receipts.*.line_id' => ['required', 'integer'],
            'receipts.*.quantity' => ['nullable', 'numeric', 'gte:0', 'decimal:0,3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'shipment_reference_ar' => 'مرجع الشحنة',
            'notes_ar' => 'ملاحظات الاستلام',
            'delivery_note_pdf' => 'محضر/إذن التسليم الموقع',
            'receipts' => 'كميات الاستلام',
            'receipts.*.line_id' => 'بند أمر الشراء',
            'receipts.*.quantity' => 'كمية الاستلام',
        ];
    }

    /**
     * @return array{
     *     lines: list<array{line_id: int, quantity: string}>,
     *     shipment_reference_ar: string|null,
     *     notes_ar: string|null,
     *     delivery_note_pdf: UploadedFile
     * }
     */
    public function payload(): array
    {
        $lines = [];
        foreach ($this->validated('receipts') as $row) {
            $qty = isset($row['quantity']) ? (float) $row['quantity'] : 0.0;
            if ($qty <= 0) {
                continue;
            }
            $lines[] = [
                'line_id' => (int) $row['line_id'],
                'quantity' => number_format($qty, 3, '.', ''),
            ];
        }

        /** @var UploadedFile $pdf */
        $pdf = $this->file('delivery_note_pdf');

        return [
            'lines' => $lines,
            'shipment_reference_ar' => $this->validated('shipment_reference_ar'),
            'notes_ar' => $this->validated('notes_ar'),
            'delivery_note_pdf' => $pdf,
        ];
    }
}
