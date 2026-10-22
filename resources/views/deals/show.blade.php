@extends('layouts.app')

@section('title', 'صفقة #'.$deal->id.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المالية · تفاصيل الصفقة</span>
@endsection

@section('content')
    @php
        $st = $deal->status instanceof \BackedEnum
            ? $deal->status->value
            : (string) $deal->status;
        $badge = $st === 'open' ? 'badge-success' : 'badge-neutral';
        $hasInvoices = $deal->invoices->isNotEmpty();
        $anyUnpaid = $hasInvoices && ! $allPaid;
    @endphp

    <div class="deals-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="deal-show-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">صفقة · #{{ $deal->id }}</p>
                <h1 id="deal-show-heading" class="admin-title">
                    {{ $deal->customer?->name_ar ?? 'صفقة' }}
                    <span class="badge {{ $badge }}">{{ $deal->status->labelAr() }}</span>
                </h1>
                <p class="admin-lead">
                    أمر شراء
                    @if ($deal->customer_purchase_order_id)
                        <a href="{{ route('customer-pos.show', $deal->customer_purchase_order_id) }}" class="sr-task-link">
                            #{{ $deal->customer_purchase_order_id }}
                        </a>
                    @else
                        —
                    @endif
                    · الإغلاق للحسابات فقط عندما كل الفواتير مسددة (TX5b)
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('deals.index') }}" class="btn btn-glass">القائمة</a>
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="inventory-metrics" aria-label="ملخص الصفقة">
            <article class="inventory-metric">
                <p class="inventory-metric-label">الحالة</p>
                <p class="inventory-metric-value">
                    <span class="badge {{ $badge }}">{{ $deal->status->labelAr() }}</span>
                </p>
            </article>
            <article class="inventory-metric">
                <p class="inventory-metric-label">الفواتير</p>
                <p class="inventory-metric-value dir-ltr">{{ $deal->invoices->count() }}</p>
            </article>
            <article class="inventory-metric @if ($anyUnpaid) inventory-metric-available @endif">
                <p class="inventory-metric-label">السداد</p>
                <p class="inventory-metric-value inventory-metric-value-sm">
                    @if (! $hasInvoices)
                        لا فواتير بعد
                    @elseif ($allPaid)
                        كل الفواتير مسددة
                    @else
                        يوجد متبقي
                    @endif
                </p>
            </article>
            @if ($deal->closed_at)
                <article class="inventory-metric">
                    <p class="inventory-metric-label">أُغلقت في</p>
                    <p class="inventory-metric-value inventory-metric-value-sm dir-ltr">
                        {{ $deal->closed_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                    </p>
                </article>
            @endif
        </section>

        <section class="pf1-card content-card" aria-labelledby="deal-pf1-heading">
            <div class="sr-section-head">
                <h2 id="deal-pf1-heading" class="customers-form-section-title">التلبية الجزئية</h2>
                <p class="customers-form-section-lead">
                    ثلاثة مؤشرات منفصلة (PF1) — لا تُدمج ولا تُختزل في رقم واحد.
                </p>
            </div>
            <div class="inventory-metrics pf1-metrics" aria-label="التلبية الجزئية PF1">
                <article class="inventory-metric {{ $pf1['unallocated'] > 0 ? 'inventory-metric-available' : '' }}">
                    <p class="inventory-metric-label">غير مخصّص</p>
                    <p class="inventory-metric-value dir-ltr {{ $pf1['unallocated'] > 0 ? 'cpo-remain-ok' : '' }}">
                        {{ qty($pf1['unallocated']) }}
                    </p>
                </article>
                <article class="inventory-metric {{ $pf1['undelivered'] > 0 ? 'inventory-metric-available' : '' }}">
                    <p class="inventory-metric-label">غير مسلّم</p>
                    <p class="inventory-metric-value dir-ltr {{ $pf1['undelivered'] > 0 ? 'cpo-remain-ok' : '' }}">
                        {{ qty($pf1['undelivered']) }}
                    </p>
                </article>
                <article class="inventory-metric {{ $pf1['uninvoiced'] > 0 ? 'inventory-metric-available' : '' }}">
                    <p class="inventory-metric-label">غير مفوتر</p>
                    <p class="inventory-metric-value dir-ltr {{ $pf1['uninvoiced'] > 0 ? 'cpo-remain-ok' : '' }}">
                        {{ qty($pf1['uninvoiced']) }}
                    </p>
                </article>
                <article class="inventory-metric">
                    <p class="inventory-metric-label">كمية أمر الشراء</p>
                    <p class="inventory-metric-value dir-ltr">{{ qty($pf1['ordered']) }}</p>
                </article>
            </div>
        </section>

        <section class="fin-lines-card" aria-labelledby="deal-inv-heading">
            <div class="sr-section-head">
                <h2 id="deal-inv-heading" class="customers-form-section-title">الفواتير المرتبطة</h2>
                <p class="customers-form-section-lead">المتبقي وفق CR7 لكل فاتورة.</p>
            </div>

            @if (! $hasInvoices)
                <div class="ds-data-empty ds-data-empty-surface" role="status">
                    <strong>لا فواتير بعد</strong>
                    <p>لا يمكن إغلاق الصفقة قبل وجود فاتورة واحدة على الأقل وكلها مسددة.</p>
                </div>
            @else
                <div class="ds-table-surface">
                    <div class="ds-data-body">
                        <table class="admin-table fin-table">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">الإجمالي شامل الضريبة</th>
                                    <th scope="col">المتبقي</th>
                                    <th scope="col">إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($deal->invoices as $inv)
                                    @php
                                        $u = (float) $inv->unpaidBalance();
                                        $invPaid = abs($u) < 0.005;
                                    @endphp
                                    <tr>
                                        <td class="dir-ltr">
                                            <a href="{{ route('invoices.show', $inv) }}" class="quotes-id-link">{{ $inv->document_number }}</a>
                                        </td>
                                        <td class="dir-ltr inventory-qty">{{ money($inv->total_vat_inclusive) }} ر.س</td>
                                        <td>
                                            <span class="dir-ltr inventory-qty {{ $invPaid ? 'ds-cell-muted' : 'fin-unpaid-emph' }}">
                                                {{ money($u) }} ر.س
                                            </span>
                                            <span class="badge {{ $invPaid ? 'badge-success' : 'badge-warning' }}">
                                                {{ $invPaid ? 'مسددة' : 'مفتوحة' }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('invoices.show', $inv) }}" class="sr-task-link">عرض</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>

        @if ($canClose && $st === 'open')
            <section class="quote-decide-panel" aria-labelledby="deal-close-heading">
                <div class="sr-decide-copy">
                    <h2 id="deal-close-heading" class="customers-form-section-title">إغلاق الصفقة</h2>
                    <p class="customers-form-section-lead">
                        للحسابات فقط. الشرط: فاتورة واحدة على الأقل وكل الفواتير المرتبطة متبقيها = 0 (TX5b).
                    </p>
                </div>

                @if ($allPaid && $hasInvoices)
                    <form method="POST" action="{{ route('deals.close', $deal) }}"
                          onsubmit="return confirm('تأكيد إغلاق الصفقة؟');">
                        @csrf
                        <button type="submit" class="btn btn-glass-primary">إغلاق الصفقة (كل الفواتير مسددة)</button>
                    </form>
                @else
                    <div class="deal-close-blocked" role="status">
                        <p class="deal-close-blocked-title">الإغلاق غير متاح</p>
                        <p class="deal-close-blocked-body">
                            @if (! $hasInvoices)
                                لا فواتير مرتبطة بعد.
                            @else
                                يوجد متبقي غير مسدد على فاتورة واحدة على الأقل (TX5b).
                            @endif
                        </p>
                    </div>
                @endif
            </section>
        @endif
    </div>
@endsection
