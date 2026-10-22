<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>عرض سعر {{ $quotation->document_number }} — {{ $company?->name_ar ?? config('app.name') }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 1rem 1.25rem 2rem;
            color: #111;
            background: #fff;
            font-family: "Segoe UI", Tahoma, "Arabic UI Text", Arial, sans-serif;
            font-size: 12.5px;
            line-height: 1.35;
        }
        .no-print { margin-bottom: 1rem; }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
        }
        .toolbar button,
        .toolbar a {
            appearance: none;
            border: 1px solid #333;
            background: #fff;
            color: #111;
            border-radius: 0.4rem;
            padding: 0.4rem 0.85rem;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }
        .toolbar button {
            background: #111;
            color: #fff;
        }

        .sheet {
            width: 100%;
            max-width: 210mm;
            margin: 0 auto;
        }

        /* —— Header —— */
        .doc-top {
            position: relative;
            display: flex;
            align-items: flex-start;
            justify-content: flex-start;
            min-height: 3.6rem;
            margin-bottom: 0.85rem;
            padding-top: 0.15rem;
        }
        .brand-block {
            display: flex;
            align-items: flex-start;
            gap: 0.55rem;
            max-width: 42%;
            z-index: 1;
        }
        .brand-logo {
            width: 56px;
            height: 56px;
            flex: 0 0 56px;
            object-fit: contain;
            display: block;
        }
        .brand-text {
            display: grid;
            gap: 0.1rem;
            text-align: right;
        }
        .brand-name {
            margin: 0;
            font-size: 13px;
            font-weight: 800;
            color: #111;
        }
        .brand-meta {
            margin: 0;
            font-size: 11px;
            color: #222;
        }
        .doc-title-wrap {
            position: absolute;
            inset-inline: 0;
            top: 0.55rem;
            text-align: center;
            pointer-events: none;
        }
        .doc-title {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            text-decoration: underline;
            text-underline-offset: 4px;
            white-space: nowrap;
        }

        /* —— Meta boxes —— */
        .meta-row {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: 0.65rem;
            margin-bottom: 0.75rem;
        }
        .box {
            border: 1px solid #111;
        }
        .box table {
            width: 100%;
            border-collapse: collapse;
        }
        .box th,
        .box td {
            border: 1px solid #111;
            padding: 0.28rem 0.4rem;
            text-align: center;
            vertical-align: middle;
            font-size: 11.5px;
        }
        .box th {
            font-weight: 800;
            background: #fff;
        }
        .customer-box .customer-head {
            font-weight: 800;
            text-align: center;
            padding: 0.28rem 0.4rem;
            border-bottom: 1px solid #111;
        }
        .customer-box .customer-body {
            padding: 0.45rem 0.55rem;
            min-height: 3.4rem;
            text-align: right;
        }
        .customer-id {
            margin: 0 0 0.25rem;
            font-weight: 700;
            direction: ltr;
            unicode-bidi: embed;
            text-align: right;
        }
        .customer-name {
            margin: 0;
            font-weight: 700;
            font-size: 13px;
        }

        /* —— Lines table —— */
        .lines-wrap {
            border: 1px solid #111;
            margin-bottom: 0.55rem;
        }
        .lines {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .lines th,
        .lines td {
            border-top: 0;
            border-bottom: 0;
            border-left: 1px solid #111;
            border-right: 1px solid #111;
            padding: 0.28rem 0.3rem;
            text-align: center;
            vertical-align: middle;
            font-size: 11.5px;
            height: 1.55rem;
        }
        .lines thead th {
            font-weight: 800;
            background: #fff;
            border-top: 1px solid #111;
            border-bottom: 1px solid #111;
        }
        .lines .col-serial { width: 6%; }
        .lines .col-code { width: 12%; }
        .lines .col-name { width: 34%; }
        .lines .col-qty { width: 10%; }
        .lines .col-unit { width: 10%; }
        .lines .col-price { width: 13%; }
        .lines .col-amount { width: 15%; }
        .lines td.num {
            font-variant-numeric: tabular-nums;
            direction: ltr;
            unicode-bidi: embed;
        }
        /* —— Totals —— */
        .bottom-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .tax-box {
            border: 1px solid #111;
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: stretch;
            min-width: 14rem;
            max-width: 18rem;
        }
        .tax-box .tax-label,
        .tax-box .tax-value {
            padding: 0.35rem 0.55rem;
            border: 0;
            text-align: center;
            font-weight: 700;
        }
        .tax-box .tax-label {
            border-inline-end: 1px solid #111;
        }
        .tax-box .tax-value {
            direction: ltr;
            unicode-bidi: embed;
            min-width: 4.5rem;
            font-variant-numeric: tabular-nums;
        }
        .sum-box {
            border: 1px solid #111;
            justify-self: end;
            min-width: 14rem;
            width: 100%;
            max-width: 16rem;
        }
        .sum-box table {
            width: 100%;
            border-collapse: collapse;
        }
        .sum-box th,
        .sum-box td {
            border: 1px solid #111;
            padding: 0.32rem 0.5rem;
            text-align: center;
            font-weight: 700;
            font-size: 12px;
        }
        .sum-box td.num {
            direction: ltr;
            unicode-bidi: embed;
            font-variant-numeric: tabular-nums;
            width: 42%;
        }

        /* —— Delivery + signature —— */
        .terms {
            margin-bottom: 1.75rem;
        }
        .terms-label {
            margin: 0 0 0.35rem;
            font-weight: 700;
            font-size: 12px;
        }
        .terms-box {
            border: 1px solid #111;
            min-height: 3.25rem;
            padding: 0.45rem 0.55rem;
            white-space: pre-wrap;
        }
        .sign-row {
            display: flex;
            justify-content: flex-end;
            padding-top: 0.5rem;
        }
        .sign-block {
            text-align: center;
            min-width: 11rem;
        }
        .sign-label {
            margin: 0 0 0.85rem;
            font-weight: 700;
            font-size: 12px;
        }
        .sign-line {
            border-bottom: 1px solid #111;
            height: 1px;
            width: 100%;
        }

        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            .lines th,
            .box th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
@php
    use App\Support\Money\Money;

    $grandTotal = (float) $quotation->totalVatInclusive();
    $vatTotal = 0.0;
    foreach ($quotation->lines as $line) {
        $vatTotal += (float) Money::vatFromInclusive($line->lineTotalVatInclusive());
    }
    $vatTotal = (float) Money::round2($vatTotal);
    $discount = 0.0;
    $netAmount = (float) Money::round2($grandTotal - $discount);

    $quoteNo = $quotation->document_number;
    $quoteDate = ($quotation->decided_at ?? $quotation->created_at)
        ?->timezone(config('app.timezone'))
        ->format('d/m/Y');
    $validUntil = $quotation->expires_at
        ?->timezone(config('app.timezone'))
        ->format('Ymd');

    $minRows = 14;
    $filler = max(0, $minRows - $quotation->lines->count());
@endphp
    <div class="sheet">
        <div class="toolbar no-print">
            <button type="button" onclick="window.print()">طباعة / حفظ PDF</button>
            <a href="{{ route('quotations.show', $quotation) }}">العودة للعرض</a>
        </div>

        <header class="doc-top">
            <div class="brand-block">
                @include('partials.company-logo', [
                    'company' => $company,
                    'class' => 'brand-logo',
                    'width' => 56,
                    'height' => 56,
                ])
                <div class="brand-text">
                    <p class="brand-name">{{ $company?->name_ar ?: config('app.name') }}</p>
                    <p class="brand-meta">{{ filled($company?->address_ar) ? $company->address_ar : 'Company Address' }}</p>
                    <p class="brand-meta" dir="ltr">{{ filled($company?->contact_phone) ? $company->contact_phone : '966-0000000000' }}</p>
                </div>
            </div>

            <div class="doc-title-wrap">
                <h1 class="doc-title">عرض سعر</h1>
            </div>
        </header>

        <section class="meta-row" aria-label="بيانات العرض والعميل">
            <div class="box customer-box">
                <div class="customer-head">العميل</div>
                <div class="customer-body">
                    <p class="customer-id">{{ $quotation->customer_id }}</p>
                    <p class="customer-name">{{ $quotation->customer?->name_ar ?? '—' }}</p>
                </div>
            </div>

            <div class="box quote-meta-box">
                <table>
                    <colgroup>
                        <col style="width:34%">
                        <col style="width:33%">
                        <col style="width:33%">
                    </colgroup>
                    <tr>
                        <th scope="row">رقم عرض السعر</th>
                        <td colspan="2" dir="ltr">{{ $quoteNo }}</td>
                    </tr>
                    <tr>
                        <th>رقم المرجع</th>
                        <th>التاريخ</th>
                        <th>صالح حتى</th>
                    </tr>
                    <tr>
                        <td dir="ltr">{{ $quotation->sales_request_id ?: '' }}</td>
                        <td dir="ltr">{{ $quoteDate }}</td>
                        <td dir="ltr">{{ $validUntil }}</td>
                    </tr>
                </table>
            </div>
        </section>

        <div class="lines-wrap">
            <table class="lines">
                <thead>
                    <tr>
                        <th class="col-serial" scope="col">مسلسل</th>
                        <th class="col-code" scope="col">رمز الصنف</th>
                        <th class="col-name" scope="col">اسم الصنف</th>
                        <th class="col-qty" scope="col">الكميه</th>
                        <th class="col-unit" scope="col">الوحده</th>
                        <th class="col-price" scope="col">السعر</th>
                        <th class="col-amount" scope="col">المبلغ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($quotation->lines as $index => $line)
                        @php
                            $unitLabel = $line->product?->unit?->symbol_ar
                                ?: ($line->product?->unit?->name_ar ?? '—');
                            $itemCode = $line->product_id
                                ? str_pad((string) $line->product_id, 4, '0', STR_PAD_LEFT)
                                : '—';
                        @endphp
                        <tr>
                            <td class="num">{{ $index + 1 }}</td>
                            <td class="num">{{ $itemCode }}</td>
                            <td>{{ $line->product_name_ar }}</td>
                            <td class="num">{{ qty($line->quantity) }}</td>
                            <td>{{ $unitLabel }}</td>
                            <td class="num">{{ money($line->selling_price_vat_inclusive) }}</td>
                            <td class="num">{{ money($line->lineTotalVatInclusive()) }}</td>
                        </tr>
                    @endforeach
                    @for ($i = 0; $i < $filler; $i++)
                        <tr aria-hidden="true">
                            <td>&nbsp;</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>

        <section class="bottom-row" aria-label="الإجماليات">
            <div class="tax-box">
                <div class="tax-label">إجمالي ضريبة السلعة 1</div>
                <div class="tax-value">{{ money($vatTotal) }}</div>
            </div>

            <div class="sum-box">
                <table>
                    <tr>
                        <th scope="row">المجموع الكلى</th>
                        <td class="num">{{ money($grandTotal) }}</td>
                    </tr>
                    <tr>
                        <th scope="row">الخصم</th>
                        <td class="num">{{ money($discount) }}</td>
                    </tr>
                    <tr>
                        <th scope="row">صافي المبلغ</th>
                        <td class="num">{{ money($netAmount) }}</td>
                    </tr>
                </table>
            </div>
        </section>

        <section class="terms">
            <p class="terms-label">Delivery Terms</p>
            <div class="terms-box">{{ $quotation->notes_ar }}</div>
        </section>

        <div class="sign-row">
            <div class="sign-block">
                <p class="sign-label">استلمت من قبل</p>
                <div class="sign-line" aria-hidden="true"></div>
            </div>
        </div>
    </div>
</body>
</html>
