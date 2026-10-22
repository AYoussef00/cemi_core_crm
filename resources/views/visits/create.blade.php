@extends('layouts.app')

@section('title', 'جدولة زيارة — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · جدولة زيارة</span>
@endsection

@section('content')
    <div class="visits-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="create-visit-heading">
            <div class="ds-page-bar-copy">
                <h1 id="create-visit-heading" class="admin-title">جدولة زيارة</h1>
                <p class="admin-lead">
                    اربط الزيارة بمنشأة ضمن نطاقك وحدّد الموعد.
                    المندوب يُسجَّل تلقائيًا من مندوب المبيعات المُعيَّن على المنشأة — ولا يمكن تغييره هنا.
                    الأوقات حسب المنطقة الزمنية للتطبيق (<span class="dir-ltr">{{ config('app.timezone') }}</span>).
                    بلا تكرار أو تذكيرات أو GPS.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('visits.index') }}" class="btn btn-glass">القائمة</a>
            </div>
        </header>

        @if ($customers->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا توجد منشآت لجدولة زيارة</strong>
                <p>أنشئ منشأة ضمن نطاقك أولاً، ثم عد لجدولة الزيارة.</p>
                @can('create', \App\Models\Customer::class)
                    <a href="{{ route('customers.create') }}" class="btn btn-glass-primary">منشأة جديدة</a>
                @endcan
            </div>
        @else
            @php
                $selectedCustomerId = old('customer_id', $preselectCustomerId);
                $preselectCustomer = $selectedCustomerId
                    ? $customers->firstWhere('id', (int) $selectedCustomerId)
                    : null;
                $hasAssignedRep = filled($preselectCustomer?->assigned_sales_rep_id);
                $assignedRepName = $preselectCustomer?->assignedSalesRep?->name;
                $scheduleBlocked = filled($selectedCustomerId) && ! $hasAssignedRep;
                $canScheduleNow = $hasAssignedRep;
            @endphp

            <section class="visit-form-panel" aria-labelledby="visit-create-fields-heading">
                <div class="visit-panel-head">
                    <p class="visit-panel-kicker visit-panel-kicker-schedule">جدولة</p>
                    <h2 id="visit-create-fields-heading" class="visit-panel-title">بيانات الزيارة</h2>
                    <p class="visit-panel-lead">المنشأة والموعد مطلوبان. الملاحظات اختيارية.</p>
                </div>

                <form method="POST" action="{{ route('visits.store') }}" class="auth-form admin-form customers-form visit-form" novalidate data-visit-create-form>
                    @csrf

                    <div class="auth-field">
                        <label for="customer_id">المنشأة <span class="required-mark" title="مطلوب">*</span></label>
                        <select id="customer_id" name="customer_id" required
                                aria-required="true"
                                aria-invalid="{{ $errors->has('customer_id') ? 'true' : 'false' }}"
                                data-visit-customer-select
                                aria-describedby="visit-assignee-status">
                            <option value="">— اختر المنشأة —</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}"
                                    data-assigned-rep-id="{{ $customer->assigned_sales_rep_id ?? '' }}"
                                    data-assigned-rep-name="{{ $customer->assignedSalesRep?->name ?? '' }}"
                                    @can('update', $customer)
                                        data-edit-url="{{ route('customers.edit', $customer) }}"
                                    @endcan
                                    @selected((string) $selectedCustomerId === (string) $customer->id)>
                                    {{ $customer->name_ar }}
                                </option>
                            @endforeach
                        </select>
                        @error('customer_id')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="visit-assignee-block" aria-labelledby="visit-assignee-heading">
                        <div class="visit-panel-head">
                            <p class="visit-panel-kicker visit-panel-kicker-locked">تعيين تلقائي</p>
                            <h3 id="visit-assignee-heading" class="visit-panel-title">مندوب المبيعات المُعيَّن</h3>
                            <p class="visit-panel-lead">يُؤخذ من سجل المنشأة. لا يوجد اختيار يدوي في شاشة الجدولة.</p>
                        </div>

                        <div id="visit-assignee-status"
                             class="visit-assignee-callout {{ $hasAssignedRep ? 'is-locked' : 'is-missing' }}"
                             role="status"
                             data-visit-assignee-status
                             @if ($scheduleBlocked) aria-live="polite" @endif>
                            @if ($hasAssignedRep)
                                <span class="visit-assignee-label">المندوب</span>
                                <strong class="visit-assignee-name" data-visit-assigned-rep-name>{{ $assignedRepName }}</strong>
                                <span class="visit-assignee-hint">ثابت على مندوب المنشأة — لا يمكن تغييره من هنا.</span>
                            @elseif ($scheduleBlocked)
                                <span class="visit-assignee-label">الجدولة موقوفة</span>
                                <strong class="visit-assignee-name">بلا مندوب مبيعات</strong>
                                <span class="visit-assignee-hint">
                                    عيّن مندوب مبيعات على سجل المنشأة أولاً، ثم عد لجدولة الزيارة.
                                    @can('update', $preselectCustomer)
                                        <a href="{{ route('customers.edit', $preselectCustomer) }}" class="visit-assignee-fix-link">تعديل المنشأة</a>
                                    @endcan
                                </span>
                            @else
                                <span class="visit-assignee-label">بانتظار الاختيار</span>
                                <strong class="visit-assignee-name">اختر منشأة</strong>
                                <span class="visit-assignee-hint">بعد اختيار منشأة ذات مندوب مُعيَّن يظهر اسمه هنا تلقائيًا.</span>
                            @endif
                        </div>
                    </div>

                    <div class="auth-field">
                        <label for="scheduled_at">موعد الزيارة <span class="required-mark" title="مطلوب">*</span></label>
                        <input id="scheduled_at" name="scheduled_at" type="datetime-local" required
                               class="dir-ltr"
                               value="{{ old('scheduled_at') }}"
                               aria-required="true"
                               aria-invalid="{{ $errors->has('scheduled_at') ? 'true' : 'false' }}"
                               @disabled(! $canScheduleNow)
                               data-visit-schedule-input>
                        @error('scheduled_at')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="auth-field">
                        <label for="notes">ملاحظات الجدولة</label>
                        <textarea id="notes" name="notes" rows="3" maxlength="5000"
                                  aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}"
                                  @disabled(! $canScheduleNow)
                                  data-visit-notes-input>{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="admin-form-actions">
                        <button type="submit" class="btn btn-glass-primary"
                                data-visit-submit
                                @disabled(! $canScheduleNow)>
                            حفظ الجدولة
                        </button>
                        <a href="{{ route('visits.index') }}" class="btn btn-glass">إلغاء</a>
                    </div>
                </form>
            </section>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const customerSelect = document.querySelector('[data-visit-customer-select]');
            const statusEl = document.querySelector('[data-visit-assignee-status]');
            const submitBtn = document.querySelector('[data-visit-submit]');
            const scheduleInput = document.querySelector('[data-visit-schedule-input]');
            const notesInput = document.querySelector('[data-visit-notes-input]');
            if (!customerSelect || !statusEl) {
                return;
            }

            const setFieldsEnabled = (enabled) => {
                if (scheduleInput) scheduleInput.disabled = !enabled;
                if (notesInput) notesInput.disabled = !enabled;
                if (submitBtn) submitBtn.disabled = !enabled;
            };

            const syncAssigneeDisplay = () => {
                const option = customerSelect.selectedOptions[0];
                const value = option?.value || '';
                const repId = option?.getAttribute('data-assigned-rep-id') || '';
                const repName = option?.getAttribute('data-assigned-rep-name') || '';
                const editUrl = option?.getAttribute('data-edit-url') || '';

                if (repId && repName) {
                    statusEl.className = 'visit-assignee-callout is-locked';
                    statusEl.innerHTML =
                        `<span class="visit-assignee-label">المندوب</span>` +
                        `<strong class="visit-assignee-name" data-visit-assigned-rep-name>${repName}</strong>` +
                        `<span class="visit-assignee-hint">ثابت على مندوب المنشأة — لا يمكن تغييره من هنا.</span>`;
                    setFieldsEnabled(true);
                } else if (value) {
                    statusEl.className = 'visit-assignee-callout is-missing';
                    const link = editUrl
                        ? ` <a href="${editUrl}" class="visit-assignee-fix-link">تعديل المنشأة</a>`
                        : '';
                    statusEl.innerHTML =
                        `<span class="visit-assignee-label">الجدولة موقوفة</span>` +
                        `<strong class="visit-assignee-name">بلا مندوب مبيعات</strong>` +
                        `<span class="visit-assignee-hint">عيّن مندوب مبيعات على سجل المنشأة أولاً، ثم عد لجدولة الزيارة.${link}</span>`;
                    setFieldsEnabled(false);
                } else {
                    statusEl.className = 'visit-assignee-callout is-missing';
                    statusEl.innerHTML =
                        `<span class="visit-assignee-label">بانتظار الاختيار</span>` +
                        `<strong class="visit-assignee-name">اختر منشأة</strong>` +
                        `<span class="visit-assignee-hint">بعد اختيار منشأة ذات مندوب مُعيَّن يظهر اسمه هنا تلقائيًا.</span>`;
                    setFieldsEnabled(false);
                }
            };

            customerSelect.addEventListener('change', syncAssigneeDisplay);
            syncAssigneeDisplay();
        })();
    </script>
@endpush
