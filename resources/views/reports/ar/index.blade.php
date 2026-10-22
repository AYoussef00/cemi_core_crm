@extends('layouts.app')

@section('title', 'تقرير الذمم — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">التقارير · الذمم</span>
@endsection

@section('content')
    <div class="reports-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="ar-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">تقارير · {{ $timezone }}</p>
                <h1 id="ar-heading" class="admin-title">الذمم المدينة (AR)</h1>
                <p class="admin-lead">
                    المتبقي وفق CR7 فقط (تحصيل مؤكد ± إشعارات معتمدة). مسودات التحصيل لا تخفض المتبقي.
                    بلا تصدير ولا جدولة في v1.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <form method="GET" action="{{ route('reports.ar.index') }}" class="ds-data-search visits-filters" role="search">
                    <label class="visually-hidden" for="ar-customer">المنشأة</label>
                    <select id="ar-customer" name="customer_id" class="visits-status-filter" aria-label="المنشأة">
                        <option value="">كل المنشآت</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) $customerId === (int) $customer->id)>
                                {{ $customer->name_ar }}
                            </option>
                        @endforeach
                    </select>
                    <label class="visually-hidden" for="ar-filter">تصفية</label>
                    <select id="ar-filter" name="filter" class="visits-status-filter" aria-label="تصفية المتبقي">
                        <option value="" @selected($filter === '')>كل المتبقي</option>
                        <option value="overdue" @selected($filter === 'overdue')>متأخر فقط</option>
                    </select>
                    <button type="submit" class="btn btn-glass btn-sm">تصفية</button>
                </form>
                @if ($customerId || $filter !== '')
                    <a href="{{ route('reports.ar.index') }}" class="btn btn-glass">مسح</a>
                @endif
                <a href="{{ route('reports.statements.index') }}" class="btn btn-glass">كشوف العملاء</a>
            </div>
        </header>

        <section class="inventory-metrics" aria-label="ملخص الذمم">
            <article class="inventory-metric inventory-metric-available">
                <p class="inventory-metric-label">فواتير بمتبقي</p>
                <p class="inventory-metric-value dir-ltr">{{ money($unpaidCount) }}</p>
            </article>
            <article class="inventory-metric">
                <p class="inventory-metric-label">متأخرة</p>
                <p class="inventory-metric-value dir-ltr {{ $overdueCount > 0 ? 'fin-unpaid-emph' : '' }}">
                    {{ number_format($overdueCount) }}
                </p>
            </article>
            <article class="inventory-metric">
                <p class="inventory-metric-label">إجمالي المتبقي</p>
                <p class="inventory-metric-value dir-ltr fin-unpaid-emph">
                    {{ money($unpaidTotal) }}
                    <span class="inventory-metric-unit">ر.س</span>
                </p>
            </article>
        </section>

        @if ($rows === [])
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا ذمم ظاهرة</strong>
                <p>لا فواتير بمتبقي ضمن صلاحياتك. الصفر حقيقي — ليست بيانات معاينة.</p>
            </div>
        @else
            <div class="ds-table-surface">
                <div class="ds-data-body">
                    <table class="admin-table fin-table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">المنشأة</th>
                                <th scope="col">الاستحقاق</th>
                                <th scope="col">المتبقي</th>
                                <th scope="col">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php $invoice = $row['invoice']; @endphp
                                <tr @class(['fin-row-overdue' => $row['overdue'], 'fin-row-unpaid' => ! $row['overdue']])>
                                    <td class="dir-ltr">
                                        <a href="{{ route('invoices.show', $invoice) }}" class="sr-task-link dir-ltr">{{ $invoice->document_number }}</a>
                                    </td>
                                    <td class="ds-cell-primary">{{ $invoice->customer?->name_ar ?? '—' }}</td>
                                    <td class="dir-ltr inventory-qty">
                                        {{ $invoice->due_at?->timezone($timezone)->format('Y-m-d') ?? '—' }}
                                    </td>
                                    <td class="dir-ltr inventory-qty fin-unpaid-emph">
                                        {{ money($row['unpaid']) }} ر.س
                                    </td>
                                    <td>
                                        @if ($row['overdue'])
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
