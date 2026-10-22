@extends('layouts.app')

@section('title', 'مهام تجهيز الطلبات — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المستودع · تجهيز الطلبات</span>
@endsection

@section('content')
    <div class="preparation-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="prep-heading">
            <div class="ds-page-bar-copy">
                <h1 id="prep-heading" class="admin-title">مهام تجهيز الطلبات</h1>
                <p class="admin-lead">
                    أوامر بيع معتمدة غير مكتملة التسليم. علّم الجاهزية ثم أنشئ تسليمًا واحدًا أو أكثر.
                    قائمة تحقق بسيطة — ليست نظام مستودع كامل. بلا أسعار · بلا GPS/سائقين في هذا الإصدار.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <form method="GET" action="{{ route('warehouse.preparation.index') }}" class="ds-data-search visits-filters" role="search">
                    <svg class="ds-data-search-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.7"/>
                        <path d="M16.2 16.2 20 20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>
                    <label class="visually-hidden" for="prep-q">بحث</label>
                    <input id="prep-q" type="search" name="q" value="{{ $q }}" placeholder="بحث بالمنشأة…" autocomplete="off">
                    <label class="visually-hidden" for="prep-status">حالة التجهيز</label>
                    <select id="prep-status" name="preparation" class="visits-status-filter" onchange="this.form.submit()" aria-label="حالة التجهيز">
                        <option value="">كل حالات التجهيز</option>
                        @foreach ($preparationStatuses as $st)
                            <option value="{{ $st->value }}" @selected($preparation === $st->value)>{{ $st->labelAr() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-glass btn-sm">تصفية</button>
                </form>
                @if ($q !== '' || $preparation !== '')
                    <a href="{{ route('warehouse.preparation.index') }}" class="btn btn-glass">مسح</a>
                @endif
                @can('delivery.manage')
                    <a href="{{ route('deliveries.create') }}" class="btn btn-glass-primary">تسليم جديد</a>
                @endcan
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($orders->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا مهام تجهيز حالية</strong>
                <p>تظهر هنا أوامر البيع المعتمدة التي لم تُسلَّم بالكامل. لا سجلات وهمية.</p>
            </div>
        @else
            <div class="ds-table-surface">
                <div class="ds-data-body">
                    <table class="admin-table prep-table">
                        <thead>
                            <tr>
                                <th scope="col">أمر البيع</th>
                                <th scope="col">المنشأة</th>
                                <th scope="col">التجهيز</th>
                                <th scope="col">التسليم</th>
                                <th scope="col">بنود</th>
                                <th scope="col">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                @php
                                    $prepValue = $order->preparation_status instanceof \BackedEnum
                                        ? $order->preparation_status->value
                                        : (string) $order->preparation_status;
                                    $fulfillValue = $order->fulfillment_status instanceof \BackedEnum
                                        ? $order->fulfillment_status->value
                                        : (string) $order->fulfillment_status;
                                    $prepBadge = $prepValue === 'ready' ? 'badge-success' : 'badge-warning';
                                    $fulfillBadge = match ($fulfillValue) {
                                        'fully_delivered' => 'badge-success',
                                        'partially_delivered' => 'badge-warning',
                                        default => 'badge-neutral',
                                    };
                                @endphp
                                <tr @class(['prep-row-pending' => $prepValue !== 'ready'])>
                                    <td class="dir-ltr">
                                        <a href="{{ route('sales-orders.show', $order) }}" class="sr-task-link">#{{ $order->id }}</a>
                                    </td>
                                    <td class="ds-cell-primary">{{ $order->customer?->name_ar ?? '—' }}</td>
                                    <td>
                                        <span class="badge {{ $prepBadge }}">{{ $order->preparation_status->labelAr() }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $fulfillBadge }}">{{ $order->fulfillment_status->labelAr() }}</span>
                                    </td>
                                    <td class="dir-ltr inventory-qty">{{ $order->lines->count() }}</td>
                                    <td class="ds-cell-actions">
                                        <div class="prep-row-actions">
                                            @if ($canManage && $prepValue !== 'ready')
                                                <form method="POST" action="{{ route('warehouse.preparation.ready', $order) }}"
                                                      onsubmit="return confirm('تعليمشر أمر البيع كجاهز للتسليم؟');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-glass btn-sm">جاهز</button>
                                                </form>
                                            @endif
                                            @if ($canManage)
                                                <a href="{{ route('deliveries.create', ['sales_order_id' => $order->id]) }}"
                                                   class="btn btn-glass-primary btn-sm">تسليم</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($orders->hasPages())
                    <div class="ds-data-footer">{{ $orders->links() }}</div>
                @endif
            </div>
        @endif
    </div>
@endsection
