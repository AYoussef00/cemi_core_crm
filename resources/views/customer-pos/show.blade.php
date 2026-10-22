@extends('layouts.app')

@section('title', 'أمر شراء '.$po->document_number.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · تفاصيل أمر الشراء</span>
@endsection

@section('content')
    @php
        $st = $po->status instanceof \BackedEnum ? $po->status->value : (string) $po->status;
        $badge = $st === 'open' ? 'badge-success' : 'badge-danger';
        $dealSt = $po->deal?->status;
        $dealBadge = ($dealSt?->value ?? '') === 'open' ? 'badge-success' : 'badge-neutral';
    @endphp

    <div class="customer-pos-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="cpo-show-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">أمر شراء عميل · <span class="dir-ltr">{{ $po->document_number }}</span></p>
                <h1 id="cpo-show-heading" class="admin-title">
                    {{ $po->customer?->name_ar ?? 'أمر شراء' }}
                    <span class="badge {{ $badge }}">{{ $po->status->labelAr() }}</span>
                </h1>
                <p class="admin-lead">
                    عرض سعر
                    <a href="{{ route('quotations.show', $po->quotation_id) }}" class="sr-task-link dir-ltr">{{ doc_no('QA', $po->quotation_id) }}</a>
                    @if ($po->reference_ar)
                        · مرجع: {{ $po->reference_ar }}
                    @endif
                    · أوامر البيع تُنشأ تحت هذا الأمر فقط
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('customer-pos.index') }}" class="btn btn-glass">القائمة</a>
                @if ($poPdf ?? null)
                    <a href="{{ route('customer-pos.pdf', $po) }}" class="btn btn-glass" target="_blank" rel="noopener">عرض PDF</a>
                @endif
                @if ($canUpdate)
                    <a href="{{ route('customer-pos.edit', $po) }}" class="btn btn-glass">تعديل</a>
                @endif
                @if ($canCreateSo)
                    <a href="{{ route('sales-orders.create', ['customer_purchase_order_id' => $po->id]) }}" class="btn btn-glass-primary">أمر بيع جديد</a>
                @endif
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="cpo-deal-card" aria-labelledby="cpo-deal-heading">
            <div class="cpo-deal-card-head">
                <h2 id="cpo-deal-heading" class="customers-form-section-title">الصفقة (للقراءة)</h2>
                <p class="customers-form-section-lead">صفقة واحدة لكل أمر شراء. لا يوجد إغلاق صفقة في هذه المرحلة.</p>
            </div>
            <dl class="cpo-deal-dl">
                <div>
                    <dt>رقم الصفقة</dt>
                    <dd class="dir-ltr">#{{ $po->deal?->id ?? '—' }}</dd>
                </div>
                <div>
                    <dt>الحالة</dt>
                    <dd>
                        @if ($dealSt)
                            <span class="badge {{ $dealBadge }}">{{ $dealSt->labelAr() }}</span>
                        @else
                            —
                        @endif
                    </dd>
                </div>
            </dl>
        </section>

        <section class="inventory-metrics" aria-label="ملخص أمر الشراء">
            <article class="inventory-metric">
                <p class="inventory-metric-label">الحالة</p>
                <p class="inventory-metric-value">
                    <span class="badge {{ $badge }}">{{ $po->status->labelAr() }}</span>
                </p>
            </article>
            <article class="inventory-metric">
                <p class="inventory-metric-label">عدد البنود</p>
                <p class="inventory-metric-value dir-ltr">{{ $po->lines->count() }}</p>
            </article>
            <article class="inventory-metric">
                <p class="inventory-metric-label">أوامر البيع</p>
                <p class="inventory-metric-value dir-ltr">{{ $po->salesOrders->count() }}</p>
            </article>
            <article class="inventory-metric">
                <p class="inventory-metric-label">أنشأه</p>
                <p class="inventory-metric-value inventory-metric-value-sm">{{ $po->createdBy?->name ?? '—' }}</p>
            </article>
        </section>

        <section class="pf1-card content-card" aria-labelledby="cpo-pf1-heading">
            <div class="sr-section-head">
                <h2 id="cpo-pf1-heading" class="customers-form-section-title">التلبية الجزئية</h2>
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

        @if ($po->notes_ar)
            <section class="quote-notes-card" aria-labelledby="cpo-notes-heading">
                <h2 id="cpo-notes-heading" class="customers-form-section-title">ملاحظات</h2>
                <p class="quote-notes-body">{{ $po->notes_ar }}</p>
            </section>
        @endif

        <section class="cpo-lines-card" aria-labelledby="cpo-lines-heading">
            <div class="sr-section-head">
                <h2 id="cpo-lines-heading" class="customers-form-section-title">البنود والتخصيص</h2>
                <p class="customers-form-section-lead">
                    PF1: غير مخصّص / غير مسلّم / غير مفوتر — أرقام منفصلة لا تُدمج.
                </p>
            </div>
            <div class="ds-table-surface">
                <div class="ds-data-body">
                    <table class="admin-table cpo-table">
                        <thead>
                            <tr>
                                <th scope="col">المنتج</th>
                                <th scope="col">كمية أمر الشراء</th>
                                <th scope="col">مخصّص</th>
                                <th scope="col">غير مخصّص</th>
                                <th scope="col">غير مسلّم</th>
                                <th scope="col">غير مفوتر</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($po->lines as $line)
                                @php
                                    $alloc = $lineAllocations[$line->id] ?? [
                                        'allocated' => 0,
                                        'unallocated' => 0,
                                        'undelivered' => 0,
                                        'uninvoiced' => 0,
                                    ];
                                @endphp
                                <tr>
                                    <td class="ds-cell-primary">{{ $line->product?->name_ar ?? '—' }}</td>
                                    <td class="dir-ltr inventory-qty">{{ qty($line->quantity) }}</td>
                                    <td class="dir-ltr inventory-qty">{{ qty($alloc['allocated']) }}</td>
                                    <td class="dir-ltr inventory-qty {{ $alloc['unallocated'] > 0 ? 'cpo-remain-ok' : 'ds-cell-muted' }}">
                                        {{ qty($alloc['unallocated']) }}
                                    </td>
                                    <td class="dir-ltr inventory-qty">{{ qty($alloc['undelivered']) }}</td>
                                    <td class="dir-ltr inventory-qty">{{ qty($alloc['uninvoiced']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @if ($po->salesOrders->isNotEmpty())
            <section class="cpo-sos-card" aria-labelledby="cpo-sos-heading">
                <h2 id="cpo-sos-heading" class="customers-form-section-title">أوامر البيع المرتبطة</h2>
                <ul class="sr-quote-list">
                    @foreach ($po->salesOrders as $so)
                        @php
                            $soSt = $so->status instanceof \BackedEnum ? $so->status->value : (string) $so->status;
                            $soBadge = match ($soSt) {
                                'approved' => 'badge-success',
                                'pending_approval' => 'badge-warning',
                                'rejected', 'cancelled' => 'badge-danger',
                                default => 'badge-neutral',
                            };
                        @endphp
                        <li class="sr-quote-item">
                            <a href="{{ route('sales-orders.show', $so) }}" class="sr-task-link">
                                أمر بيع #{{ $so->id }}
                                <span class="badge {{ $soBadge }}">{{ $so->status->labelAr() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($canCancel)
            <section class="quote-decide-panel" aria-labelledby="cpo-cancel-heading">
                <div class="sr-decide-copy">
                    <h2 id="cpo-cancel-heading" class="customers-form-section-title">إلغاء أمر الشراء</h2>
                    <p class="customers-form-section-lead">
                        لمدير المبيعات فقط عندما لا توجد أوامر بيع نشطة (مسودة / بانتظار / معتمدة).
                    </p>
                </div>
                <form method="POST" action="{{ route('customer-pos.cancel', $po) }}"
                      onsubmit="return confirm('تأكيد إلغاء أمر الشراء؟');">
                    @csrf
                    <button type="submit" class="btn btn-glass inventory-reject-btn">إلغاء أمر الشراء</button>
                </form>
            </section>
        @endif
    </div>
@endsection
