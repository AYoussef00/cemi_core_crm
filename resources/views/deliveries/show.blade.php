@extends('layouts.app')

@section('title', 'إذن تسليم '.$delivery->document_number.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المستودع · إذن تسليم</span>
@endsection

@section('content')
    @php
        $st = $delivery->status instanceof \BackedEnum
            ? $delivery->status->value
            : (string) $delivery->status;
        $badge = match ($st) {
            'confirmed' => 'badge-success',
            'pending_pod' => 'badge-warning',
            'cancelled' => 'badge-danger',
            default => 'badge-neutral',
        };
        $hasPod = $delivery->attachments->isNotEmpty();
        $isConfirmed = $st === 'confirmed';
        $canAct = $canPendingPod || $canConfirm;
        $pdfIcon = '<svg class="so-doc-pdf-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-6Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 2v6h6" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 13h8M8 16h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
    @endphp

    <div class="deliveries-page del-show-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="del-show-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">إذن تسليم · <span class="dir-ltr">{{ $delivery->document_number }}</span></p>
                <h1 id="del-show-heading" class="admin-title">{{ $delivery->customer?->name_ar ?? 'تسليم' }}</h1>
                <p class="procurement-show-status">
                    <span class="badge {{ $badge }}">{{ $delivery->status->labelAr() }}</span>
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('deliveries.index') }}" class="btn btn-glass">القائمة</a>
                <a href="{{ route('deliveries.print', $delivery) }}" class="btn btn-glass" target="_blank" rel="noopener">طباعة</a>
                @if ($canUpdate)
                    <a href="{{ route('deliveries.edit', $delivery) }}" class="btn btn-glass-primary">تعديل</a>
                @endif
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($canAct)
            <section class="so-detail-section so-action-section" aria-labelledby="del-act-heading">
                <div class="so-detail-section-head">
                    <h2 id="del-act-heading" class="customers-form-section-title">الإجراءات</h2>
                </div>
                @if ($canConfirm && ! $hasPod)
                    <div class="app-alert app-alert-warning" role="status">ارفع إثبات التسليم قبل التأكيد.</div>
                @endif
                <div class="quote-decide-actions">
                    @if ($canPendingPod)
                        <form method="POST" action="{{ route('deliveries.pending-pod', $delivery) }}">
                            @csrf
                            <button type="submit" class="btn btn-glass">بانتظار الإثبات</button>
                        </form>
                    @endif
                    @if ($canConfirm)
                        <form method="POST" action="{{ route('deliveries.confirm', $delivery) }}"
                              onsubmit="return confirm('تأكيد التسليم وخصم المخزون؟');">
                            @csrf
                            <button type="submit" class="btn btn-glass-primary" @disabled(! $hasPod)>
                                تأكيد التسليم
                            </button>
                        </form>
                    @endif
                </div>
            </section>
        @endif

        <section class="so-detail-section" aria-labelledby="del-overview-heading">
            <div class="so-detail-section-head">
                <h2 id="del-overview-heading" class="customers-form-section-title">نظرة عامة</h2>
            </div>
            <div class="so-overview-grid" role="list">
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">الحالة</p>
                    <p class="so-overview-value"><span class="badge {{ $badge }}">{{ $delivery->status->labelAr() }}</span></p>
                </article>
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">أمر البيع</p>
                    <p class="so-overview-value">
                        <a href="{{ route('sales-orders.show', $delivery->sales_order_id) }}" class="quotes-id-link dir-ltr">
                            #{{ $delivery->sales_order_id }}
                        </a>
                    </p>
                </article>
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">إثبات التسليم</p>
                    <p class="so-overview-value">
                        @if ($hasPod)
                            <span class="badge badge-success">مرفوع</span>
                        @else
                            <span class="badge badge-warning">مطلوب</span>
                        @endif
                    </p>
                </article>
                <article class="so-overview-cell" role="listitem">
                    <p class="so-overview-label">التأكيد</p>
                    <p class="so-overview-value">
                        <span class="so-overview-ltr">
                            {{ $delivery->confirmed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                        </span>
                    </p>
                </article>
                @if ($delivery->notes_ar)
                    <article class="so-overview-cell so-overview-cell-wide" role="listitem">
                        <p class="so-overview-label">ملاحظات</p>
                        <p class="so-overview-value">{{ $delivery->notes_ar }}</p>
                    </article>
                @endif
                @if ($delivery->cancel_notes_ar)
                    <article class="so-overview-cell so-overview-cell-wide" role="listitem">
                        <p class="so-overview-label">سبب الإلغاء</p>
                        <p class="so-overview-value">{{ $delivery->cancel_notes_ar }}</p>
                    </article>
                @endif
            </div>
        </section>

        <section class="so-detail-section" aria-labelledby="del-lines-heading">
            <div class="so-detail-section-head">
                <h2 id="del-lines-heading" class="customers-form-section-title">البنود</h2>
                <p class="customers-form-section-lead">كميات فقط — بلا أسعار</p>
            </div>
            <div class="ds-list-panel spo-lines-panel">
                <div class="ds-list-body">
                    <table class="admin-table admin-table--sheet">
                        <thead>
                            <tr>
                                <th scope="col">المنتج</th>
                                <th scope="col">الكمية</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($delivery->lines as $line)
                                <tr>
                                    <td><span class="ds-cell-primary">{{ $line->product?->name_ar ?? '—' }}</span></td>
                                    <td class="dir-ltr inventory-qty">{{ qty($line->quantity) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @if ($delivery->status->isOpen() || $hasPod)
            <section class="so-detail-section" aria-labelledby="pod-heading">
                <div class="so-detail-section-head">
                    <h2 id="pod-heading" class="customers-form-section-title">إثبات التسليم</h2>
                </div>

                @if ($hasPod)
                    <ul class="so-docs-stack" role="list">
                        @foreach ($delivery->attachments as $att)
                            <li class="so-doc-row">
                                <div class="so-doc-copy">
                                    <p class="so-detail-label">مرفق</p>
                                    <p class="so-detail-value">{{ $att->original_name }}</p>
                                </div>
                                <div class="del-pod-row-actions">
                                    <a href="{{ route('deliveries.pod.download', [$delivery, $att]) }}"
                                       class="so-doc-pdf"
                                       target="_blank"
                                       rel="noopener"
                                       title="فتح {{ $att->original_name }}"
                                       aria-label="فتح {{ $att->original_name }}">
                                        {!! $pdfIcon !!}
                                        <span class="so-doc-pdf-ext">ملف</span>
                                    </a>
                                    @if ($canUploadPod)
                                        <form method="POST" action="{{ route('deliveries.pod.destroy', [$delivery, $att]) }}"
                                              onsubmit="return confirm('حذف المرفق؟');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-glass btn-sm inventory-reject-btn">حذف</button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @elseif (! $isConfirmed)
                    <p class="ds-cell-muted del-pod-hint">لا يوجد إثبات بعد.</p>
                @endif

                @if ($canUploadPod)
                    <form method="POST" action="{{ route('deliveries.pod.store', $delivery) }}"
                          enctype="multipart/form-data" class="auth-form admin-form del-pod-upload">
                        @csrf
                        <div class="auth-field">
                            <label for="pod-file">رفع إثبات <span class="required-mark" title="مطلوب">*</span></label>
                            <input id="pod-file" type="file" name="file" required
                                   accept=".{{ implode(',.', $podLimits['extensions']) }}">
                            @error('file')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-glass-primary">رفع</button>
                    </form>
                @endif
            </section>
        @endif
    </div>
@endsection
