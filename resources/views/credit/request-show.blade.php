@extends('layouts.app')

@section('title', 'طلب حد ائتمان — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المالية · طلب ائتمان</span>
@endsection

@section('content')
    @php
        $statusValue = $creditRequest->status instanceof \BackedEnum
            ? $creditRequest->status->value
            : (string) $creditRequest->status;
        $statusBadge = match ($statusValue) {
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            'pending_final' => 'badge-process',
            default => 'badge-warning',
        };
    @endphp

    <div class="credit-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="credit-request-heading">
            <div class="ds-page-bar-copy">
                <h1 id="credit-request-heading" class="admin-title">طلب حد ائتمان</h1>
                <p class="admin-lead">
                    {{ $creditRequest->customer?->name_ar ?? '—' }}
                    <span class="badge {{ $statusBadge }}">{{ $creditRequest->statusLabelAr() }}</span>
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('credit-requests.index') }}" class="btn btn-glass">القائمة</a>
                @if ($creditRequest->customer)
                    <a href="{{ route('customers.credit.show', $creditRequest->customer) }}" class="btn btn-glass">الملف الائتماني</a>
                @endif
            </div>
        </header>

        <p class="credit-trust-line" role="note">
            القيم المطلوبة أعلاه ليست فعّالة حتى الاعتماد النهائي. الرفض لا يغيّر القيم المعتمدة على العميل.
        </p>

        <div class="credit-compare" aria-label="مطلوب مقابل معتمد">
            <section class="credit-panel credit-panel-requested" aria-labelledby="credit-requested-heading">
                <div class="credit-panel-head">
                    <p class="credit-panel-kicker credit-panel-kicker-req">مطلوب · غير فعّال</p>
                    <h2 id="credit-requested-heading" class="credit-panel-title">قيم الطلب</h2>
                </div>
                <dl class="credit-metrics">
                    <div class="credit-metric">
                        <dt>حد الائتمان المطلوب</dt>
                        <dd class="dir-ltr">{{ money($creditRequest->requested_credit_limit) }}</dd>
                    </div>
                    <div class="credit-metric">
                        <dt>شروط الدفع المطلوبة</dt>
                        <dd>{{ $creditRequest->requested_payment_term ?: '—' }}</dd>
                    </div>
                    <div class="credit-metric">
                        <dt>مقدّم الطلب</dt>
                        <dd>{{ $creditRequest->requestedBy?->name ?? '—' }}</dd>
                    </div>
                    <div class="credit-metric credit-metric-wide">
                        <dt>ملاحظات الطلب</dt>
                        <dd>{{ $creditRequest->request_notes ?: '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="credit-panel credit-panel-approved" aria-labelledby="credit-current-approved-heading">
                <div class="credit-panel-head">
                    <p class="credit-panel-kicker credit-panel-kicker-ok">معتمد على العميل</p>
                    <h2 id="credit-current-approved-heading" class="credit-panel-title">القيم الحالية</h2>
                </div>
                <dl class="credit-metrics">
                    <div class="credit-metric">
                        <dt>حد الائتمان المعتمد حاليًا (العميل)</dt>
                        <dd class="dir-ltr">
                            {{ $creditRequest->customer?->approved_credit_limit !== null
                                ? money($creditRequest->customer->approved_credit_limit)
                                : '—' }}
                        </dd>
                    </div>
                    <div class="credit-metric">
                        <dt>شروط الدفع المعتمدة حاليًا</dt>
                        <dd>{{ $creditRequest->customer?->approved_payment_term ?: '—' }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        @if ($creditRequest->reviewed_at || $creditRequest->decided_at)
            <section class="credit-timeline" aria-label="سجل المراجعة والقرار">
                @if ($creditRequest->reviewed_at)
                    <div class="credit-timeline-item">
                        <p class="credit-timeline-label">المراجعة</p>
                        <p class="credit-timeline-value">
                            {{ $creditRequest->reviewedBy?->name }} —
                            <span class="dir-ltr">{{ $creditRequest->reviewed_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span>
                        </p>
                        <p class="credit-timeline-notes">{{ $creditRequest->review_notes ?: '—' }}</p>
                    </div>
                @endif
                @if ($creditRequest->decided_at)
                    <div class="credit-timeline-item">
                        <p class="credit-timeline-label">القرار النهائي</p>
                        <p class="credit-timeline-value">
                            {{ $creditRequest->decidedBy?->name }} —
                            <span class="dir-ltr">{{ $creditRequest->decided_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span>
                        </p>
                        <p class="credit-timeline-notes">{{ $creditRequest->decision_notes ?: '—' }}</p>
                    </div>
                @endif
            </section>
        @endif

        @if ($canReview)
            <section class="credit-action-panel" aria-labelledby="credit-review-heading">
                <div class="credit-panel-head">
                    <p class="credit-panel-kicker credit-panel-kicker-warn">مراجعة الحسابات</p>
                    <h2 id="credit-review-heading" class="credit-panel-title">مراجعة الحسابات</h2>
                    <p class="credit-panel-lead">إحالة لمدير المبيعات أو رفض في هذه المرحلة — بلا تفعيل للحد المطلوب.</p>
                </div>
                <form method="POST" action="{{ route('credit-requests.review', $creditRequest) }}" class="auth-form admin-form credit-decision-form" novalidate>
                    @csrf
                    <div class="auth-field">
                        <label for="review_notes">ملاحظات</label>
                        <textarea id="review_notes" name="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                        @error('decision')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="admin-form-actions credit-decision-actions">
                        <button type="submit" name="decision" value="forward" class="btn btn-glass-primary">إحالة للاعتماد النهائي</button>
                        <button type="submit" name="decision" value="reject" class="btn btn-glass">رفض في المراجعة</button>
                    </div>
                </form>
            </section>
        @endif

        @if ($canDecide)
            <section class="credit-action-panel credit-action-final" aria-labelledby="credit-decide-heading">
                <div class="credit-panel-head">
                    <p class="credit-panel-kicker credit-panel-kicker-ok">الاعتماد النهائي</p>
                    <h2 id="credit-decide-heading" class="credit-panel-title">الاعتماد النهائي (مدير المبيعات)</h2>
                    <p class="credit-panel-lead">الاعتماد يفعّل الحد وشروط الدفع على سجل العميل فورًا.</p>
                </div>
                <form method="POST" action="{{ route('credit-requests.decide', $creditRequest) }}" class="auth-form admin-form credit-decision-form" novalidate>
                    @csrf
                    <div class="auth-field">
                        <label for="decide_notes">ملاحظات</label>
                        <textarea id="decide_notes" name="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                        @error('decision')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="admin-form-actions credit-decision-actions">
                        <button type="submit" name="decision" value="approve" class="btn btn-glass-primary">اعتماد وتفعيل</button>
                        <button type="submit" name="decision" value="reject" class="btn btn-glass">رفض نهائي</button>
                    </div>
                </form>
            </section>
        @endif
    </div>
@endsection
