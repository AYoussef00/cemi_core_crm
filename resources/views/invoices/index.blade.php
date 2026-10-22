@extends('layouts.app')

@section('title', 'الفواتير — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المالية · الفواتير</span>
@endsection

@section('content')
    <div class="invoices-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="inv-heading">
            <div class="ds-page-bar-copy">
                <h1 id="inv-heading" class="admin-title">الفواتير</h1>
                <p class="admin-lead">
                    فاتورة واحدة لكل تسليم مؤكد (PF3). المتبقي وفق CR7 (تحصيلات مؤكدة + إشعارات معتمدة فقط).
                    بلا خصومات · ضريبة 15% شاملة · منزلتان.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <form method="GET" action="{{ route('invoices.index') }}" class="ds-data-search visits-filters" role="search">
                    <svg class="ds-data-search-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.7"/>
                        <path d="M16.2 16.2 20 20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>
                    <label class="visually-hidden" for="inv-q">بحث</label>
                    <input id="inv-q" type="search" name="q" value="{{ $q }}" placeholder="بحث بالمنشأة…" autocomplete="off">
                    <button type="submit" class="btn btn-glass btn-sm">تصفية</button>
                </form>
                @if ($q !== '')
                    <a href="{{ route('invoices.index') }}" class="btn btn-glass">مسح</a>
                @endif
                @if ($canIssue)
                    <a href="{{ route('invoices.create') }}" class="btn btn-glass-primary">إصدار فاتورة</a>
                @endif
            </div>
        </header>

        @if ($invoices->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا فواتير</strong>
                <p>أصدر فاتورة من تسليم مؤكد غير مُفوتر. لا سجلات وهمية.</p>
            </div>
        @else
            <div class="ds-table-surface">
                <div class="ds-data-body">
                    <table class="admin-table fin-table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">المنشأة</th>
                                <th scope="col">التسليم</th>
                                <th scope="col">الصفقة</th>
                                <th scope="col">الاستحقاق</th>
                                <th scope="col">الإجمالي شامل الضريبة</th>
                                <th scope="col">المتبقي</th>
                                <th scope="col">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $item)
                                @php
                                    $unpaid = (float) $item->unpaidBalance();
                                    $paid = $item->isFullyPaid();
                                    $overdue = $item->isOverdue();
                                    $statusBadge = $paid ? 'badge-success' : ($overdue ? 'badge-danger' : 'badge-warning');
                                    $statusLabel = $paid ? 'مسددة' : ($overdue ? 'متأخرة' : 'مفتوحة');
                                @endphp
                                <tr @class([
                                    'fin-row-overdue' => $overdue,
                                    'fin-row-unpaid' => ! $paid && ! $overdue,
                                ])>
                                    <td class="dir-ltr">
                                        <a href="{{ route('invoices.show', $item) }}" class="quotes-id-link">{{ $item->document_number }}</a>
                                    </td>
                                    <td class="ds-cell-primary">{{ $item->customer?->name_ar ?? '—' }}</td>
                                    <td class="dir-ltr">
                                        <a href="{{ route('deliveries.show', $item->delivery_id) }}" class="sr-task-link dir-ltr">{{ doc_no('DN', $item->delivery_id) }}</a>
                                    </td>
                                    <td class="dir-ltr">
                                        <a href="{{ route('deals.show', $item->deal_id) }}" class="sr-task-link">#{{ $item->deal_id }}</a>
                                    </td>
                                    <td class="dir-ltr inventory-qty">{{ $item->due_at?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="dir-ltr inventory-qty">{{ money($item->total_vat_inclusive) }} ر.س</td>
                                    <td>
                                        <span class="dir-ltr inventory-qty {{ $paid ? 'ds-cell-muted' : 'fin-unpaid-emph' }}">
                                            {{ money($unpaid) }} ر.س
                                        </span>
                                        <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td class="ds-cell-actions">
                                        <a href="{{ route('invoices.show', $item) }}" class="btn btn-glass btn-sm">عرض</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($invoices->hasPages())
                    <div class="ds-data-footer">{{ $invoices->links() }}</div>
                @endif
            </div>
        @endif
    </div>
@endsection
