@extends('layouts.app')

@section('title', 'أوامر التسليم — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المستودع · التسليم</span>
@endsection

@section('content')
    <div class="deliveries-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="del-heading">
            <div class="ds-page-bar-copy">
                <h1 id="del-heading" class="admin-title">أوامر التسليم</h1>
                <p class="admin-lead">
                    تسليمات متعددة لكل أمر بيع. التأكيد يخصم المخزون بعد إثبات التسليم.
                    بلا أسعار · بلا سائقين/GPS في هذا الإصدار.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <form method="GET" action="{{ route('deliveries.index') }}" class="ds-data-search visits-filters" role="search">
                    <svg class="ds-data-search-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.7"/>
                        <path d="M16.2 16.2 20 20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>
                    <label class="visually-hidden" for="del-q">بحث</label>
                    <input id="del-q" type="search" name="q" value="{{ $q }}" placeholder="بحث بالمنشأة…" autocomplete="off">
                    <label class="visually-hidden" for="del-status">الحالة</label>
                    <select id="del-status" name="status" class="visits-status-filter" onchange="this.form.submit()" aria-label="الحالة">
                        <option value="">كل الحالات</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st->value }}" @selected($status === $st->value)>{{ $st->labelAr() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-glass btn-sm">تصفية</button>
                </form>
                @if ($q !== '' || $status !== '')
                    <a href="{{ route('deliveries.index') }}" class="btn btn-glass">مسح</a>
                @endif
                @if ($canCreate)
                    <a href="{{ route('deliveries.create') }}" class="btn btn-glass-primary">تسليم جديد</a>
                @endif
            </div>
        </header>

        @if ($deliveries->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا توجد تسليمات</strong>
                <p>أنشئ تسليمًا من أمر بيع معتمد غير مكتمل. لا سجلات وهمية.</p>
            </div>
        @else
            <div class="ds-table-surface">
                <div class="ds-data-body">
                    <table class="admin-table del-table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">المنشأة</th>
                                <th scope="col">أمر البيع</th>
                                <th scope="col">الحالة</th>
                                <th scope="col">بنود</th>
                                <th scope="col">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deliveries as $item)
                                @php
                                    $st = $item->status instanceof \BackedEnum
                                        ? $item->status->value
                                        : (string) $item->status;
                                    $badge = match ($st) {
                                        'confirmed' => 'badge-success',
                                        'pending_pod' => 'badge-warning',
                                        'cancelled' => 'badge-danger',
                                        default => 'badge-neutral',
                                    };
                                @endphp
                                <tr @class([
                                    'quotes-row-pending' => $st === 'pending_pod',
                                    'del-row-draft' => $st === 'draft',
                                ])>
                                    <td class="dir-ltr">
                                        <a href="{{ route('deliveries.show', $item) }}" class="quotes-id-link">{{ $item->document_number }}</a>
                                    </td>
                                    <td class="ds-cell-primary">{{ $item->customer?->name_ar ?? '—' }}</td>
                                    <td class="dir-ltr">
                                        <a href="{{ route('sales-orders.show', $item->sales_order_id) }}" class="sr-task-link">#{{ $item->sales_order_id }}</a>
                                    </td>
                                    <td><span class="badge {{ $badge }}">{{ $item->status->labelAr() }}</span></td>
                                    <td class="dir-ltr inventory-qty">{{ $item->lines->count() }}</td>
                                    <td class="ds-cell-actions">
                                        <details class="ds-row-menu">
                                            <summary aria-label="إجراءات تسليم {{ $item->id }}">⋮</summary>
                                            <div class="ds-row-menu-panel">
                                                <a href="{{ route('deliveries.show', $item) }}">عرض</a>
                                                @can('update', $item)
                                                    <a href="{{ route('deliveries.edit', $item) }}">تعديل</a>
                                                @endcan
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($deliveries->hasPages())
                    <div class="ds-data-footer">{{ $deliveries->links() }}</div>
                @endif
            </div>
        @endif
    </div>
@endsection
