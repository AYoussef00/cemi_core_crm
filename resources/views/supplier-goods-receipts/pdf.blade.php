<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: dejavusans, sans-serif; font-size: 11pt; color: #111; }
        h1 { font-size: 16pt; margin: 0 0 8px; }
        .meta { margin: 0 0 14px; font-size: 10pt; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: center; }
        th { background: #f3f4f6; font-size: 9pt; }
        .foot { margin-top: 18px; font-size: 9pt; color: #555; }
    </style>
</head>
<body>
    <h1>أمر استلام شحنة #{{ $receipt->id }}</h1>
    <p class="meta">
        @if ($company?->name_ar)
            الشركة: {{ $company->name_ar }}<br>
        @endif
        أمر شراء المورد: #{{ $spo?->id }}
        @if ($spo?->supplier_name_ar)
            — {{ $spo->supplier_name_ar }}
        @endif
        <br>
        تاريخ الاستلام: {{ $receipt->received_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
        @if ($receipt->shipment_reference_ar)
            <br>مرجع الشحنة: {{ $receipt->shipment_reference_ar }}
        @endif
        @if ($receipt->receivedBy?->name)
            <br>المستلم: {{ $receipt->receivedBy->name }}
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th>المنتج</th>
                <th>الوحدة</th>
                <th>الكمية المستلمة</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($receipt->lines as $line)
                <tr>
                    <td>{{ $line->product?->name_ar ?? '—' }}</td>
                    <td>{{ $line->product?->unit?->name_ar ?? '—' }}</td>
                    <td class="dir-ltr">{{ qty($line->quantity) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($receipt->notes_ar)
        <p class="foot">ملاحظات: {{ $receipt->notes_ar }}</p>
    @endif
    <p class="foot">مستند استلام شحنة مرتبط بأمر شراء المورد — صادر من النظام.</p>
</body>
</html>
