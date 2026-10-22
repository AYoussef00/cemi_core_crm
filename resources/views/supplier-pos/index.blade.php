@extends('layouts.app')

@section('title', ($warehouseFacing ?? false) ? 'طلبيات واردة — '.config('app.name') : 'أوامر شراء الموردين — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">{{ ($warehouseFacing ?? false) ? 'المستودع · طلبيات واردة' : 'المشتريات · أوامر الموردين' }}</span>
@endsection

@section('content')
    @php
        $warehouseFacing = $warehouseFacing ?? false;
        $inbox = $inbox ?? '';
    @endphp
    <div class="procurement-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="spo-heading">
            <div class="ds-page-bar-copy">
                <h1 id="spo-heading" class="admin-title">
                    {{ $warehouseFacing ? 'طلبيات واردة من الموردين' : 'أوامر شراء الموردين' }}
                </h1>
                @if ($warehouseFacing)
                    <p class="admin-lead">
                        بعد إصدار المشتريات لأمر الشراء، تظهر الطلبية هنا بالكمية الكلية. سجّل كل شحنة واستلم الكمية الفعلية — المتبقي يظهر فورًا.
                    </p>
                @endif
            </div>
            @if ($warehouseFacing)
                <div class="ds-page-bar-tools">
                    <a href="{{ route('supplier-pos.index', ['inbox' => 'inbound']) }}"
                       class="btn {{ $inbox === 'inbound' && $status === '' ? 'btn-glass-primary' : 'btn-glass' }} btn-sm">بانتظار الاستلام</a>
                    <a href="{{ route('supplier-pos.index', ['inbox' => 'all']) }}"
                       class="btn {{ $inbox === 'all' || $status !== '' ? 'btn-glass-primary' : 'btn-glass' }} btn-sm">كل الأوامر</a>
                </div>
            @endif
        </header>

        @php
            $spoFilteredEmpty = $orders->isEmpty() && ($q !== '' || $status !== '' || in_array($inbox, ['inbound', 'all'], true));
        @endphp

        @if ($orders->isEmpty() && ! $spoFilteredEmpty)
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                @if ($warehouseFacing)
                    <strong>لا توجد طلبيات واردة الآن</strong>
                    <p>تظهر هنا الأوامر بعد أن تصدرها المشتريات للمورد. ستصلك إشعار عند كل أمر جديد.</p>
                @else
                    <strong>لا توجد أوامر شراء موردين</strong>
                    <p>تُنشأ عند اعتماد عرض فيه بنود توريد، أو عند نقص مخزون أمر البيع.</p>
                @endif
            </div>
        @else
            <section class="ds-list-panel" aria-label="{{ $warehouseFacing ? 'طلبيات واردة' : 'قائمة أوامر شراء الموردين' }}">
                <div class="ds-list-toolbar">
                    <div class="ds-list-meta">
                        <span class="ds-list-count dir-ltr">{{ number_format($orders->total()) }}</span>
                        <span class="ds-list-count-label">{{ $warehouseFacing ? 'طلبية ضمن نطاقك' : 'أمر ضمن نطاقك' }}</span>
                    </div>
                    <form method="GET" action="{{ route('supplier-pos.index') }}" class="ds-list-filters" role="search">
                        @if ($warehouseFacing && $inbox !== '')
                            <input type="hidden" name="inbox" value="{{ $inbox }}">
                        @endif
                        <div class="ds-data-search">
                            <svg class="ds-data-search-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M16.2 16.2 20 20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                            <label class="visually-hidden" for="spo-q">بحث</label>
                            <input id="spo-q" type="search" name="q" value="{{ $q }}" placeholder="بحث بالمورد أو المنتج أو رقم الأمر…" autocomplete="off">
                        </div>
                        <label class="visually-hidden" for="spo-status">الحالة</label>
                        <select id="spo-status" name="status" class="visits-status-filter" onchange="this.form.submit()" aria-label="تصفية الحالة">
                            <option value="">كل الحالات</option>
                            @foreach ($statuses as $st)
                                <option value="{{ $st->value }}" @selected($status === $st->value)>{{ $st->labelAr() }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-glass btn-sm">تصفية</button>
                        @if ($q !== '' || $status !== '')
                            <a href="{{ route('supplier-pos.index', $warehouseFacing ? ['inbox' => $inbox ?: 'inbound'] : []) }}" class="btn btn-glass btn-sm">مسح</a>
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
                                    <th scope="col">الأمر</th>
                                    <th scope="col">المورد</th>
                                    <th scope="col">الكمية الكلية</th>
                                    <th scope="col">المستلم</th>
                                    <th scope="col">المتبقي</th>
                                    <th scope="col">الحالة</th>
                                    <th scope="col" class="quotes-col-actions"><span class="visually-hidden">إجراء</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($orders as $spo)
                                    @php
                                        $stValue = $spo->status instanceof \BackedEnum
                                            ? $spo->status->value
                                            : (string) $spo->status;
                                        $stBadge = match ($stValue) {
                                            'open' => 'badge-warning',
                                            'ordered' => 'badge-neutral',
                                            'partially_received' => 'badge-warning',
                                            'received' => 'badge-success',
                                            'cancelled' => 'badge-danger',
                                            default => 'badge-neutral',
                                        };
                                        $totalQty = $spo->totalOrderedQuantity();
                                        $receivedQty = $spo->totalReceivedQuantity();
                                        $remainingQty = $spo->totalRemainingQuantity();
                                    @endphp
                                    <tr @class(['quotes-row-pending' => in_array($stValue, ['open', 'ordered', 'partially_received'], true)])>
                                        <td>
                                            <div class="quotes-id-block">
                                                <a href="{{ route('supplier-pos.show', $spo) }}" class="quotes-id-link dir-ltr">#{{ $spo->id }}</a>
                                                <span class="quotes-meta-line dir-ltr">
                                                    {{ $spo->lines->count() }} بنود
                                                    @if (($spo->goods_receipts_count ?? 0) > 0)
                                                        · {{ $spo->goods_receipts_count }} استلام
                                                    @endif
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="ds-cell-primary">{{ $spo->supplier_name_ar ?: '—' }}</span>
                                            @if ($spo->expected_at)
                                                <span class="quotes-meta-line dir-ltr">متوقع {{ $spo->expected_at->timezone(config('app.timezone'))->format('Y-m-d') }}</span>
                                            @endif
                                        </td>
                                        <td class="dir-ltr inventory-qty">{{ qty($totalQty) }}</td>
                                        <td class="dir-ltr inventory-qty">{{ qty($receivedQty) }}</td>
                                        <td class="dir-ltr inventory-qty">
                                            <span @class(['spo-remaining-emph' => $remainingQty > 1e-9])>{{ qty($remainingQty) }}</span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $stBadge }}">{{ $spo->status->labelAr() }}</span>
                                        </td>
                                        <td class="ds-cell-actions quotes-col-actions">
                                            <div class="quotes-row-actions">
                                                <a href="{{ route('supplier-pos.show', $spo) }}" class="btn btn-glass btn-sm">
                                                    {{ $warehouseFacing && in_array($stValue, ['ordered', 'partially_received'], true) ? 'استلام شحنة' : 'عرض' }}
                                                </a>
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
