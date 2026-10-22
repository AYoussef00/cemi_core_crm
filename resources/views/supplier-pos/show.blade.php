@extends('layouts.app')

@section('title', 'أمر شراء مورد #'.$spo->id.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">{{ ($warehouseFacing ?? false) ? 'المستودع · طلبية واردة' : 'المشتريات · أمر مورد' }}</span>
@endsection

@section('content')
    @php
        $warehouseFacing = $warehouseFacing ?? false;
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
        $showCosts = $canViewCosts && $spo->lines->contains(fn ($l) => $l->unit_cost !== null);
        $totalQty = $spo->totalOrderedQuantity();
        $receivedQty = $spo->totalReceivedQuantity();
        $remainingQty = $spo->totalRemainingQuantity();
        $progressPct = $totalQty > 1e-9
            ? min(100, round(($receivedQty / $totalQty) * 100))
            : 0;
        $awaitingIssue = $stValue === 'open' && $warehouseFacing && ! $canMarkOrdered;
        $customerName = $spo->salesOrder?->customer?->name_ar
            ?? $spo->quotation?->customer?->name_ar;
        $headingTitle = $spo->supplier_name_ar ?: ($customerName ?: 'أمر توريد');
        $pdfIcon = '<svg class="so-doc-pdf-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-6Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 2v6h6" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 13h8M8 16h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
    @endphp

    <div class="procurement-page procurement-show-page spo-show-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="spo-show-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">
                    {{ $warehouseFacing ? 'طلبية واردة' : 'أمر شراء مورد' }}
                    · <span class="dir-ltr">#{{ $spo->id }}</span>
                </p>
                <h1 id="spo-show-heading" class="admin-title">{{ $headingTitle }}</h1>
                <div class="procurement-show-status spo-show-status-row">
                    <span class="badge {{ $stBadge }}">{{ $spo->status->labelAr() }}</span>
                    @if ($customerName && $spo->supplier_name_ar)
                        <span class="spo-show-submeta" aria-label="العميل">{{ $customerName }}</span>
                    @endif
                </div>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('supplier-pos.index', $warehouseFacing ? ['inbox' => 'inbound'] : []) }}" class="btn btn-glass">
                    {{ $warehouseFacing ? 'طلبيات واردة' : 'القائمة' }}
                </a>
                @if ($canPrint)
                    <a href="{{ route('supplier-pos.pdf', $spo) }}" class="btn btn-glass" target="_blank" rel="noopener">PDF عربي</a>
                @endif
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($awaitingIssue)
            <div class="app-alert app-alert-warning" role="status">
                الطلبية لم تُصدَر للمورد بعد. بعد أن تؤكد المشتريات الإصدار سيظهر هنا إجراء استلام الشحنة.
            </div>
        @endif

        <section class="so-detail-section spo-progress-section" aria-label="تقدم الاستلام">
            <div class="spo-progress-head">
                <div>
                    <p class="spo-progress-kicker">تقدم الاستلام</p>
                    <p class="spo-progress-pct dir-ltr">{{ $progressPct }}%</p>
                </div>
                <p class="spo-progress-summary">
                    مستلم
                    <span class="dir-ltr">{{ qty($receivedQty) }}</span>
                    من
                    <span class="dir-ltr">{{ qty($totalQty) }}</span>
                    · متبقي
                    <span class="spo-progress-remain dir-ltr">{{ qty($remainingQty) }}</span>
                </p>
            </div>
            <div class="spo-progress-track" role="progressbar"
                 aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progressPct }}"
                 aria-label="نسبة الاستلام">
                <span class="spo-progress-fill" style="width: {{ $progressPct }}%"></span>
            </div>
            <div class="spo-qty-metrics" role="list">
                <article class="spo-qty-metric" role="listitem">
                    <p class="spo-qty-label">الكمية الكلية</p>
                    <p class="spo-qty-value dir-ltr">{{ qty($totalQty) }}</p>
                </article>
                <article class="spo-qty-metric" role="listitem">
                    <p class="spo-qty-label">المستلم</p>
                    <p class="spo-qty-value dir-ltr">{{ qty($receivedQty) }}</p>
                </article>
                <article class="spo-qty-metric spo-qty-metric-remain" role="listitem">
                    <p class="spo-qty-label">المتبقي</p>
                    <p class="spo-qty-value dir-ltr">{{ qty($remainingQty) }}</p>
                </article>
            </div>
        </section>

        @if ($canMarkOrdered)
            <section class="so-detail-section procurement-action-section" aria-labelledby="spo-order-heading">
                <div class="so-detail-section-head">
                    <h2 id="spo-order-heading" class="customers-form-section-title">إصدار أمر الشراء للمورد</h2>
                    <p class="customers-form-section-lead">يُنشأ PDF عربي ويُرفق تلقائيًا. يمكن إرفاق نسخة موقّعة إضافية اختياريًا.</p>
                </div>
                <form method="POST" action="{{ route('supplier-pos.mark-ordered', $spo) }}" class="auth-form admin-form" enctype="multipart/form-data" novalidate>
                    @csrf
                    <div class="procurement-cost-grid">
                        <div class="auth-field">
                            <label for="expected_at">موعد الوصول المتوقع <span class="required-mark" title="مطلوب">*</span></label>
                            <input id="expected_at" type="datetime-local" name="expected_at"
                                   value="{{ old('expected_at', $spo->expected_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i')) }}"
                                   required class="dir-ltr"
                                   aria-invalid="{{ $errors->has('expected_at') ? 'true' : 'false' }}">
                            @error('expected_at')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="auth-field">
                            <label for="supplier_name_ar">اسم المورد</label>
                            <input id="supplier_name_ar" type="text" name="supplier_name_ar"
                                   value="{{ old('supplier_name_ar', $spo->supplier_name_ar) }}" maxlength="255">
                            @error('supplier_name_ar')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="auth-field">
                            <label for="po_document">نسخة إضافية من أمر الشراء (PDF)</label>
                            <input id="po_document" type="file" name="po_document" accept="application/pdf,.pdf">
                            <p class="field-hint">اختياري — إن وُجدت نسخة موقّعة من المورد.</p>
                            @error('po_document')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="auth-field procurement-cost-notes">
                            <label for="notes_ar">ملاحظات</label>
                            <textarea id="notes_ar" name="notes_ar" rows="2" maxlength="2000">{{ old('notes_ar', $spo->notes_ar) }}</textarea>
                            @error('notes_ar')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="admin-form-actions">
                        <button type="submit" class="btn btn-glass-primary">تأكيد الإصدار وإشعار المستودع</button>
                    </div>
                </form>
            </section>
        @endif

        @if ($canReceive)
            <section class="so-detail-section procurement-action-section spo-receive-panel" aria-labelledby="spo-recv-heading">
                <div class="so-detail-section-head">
                    <h2 id="spo-recv-heading" class="customers-form-section-title">استلام شحنة في المستودع</h2>
                    <p class="customers-form-section-lead">
                        أدخل كمية هذه الشحنة فقط. المتبقي يتحدّث تلقائيًا ويمكن الاستلام على دفعات حتى اكتمال
                        <span class="dir-ltr">{{ qty($totalQty) }}</span>.
                    </p>
                </div>
                <form method="POST" action="{{ route('supplier-pos.receive', $spo) }}"
                      class="auth-form admin-form" enctype="multipart/form-data" novalidate>
                    @csrf
                    <div class="spo-receive-meta">
                        <div class="auth-field">
                            <label for="delivery_note_pdf">
                                محضر/إذن التسليم الموقع مع المورد
                                <span class="required-mark" title="مطلوب">*</span>
                            </label>
                            <input id="delivery_note_pdf" type="file" name="delivery_note_pdf"
                                   accept="application/pdf,.pdf" required
                                   aria-invalid="{{ $errors->has('delivery_note_pdf') ? 'true' : 'false' }}">
                            @error('delivery_note_pdf')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="ds-list-panel spo-lines-panel">
                        <div class="ds-list-body">
                            <table class="admin-table admin-table--sheet">
                                <thead>
                                    <tr>
                                        <th scope="col">المنتج</th>
                                        <th scope="col">الكلية</th>
                                        <th scope="col">مستلم سابقًا</th>
                                        <th scope="col">المتبقي</th>
                                        <th scope="col">كمية هذه الشحنة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($spo->lines as $i => $line)
                                        @php $remaining = $line->remainingQuantity(); @endphp
                                        <tr>
                                            <td>
                                                <span class="ds-cell-primary">{{ $line->product?->name_ar ?? '—' }}</span>
                                                <input type="hidden" name="receipts[{{ $i }}][line_id]" value="{{ $line->id }}">
                                            </td>
                                            <td class="dir-ltr inventory-qty">{{ qty($line->quantity) }}</td>
                                            <td class="dir-ltr inventory-qty">{{ qty($line->received_quantity) }}</td>
                                            <td class="dir-ltr inventory-qty">
                                                <span @class(['spo-remaining-emph' => $remaining > 1e-9])>{{ qty($remaining) }}</span>
                                            </td>
                                            <td>
                                                @if ($remaining > 1e-9)
                                                    <input type="text" inputmode="decimal" name="receipts[{{ $i }}][quantity]"
                                                           value="{{ old('receipts.'.$i.'.quantity') }}"
                                                           class="dir-ltr quote-margin-input"
                                                           placeholder="0"
                                                           aria-label="كمية شحنة {{ $line->product?->name_ar }}">
                                                @else
                                                    <span class="badge badge-success">مكتمل</span>
                                                    <input type="hidden" name="receipts[{{ $i }}][quantity]" value="0">
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="admin-form-actions spo-receive-actions">
                        <button type="submit" class="btn btn-glass-primary"
                                onclick="return confirm('تأكيد استلام هذه الشحنة وزيادة المخزون؟');">
                            تأكيد استلام الشحنة
                        </button>
                    </div>
                </form>
            </section>
        @endif

        <section class="so-detail-section" aria-labelledby="spo-overview-heading">
            <div class="so-detail-section-head">
                <h2 id="spo-overview-heading" class="customers-form-section-title">نظرة عامة</h2>
            </div>
            <div class="so-overview-grid" role="list">
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">الحالة</p>
                    <p class="so-overview-value">
                        <span class="badge {{ $stBadge }}">{{ $spo->status->labelAr() }}</span>
                    </p>
                </article>
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">المورد</p>
                    <p class="so-overview-value">{{ $spo->supplier_name_ar ?: '—' }}</p>
                </article>
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">الوصول المتوقع</p>
                    <p class="so-overview-value">
                        <span class="so-overview-ltr">
                            {{ $spo->expected_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                        </span>
                    </p>
                </article>
                @if ($customerName)
                    <article class="so-overview-cell" role="listitem">
                        <p class="so-overview-label">العميل</p>
                        <p class="so-overview-value">{{ $customerName }}</p>
                    </article>
                @endif
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">عرض السعر</p>
                    <p class="so-overview-value">
                        @if ($spo->quotation_id)
                            @can('view', $spo->quotation)
                                <a href="{{ route('quotations.show', $spo->quotation_id) }}" class="quotes-id-link dir-ltr">{{ doc_no('QA', $spo->quotation_id) }}</a>
                            @else
                                <span class="quotes-id-link is-static dir-ltr">{{ doc_no('QA', $spo->quotation_id) }}</span>
                            @endcan
                        @else
                            —
                        @endif
                    </p>
                </article>
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">أمر البيع</p>
                    <p class="so-overview-value">
                        @if ($spo->sales_order_id)
                            @can('view', $spo->salesOrder)
                                <a href="{{ route('sales-orders.show', $spo->sales_order_id) }}" class="quotes-id-link dir-ltr">#{{ $spo->sales_order_id }}</a>
                            @else
                                <span class="quotes-id-link is-static dir-ltr">#{{ $spo->sales_order_id }}</span>
                            @endcan
                        @else
                            —
                        @endif
                    </p>
                </article>
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">أنشئ بواسطة</p>
                    <p class="so-overview-value">{{ $spo->createdBy?->name ?? '—' }}</p>
                </article>
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">عدد البنود</p>
                    <p class="so-overview-value">
                        <span class="so-overview-ltr">{{ $spo->lines->count() }}</span>
                    </p>
                </article>
                @if ($spo->notes_ar)
                    <article class="so-overview-cell so-overview-cell-wide" role="listitem">
                        <p class="so-overview-label">ملاحظات</p>
                        <p class="so-overview-value">{{ $spo->notes_ar }}</p>
                    </article>
                @endif
            </div>
        </section>

        @if ($spo->attachments->isNotEmpty() || $canPrint)
            <section class="so-detail-section" aria-labelledby="spo-docs-heading">
                <div class="so-detail-section-head">
                    <h2 id="spo-docs-heading" class="customers-form-section-title">المستندات</h2>
                </div>
                <ul class="so-docs-stack" role="list">
                    @if ($canPrint)
                        <li class="so-doc-row">
                            <div class="so-doc-copy">
                                <p class="so-detail-label">أمر الشراء الرسمي</p>
                                <p class="so-detail-value">PDF عربي للنظام</p>
                            </div>
                            <a href="{{ route('supplier-pos.pdf', $spo) }}"
                               class="so-doc-pdf"
                               target="_blank"
                               rel="noopener"
                               title="فتح PDF أمر الشراء"
                               aria-label="فتح PDF أمر شراء المورد #{{ $spo->id }}">
                                {!! $pdfIcon !!}
                                <span class="so-doc-pdf-ext">PDF</span>
                            </a>
                        </li>
                    @endif
                    @foreach ($spo->attachments as $att)
                        <li class="so-doc-row">
                            <div class="so-doc-copy">
                                <p class="so-detail-label">مرفق</p>
                                <p class="so-detail-value">{{ $att->original_name }}</p>
                            </div>
                            <a href="{{ route('supplier-pos.attachment', [$spo, $att]) }}"
                               class="so-doc-pdf"
                               target="_blank"
                               rel="noopener"
                               title="فتح {{ $att->original_name }}"
                               aria-label="فتح المرفق {{ $att->original_name }}">
                                {!! $pdfIcon !!}
                                <span class="so-doc-pdf-ext">PDF</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="so-detail-section" aria-labelledby="spo-lines-heading">
            <div class="so-detail-section-head">
                <h2 id="spo-lines-heading" class="customers-form-section-title">بنود أمر الشراء</h2>
                @unless ($showCosts)
                    <p class="customers-form-section-lead">كميات الاستلام — بلا تكاليف على هذه الشاشة</p>
                @endunless
            </div>
            <div class="ds-list-panel spo-lines-panel">
                <div class="ds-list-body">
                    <table class="admin-table admin-table--sheet">
                        <thead>
                            <tr>
                                <th scope="col">المنتج</th>
                                <th scope="col">الوحدة</th>
                                <th scope="col">الكمية</th>
                                <th scope="col">المستلم</th>
                                <th scope="col">المتبقي</th>
                                @if ($showCosts)
                                    <th scope="col" class="quotes-col-amount">تكلفة الوحدة</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($spo->lines as $line)
                                @php
                                    $lineRemain = $line->remainingQuantity();
                                    $lineDone = $lineRemain <= 1e-9;
                                @endphp
                                <tr @class(['spo-line-complete' => $lineDone])>
                                    <td>
                                        <span class="ds-cell-primary">{{ $line->product?->name_ar ?? '—' }}</span>
                                    </td>
                                    <td>{{ $line->product?->unit?->name_ar ?? '—' }}</td>
                                    <td class="dir-ltr inventory-qty">{{ qty($line->quantity) }}</td>
                                    <td class="dir-ltr inventory-qty">{{ qty($line->received_quantity) }}</td>
                                    <td class="dir-ltr inventory-qty">
                                        @if ($lineDone)
                                            <span class="badge badge-success">مكتمل</span>
                                        @else
                                            <span class="spo-remaining-emph">{{ qty($lineRemain) }}</span>
                                        @endif
                                    </td>
                                    @if ($showCosts)
                                        <td class="quotes-col-amount">
                                            @if ($line->unit_cost !== null)
                                                <span class="quotes-amount dir-ltr">
                                                    {{ money($line->unit_cost) }}
                                                    <span class="quotes-amount-unit">ر.س</span>
                                                </span>
                                            @else
                                                <span class="ds-cell-muted">—</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @if ($spo->goodsReceipts->isNotEmpty())
            <section class="so-detail-section" aria-labelledby="spo-shipments-heading">
                <div class="so-detail-section-head">
                    <h2 id="spo-shipments-heading" class="customers-form-section-title">سجل الشحنات</h2>
                    <p class="customers-form-section-lead">أوامر الاستلام المسجّلة على هذه الطلبية</p>
                </div>
                <div class="ds-list-panel spo-lines-panel">
                    <div class="ds-list-body">
                        <table class="admin-table admin-table--sheet">
                            <thead>
                                <tr>
                                    <th scope="col">أمر الاستلام</th>
                                    <th scope="col">التاريخ</th>
                                    <th scope="col">المرجع</th>
                                    <th scope="col">المستلم</th>
                                    <th scope="col" class="quotes-col-actions"><span class="visually-hidden">إجراء</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($spo->goodsReceipts as $gr)
                                    <tr>
                                        <td>
                                            <a href="{{ route('supplier-goods-receipts.show', $gr) }}" class="quotes-id-link dir-ltr">#{{ $gr->id }}</a>
                                            <span class="quotes-meta-line dir-ltr">{{ $gr->lines->count() }} بنود</span>
                                        </td>
                                        <td>
                                            <span class="so-overview-ltr">
                                                {{ $gr->received_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                                            </span>
                                        </td>
                                        <td>{{ $gr->shipment_reference_ar ?: '—' }}</td>
                                        <td>{{ $gr->receivedBy?->name ?? '—' }}</td>
                                        <td class="ds-cell-actions quotes-col-actions">
                                            <a href="{{ route('supplier-goods-receipts.show', $gr) }}" class="btn btn-glass btn-sm">عرض</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif
    </div>
@endsection
