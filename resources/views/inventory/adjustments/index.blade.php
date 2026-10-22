@extends('layouts.app')

@section('title', 'تسويات المخزون — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المخزون · التسويات</span>
@endsection

@section('content')
    <div class="inventory-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="adj-heading">
            <div class="ds-page-bar-copy">
                <h1 id="adj-heading" class="admin-title">تسويات المخزون</h1>
                <p class="admin-lead">
                    يسجّل المستودع التسوية؛ يعتمدها أو يرفضها مدير المبيعات.
                    الاعتماد يحدّث الرصيد فقط عند النجاح، ولا يُسمح برصيد متاح سالب.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <form method="GET" action="{{ route('inventory.adjustments.index') }}" class="ds-data-search visits-filters" role="search">
                    <svg class="ds-data-search-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.7"/>
                        <path d="M16.2 16.2 20 20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>
                    <label class="visually-hidden" for="adj-q">بحث</label>
                    <input id="adj-q" type="search" name="q" value="{{ $q }}" placeholder="بحث بالمنتج أو السبب…">
                    <label class="visually-hidden" for="adj-status">الحالة</label>
                    <select id="adj-status" name="status" class="visits-status-filter" aria-label="تصفية حسب الحالة"
                            onchange="this.form.submit()">
                        <option value="">كل الحالات</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st->value }}" @selected($status === $st->value)>{{ $st->labelAr() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-glass btn-sm">تصفية</button>
                </form>
                @if ($q !== '' || $status !== '')
                    <a href="{{ route('inventory.adjustments.index') }}" class="btn btn-glass">مسح</a>
                @endif
                @if ($canCreate)
                    <a href="{{ route('inventory.adjustments.create') }}" class="btn btn-glass-primary">+ تسوية جديدة</a>
                @endif
                <a href="{{ route('inventory.stock.index') }}" class="btn btn-glass">الأرصدة</a>
            </div>
        </header>

        @if ($adjustments->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا توجد تسويات</strong>
                <p>لا توجد طلبات تسوية مطابقة. لن تُعرض بيانات تجريبية.</p>
            </div>
        @else
            <div class="ds-table-surface">
                <div class="ds-data-body">
                    <table class="admin-table inventory-adj-table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">المنتج</th>
                                <th scope="col">الكمية</th>
                                <th scope="col">الحالة</th>
                                <th scope="col">سجّلها</th>
                                <th scope="col">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($adjustments as $adj)
                                @php
                                    $delta = (float) $adj->quantity_delta;
                                    $statusValue = $adj->status instanceof \BackedEnum
                                        ? $adj->status->value
                                        : (string) $adj->status;
                                    $statusBadge = match ($statusValue) {
                                        'approved' => 'badge-success',
                                        'rejected' => 'badge-danger',
                                        default => 'badge-warning',
                                    };
                                    $deltaClass = $delta > 0
                                        ? 'inventory-delta is-increase'
                                        : ($delta < 0 ? 'inventory-delta is-decrease' : 'inventory-delta');
                                @endphp
                                <tr @class(['inventory-row-pending' => $statusValue === 'pending'])>
                                    <td class="dir-ltr ds-cell-muted">{{ $adj->id }}</td>
                                    <td class="ds-cell-primary">{{ $adj->product?->name_ar ?? '—' }}</td>
                                    <td class="dir-ltr">
                                        <span class="{{ $deltaClass }}">
                                            {{ $delta > 0 ? '+' : '' }}{{ qty($delta) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $statusBadge }}">{{ $adj->status->labelAr() }}</span>
                                    </td>
                                    <td>{{ $adj->recordedBy?->name ?? '—' }}</td>
                                    <td class="ds-cell-actions">
                                        <details class="ds-row-menu">
                                            <summary aria-label="إجراءات تسوية {{ $adj->id }}">⋮</summary>
                                            <div class="ds-row-menu-panel">
                                                <a href="{{ route('inventory.adjustments.show', $adj) }}">عرض</a>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($adjustments->hasPages())
                    <div class="ds-data-footer">{{ $adjustments->links() }}</div>
                @endif
            </div>
        @endif
    </div>
@endsection
