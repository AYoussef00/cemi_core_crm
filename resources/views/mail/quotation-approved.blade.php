<x-mail::message>
# عرض سعر معتمد

السلام عليكم،

نرفق تفاصيل عرض السعر رقم **{{ $quotation->document_number }}** (الإصدار {{ $quotation->version }}) للمنشأة **{{ $quotation->customer?->name_ar }}**.

الأسعار **شاملة ضريبة القيمة المضافة 15%** (ثابتة في الإصدار الحالي).

| المنتج | الكمية | سعر الوحدة (شامل الضريبة) | الإجمالي |
|:--|--:|--:|--:|
@foreach ($quotation->lines as $line)
| {{ $line->product_name_ar }} | {{ qty($line->quantity) }} | {{ money($line->selling_price_vat_inclusive) }} ر.س | {{ money($line->lineTotalVatInclusive()) }} ر.س |
@endforeach

**الإجمالي الشامل للضريبة:** {{ money($quotation->totalVatInclusive()) }} ر.س

@if ($quotation->expires_at)
صلاحية العرض حتى: {{ $quotation->expires_at->timezone(config('app.timezone'))->format('Y-m-d') }}
@endif

@if ($company?->name_ar)
{{ $company->name_ar }}
@endif

مع التحية،  
{{ config('app.name') }}
</x-mail::message>
