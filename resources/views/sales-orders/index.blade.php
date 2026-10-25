@extends('layouts.app')

@section('title', 'أوامر البيع — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · أوامر البيع</span>
@endsection

@section('content')
    <div class="sales-orders-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="so-heading">
            <div class="ds-page-bar-copy">
                <h1 id="so-heading" class="admin-title">أوامر البيع</h1>
            </div>
            @if ($canCreate)
                <div class="ds-page-bar-tools">
                    <a href="{{ route('sales-orders.create') }}" class="btn btn-glass-primary">أمر بيع جديد</a>
                </div>
            @endif
        </header>

        @php $soFilteredEmpty = $orders->isEmpty() && ($q !== '' || $status !== ''); @endphp

        @if ($orders->isEmpty() && ! $soFilteredEmpty)
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا توجد أوامر بيع</strong>
                <p>أنشئ أمر بيع من أمر شراء مفتوح ضمن نطاقك. لا أمر بيع بلا أمر شراء.</p>
            </div>
        @else
            <section class="ds-list-panel" aria-label="قائمة أوامر البيع">
                <div class="ds-list-toolbar">
                    <div class="ds-list-meta">
                        <span class="ds-list-count dir-ltr">{{ number_format($orders->total()) }}</span>
                        <span class="ds-list-count-label">أمر بيع ضمن نطاقك</span>
                    </div>
                    <form method="GET" action="{{ route('sales-orders.index') }}" class="ds-list-filters" role="search">
                        <div class="ds-data-search">
                            <svg class="ds-data-search-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M16.2 16.2 20 20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                            <label class="visually-hidden" for="so-q">بحث</label>
                            <input id="so-q" type="search" name="q" value="{{ $q }}" placeholder="بحث بالمنشأة…" autocomplete="off">
                        </div>
                        <label class="visually-hidden" for="so-status">الحالة</label>
                        <select id="so-status" name="status" class="visits-status-filter" onchange="this.form.submit()" aria-label="تصفية الحالة">
                            <option value="">كل الحالات</option>
                            @foreach ($statuses as $st)
                                <option value="{{ $st->value }}" @selected($status === $st->value)>{{ $st->labelAr() }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-glass btn-sm">تصفية</button>
                        @if ($q !== '' || $status !== '')
                            <a href="{{ route('sales-orders.index') }}" class="btn btn-glass btn-sm">مسح</a>
                        @endif
                    </form>
                </div>

                @if ($orders->isEmpty())
                    <div class="ds-data-empty ds-list-empty" role="status">
                        <strong>لا نتائج مطابقة</strong>
                        <p>لا أوامر ضمن التصفية الحالية. امسح البحث أو غيّر الحالة.</p>
                    </div>
                @else
                    <div class="ds-list-body">
                        <table class="admin-table admin-table--sheet">
                            <thead>
                                <tr>
                                    <th scope="col">أمر البيع</th>
                                    <th scope="col">المنشأة</th>
                                    <th scope="col">أمر الشراء</th>
                                    <th scope="col">الحالة</th>
                                    <th scope="col" class="quotes-col-actions"><span class="visually-hidden">إجراء</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($orders as $item)
                                    @php
                                        $st = $item->status instanceof \BackedEnum
                                            ? $item->status->value
                                            : (string) $item->status;
                                        $next = $item->nextAction();
                                    @endphp
                                    <tr @class(['quotes-row-pending' => $st === 'pending_approval'])>
                                        <td>
                                            <div class="quotes-id-block">
                                                <a href="{{ route('sales-orders.show', $item) }}" class="quotes-id-link dir-ltr">#{{ $item->id }}</a>
                                                @if ($item->createdBy?->name)
                                                    <span class="quotes-meta-line">أنشأه {{ $item->createdBy->name }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="quotes-customer-block">
                                                <span class="ds-cell-primary">{{ $item->customer?->name_ar ?? '—' }}</span>
                                                <span class="quotes-meta-line dir-ltr">صفقة #{{ $item->deal_id }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="{{ route('customer-pos.show', $item->customer_purchase_order_id) }}" class="quotes-sr-chip dir-ltr">شراء #{{ $item->customer_purchase_order_id }}</a>
                                        </td>
                                        <td>
                                            <span class="badge {{ $next->badge }}">{{ $next->summaryAr() }}</span>
                                        </td>
                                        <td class="ds-cell-actions quotes-col-actions">
                                            <div class="quotes-row-actions">
                                                <a href="{{ route('sales-orders.show', $item) }}" class="btn btn-glass btn-sm">عرض</a>
                                                <details class="ds-row-menu">
                                                    <summary aria-label="إجراءات أمر بيع {{ $item->id }}">⋮</summary>
                                                    <div class="ds-row-menu-panel">
                                                        <a href="{{ route('sales-orders.show', $item) }}">عرض التفاصيل</a>
                                                        @can('update', $item)
                                                            <a href="{{ route('sales-orders.edit', $item) }}">تعديل</a>
                                                        @endcan
                                                        @can('submit', $item)
                                                            <form method="POST" action="{{ route('sales-orders.submit', $item) }}"
                                                                  onsubmit="return confirm('إرسال أمر البيع للاعتماد؟');">
                                                                @csrf
                                                                <button type="submit" class="ds-row-menu-btn">إرسال للاعتماد</button>
                                                            </form>
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

                @if ($orders->hasPages())
                    <div class="ds-list-footer">{{ $orders->links() }}</div>
                @endif
            </section>
        @endif
    </div>
@endsection
