@extends('layouts.app')

@section('title', 'أوامر شراء العملاء — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · أوامر شراء العملاء</span>
@endsection

@section('content')
    <div class="customer-pos-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="cpo-heading">
            <div class="ds-page-bar-copy">
                <h1 id="cpo-heading" class="admin-title">أوامر شراء العملاء</h1>
            </div>
            @if ($canCreate)
                <div class="ds-page-bar-tools">
                    <a href="{{ route('customer-pos.create') }}" class="btn btn-glass-primary">أمر شراء جديد</a>
                </div>
            @endif
        </header>

        @php $cpoFilteredEmpty = $orders->isEmpty() && ($q !== '' || $status !== ''); @endphp

        @if ($orders->isEmpty() && ! $cpoFilteredEmpty)
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا توجد أوامر شراء</strong>
                <p>أنشئ أمر شراء من عرض سعر معتمد/مُرسل ضمن نطاقك. لا تُعرض سجلات وهمية.</p>
            </div>
        @else
            <section class="ds-list-panel" aria-label="قائمة أوامر شراء العملاء">
                <div class="ds-list-toolbar">
                    <div class="ds-list-meta">
                        <span class="ds-list-count dir-ltr">{{ number_format($orders->total()) }}</span>
                        <span class="ds-list-count-label">أمر شراء ضمن نطاقك</span>
                    </div>
                    <form method="GET" action="{{ route('customer-pos.index') }}" class="ds-list-filters" role="search">
                        <div class="ds-data-search">
                            <svg class="ds-data-search-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M16.2 16.2 20 20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                            <label class="visually-hidden" for="cpo-q">بحث</label>
                            <input id="cpo-q" type="search" name="q" value="{{ $q }}" placeholder="بحث بالمنشأة أو المرجع…" autocomplete="off">
                        </div>
                        <label class="visually-hidden" for="cpo-status">الحالة</label>
                        <select id="cpo-status" name="status" class="visits-status-filter" onchange="this.form.submit()" aria-label="تصفية الحالة">
                            <option value="">كل الحالات</option>
                            @foreach ($statuses as $st)
                                <option value="{{ $st->value }}" @selected($status === $st->value)>{{ $st->labelAr() }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-glass btn-sm">تصفية</button>
                        @if ($q !== '' || $status !== '')
                            <a href="{{ route('customer-pos.index') }}" class="btn btn-glass btn-sm">مسح</a>
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
                                    <th scope="col">أمر الشراء</th>
                                    <th scope="col">المنشأة</th>
                                    <th scope="col">عرض السعر</th>
                                    <th scope="col">الصفقة</th>
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
                                        $badge = $st === 'open' ? 'badge-success' : 'badge-danger';
                                        $dealSt = $item->deal?->status;
                                        $dealBadge = ($dealSt?->value ?? '') === 'open' ? 'badge-success' : 'badge-neutral';
                                        $showUrl = $item->attachments->isNotEmpty()
                                            ? route('customer-pos.pdf', $item)
                                            : route('customer-pos.show', $item);
                                    @endphp
                                    <tr @class(['cpo-row-cancelled' => $st === 'cancelled'])>
                                        <td>
                                            <div class="quotes-id-block">
                                                <a href="{{ route('customer-pos.show', $item) }}" class="quotes-id-link dir-ltr">{{ $item->document_number }}</a>
                                                @if ($item->reference_ar)
                                                    <span class="quotes-meta-line">{{ $item->reference_ar }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="quotes-customer-block">
                                                <span class="ds-cell-primary">{{ $item->customer?->name_ar ?? '—' }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            @if ($item->quotation_id)
                                                <a href="{{ route('quotations.show', $item->quotation_id) }}" class="quotes-sr-chip dir-ltr">{{ doc_no('QA', $item->quotation_id) }}</a>
                                            @else
                                                <span class="ds-cell-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($item->deal)
                                                <div class="quotes-id-block">
                                                    <span class="quotes-version-chip dir-ltr">#{{ $item->deal->id }}</span>
                                                    <span class="badge {{ $dealBadge }}">{{ $dealSt?->labelAr() ?? '—' }}</span>
                                                </div>
                                            @else
                                                <span class="ds-cell-muted">—</span>
                                            @endif
                                        </td>
                                        <td><span class="badge {{ $badge }}">{{ $item->status->labelAr() }}</span></td>
                                        <td class="ds-cell-actions quotes-col-actions">
                                            <div class="quotes-row-actions">
                                                <a href="{{ $showUrl }}" @if ($item->attachments->isNotEmpty()) target="_blank" rel="noopener" @endif class="btn btn-glass btn-sm">عرض</a>
                                                <details class="ds-row-menu">
                                                    <summary aria-label="إجراءات أمر شراء {{ $item->id }}">⋮</summary>
                                                    <div class="ds-row-menu-panel">
                                                        @if ($item->attachments->isNotEmpty())
                                                            <a href="{{ route('customer-pos.pdf', $item) }}" target="_blank" rel="noopener">عرض PDF</a>
                                                        @endif
                                                        <a href="{{ route('customer-pos.show', $item) }}">عرض التفاصيل</a>
                                                        @can('update', $item)
                                                            <a href="{{ route('customer-pos.edit', $item) }}">تعديل</a>
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
