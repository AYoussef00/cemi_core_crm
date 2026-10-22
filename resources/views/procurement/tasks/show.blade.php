@extends('layouts.app')

@section('title', 'مهمة مشتريات #'.$task->id.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المشتريات · تفاصيل المهمة</span>
@endsection

@section('content')
    @php
        $stValue = $task->status instanceof \BackedEnum
            ? $task->status->value
            : (string) $task->status;
        $stBadge = match ($stValue) {
            'open' => 'badge-warning',
            'cost_recorded' => 'badge-success',
            'cancelled' => 'badge-danger',
            default => 'badge-neutral',
        };
        $request = $task->salesRequest;
        $product = $task->product;
        $line = $task->line;
        $qtyLabel = $line?->quantity !== null ? qty($line->quantity) : null;
        $reqStatus = $request?->status;
        $reqStValue = $reqStatus instanceof \BackedEnum ? $reqStatus->value : (string) ($reqStatus ?? '');
        $reqStBadge = match ($reqStValue) {
            'draft' => 'badge-neutral',
            'awaiting_procurement' => 'badge-warning',
            'ready_for_pricing' => 'badge-warning',
            'quotation_linked' => 'badge-success',
            'cancelled' => 'badge-danger',
            default => 'badge-neutral',
        };
        $linkedQuotation = $linkedQuotation ?? null;
        $canViewQuotation = $canViewQuotation ?? false;
    @endphp

    <div class="procurement-page procurement-show-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="proc-show-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">مهمة مشتريات · <span class="dir-ltr">#{{ $task->id }}</span></p>
                <h1 id="proc-show-heading" class="admin-title">{{ $product?->name_ar ?? 'مهمة توريد' }}</h1>
                <p class="procurement-show-status">
                    <span class="badge {{ $stBadge }}">{{ $task->status->labelAr() }}</span>
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('procurement.tasks.index') }}" class="btn btn-glass">قائمة المهام</a>
                @if ($request)
                    @can('view', $request)
                        <a href="{{ route('sales-requests.show', $request) }}" class="btn btn-glass">طلب #{{ $request->id }}</a>
                    @endcan
                @endif
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($canRecordCost)
            <section class="so-detail-section procurement-action-section" aria-labelledby="proc-cost-heading">
                <div class="so-detail-section-head">
                    <h2 id="proc-cost-heading" class="customers-form-section-title">تسجيل تكلفة المورد</h2>
                </div>
                <form method="POST" action="{{ route('procurement.tasks.cost', $task) }}"
                      class="auth-form admin-form procurement-cost-form" enctype="multipart/form-data" novalidate>
                    @csrf
                    <div class="procurement-cost-grid">
                        <div class="auth-field">
                            <label for="supplier_cost">التكلفة (ر.س) <span class="required-mark" title="مطلوب">*</span></label>
                            <input id="supplier_cost" type="text" inputmode="decimal" name="supplier_cost"
                                   value="{{ old('supplier_cost') }}" required class="dir-ltr"
                                   placeholder="0.00"
                                   aria-invalid="{{ $errors->has('supplier_cost') ? 'true' : 'false' }}">
                            @error('supplier_cost')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="auth-field">
                            <label for="supplier_name_ar">اسم المورد</label>
                            <input id="supplier_name_ar" type="text" name="supplier_name_ar"
                                   value="{{ old('supplier_name_ar') }}" maxlength="255">
                            @error('supplier_name_ar')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="auth-field procurement-cost-notes">
                            <label for="supplier_quote_pdf">عرض سعر المورد (PDF) <span class="required-mark" title="مطلوب">*</span></label>
                            <input id="supplier_quote_pdf" type="file" name="supplier_quote_pdf" accept="application/pdf,.pdf" required
                                   aria-invalid="{{ $errors->has('supplier_quote_pdf') ? 'true' : 'false' }}">
                            <p class="field-hint">إلزامي — PDF فقط.</p>
                            @error('supplier_quote_pdf')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="auth-field procurement-cost-notes">
                            <label for="notes_ar">ملاحظات</label>
                            <textarea id="notes_ar" name="notes_ar" rows="2" maxlength="1000">{{ old('notes_ar') }}</textarea>
                            @error('notes_ar')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="admin-form-actions procurement-cost-actions">
                        <button type="submit" class="btn btn-glass-primary">تسجيل التكلفة</button>
                        @if ($canCancel)
                            <button type="submit" form="proc-cancel-form" class="btn btn-glass inventory-reject-btn"
                                    onclick="return confirm('تأكيد إلغاء مهمة المشتريات؟');">
                                إلغاء المهمة
                            </button>
                        @endif
                    </div>
                </form>
                @if ($canCancel)
                    <form id="proc-cancel-form" method="POST" action="{{ route('procurement.tasks.cancel', $task) }}" hidden>
                        @csrf
                    </form>
                @endif
            </section>
        @elseif ($canCancel)
            <section class="so-detail-section procurement-action-section" aria-labelledby="proc-cancel-heading">
                <div class="so-detail-section-head">
                    <h2 id="proc-cancel-heading" class="customers-form-section-title">إجراء</h2>
                </div>
                <form method="POST" action="{{ route('procurement.tasks.cancel', $task) }}"
                      onsubmit="return confirm('تأكيد إلغاء مهمة المشتريات؟');">
                    @csrf
                    <button type="submit" class="btn btn-glass inventory-reject-btn">إلغاء المهمة</button>
                </form>
            </section>
        @endif

        <section class="so-detail-section" aria-labelledby="proc-summary-heading">
            <div class="so-detail-section-head">
                <h2 id="proc-summary-heading" class="customers-form-section-title">ملخص المهمة</h2>
            </div>
            <div class="so-detail-grid" role="list">
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">الحالة</p>
                    <p class="so-detail-value"><span class="badge {{ $stBadge }}">{{ $task->status->labelAr() }}</span></p>
                </article>
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">الكمية</p>
                    <p class="so-detail-value">
                        <span class="so-detail-ltr">{{ $qtyLabel ?? '—' }}</span>
                    </p>
                </article>
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">المنشأة</p>
                    <p class="so-detail-value">{{ $request?->customer?->name_ar ?? '—' }}</p>
                </article>
                @if ($canViewCosts)
                    <article class="so-detail-field" role="listitem">
                        <p class="so-detail-label">تكلفة المورد</p>
                        <p class="so-detail-value">
                            @if ($task->supplier_cost !== null)
                                <span class="so-detail-ltr">
                                    {{ money($task->supplier_cost) }}
                                    <span class="so-detail-unit">ر.س</span>
                                </span>
                            @else
                                —
                            @endif
                        </p>
                    </article>
                @endif
            </div>
        </section>

        <section class="so-detail-section" aria-labelledby="proc-item-heading">
            <div class="so-detail-section-head">
                <h2 id="proc-item-heading" class="customers-form-section-title">المنتج وطلب البيع</h2>
            </div>
            <div class="so-detail-grid" role="list">
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">المنتج</p>
                    <p class="so-detail-value">
                        {{ $product?->name_ar ?? '—' }}
                        @if ($product?->unit)
                            <span class="so-detail-unit">{{ $product->unit->name_ar }}</span>
                        @endif
                    </p>
                </article>
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">طلب البيع</p>
                    <p class="so-detail-value">
                        @if ($request)
                            <span class="proc-ref-stack">
                                @can('view', $request)
                                    <a href="{{ route('sales-requests.show', $request) }}" class="quotes-id-link dir-ltr">#{{ $request->id }}</a>
                                @else
                                    <span class="quotes-id-link is-static dir-ltr">#{{ $request->id }}</span>
                                @endcan
                                @if ($reqStatus)
                                    <span class="badge {{ $reqStBadge }}">{{ $reqStatus->labelAr() }}</span>
                                @endif
                            </span>
                        @else
                            —
                        @endif
                    </p>
                </article>
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">عرض السعر</p>
                    <p class="so-detail-value">
                        @if ($linkedQuotation)
                            <span class="proc-ref-stack">
                                @if ($canViewQuotation)
                                    <a href="{{ route('quotations.show', $linkedQuotation) }}" class="quotes-id-link dir-ltr">{{ $linkedQuotation->document_number }}</a>
                                @else
                                    <span class="quotes-id-link is-static dir-ltr">{{ $linkedQuotation->document_number }}</span>
                                @endif
                                @if ($linkedQuotation->status)
                                    <span class="quotes-meta-line">{{ $linkedQuotation->status->labelAr() }}</span>
                                @endif
                            </span>
                        @else
                            <span class="ds-cell-muted">لا يوجد عرض بعد</span>
                        @endif
                    </p>
                </article>
                @if ($task->notes_ar && ! $canRecordCost)
                    <article class="so-detail-field so-detail-field-wide" role="listitem">
                        <p class="so-detail-label">ملاحظات المهمة</p>
                        <p class="so-detail-value">{{ $task->notes_ar }}</p>
                    </article>
                @endif
            </div>
        </section>

        @if ($canViewCosts && ($task->supplier_cost !== null || $task->supplier_name_ar || $task->cost_recorded_at || $task->recordedBy))
            <section class="so-detail-section" aria-labelledby="proc-recorded-heading">
                <div class="so-detail-section-head">
                    <h2 id="proc-recorded-heading" class="customers-form-section-title">تسجيل التكلفة</h2>
                </div>
                <div class="so-detail-grid" role="list">
                    @if ($task->supplier_name_ar)
                        <article class="so-detail-field" role="listitem">
                            <p class="so-detail-label">المورد</p>
                            <p class="so-detail-value">{{ $task->supplier_name_ar }}</p>
                        </article>
                    @endif
                    @if ($task->supplier_cost !== null)
                        <article class="so-detail-field" role="listitem">
                            <p class="so-detail-label">التكلفة</p>
                            <p class="so-detail-value">
                                <span class="so-detail-ltr">
                                    {{ money($task->supplier_cost) }}
                                    <span class="so-detail-unit">ر.س</span>
                                </span>
                            </p>
                        </article>
                    @endif
                    @if ($task->recordedBy)
                        <article class="so-detail-field" role="listitem">
                            <p class="so-detail-label">سجّلها</p>
                            <p class="so-detail-value">{{ $task->recordedBy->name }}</p>
                        </article>
                    @endif
                    @if ($task->cost_recorded_at)
                        <article class="so-detail-field" role="listitem">
                            <p class="so-detail-label">تاريخ التسجيل</p>
                            <p class="so-detail-value">
                                <span class="so-detail-ltr">
                                    {{ $task->cost_recorded_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                </span>
                            </p>
                        </article>
                    @endif
                    @if ($task->notes_ar)
                        <article class="so-detail-field so-detail-field-wide" role="listitem">
                            <p class="so-detail-label">ملاحظات</p>
                            <p class="so-detail-value">{{ $task->notes_ar }}</p>
                        </article>
                    @endif
                    @if ($supplierQuote ?? null)
                        <article class="so-detail-field so-detail-field-wide" role="listitem">
                            <p class="so-detail-label">عرض سعر المورد</p>
                            <p class="so-detail-value">
                                <a href="{{ route('procurement.tasks.quote-pdf', [$task, $supplierQuote]) }}"
                                   class="so-doc-pdf"
                                   target="_blank"
                                   rel="noopener"
                                   title="فتح عرض سعر المورد"
                                   aria-label="فتح عرض سعر المورد PDF">
                                    <svg class="so-doc-pdf-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-6Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                        <path d="M14 2v6h6" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                        <path d="M8 13h8M8 16h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                    </svg>
                                    <span class="so-doc-pdf-ext">PDF</span>
                                </a>
                            </p>
                        </article>
                    @endif
                </div>
            </section>
        @endif
    </div>
@endsection
