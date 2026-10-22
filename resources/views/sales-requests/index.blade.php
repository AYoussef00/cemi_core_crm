@extends('layouts.app')

@section('title', 'طلبات البيع — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · طلبات البيع</span>
@endsection

@section('content')
    <div class="sales-requests-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="sr-heading">
            <div class="ds-page-bar-copy">
                <h1 id="sr-heading" class="admin-title">طلبات البيع</h1>
                <p class="admin-lead">
                    مسودة → إرسال (متوفر = جاهز للتسعير، أو بانتظار المشتريات عند بنود غير متوفرة).
                    التوفر اختيار صريح لكل بند — لا توجيه تلقائي من المخزون.
                    التسعير والعروض في خطوة لاحقة.
                </p>
            </div>
            @if ($canCreate)
                <div class="ds-page-bar-tools">
                    <a href="{{ route('sales-requests.create') }}" class="btn btn-glass-primary">+ طلب بيع</a>
                </div>
            @endif
        </header>

        @php $srFilteredEmpty = $requests->isEmpty() && ($q !== '' || $status !== ''); @endphp

        @if ($requests->isEmpty() && ! $srFilteredEmpty)
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا توجد طلبات بيع</strong>
                <p>لا تُعرض سجلات وهمية. أنشئ طلبًا ضمن نطاق صلاحيتك عند الحاجة.</p>
            </div>
        @else
            <section class="ds-list-panel" aria-label="قائمة طلبات البيع">
                <div class="ds-list-toolbar">
                    <div class="ds-list-meta">
                        <span class="ds-list-count dir-ltr">{{ number_format($requests->total()) }}</span>
                        <span class="ds-list-count-label">طلب ضمن نطاقك</span>
                    </div>
                    <form method="GET" action="{{ route('sales-requests.index') }}" class="ds-list-filters" role="search">
                        <div class="ds-data-search">
                            <svg class="ds-data-search-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M16.2 16.2 20 20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                            <label class="visually-hidden" for="sr-q">بحث</label>
                            <input id="sr-q" type="search" name="q" value="{{ $q }}" placeholder="بحث بالمنشأة أو المنتج…" autocomplete="off">
                        </div>
                        <label class="visually-hidden" for="sr-status">الحالة</label>
                        <select id="sr-status" name="status" class="visits-status-filter" onchange="this.form.submit()" aria-label="تصفية الحالة">
                            <option value="">كل الحالات</option>
                            @foreach ($statuses as $st)
                                <option value="{{ $st->value }}" @selected($status === $st->value)>{{ $st->labelAr() }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-glass btn-sm">تصفية</button>
                        @if ($q !== '' || $status !== '')
                            <a href="{{ route('sales-requests.index') }}" class="btn btn-glass btn-sm">مسح</a>
                        @endif
                    </form>
                </div>

                @if ($requests->isEmpty())
                    <div class="ds-data-empty ds-list-empty" role="status">
                        <strong>لا نتائج مطابقة</strong>
                        <p>لا طلبات ضمن التصفية الحالية. امسح البحث أو غيّر الحالة.</p>
                    </div>
                @else
                    <div class="ds-list-body">
                        <table class="admin-table admin-table--sheet">
                            <thead>
                                <tr>
                                    <th scope="col">الطلب</th>
                                    <th scope="col">المنشأة</th>
                                    <th scope="col">الحالة</th>
                                    <th scope="col">البنود</th>
                                    <th scope="col" class="quotes-col-actions"><span class="visually-hidden">إجراء</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($requests as $item)
                                    @php
                                        $statusValue = $item->status instanceof \BackedEnum
                                            ? $item->status->value
                                            : (string) $item->status;
                                        $statusBadge = match ($statusValue) {
                                            'ready_for_pricing' => 'badge-success',
                                            'awaiting_procurement' => 'badge-warning',
                                            'cancelled' => 'badge-danger',
                                            default => 'badge-neutral',
                                        };
                                    @endphp
                                    <tr @class(['sr-row-awaiting' => $statusValue === 'awaiting_procurement'])>
                                        <td>
                                            <div class="quotes-id-block">
                                                <a href="{{ route('sales-requests.show', $item) }}" class="quotes-id-link dir-ltr">#{{ $item->id }}</a>
                                                @if ($item->createdBy?->name)
                                                    <span class="quotes-meta-line">أنشأه {{ $item->createdBy->name }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="quotes-customer-block">
                                                <span class="ds-cell-primary">{{ $item->customer?->name_ar ?? '—' }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $statusBadge }}">{{ $item->status->labelAr() }}</span>
                                        </td>
                                        <td>
                                            <span class="quotes-sr-chip is-static dir-ltr">{{ $item->lines->count() }}</span>
                                        </td>
                                        <td class="ds-cell-actions quotes-col-actions">
                                            <div class="quotes-row-actions">
                                                <a href="{{ route('sales-requests.show', $item) }}" class="btn btn-glass btn-sm">عرض</a>
                                                <details class="ds-row-menu">
                                                    <summary aria-label="إجراءات طلب {{ $item->id }}">⋮</summary>
                                                    <div class="ds-row-menu-panel">
                                                        <a href="{{ route('sales-requests.show', $item) }}">عرض التفاصيل</a>
                                                        @can('update', $item)
                                                            <a href="{{ route('sales-requests.edit', $item) }}">تعديل</a>
                                                        @endcan
                                                    </div>
                                                </details>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($requests->hasPages())
                    <div class="ds-list-footer">{{ $requests->links() }}</div>
                @endif
            </section>
        @endif
    </div>
@endsection
