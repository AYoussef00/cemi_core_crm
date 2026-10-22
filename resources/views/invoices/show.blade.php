@extends('layouts.app')

@section('title', 'فاتورة '.$invoice->document_number.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المالية · تفاصيل الفاتورة</span>
@endsection

@section('content')
    @php
        $paid = $invoice->isFullyPaid();
        $overdue = $invoice->isOverdue();
        $statusBadge = $paid ? 'badge-success' : ($overdue ? 'badge-danger' : 'badge-warning');
        $statusLabel = $paid ? 'مسددة' : ($overdue ? 'متأخرة' : 'مفتوحة');

        $confirmedCollections = (float) $invoice->allocations
            ->filter(fn ($a) => $a->collection?->status?->value === 'confirmed')
            ->sum(fn ($a) => (float) $a->amount);
        $draftCollections = (float) $invoice->allocations
            ->filter(fn ($a) => $a->collection?->status?->value === 'draft')
            ->sum(fn ($a) => (float) $a->amount);
        $approvedCredits = (float) $invoice->notes
            ->filter(fn ($n) => $n->type?->value === 'credit' && $n->status?->value === 'approved')
            ->sum(fn ($n) => (float) $n->amount);
        $approvedDebits = (float) $invoice->notes
            ->filter(fn ($n) => $n->type?->value === 'debit' && $n->status?->value === 'approved')
            ->sum(fn ($n) => (float) $n->amount);

        $tz = config('app.timezone');
        $deliveryRef = $invoice->delivery?->document_number ?? doc_no('DN', $invoice->delivery_id);
        $lineCount = $invoice->lines->count();
        $qtyTotal = $invoice->lines->sum(fn ($l) => (float) $l->quantity);
        $hasActivity = $invoice->allocations->isNotEmpty() || $invoice->notes->isNotEmpty();
    @endphp

    <div class="invoices-page inv-show-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="inv-show-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">فاتورة · <span class="dir-ltr">{{ $invoice->document_number }}</span></p>
                <h1 id="inv-show-heading" class="admin-title">{{ $invoice->customer?->name_ar ?? 'فاتورة' }}</h1>
                <p class="procurement-show-status">
                    <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                    @if ($overdue && ! $paid)
                        <span class="badge badge-danger">تجاوز الاستحقاق</span>
                    @endif
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('invoices.index') }}" class="btn btn-glass">القائمة</a>
                <a href="{{ route('invoices.print', $invoice) }}" class="btn btn-glass" target="_blank" rel="noopener">طباعة</a>
                @if ($canCreateNote)
                    <a href="{{ route('invoice-notes.create', ['invoice_id' => $invoice->id]) }}" class="btn btn-glass">إشعار دائن/مدين</a>
                @endif
                @if (! $paid)
                    <a href="{{ route('collections.create', ['customer_id' => $invoice->customer_id]) }}" class="btn btn-glass-primary">تسجيل تحصيل</a>
                @endif
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="inv-money-strip" aria-label="ملخص المبالغ">
            <article class="inv-money-cell">
                <p class="inv-money-label">إجمالي الفاتورة</p>
                <p class="inv-money-value dir-ltr">
                    <span class="inv-money-num">{{ money($invoice->total_vat_inclusive) }}</span>
                    <span class="inv-money-unit">ر.س</span>
                </p>
                <p class="inv-money-hint">شامل الضريبة · لقطة عند الإصدار</p>
            </article>
            <article class="inv-money-cell">
                <p class="inv-money-label">المحصَّل المؤكد</p>
                <p class="inv-money-value dir-ltr">
                    <span class="inv-money-num">{{ money($confirmedCollections) }}</span>
                    <span class="inv-money-unit">ر.س</span>
                </p>
                <p class="inv-money-hint">
                    @if ($draftCollections > 0)
                        مسودة قيد التأكيد: {{ money($draftCollections) }} ر.س
                    @else
                        لا تدخل المسودات في الرصيد
                    @endif
                </p>
            </article>
            <article @class(['inv-money-cell', 'inv-money-cell-emphasis' => ! $paid, 'inv-money-cell-ok' => $paid])>
                <p class="inv-money-label">المتبقي على العميل</p>
                <p class="inv-money-value dir-ltr">
                    <span class="inv-money-num {{ $paid ? '' : 'fin-unpaid-emph' }}">{{ money($unpaid) }}</span>
                    <span class="inv-money-unit">ر.س</span>
                </p>
                <p class="inv-money-hint">{{ $paid ? 'لا يوجد رصيد مستحق' : 'واجب التحصيل' }}</p>
            </article>
            <article class="inv-money-cell">
                <p class="inv-money-label">تاريخ الاستحقاق</p>
                <p class="inv-money-value inv-money-value-sm dir-ltr">
                    {{ $invoice->due_at?->timezone($tz)->format('Y-m-d') ?? '—' }}
                </p>
                <p class="inv-money-hint">
                    إصدار {{ $invoice->issued_at?->timezone($tz)->format('Y-m-d') ?? '—' }}
                </p>
            </article>
        </section>

        <section class="so-detail-section" aria-labelledby="inv-overview-heading">
            <div class="so-detail-section-head">
                <h2 id="inv-overview-heading" class="customers-form-section-title">نظرة عامة</h2>
                <p class="customers-form-section-lead">فاتورة على كميات التسليم المؤكد فقط — ليست إجمالية لأمر البيع.</p>
            </div>
            <div class="so-overview-grid" role="list">
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">الحالة</p>
                    <p class="so-overview-value"><span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span></p>
                </article>
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">العميل</p>
                    <p class="so-overview-value">
                        @if ($invoice->customer)
                            <a href="{{ route('customers.show', $invoice->customer) }}" class="quotes-id-link">
                                {{ $invoice->customer->name_ar }}
                            </a>
                        @else
                            —
                        @endif
                    </p>
                </article>
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">إذن التسليم</p>
                    <p class="so-overview-value">
                        <a href="{{ route('deliveries.show', $invoice->delivery_id) }}" class="quotes-id-link dir-ltr">
                            {{ $deliveryRef }}
                        </a>
                    </p>
                </article>
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">أمر البيع</p>
                    <p class="so-overview-value">
                        @if ($invoice->sales_order_id)
                            <a href="{{ route('sales-orders.show', $invoice->sales_order_id) }}" class="quotes-id-link dir-ltr">
                                #{{ $invoice->sales_order_id }}
                            </a>
                        @else
                            —
                        @endif
                    </p>
                </article>
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">الصفقة</p>
                    <p class="so-overview-value">
                        @if ($invoice->deal_id)
                            <a href="{{ route('deals.show', $invoice->deal_id) }}" class="quotes-id-link">
                                #{{ $invoice->deal_id }}
                            </a>
                        @else
                            —
                        @endif
                    </p>
                </article>
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">أصدرها</p>
                    <p class="so-overview-value">{{ $invoice->issuedBy?->name ?? '—' }}</p>
                </article>
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">البنود / الكمية</p>
                    <p class="so-overview-value">
                        <span class="dir-ltr">{{ $lineCount }}</span>
                        ·
                        <span class="dir-ltr inventory-qty">{{ qty($qtyTotal) }}</span>
                    </p>
                </article>
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">التعديل المباشر</p>
                    <p class="so-overview-value">غير متاح — عبر إشعار دائن/مدين</p>
                </article>
                @if ($invoice->notes_ar)
                    <article class="so-overview-cell so-overview-cell-wide" role="listitem">
                        <p class="so-overview-label">ملاحظة الإصدار</p>
                        <p class="so-overview-value">{{ $invoice->notes_ar }}</p>
                    </article>
                @endif
            </div>
        </section>

        <section class="so-detail-section" aria-labelledby="inv-lines-heading">
            <div class="so-detail-section-head">
                <h2 id="inv-lines-heading" class="customers-form-section-title">بنود الفاتورة</h2>
                <p class="customers-form-section-lead">أسعار بيع شاملة الضريبة كما في عرض السعر وقت الإصدار.</p>
            </div>
            <div class="ds-list-panel spo-lines-panel">
                <div class="ds-list-body">
                    <table class="admin-table admin-table--sheet fin-table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">المنتج</th>
                                <th scope="col">الكمية</th>
                                @if ($canViewPrices)
                                    <th scope="col">سعر الوحدة</th>
                                    <th scope="col">إجمالي البند</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->lines as $index => $line)
                                <tr>
                                    <td class="dir-ltr ds-cell-muted">{{ $index + 1 }}</td>
                                    <td><span class="ds-cell-primary">{{ $line->product_name_ar }}</span></td>
                                    <td class="dir-ltr inventory-qty">{{ qty($line->quantity) }}</td>
                                    @if ($canViewPrices)
                                        <td class="dir-ltr inventory-qty">{{ money($line->unit_price_vat_inclusive) }}</td>
                                        <td class="dir-ltr inventory-qty">{{ money($line->line_total_vat_inclusive) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        @if ($canViewPrices)
                            <tfoot>
                                <tr class="inv-lines-total-row">
                                    <th scope="row" colspan="4">إجمالي الفاتورة</th>
                                    <td class="dir-ltr">{{ money($invoice->total_vat_inclusive) }} ر.س</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </section>

        <section class="so-detail-section" aria-labelledby="inv-balance-heading">
            <div class="so-detail-section-head">
                <h2 id="inv-balance-heading" class="customers-form-section-title">تسوية الرصيد</h2>
                <p class="customers-form-section-lead">
                    المتبقي = الصادر − تحصيلات مؤكدة − إشعارات دائنة معتمدة + إشعارات مدينة معتمدة.
                </p>
            </div>
            <div class="inv-settle-list" role="list">
                <div class="inv-settle-row" role="listitem">
                    <span class="inv-settle-label">قيمة الفاتورة الصادرة</span>
                    <span class="inv-settle-value dir-ltr">{{ money($invoice->total_vat_inclusive) }} ر.س</span>
                </div>
                <div class="inv-settle-row" role="listitem">
                    <span class="inv-settle-label">تحصيلات مؤكدة</span>
                    <span class="inv-settle-value dir-ltr inv-settle-minus">− {{ money($confirmedCollections) }} ر.س</span>
                </div>
                <div class="inv-settle-row inv-settle-muted" role="listitem">
                    <span class="inv-settle-label">تحصيلات مسودة <span class="ds-cell-muted">(لا تؤثر)</span></span>
                    <span class="inv-settle-value dir-ltr fin-draft-amt">{{ money($draftCollections) }} ر.س</span>
                </div>
                <div class="inv-settle-row" role="listitem">
                    <span class="inv-settle-label">إشعارات دائنة معتمدة</span>
                    <span class="inv-settle-value dir-ltr inv-settle-minus">− {{ money($approvedCredits) }} ر.س</span>
                </div>
                <div class="inv-settle-row" role="listitem">
                    <span class="inv-settle-label">إشعارات مدينة معتمدة</span>
                    <span class="inv-settle-value dir-ltr inv-settle-plus">+ {{ money($approvedDebits) }} ر.س</span>
                </div>
                <div class="inv-settle-row inv-settle-total" role="listitem">
                    <span class="inv-settle-label">المتبقي</span>
                    <span class="inv-settle-value dir-ltr fin-unpaid-emph">{{ money($unpaid) }} ر.س</span>
                </div>
            </div>
        </section>

        @if ($hasActivity)
            <section class="so-detail-section" aria-labelledby="inv-activity-heading">
                <div class="so-detail-section-head">
                    <h2 id="inv-activity-heading" class="customers-form-section-title">الحركة المرتبطة</h2>
                    <p class="customers-form-section-lead">تحصيلات وإشعارات مربوطة بهذه الفاتورة.</p>
                </div>

                @if ($invoice->allocations->isNotEmpty())
                    <h3 class="inv-activity-sub">التحصيلات</h3>
                    <div class="ds-list-panel spo-lines-panel">
                        <div class="ds-list-body">
                            <table class="admin-table admin-table--sheet fin-table">
                                <thead>
                                    <tr>
                                        <th scope="col">التحصيل</th>
                                        <th scope="col">الحالة</th>
                                        <th scope="col">المبلغ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($invoice->allocations as $alloc)
                                        @php
                                            $col = $alloc->collection;
                                            $colSt = $col?->status?->value;
                                            $colBadge = match ($colSt) {
                                                'confirmed' => 'badge-success',
                                                'draft' => 'badge-warning',
                                                'reversed' => 'badge-danger',
                                                default => 'badge-neutral',
                                            };
                                        @endphp
                                        <tr>
                                            <td>
                                                @if ($col)
                                                    <a href="{{ route('collections.show', $col) }}" class="quotes-id-link dir-ltr">#{{ $col->id }}</a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge {{ $colBadge }}">
                                                    {{ $col?->status?->labelAr() ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="dir-ltr inventory-qty">{{ money($alloc->amount) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if ($invoice->notes->isNotEmpty())
                    <h3 class="inv-activity-sub">الإشعارات</h3>
                    <div class="ds-list-panel spo-lines-panel">
                        <div class="ds-list-body">
                            <table class="admin-table admin-table--sheet fin-table">
                                <thead>
                                    <tr>
                                        <th scope="col">الإشعار</th>
                                        <th scope="col">النوع</th>
                                        <th scope="col">الحالة</th>
                                        <th scope="col">المبلغ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($invoice->notes as $note)
                                        @php
                                            $nType = $note->type?->value;
                                            $nSt = $note->status?->value;
                                            $typeLabel = $nType === 'credit' ? 'دائن' : ($nType === 'debit' ? 'مدين' : ($nType ?? '—'));
                                            $stBadge = match ($nSt) {
                                                'approved' => 'badge-success',
                                                'rejected' => 'badge-danger',
                                                'draft' => 'badge-neutral',
                                                default => 'badge-warning',
                                            };
                                        @endphp
                                        <tr>
                                            <td>
                                                <a href="{{ route('invoice-notes.show', $note) }}" class="quotes-id-link dir-ltr">#{{ $note->id }}</a>
                                            </td>
                                            <td>{{ $typeLabel }}</td>
                                            <td>
                                                <span class="badge {{ $stBadge }}">
                                                    {{ $note->status?->labelAr() ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="dir-ltr inventory-qty">{{ money($note->amount) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </section>
        @endif

        @if ($canResendOverdue || ($canCloseDeal && $invoice->deal))
            <section class="so-detail-section so-action-section" aria-labelledby="inv-act-heading">
                <div class="so-detail-section-head">
                    <h2 id="inv-act-heading" class="customers-form-section-title">إجراءات إضافية</h2>
                </div>
                <div class="quote-decide-actions">
                    @if ($canResendOverdue)
                        <form method="POST" action="{{ route('invoices.resend-overdue', $invoice) }}"
                              onsubmit="return confirm('إعادة إرسال تذكير التأخر؟');">
                            @csrf
                            <button type="submit" class="btn btn-glass">إعادة تذكير التأخر</button>
                        </form>
                    @endif
                    @if ($canCloseDeal && $invoice->deal)
                        <a href="{{ route('deals.show', $invoice->deal) }}" class="btn btn-glass-primary">عرض الصفقة وإغلاقها</a>
                    @endif
                </div>
            </section>
        @endif
    </div>
@endsection
