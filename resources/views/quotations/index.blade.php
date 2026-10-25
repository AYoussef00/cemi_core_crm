@extends('layouts.app')

@section('title', 'عروض الأسعار — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · عروض الأسعار</span>
@endsection

@section('content')
    <div class="quotations-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="quotes-heading">
            <div class="ds-page-bar-copy">
                <h1 id="quotes-heading" class="admin-title">عروض الأسعار</h1>
            </div>
        </header>

        @php $quotesFilteredEmpty = $quotations->isEmpty() && ($q !== '' || $status !== ''); @endphp

        @if ($quotations->isEmpty() && ! $quotesFilteredEmpty)
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا توجد عروض أسعار</strong>
                <p>أنشئ عرضًا من طلب بيع جاهز للتسعير ضمن نطاقك. لا تُعرض سجلات وهمية.</p>
            </div>
        @else
            <section class="quotes-list-panel" aria-label="قائمة عروض الأسعار">
                <div class="quotes-list-toolbar">
                    <div class="quotes-list-meta">
                        <span class="quotes-list-count dir-ltr">{{ number_format($quotations->total()) }}</span>
                        <span class="quotes-list-count-label">عرض ضمن نطاقك</span>
                    </div>
                    <form method="GET" action="{{ route('quotations.index') }}" class="quotes-list-filters" role="search">
                        <div class="ds-data-search">
                            <svg class="ds-data-search-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M16.2 16.2 20 20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                            <label class="visually-hidden" for="quotes-q">بحث</label>
                            <input id="quotes-q" type="search" name="q" value="{{ $q }}" placeholder="بحث بالمنشأة…" autocomplete="off">
                        </div>
                        <label class="visually-hidden" for="quotes-status">الحالة</label>
                        <select id="quotes-status" name="status" class="visits-status-filter" onchange="this.form.submit()" aria-label="تصفية الحالة">
                            <option value="">كل الحالات</option>
                            @foreach ($statuses as $st)
                                <option value="{{ $st->value }}" @selected($status === $st->value)>{{ $st->labelAr() }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-glass btn-sm">تصفية</button>
                        @if ($q !== '' || $status !== '')
                            <a href="{{ route('quotations.index') }}" class="btn btn-glass btn-sm">مسح</a>
                        @endif
                    </form>
                </div>

                @if ($quotations->isEmpty())
                    <div class="ds-data-empty quotes-list-empty" role="status">
                        <strong>لا نتائج مطابقة</strong>
                        <p>لا عروض ضمن التصفية الحالية. امسح البحث أو غيّر الحالة.</p>
                    </div>
                @else
                    <div class="quotes-list-body">
                        <table class="admin-table admin-table--sheet quotes-table">
                            <thead>
                                <tr>
                                    <th scope="col">العرض</th>
                                    <th scope="col">المنشأة</th>
                                    <th scope="col">الحالة</th>
                                    <th scope="col" class="quotes-col-amount">الإجمالي شامل الضريبة</th>
                                    <th scope="col">طلب البيع</th>
                                    <th scope="col" class="quotes-col-actions"><span class="visually-hidden">إجراء</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($quotations as $item)
                                    @php
                                        $st = $item->status instanceof \BackedEnum
                                            ? $item->status->value
                                            : (string) $item->status;
                                        $badge = match ($st) {
                                            'approved', 'sent' => 'badge-success',
                                            'pending_price_approval' => 'badge-warning',
                                            'cancelled', 'expired' => 'badge-danger',
                                            'superseded' => 'badge-neutral',
                                            default => 'badge-neutral',
                                        };
                                        $total = (float) $item->totalVatInclusive();
                                    @endphp
                                    <tr @class(['quotes-row-pending' => $st === 'pending_price_approval'])>
                                        <td>
                                            <div class="quotes-id-block">
                                                <a href="{{ route('quotations.show', $item) }}" class="quotes-id-link dir-ltr">{{ $item->document_number }}</a>
                                                <span class="quotes-version-chip dir-ltr">v{{ $item->version }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="quotes-customer-block">
                                                <span class="ds-cell-primary">{{ $item->customer?->name_ar ?? '—' }}</span>
                                                @if ($item->createdBy?->name)
                                                    <span class="quotes-meta-line">أنشأه {{ $item->createdBy->name }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $badge }}">{{ $item->status->labelAr() }}</span>
                                        </td>
                                        <td class="quotes-col-amount">
                                            <span class="quotes-amount dir-ltr">
                                                {{ money($total) }}
                                                <span class="quotes-amount-unit">ر.س</span>
                                            </span>
                                        </td>
                                        <td>
                                            @if ($item->salesRequest)
                                                @can('view', $item->salesRequest)
                                                    <a href="{{ route('sales-requests.show', $item->salesRequest) }}" class="quotes-sr-chip dir-ltr">طلب #{{ $item->sales_request_id }}</a>
                                                @else
                                                    <span class="quotes-sr-chip is-static dir-ltr">طلب #{{ $item->sales_request_id }}</span>
                                                @endcan
                                            @else
                                                <span class="ds-cell-muted dir-ltr">#{{ $item->sales_request_id }}</span>
                                            @endif
                                        </td>
                                        <td class="ds-cell-actions quotes-col-actions">
                                            <div class="quotes-row-actions">
                                                <a href="{{ route('quotations.show', $item) }}" class="btn btn-glass btn-sm">عرض</a>
                                                <details class="ds-row-menu">
                                                    <summary aria-label="إجراءات عرض {{ $item->id }}">⋮</summary>
                                                    <div class="ds-row-menu-panel">
                                                        <a href="{{ route('quotations.show', $item) }}">عرض التفاصيل</a>
                                                        @can('update', $item)
                                                            <a href="{{ route('quotations.show', $item) }}#quote-pricing">تسعير الهامش</a>
                                                        @endcan
                                                        @can('print', $item)
                                                            <a href="{{ route('quotations.print', $item) }}" target="_blank" rel="noopener">عرض عرض السعر PDF</a>
                                                        @endcan
                                                        @can('convertToSalesOrder', $item)
                                                            <button
                                                                type="button"
                                                                class="ds-row-menu-btn"
                                                                data-quote-convert-open
                                                                data-quote-id="{{ $item->id }}"
                                                                data-quote-number="{{ $item->document_number }}"
                                                                data-quote-customer="{{ e($item->customer?->name_ar ?? '') }}"
                                                                data-quote-total="{{ money($total) }}"
                                                                data-quote-action="{{ route('quotations.convert.store', $item) }}"
                                                            >تحويل إلى أمر بيع</button>
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

                @if ($quotations->hasPages())
                    <div class="quotes-list-footer">{{ $quotations->links() }}</div>
                @endif
            </section>
        @endif

        @include('quotations.partials.convert-modal')
    </div>
@endsection
