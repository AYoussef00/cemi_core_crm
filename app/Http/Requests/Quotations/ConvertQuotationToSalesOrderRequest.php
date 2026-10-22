<?php

namespace App\Http\Requests\Quotations;

use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;

class ConvertQuotationToSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Quotation $quotation */
        $quotation = $this->route('quotation');

        return $this->user()?->can('convertToSalesOrder', $quotation) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('attachments.max_kilobytes', 5120);

        return [
            'customer_po_pdf' => ['required', 'file', 'max:'.$maxKb, 'extensions:pdf', 'mimes:pdf'],
            'reference_ar' => ['nullable', 'string', 'max:255'],
            'notes_ar' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_po_pdf' => 'ملف أمر شراء العميل (PDF)',
            'reference_ar' => 'مرجع أمر الشراء',
            'notes_ar' => 'ملاحظات',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_po_pdf.required' => 'يجب رفع أمر الشراء من العميل بصيغة PDF قبل التحويل إلى أمر بيع.',
            'customer_po_pdf.extensions' => 'ملف أمر الشراء يجب أن يكون PDF فقط.',
            'customer_po_pdf.mimes' => 'ملف أمر الشراء يجب أن يكون PDF فقط.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        /** @var Quotation $quotation */
        $quotation = $this->route('quotation');

        session()->flash('open_convert_quotation_id', $quotation->id);

        return route('quotations.index', ['convert' => $quotation->id]);
    }
}
