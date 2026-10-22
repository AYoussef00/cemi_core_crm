@extends('layouts.app')

@section('title', 'الملف الائتماني — '.$customer->name_ar.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المالية · الملف الائتماني</span>
@endsection

@section('content')
    <div class="credit-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="credit-profile-heading">
            <div class="ds-page-bar-copy">
                <h1 id="credit-profile-heading" class="admin-title">الملف الائتماني</h1>
                <p class="admin-lead">
                    {{ $customer->name_ar }} — القيم المعتمدة فقط بعد الاعتماد النهائي لمدير المبيعات.
                    المطلوب في الطلبات ليس حدًا فعّالًا.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('customers.show', $customer) }}" class="btn btn-glass">سجل العميل</a>
                <a href="{{ route('credit-requests.index') }}" class="btn btn-glass">طلبات الائتمان</a>
            </div>
        </header>

        <div class="credit-split" role="group" aria-label="معتمد مقابل مؤجّل">
            <section class="credit-panel credit-panel-approved" aria-labelledby="credit-approved-heading">
                <div class="credit-panel-head">
                    <p class="credit-panel-kicker credit-panel-kicker-ok">فعّال</p>
                    <h2 id="credit-approved-heading" class="credit-panel-title">القيم المعتمدة</h2>
                    <p class="credit-panel-lead">تُحدَّث فقط عند اعتماد مدير المبيعات الطلب نهائيًا.</p>
                </div>
                <dl class="credit-metrics">
                    <div class="credit-metric">
                        <dt>حد الائتمان المعتمد</dt>
                        <dd class="dir-ltr">
                            {{ $customer->approved_credit_limit !== null ? money($customer->approved_credit_limit) : '—' }}
                        </dd>
                    </div>
                    <div class="credit-metric">
                        <dt>شروط الدفع المعتمدة</dt>
                        <dd>{{ $customer->approved_payment_term ?: '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="credit-panel credit-panel-deferred" aria-labelledby="credit-deferred-heading">
                <div class="credit-panel-head">
                    <p class="credit-panel-kicker credit-panel-kicker-warn">مؤجّل</p>
                    <h2 id="credit-deferred-heading" class="credit-panel-title">التعرض ومنع الأوامر</h2>
                    <p class="credit-panel-lead">لا أرقام وهمية ولا زر منع يدوي هنا.</p>
                </div>
                <dl class="credit-metrics">
                    <div class="credit-metric">
                        <dt>التعرض الائتماني (فواتير غير مسددة)</dt>
                        <dd class="credit-deferred-note">غير محسوب بعد — بانتظار وحدة الفواتير</dd>
                    </div>
                </dl>
                <p class="credit-gate-note" role="note">
                    منع أوامر البيع عند بلوغ حد الائتمان غير مفعّل حاليًا، وسيُربط لاحقًا بوحدة أوامر البيع. لا تُعرض هنا أرصدة افتراضية.
                </p>
            </section>
        </div>

        @if ($canRequest)
            <section class="credit-request-block" aria-labelledby="credit-request-heading">
                <div class="credit-panel-head">
                    <p class="credit-panel-kicker credit-panel-kicker-req">مطلوب · غير فعّال</p>
                    <h2 id="credit-request-heading" class="credit-panel-title">طلب تغيير حد الائتمان</h2>
                    <p class="credit-panel-lead">
                        يذهب للمراجعة ثم الاعتماد النهائي. الرفض لا يغيّر القيم المعتمدة أعلاه.
                    </p>
                </div>

                @if ($openRequest)
                    <p class="credit-open-request" role="status">
                        يوجد طلب مفتوح بحالة
                        <span class="badge badge-warning">{{ $openRequest->statusLabelAr() }}</span>
                        <a href="{{ route('credit-requests.show', $openRequest) }}" class="credit-open-request-link">عرض الطلب</a>
                    </p>
                @else
                    <form method="POST" action="{{ route('customers.credit-requests.store', $customer) }}" class="auth-form admin-form credit-request-form" novalidate>
                        @csrf
                        <div class="credit-form-grid">
                            <div class="auth-field">
                                <label for="requested_credit_limit">حد الائتمان المطلوب <span class="required-mark" title="مطلوب">*</span></label>
                                <input id="requested_credit_limit" name="requested_credit_limit" type="number" step="0.01" min="0"
                                       value="{{ old('requested_credit_limit') }}" required class="dir-ltr"
                                       aria-required="true"
                                       aria-invalid="{{ $errors->has('requested_credit_limit') ? 'true' : 'false' }}">
                                @error('requested_credit_limit')
                                    <p class="auth-error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="auth-field">
                                <label for="requested_payment_term">شروط الدفع المطلوبة</label>
                                <input id="requested_payment_term" name="requested_payment_term" type="text" maxlength="255"
                                       value="{{ old('requested_payment_term') }}"
                                       placeholder="مثال: نقدي، أو آجل 30 يومًا"
                                       aria-invalid="{{ $errors->has('requested_payment_term') ? 'true' : 'false' }}">
                                @error('requested_payment_term')
                                    <p class="auth-error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <div class="auth-field">
                            <label for="request_notes">ملاحظات</label>
                            <textarea id="request_notes" name="request_notes" rows="3" maxlength="2000">{{ old('request_notes') }}</textarea>
                            @error('request_notes')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="admin-form-actions">
                            <button type="submit" class="btn btn-glass-primary">إرسال للمراجعة</button>
                        </div>
                    </form>
                @endif
            </section>
        @endif

        <div class="customers-section-head">
            <h2 class="customers-section-title">سجل الطلبات</h2>
            <p class="customers-section-lead">طلبات الائتمان لهذا العميل — المطلوب ليس حدًا فعّالًا حتى الاعتماد النهائي.</p>
        </div>
        <div class="ds-table-surface">
            <div class="ds-data-body">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">الحالة</th>
                            <th scope="col">الحد المطلوب</th>
                            <th scope="col">شروط الدفع</th>
                            <th scope="col">مقدّم الطلب</th>
                            <th scope="col">التاريخ</th>
                            <th scope="col">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customer->creditLimitRequests as $req)
                            @php
                                $statusValue = $req->status instanceof \BackedEnum ? $req->status->value : (string) $req->status;
                                $statusBadge = match ($statusValue) {
                                    'approved' => 'badge-success',
                                    'rejected' => 'badge-danger',
                                    'pending_final' => 'badge-process',
                                    default => 'badge-warning',
                                };
                            @endphp
                            <tr>
                                <td><span class="badge {{ $statusBadge }}">{{ $req->statusLabelAr() }}</span></td>
                                <td class="dir-ltr">{{ money($req->requested_credit_limit) }}</td>
                                <td>{{ $req->requested_payment_term ?: '—' }}</td>
                                <td>{{ $req->requestedBy?->name ?? '—' }}</td>
                                <td class="dir-ltr ds-cell-muted">{{ $req->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                <td class="ds-cell-actions">
                                    <details class="ds-row-menu">
                                        <summary aria-label="إجراءات الطلب">⋮</summary>
                                        <div class="ds-row-menu-panel">
                                            <a href="{{ route('credit-requests.show', $req) }}">عرض</a>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="ds-data-empty" role="status">
                                        <strong>لا توجد طلبات</strong>
                                        <p>لا توجد طلبات ائتمان لهذا العميل بعد.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
