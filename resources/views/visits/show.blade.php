@extends('layouts.app')

@section('title', 'تفاصيل الزيارة — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · تفاصيل الزيارة</span>
@endsection

@section('content')
    @php
        $statusValue = $visit->status instanceof \BackedEnum
            ? $visit->status->value
            : (string) $visit->status;
        $statusBadge = match ($statusValue) {
            'completed' => 'badge-success',
            'cancelled' => 'badge-danger',
            default => 'badge-warning',
        };
        $statusTone = match ($statusValue) {
            'completed' => 'visit-status-completed',
            'cancelled' => 'visit-status-cancelled',
            default => 'visit-status-scheduled',
        };
        $statusKicker = match ($statusValue) {
            'completed' => 'مكتملة',
            'cancelled' => 'ملغاة',
            default => 'مجدولة',
        };
    @endphp

    <div class="visits-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="visit-show-heading">
            <div class="ds-page-bar-copy">
                <h1 id="visit-show-heading" class="admin-title">
                    {{ $visit->customer?->name_ar ?? 'زيارة' }}
                    <span class="badge {{ $statusBadge }}">{{ $visit->statusLabelAr() }}</span>
                </h1>
                <p class="admin-lead">
                    موعد
                    <span class="dir-ltr">{{ $visit->scheduled_at?->timezone($timezone)->format('Y-m-d H:i') }}</span>
                    · المنطقة الزمنية <span class="dir-ltr">{{ $timezone }}</span>
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('visits.index') }}" class="btn btn-glass">القائمة</a>
                @if ($visit->customer)
                    <a href="{{ route('customers.show', $visit->customer) }}" class="btn btn-glass">المنشأة</a>
                @endif
                @if ($canUpdate)
                    <a href="{{ route('visits.edit', $visit) }}" class="btn btn-glass-primary">تعديل الموعد</a>
                @endif
            </div>
        </header>

        <section class="visit-summary {{ $statusTone }}" aria-labelledby="visit-summary-heading">
            <div class="visit-panel-head">
                <p class="visit-panel-kicker visit-panel-kicker-{{ $statusValue === 'completed' ? 'ok' : ($statusValue === 'cancelled' ? 'danger' : 'schedule') }}">
                    {{ $statusKicker }}
                </p>
                <h2 id="visit-summary-heading" class="visit-panel-title">ملخص الزيارة</h2>
                <p class="visit-panel-lead">
                    @if ($statusValue === 'completed')
                        اكتملت — النتيجة واحتياجات العميل أدناه للقراءة فقط. لا يمكن الإلغاء أو إعادة الجدولة.
                    @elseif ($statusValue === 'cancelled')
                        أُلغيت — حالة نهائية. لا يمكن الإكمال أو إعادة الجدولة من هذه الشاشة.
                    @else
                        مجدولة — سجّل النتيجة للإكمال، أو ألغِ إن لزم. بلا GPS أو تذكيرات متكررة.
                    @endif
                </p>
            </div>

            <dl class="visit-metrics">
                <div class="visit-metric">
                    <dt>اسم المنشأة</dt>
                    <dd>{{ $visit->customer?->name_ar ?? '—' }}</dd>
                </div>
                <div class="visit-metric">
                    <dt>مندوب المبيعات المُعيَّن</dt>
                    <dd>
                        {{ $visit->assignedUser?->name ?? '—' }}
                        <span class="visit-assignee-fixed-note">ثابت من سجل المنشأة</span>
                    </dd>
                </div>
                <div class="visit-metric">
                    <dt>أنشأها</dt>
                    <dd>{{ $visit->createdBy?->name ?? '—' }}</dd>
                </div>
                <div class="visit-metric">
                    <dt>الموعد</dt>
                    <dd class="dir-ltr">{{ $visit->scheduled_at?->timezone($timezone)->format('Y-m-d H:i') }}</dd>
                </div>
                <div class="visit-metric visit-metric-wide">
                    <dt>ملاحظات الجدولة</dt>
                    <dd>{{ $visit->notes ?: '—' }}</dd>
                </div>
                @if ($visit->completed_at)
                    <div class="visit-metric">
                        <dt>تاريخ الإكمال</dt>
                        <dd class="dir-ltr">{{ $visit->completed_at->timezone($timezone)->format('Y-m-d H:i') }}</dd>
                    </div>
                @endif
                @if ($visit->cancelled_at)
                    <div class="visit-metric">
                        <dt>تاريخ الإلغاء</dt>
                        <dd class="dir-ltr">{{ $visit->cancelled_at->timezone($timezone)->format('Y-m-d H:i') }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        @if ($statusValue === 'completed')
            <section class="visit-outcome-readonly" aria-labelledby="visit-outcome-heading">
                <div class="visit-panel-head">
                    <p class="visit-panel-kicker visit-panel-kicker-ok">نتيجة</p>
                    <h2 id="visit-outcome-heading" class="visit-panel-title">نتيجة الزيارة</h2>
                </div>
                <dl class="visit-metrics">
                    <div class="visit-metric visit-metric-wide">
                        <dt>نتيجة الزيارة</dt>
                        <dd>{{ $visit->outcome ?: '—' }}</dd>
                    </div>
                    <div class="visit-metric visit-metric-wide">
                        <dt>احتياجات العميل</dt>
                        <dd>{{ $visit->customer_needs ?: '—' }}</dd>
                    </div>
                </dl>
            </section>

            @if ($canCreateSalesRequest ?? false)
                <section id="visit-next" class="visit-action-panel visit-action-next" aria-labelledby="visit-next-heading">
                    <div class="visit-panel-head">
                        <p class="visit-panel-kicker visit-panel-kicker-next">التالي</p>
                        <h2 id="visit-next-heading" class="visit-panel-title">طلب بيع لهذه المنشأة</h2>
                        <p class="visit-panel-lead">
                            الزيارة لا تُنشئ طلب بيع تلقائيًا. إن كان العميل يحتاج عرضًا أو توريدًا، ابدأ المسودة من هنا — المنشأة ستكون مختارة.
                        </p>
                    </div>

                    @if ($visit->originatedSalesRequests->isNotEmpty())
                        <ul class="visit-origin-sr-list">
                            @foreach ($visit->originatedSalesRequests as $originSr)
                                <li>
                                    <a href="{{ route('sales-requests.show', $originSr) }}" class="quotes-id-link dir-ltr">#{{ $originSr->id }}</a>
                                    <span class="badge badge-neutral">{{ $originSr->status->labelAr() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="admin-form-actions">
                        <a href="{{ route('sales-requests.create', ['customer_id' => $visit->customer_id, 'visit_id' => $visit->id]) }}"
                           class="btn btn-glass-primary">
                            {{ $visit->originatedSalesRequests->isNotEmpty() ? 'طلب بيع آخر' : 'إنشاء طلب بيع' }}
                        </a>
                    </div>
                </section>
            @endif
        @endif

        @if ($canComplete)
            <section class="visit-action-panel visit-action-complete" aria-labelledby="visit-complete-heading">
                <div class="visit-panel-head">
                    <p class="visit-panel-kicker visit-panel-kicker-ok">إكمال</p>
                    <h2 id="visit-complete-heading" class="visit-panel-title">تسجيل نتيجة الزيارة</h2>
                    <p class="visit-panel-lead">
                        المسار الإيجابي: سجّل النتيجة واحتياجات العميل ثم أكّد الإكمال.
                        لا GPS ولا تحقق موقع. بعد الإكمال تصبح الحالة نهائية.
                    </p>
                </div>

                <form method="POST" action="{{ route('visits.complete', $visit) }}" class="auth-form admin-form customers-form visits-outcome-form" novalidate>
                    @csrf
                    <div class="auth-field">
                        <label for="outcome">نتيجة الزيارة <span class="required-mark" title="مطلوب">*</span></label>
                        <textarea id="outcome" name="outcome" rows="3" required maxlength="5000"
                                  aria-required="true">{{ old('outcome') }}</textarea>
                        @error('outcome')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="auth-field">
                        <label for="complete_notes">ملاحظات إضافية</label>
                        <textarea id="complete_notes" name="notes" rows="2" maxlength="5000">{{ old('notes', $visit->notes) }}</textarea>
                        @error('notes')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="auth-field">
                        <label for="customer_needs">احتياجات العميل</label>
                        <textarea id="customer_needs" name="customer_needs" rows="3" maxlength="5000">{{ old('customer_needs') }}</textarea>
                        @error('customer_needs')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="admin-form-actions">
                        <button type="submit" class="btn btn-glass-primary">تأكيد الإكمال</button>
                    </div>
                </form>
            </section>
        @endif

        @if ($canCancel)
            <section class="visit-action-panel visit-action-cancel visits-cancel-block" aria-labelledby="visit-cancel-heading">
                <div class="visit-panel-head">
                    <p class="visit-panel-kicker visit-panel-kicker-danger">إلغاء</p>
                    <h2 id="visit-cancel-heading" class="visit-panel-title">إلغاء الزيارة</h2>
                    <p class="visit-panel-lead">
                        إجراء نهائي منفصل عن الإكمال.
                        المندوب يلغي زيارته المُعيَّنة له فقط؛ مدير المبيعات أي زيارة مجدولة.
                        لا يمكن التراجع من هذه الشاشة.
                    </p>
                </div>
                <form method="POST" action="{{ route('visits.cancel', $visit) }}" class="auth-form admin-form customers-form visit-cancel-form" onsubmit="return confirm('تأكيد إلغاء هذه الزيارة؟ لا يمكن التراجع.');">
                    @csrf
                    <div class="auth-field">
                        <label for="cancel_notes">ملاحظة الإلغاء (اختياري)</label>
                        <textarea id="cancel_notes" name="notes" rows="2" maxlength="5000">{{ old('notes') }}</textarea>
                    </div>
                    <div class="admin-form-actions">
                        <button type="submit" class="btn btn-glass visits-cancel-btn">إلغاء الزيارة نهائيًا</button>
                    </div>
                </form>
            </section>
        @endif
    </div>
@endsection
