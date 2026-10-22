@php
    use App\Support\Money\Money;

    $grandTotal = 0.0;
    foreach ($spo->lines as $line) {
        if ($line->unit_cost === null) {
            continue;
        }
        $grandTotal += (float) $line->quantity * (float) $line->unit_cost;
    }
    $grandTotal = (float) Money::round2($grandTotal);
    $vatTotal = 0.0;
    $discount = 0.0;
    $netAmount = (float) Money::round2($grandTotal - $discount);

    $poNo = sprintf('%04d', $spo->id);
    $poDate = ($spo->created_at ?? now())
        ->timezone(config('app.timezone'))
        ->format('d/m/Y');
    $expectedUntil = $spo->expected_at
        ?->timezone(config('app.timezone'))
        ->format('Ymd');

    $refNo = $spo->quotation_id
        ?: ($spo->sales_order_id ?: '');

    $companyName = $company?->name_ar ?: config('app.name');
    $companyAddress = filled($company?->address_ar) ? $company->address_ar : 'Company Address';
    $companyPhone = filled($company?->contact_phone) ? $company->contact_phone : '966-0000000000';

    $minRows = 14;
    $filler = max(0, $minRows - $spo->lines->count());
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: dejavusans, sans-serif;
            font-size: 11pt;
            color: #111;
            direction: rtl;
        }
        table { border-collapse: collapse; }
        .w-100 { width: 100%; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .border { border: 1px solid #111; }
        .border td, .border th { border: 1px solid #111; }
        .pad { padding: 4px 6px; }
        .small { font-size: 9.5pt; }
        .title {
            font-size: 16pt;
            font-weight: bold;
            text-decoration: underline;
            text-align: center;
        }
        .logo-cell {
            width: 56px;
            text-align: center;
            vertical-align: middle;
        }
        .logo-img {
            width: 52px;
            height: 52px;
        }
        .lines {
            border: 1px solid #111;
        }
        .lines th, .lines td {
            border-top: 0;
            border-bottom: 0;
            border-left: 1px solid #111;
            border-right: 1px solid #111;
            padding: 4px 4px;
            text-align: center;
            vertical-align: middle;
            font-size: 9.5pt;
            height: 24px;
        }
        .lines th {
            font-weight: bold;
            border-top: 1px solid #111;
            border-bottom: 1px solid #111;
        }
        .lines tbody tr:last-child td {
            border-bottom: 0;
        }
        .meta th, .meta td {
            border: 1px solid #111;
            padding: 4px 6px;
            text-align: center;
            font-size: 9.5pt;
        }
        .meta th { font-weight: bold; }
        .sum th, .sum td {
            border: 1px solid #111;
            padding: 5px 8px;
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
        }
        .terms-box {
            border: 1px solid #111;
            height: 48px;
            padding: 6px;
        }
        .sign-line {
            border-bottom: 1px solid #111;
            width: 160px;
            height: 18px;
        }
    </style>
</head>
<body>

{{-- Header: brand right + centered title --}}
<table class="w-100" style="margin-bottom: 8px;">
    <tr>
        <td style="width: 38%; vertical-align: top;">
            <table>
                <tr>
                    <td class="logo-cell">
                        @include('partials.company-logo', [
                            'company' => $company,
                            'class' => 'logo-img',
                            'width' => 52,
                            'height' => 52,
                            'forPdf' => true,
                        ])
                    </td>
                    <td class="right" style="padding-right: 6px;">
                        <div class="bold" style="font-size: 11pt;">{{ $companyName }}</div>
                        <div class="small">{{ $companyAddress }}</div>
                        <div class="small" dir="ltr">{{ $companyPhone }}</div>
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 24%; vertical-align: middle;" class="title">أمر شراء</td>
        <td style="width: 38%;">&nbsp;</td>
    </tr>
</table>

{{-- Supplier + PO meta --}}
<table class="w-100" style="margin-bottom: 8px;">
    <tr>
        <td style="width: 52%; vertical-align: top; padding-left: 6px;">
            <table class="w-100 border">
                <tr>
                    <th class="pad center">المورد</th>
                </tr>
                <tr>
                    <td class="pad right" style="height: 48px;">
                        <div class="bold">{{ $spo->supplier_name_ar ?: '—' }}</div>
                        @if ($spo->salesOrder?->customer)
                            <div class="small">عميل الطلب: {{ $spo->salesOrder->customer->name_ar }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
        <td style="width: 48%; vertical-align: top;">
            <table class="w-100 meta">
                <tr>
                    <th style="width: 34%;">رقم أمر الشراء</th>
                    <td colspan="2" dir="ltr">{{ $poNo }}</td>
                </tr>
                <tr>
                    <th>رقم المرجع</th>
                    <th>التاريخ</th>
                    <th>متوقع</th>
                </tr>
                <tr>
                    <td dir="ltr">{{ $refNo }}</td>
                    <td dir="ltr">{{ $poDate }}</td>
                    <td dir="ltr">{{ $expectedUntil ?: '' }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Lines --}}
<table class="w-100 lines" style="margin-bottom: 8px;">
    <thead>
        <tr>
            <th style="width: 7%;">مسلسل</th>
            <th style="width: 12%;">رمز الصنف</th>
            <th style="width: 33%;">اسم الصنف</th>
            <th style="width: 10%;">الكميه</th>
            <th style="width: 10%;">الوحده</th>
            <th style="width: 13%;">السعر</th>
            <th style="width: 15%;">المبلغ</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($spo->lines as $index => $line)
            @php
                $unitLabel = $line->product?->unit?->symbol_ar
                    ?: ($line->product?->unit?->name_ar ?? '—');
                $itemCode = $line->product_id
                    ? str_pad((string) $line->product_id, 4, '0', STR_PAD_LEFT)
                    : '—';
                $hasCost = $line->unit_cost !== null;
                $lineAmount = $hasCost
                    ? Money::round2((float) $line->quantity * (float) $line->unit_cost)
                    : null;
            @endphp
            <tr>
                <td dir="ltr">{{ $index + 1 }}</td>
                <td dir="ltr">{{ $itemCode }}</td>
                <td>{{ $line->product?->name_ar ?? '—' }}</td>
                <td dir="ltr">{{ qty($line->quantity) }}</td>
                <td>{{ $unitLabel }}</td>
                <td dir="ltr">{{ $hasCost ? money($line->unit_cost) : '—' }}</td>
                <td dir="ltr">{{ $lineAmount !== null ? money($lineAmount) : '—' }}</td>
            </tr>
        @endforeach
        @for ($i = 0; $i < $filler; $i++)
            <tr>
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

{{-- Tax + totals --}}
<table class="w-100" style="margin-bottom: 10px;">
    <tr>
        <td style="width: 55%; vertical-align: top;">
            <table class="border" style="width: 70%;">
                <tr>
                    <th class="pad center" style="width: 65%;">إجمالي ضريبة السلعة 1</th>
                    <td class="pad center" dir="ltr">{{ money($vatTotal) }}</td>
                </tr>
            </table>
        </td>
        <td style="width: 45%; vertical-align: top;">
            <table class="w-100 sum">
                <tr>
                    <th style="width: 55%;">المجموع الكلى</th>
                    <td dir="ltr">{{ money($grandTotal) }}</td>
                </tr>
                <tr>
                    <th>الخصم</th>
                    <td dir="ltr">{{ money($discount) }}</td>
                </tr>
                <tr>
                    <th>صافي المبلغ</th>
                    <td dir="ltr">{{ money($netAmount) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="bold small" style="margin-bottom: 4px;">Delivery Terms</div>
<div class="terms-box small">{{ $spo->notes_ar }}</div>

<table class="w-100" style="margin-top: 28px;">
    <tr>
        <td style="width: 60%;">&nbsp;</td>
        <td style="width: 40%;" class="center">
            <div class="bold" style="margin-bottom: 14px;">اعتمد من قبل</div>
            <div class="sign-line">&nbsp;</div>
        </td>
    </tr>
</table>

</body>
</html>
