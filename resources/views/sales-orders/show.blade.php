@extends('layouts.app')

@section('title', 'أمر بيع #'.$order->id.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · تفاصيل أمر البيع</span>
@endsection

@section('content')
    @php
        $next = $order->nextAction();
        $canDecide = $canApprove || $canReject;
        $canAct = $canSubmit || $canDecide || $canCancelApproved;
        $rep = $order->submittedBy ?? $order->customer?->assignedSalesRep ?? $order->createdBy;
        $po = $order->customerPurchaseOrder;
        $totalProfit = 0.0;
        $totalSell = 0.0;
        $totalCost = 0.0;
        $hasDocs = $po || $quotation;
        $pdfIcon = '<svg class="so-doc-pdf-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-6Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 2v6h6" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 13h8M8 16h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
    @endphp

    <div class="sales-orders-page so-show-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="so-show-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">أمر بيع · <span class="dir-ltr">#{{ $order->id }}</span></p>
                <h1 id="so-show-heading" class="admin-title">{{ $order->customer?->name_ar ?? 'أمر بيع' }}</h1>
                <p class="procurement-show-status">
                    <span class="badge {{ $next->badge }}">{{ $next->summaryAr() }}</span>
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('sales-orders.index') }}" class="btn btn-glass">القائمة</a>
                @if ($canUpdate)
                    <a href="{{ route('sales-orders.edit', $order) }}" class="btn btn-glass-primary">تعديل المسودة</a>
                @endif
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($awaitingSupplierSupply ?? false)
            <div class="app-alert app-alert-warning" role="status">
                أمر البيع بانتظار توريد المشتريات ثم استلام المستودع
                @if ($blockingSupplyPo ?? null)
                    — <a href="{{ route('supplier-pos.show', $blockingSupplyPo) }}" class="quotes-id-link dir-ltr">أمر مورد #{{ $blockingSupplyPo->id }}</a>
                @endif
                .
                بعد أول استلام شحنة يتفعّل أمر البيع تلقائيًا وتُحجز الكمية الواصلة للتوريد — بلا إرسال من المندوب أو اعتماد من المدير.
            </div>
        @endif

        @if ($canAct)
            <section class="so-detail-section so-action-section" aria-labelledby="so-act-heading">
                <div class="so-detail-section-head">
                    <h2 id="so-act-heading" class="customers-form-section-title">الإجراءات</h2>
                </div>

                @if ($canDecide && $canViewCredit)
                    <div class="so-credit-stub" role="note">
                        <p class="so-credit-stub-title">بوابة الائتمان</p>
                        <p class="so-credit-stub-body">
                            مديونية <span class="dir-ltr">{{ money($creditExposure) }} ر.س</span>
                            · حد
                            @if ($creditLimit !== null)
                                <span class="dir-ltr">{{ money($creditLimit) }} ر.س</span>
                            @else
                                غير محدد
                            @endif
                        </p>
                    </div>
                @endif

                <div class="quote-decide-actions">
                    @if ($canSubmit)
                        <form method="POST" action="{{ route('sales-orders.submit', $order) }}"
                              onsubmit="return confirm('إرسال أمر البيع للاعتماد؟');">
                            @csrf
                            <button type="submit" class="btn btn-glass-primary">إرسال للاعتماد</button>
                        </form>
                    @endif

                    @if ($canDecide)
                        <form method="POST" action="{{ route('sales-orders.decide', $order) }}"
                              class="auth-form quote-decide-form">
                            @csrf
                            <div class="auth-field">
                                <label for="decision_notes">ملاحظات القرار</label>
                                <input id="decision_notes" type="text" name="decision_notes"
                                       value="{{ old('decision_notes') }}" maxlength="1000">
                            </div>
                            <div class="quote-decide-btns">
                                @if ($canApprove)
                                    <button type="submit" name="decision" value="approve" class="btn btn-glass-primary"
                                            onclick="return confirm('اعتماد أمر البيع وحجز المخزون؟');">
                                        اعتماد وحجز المخزون
                                    </button>
                                @endif
                                @if ($canReject)
                                    <button type="submit" name="decision" value="reject" class="btn btn-glass inventory-reject-btn"
                                            onclick="return confirm('رفض أمر البيع؟ لن يُحجز المخزون.');">
                                        رفض
                                    </button>
                                @endif
                            </div>
                        </form>
                    @endif

                    @if ($canCancelApproved)
                        <form method="POST" action="{{ route('sales-orders.cancel-approved', $order) }}"
                              class="auth-form quote-decide-form"
                              onsubmit="return confirm('تأكيد إلغاء أمر البيع المعتمد والإفراج عن الحجز؟');">
                            @csrf
                            <div class="auth-field">
                                <label for="cancel_notes">سبب الإلغاء</label>
                                <input id="cancel_notes" type="text" name="decision_notes"
                                       value="{{ old('decision_notes') }}" maxlength="1000">
                            </div>
                            <button type="submit" class="btn btn-glass inventory-reject-btn">إلغاء المعتمد</button>
                        </form>
                    @endif
                </div>
            </section>
        @endif

        <section class="so-detail-section" aria-labelledby="so-overview-heading">
            <div class="so-detail-section-head">
                <h2 id="so-overview-heading" class="customers-form-section-title">نظرة عامة</h2>
            </div>
            <div class="so-overview-grid" role="list">
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">المندوب</p>
                    <p class="so-overview-value">{{ $rep?->name ?? '—' }}</p>
                </article>
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">تاريخ الإرسال</p>
                    <p class="so-overview-value">
                        <span class="so-overview-ltr">
                            {{ $order->submitted_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                        </span>
                    </p>
                </article>
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">البنود</p>
                    <p class="so-overview-value">
                        <span class="so-overview-ltr">{{ $order->lines->count() }}</span>
                    </p>
                </article>
                @if ($order->deal_id)
                    <article class="so-overview-cell" role="listitem">
                        <p class="so-overview-label">الصفقة</p>
                        <p class="so-overview-value">
                            <span class="so-overview-ltr">#{{ $order->deal_id }}</span>
                        </p>
                    </article>
                @endif
                @if ($canViewCredit)
                    <article class="so-overview-cell" role="listitem">
                        <p class="so-overview-label">حد الائتمان</p>
                        <p class="so-overview-value">
                            @if ($creditLimit !== null)
                                <span class="so-overview-ltr">{{ money($creditLimit) }}</span>
                                <span class="so-detail-unit">ر.س</span>
                            @else
                                غير محدد
                            @endif
                        </p>
                    </article>
                    <article class="so-overview-cell {{ (float) $creditExposure > 0 ? 'is-warn' : '' }}" role="listitem">
                        <p class="so-overview-label">المديونية</p>
                        <p class="so-overview-value">
                            <span class="so-overview-ltr">{{ money($creditExposure) }}</span>
                            <span class="so-detail-unit">ر.س</span>
                        </p>
                    </article>
                    <article class="so-overview-cell {{ $overdueInvoicesCount > 0 ? 'is-warn' : '' }}" role="listitem">
                        <p class="so-overview-label">فواتير متأخرة</p>
                        <p class="so-overview-value">
                            @if ($overdueInvoicesCount > 0)
                                <span class="so-overview-ltr">{{ number_format($overdueInvoicesCount) }}</span>
                            @else
                                لا يوجد
                            @endif
                        </p>
                    </article>
                @endif
                @if ($order->notes_ar)
                    <article class="so-overview-cell so-overview-cell-wide" role="listitem">
                        <p class="so-overview-label">ملاحظات داخلية</p>
                        <p class="so-overview-value">{{ $order->notes_ar }}</p>
                    </article>
                @endif
                @if ($order->decision_notes)
                    <article class="so-overview-cell so-overview-cell-wide" role="listitem">
                        <p class="so-overview-label">ملاحظات القرار</p>
                        <p class="so-overview-value">{{ $order->decision_notes }}</p>
                    </article>
                @endif
            </div>
        </section>

        @if ($hasDocs)
            <section class="so-detail-section" aria-labelledby="so-docs-heading">
                <div class="so-detail-section-head">
                    <h2 id="so-docs-heading" class="customers-form-section-title">المستندات</h2>
                </div>
                <ul class="so-docs-stack" role="list">
                    <li class="so-doc-row">
                        <div class="so-doc-copy">
                            <p class="so-detail-label">أمر شراء العميل</p>
                            <p class="so-detail-value">
                                @if ($po)
                                    <a href="{{ route('customer-pos.show', $po) }}" class="quotes-sr-chip dir-ltr">{{ $po->document_number }}</a>
                                    @if ($po->reference_ar)
                                        <span class="quotes-meta-line">{{ $po->reference_ar }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </p>
                            @if (! $canViewPoPdf && $po)
                                <p class="so-doc-missing">لا يوجد PDF مرفق</p>
                            @endif
                        </div>
                        @if ($canViewPoPdf)
                            <a href="{{ route('customer-pos.pdf', $po) }}"
                               class="so-doc-pdf"
                               target="_blank"
                               rel="noopener"
                               title="فتح PDF أمر الشراء"
                               aria-label="فتح PDF أمر شراء العميل {{ $po->document_number }}">
                                {!! $pdfIcon !!}
                                <span class="so-doc-pdf-ext">PDF</span>
                            </a>
                        @endif
                    </li>
                    <li class="so-doc-row">
                        <div class="so-doc-copy">
                            <p class="so-detail-label">عرض السعر</p>
                            <p class="so-detail-value">
                                @if ($quotation)
                                    <a href="{{ route('quotations.show', $quotation) }}" class="quotes-sr-chip dir-ltr">{{ $quotation->document_number }}</a>
                                    @if ($quotation->status)
                                        <span class="quotes-meta-line">{{ $quotation->status->labelAr() }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </p>
                            @if (! $canPrintQuotation && $quotation)
                                <p class="so-doc-missing">PDF بعد اعتماد العرض</p>
                            @endif
                        </div>
                        @if ($canPrintQuotation)
                            <a href="{{ route('quotations.print', $quotation) }}"
                               class="so-doc-pdf"
                               target="_blank"
                               rel="noopener"
                               title="فتح PDF عرض السعر"
                               aria-label="فتح PDF عرض السعر {{ $quotation->document_number }}">
                                {!! $pdfIcon !!}
                                <span class="so-doc-pdf-ext">PDF</span>
                            </a>
                        @endif
                    </li>
                </ul>
            </section>
        @endif

        <section class="so-detail-section" aria-labelledby="so-lines-heading">
            <div class="so-detail-section-head">
                <h2 id="so-lines-heading" class="customers-form-section-title">البنود</h2>
                @unless ($canViewPrices)
                    <p class="customers-form-section-lead">كميات فقط — بلا أسعار على هذه الشاشة لدور المستودع</p>
                @endunless
            </div>
            <div class="ds-list-panel so-lines-panel">
                <div class="ds-list-body">
                    <table class="admin-table admin-table--sheet so-table">
                        <thead>
                            <tr>
                                <th scope="col">المنتج</th>
                                <th scope="col">الكمية</th>
                                @if ($canViewPrices)
                                    <th scope="col" class="quotes-col-amount">التكلفة</th>
                                    <th scope="col">المورد</th>
                                    <th scope="col" class="quotes-col-amount">سعر البيع</th>
                                    <th scope="col" class="quotes-col-amount">ربح البند</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($order->lines as $line)
                                @php
                                    $quoteLine = $line->customerPurchaseOrderLine?->quotationLine;
                                    $supplierCost = $quoteLine?->supplier_cost
                                        ?? $line->product?->purchase_cost;
                                    $supplierName = $quoteLine?->salesRequestLine?->procurementTask?->supplier_name_ar;
                                    $selling = $quoteLine?->selling_price_vat_inclusive;
                                    $unitProfit = $quoteLine?->margin_amount;
                                    $qty = (float) $line->quantity;
                                    $lineProfit = $unitProfit !== null ? (float) $unitProfit * $qty : null;
                                    if ($canViewPrices && $supplierCost !== null) {
                                        $totalCost += (float) $supplierCost * $qty;
                                    }
                                    if ($canViewPrices && $selling !== null) {
                                        $totalSell += (float) $selling * $qty;
                                    }
                                    if ($canViewPrices && $lineProfit !== null) {
                                        $totalProfit += $lineProfit;
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        <div class="quotes-customer-block">
                                            <span class="ds-cell-primary">{{ $line->product?->name_ar ?? '—' }}</span>
                                            @if ($canViewPrices && $unitProfit !== null)
                                                <span class="quotes-meta-line dir-ltr">ربح الوحدة {{ money($unitProfit) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="dir-ltr inventory-qty">{{ qty($line->quantity) }}</td>
                                    @if ($canViewPrices)
                                        <td class="quotes-col-amount">
                                            <span class="dir-ltr inventory-qty">
                                                {{ $supplierCost !== null ? money($supplierCost) : '—' }}
                                            </span>
                                        </td>
                                        <td>{{ $supplierName ?: '—' }}</td>
                                        <td class="quotes-col-amount">
                                            @if ($selling !== null)
                                                <span class="quotes-amount dir-ltr quote-selling-cell">
                                                    {{ money($selling) }}
                                                    <span class="quotes-amount-unit">ر.س</span>
                                                </span>
                                            @else
                                                <span class="ds-cell-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="quotes-col-amount">
                                            <span class="dir-ltr inventory-qty">
                                                {{ $lineProfit !== null ? money($lineProfit) : '—' }}
                                            </span>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canViewPrices ? 6 : 2 }}" class="ds-cell-muted">لا توجد بنود</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($canViewPrices && $order->lines->isNotEmpty())
                            <tfoot>
                                <tr>
                                    <th scope="row" colspan="2">الإجمالي</th>
                                    <td class="quotes-col-amount">
                                        <span class="dir-ltr inventory-qty">{{ money($totalCost) }}</span>
                                    </td>
                                    <td>—</td>
                                    <td class="quotes-col-amount">
                                        <span class="quotes-amount dir-ltr">
                                            {{ money($totalSell) }}
                                            <span class="quotes-amount-unit">ر.س</span>
                                        </span>
                                    </td>
                                    <td class="quotes-col-amount">
                                        <span class="quotes-amount dir-ltr quote-selling-cell">
                                            {{ money($totalProfit) }}
                                            <span class="quotes-amount-unit">ر.س</span>
                                        </span>
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </section>
    </div>
@endsection
