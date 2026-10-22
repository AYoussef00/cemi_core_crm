<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>فاتورة {{ $invoice->document_number }} — {{ $company?->name_ar ?: config('app.name') }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Almarai', 'Segoe UI', Tahoma, sans-serif;
            color: #111;
            background: #e8eaed;
            direction: rtl;
        }
        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 1rem auto;
            padding: 14mm 12mm;
            background: #fff;
            box-shadow: 0 8px 28px rgb(15 23 42 / 0.12);
        }
        .toolbar { margin-bottom: 1rem; display: flex; gap: 0.75rem; align-items: center; }
        .toolbar button, .toolbar a {
            font: inherit; font-weight: 700; padding: 0.45rem 0.9rem;
            border-radius: 0.55rem; border: 1px solid #cbd5e1; background: #fff; color: #111; text-decoration: none; cursor: pointer;
        }
        .doc-brand-header {
            display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;
            margin-bottom: 1.25rem; padding-bottom: 0.85rem; border-bottom: 2px solid #003D33;
        }
        .doc-brand-block { display: flex; align-items: flex-start; gap: 0.65rem; max-width: 55%; }
        .doc-brand-logo { width: 56px; height: 56px; object-fit: contain; flex: 0 0 56px; }
        .doc-brand-text { display: grid; gap: 0.1rem; }
        .doc-brand-name { margin: 0; font-size: 14px; font-weight: 800; }
        .doc-brand-meta { margin: 0; font-size: 11px; color: #334155; }
        .doc-brand-title { margin: 0; font-size: 20px; font-weight: 800; color: #003D33; }
        .meta {
            display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;
        }
        .box {
            border: 1px solid #cbd5e1; border-radius: 0.65rem; padding: 0.75rem 0.85rem;
        }
        .box h2 { margin: 0 0 0.4rem; font-size: 12px; color: #64748b; font-weight: 800; }
        .box p { margin: 0.15rem 0; font-size: 13px; font-weight: 700; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 0.5rem; }
        table.lines th, table.lines td {
            border: 1px solid #94a3b8; padding: 0.45rem 0.55rem; font-size: 12px; text-align: start;
        }
        table.lines th { background: #f1f5f9; font-weight: 800; }
        .totals {
            margin-top: 1rem; max-width: 16rem; margin-inline-start: auto;
            border: 1px solid #cbd5e1; border-radius: 0.65rem; overflow: hidden;
        }
        .totals div {
            display: flex; justify-content: space-between; gap: 1rem;
            padding: 0.5rem 0.75rem; font-size: 13px; font-weight: 700;
            border-bottom: 1px solid #e2e8f0;
        }
        .totals div:last-child { border-bottom: 0; background: #ecfdf8; color: #003D33; font-size: 14px; }
        .muted { color: #64748b; font-weight: 600; }
        .foot { margin-top: 1.5rem; font-size: 11px; color: #64748b; }
        @media print {
            body { background: #fff; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: auto; }
            .toolbar { display: none !important; }
            .doc-brand-logo, .totals div:last-child { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
@php
    $unpaid = (float) ($unpaid ?? $invoice->unpaidBalance());
@endphp
<div class="sheet">
    <div class="toolbar">
        <button type="button" onclick="window.print()">طباعة / حفظ PDF</button>
        <a href="{{ route('invoices.show', $invoice) }}">العودة للفاتورة</a>
    </div>

    @include('partials.doc-brand-header', [
        'company' => $company,
        'title' => 'فاتورة ضريبية',
    ])

    <section class="meta" aria-label="بيانات الفاتورة">
        <div class="box">
            <h2>العميل</h2>
            <p>{{ $invoice->customer?->name_ar ?? '—' }}</p>
            <p class="muted dir-ltr">#{{ $invoice->customer_id }}</p>
        </div>
        <div class="box">
            <h2>بيانات الفاتورة</h2>
            <p>رقم الفاتورة: <span class="dir-ltr">{{ $invoice->document_number }}</span></p>
            <p>التسليم: <span class="dir-ltr">{{ doc_no('DN', $invoice->delivery_id) }}</span></p>
            <p>الصفقة: <span class="dir-ltr">#{{ $invoice->deal_id }}</span></p>
            <p>الإصدار: {{ $invoice->issued_at?->timezone(config('app.timezone'))->format('Y-m-d') ?? '—' }}</p>
            <p>الاستحقاق: {{ $invoice->due_at?->timezone(config('app.timezone'))->format('Y-m-d') ?? '—' }}</p>
        </div>
    </section>

    <table class="lines">
        <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">المنتج</th>
                <th scope="col">الكمية</th>
                <th scope="col">الإجمالي شامل الضريبة</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $i => $line)
                <tr>
                    <td class="dir-ltr">{{ $i + 1 }}</td>
                    <td>{{ $line->product?->name_ar ?? '—' }}</td>
                    <td class="dir-ltr">{{ number_format((float) $line->quantity, 3) }}</td>
                    <td class="dir-ltr">{{ number_format((float) $line->line_total_vat_inclusive, 2) }} ر.س</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals" aria-label="المجاميع">
        <div>
            <span>الإجمالي شامل الضريبة</span>
            <span class="dir-ltr">{{ number_format((float) $invoice->total_vat_inclusive, 2) }} ر.س</span>
        </div>
        <div>
            <span>المتبقي (CR7)</span>
            <span class="dir-ltr">{{ number_format($unpaid, 2) }} ر.س</span>
        </div>
    </div>

    <p class="foot">
        فاتورة صادرة من {{ $company?->name_ar ?: config('app.name') }} · ضريبة القيمة المضافة 15% شاملة · بلا خصومات.
        ترقيم/ZATCA الإلكتروني خارج نطاق هذه الطباعة (TX6).
    </p>
</div>
</body>
</html>
