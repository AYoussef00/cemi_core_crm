@extends('layouts.app')

@section('title', 'كشف '.$customer->name_ar.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">التقارير · كشف عميل</span>
@endsection

@section('content')
    <div class="reports-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="stmt-show-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">كشف حساب · {{ $timezone }}</p>
                <h1 id="stmt-show-heading" class="admin-title">{{ $customer->name_ar }}</h1>
                <p class="admin-lead">
                    فواتير صادرة والمتبقي وفق CR7. التحصيل المسودة لا يقلل المتبقي. بلا تصدير في v1.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('reports.statements.index') }}" class="btn btn-glass">كل الكشوف</a>
                <a href="{{ route('reports.ar.index', ['customer_id' => $customer->id]) }}" class="btn btn-glass">ذمم هذه المنشأة</a>
            </div>
        </header>

        <section class="inventory-metrics" aria-label="ملخص الكشف">
            <article class="inventory-metric">
                <p class="inventory-metric-label">عدد الفواتير</p>
                <p class="inventory-metric-value dir-ltr">{{ money($invoices->count()) }}</p>
            </article>
            <article class="inventory-metric inventory-metric-available">
                <p class="inventory-metric-label">إجمالي المتبقي</p>
                <p class="inventory-metric-value dir-ltr fin-unpaid-emph">
                    {{ money($unpaidTotal) }}
                    <span class="inventory-metric-unit">ر.س</span>
                </p>
            </article>
        </section>

        @if ($invoices->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا فواتير صادرة</strong>
                <p>لا سجلات وهمية — لم تُصدر فواتير لهذه المنشأة بعد.</p>
            </div>
        @else
            <div class="ds-table-surface">
                <div class="ds-data-body">
                    <table class="admin-table fin-table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">الإصدار</th>
                                <th scope="col">الاستحقاق</th>
                                <th scope="col">الإجمالي</th>
                                <th scope="col">المتبقي</th>
                                <th scope="col">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $row)
                                @php
                                    $invoice = $row['invoice'];
                                    $paid = abs((float) $row['unpaid']) < 0.005;
                                @endphp
                                <tr @class(['fin-row-overdue' => $row['overdue'], 'fin-row-unpaid' => ! $paid && ! $row['overdue']])>
                                    <td class="dir-ltr">
                                        <a href="{{ route('invoices.show', $invoice) }}" class="sr-task-link dir-ltr">{{ $invoice->document_number }}</a>
                                    </td>
                                    <td class="dir-ltr inventory-qty">{{ $invoice->issued_at?->timezone($timezone)->format('Y-m-d') ?? '—' }}</td>
                                    <td class="dir-ltr inventory-qty">{{ $invoice->due_at?->timezone($timezone)->format('Y-m-d') ?? '—' }}</td>
                                    <td class="dir-ltr inventory-qty">{{ money($invoice->total_vat_inclusive) }} ر.س</td>
                                    <td class="dir-ltr inventory-qty {{ $paid ? 'ds-cell-muted' : 'fin-unpaid-emph' }}">
                                        {{ money($row['unpaid']) }} ر.س
                                    </td>
                                    <td>
                                        @if ($paid)
                                            <span class="badge badge-success">مسددة</span>
                                        @elseif ($row['overdue'])
                                            <span class="badge badge-danger">متأخرة</span>
                                        @else
                                            <span class="badge badge-warning">بمتبقي</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
