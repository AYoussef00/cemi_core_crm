@extends('layouts.app')

@section('title', 'تعديل زيارة — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · تعديل زيارة</span>
@endsection

@section('content')
    @php
        $scheduledLocal = old(
            'scheduled_at',
            $visit->scheduled_at?->timezone($timezone)->format('Y-m-d\TH:i')
        );
        $repName = $visit->customer?->assignedSalesRep?->name
            ?? $visit->assignedUser?->name
            ?? '—';
        $establishmentName = $visit->customer?->name_ar ?? '—';
    @endphp

    <div class="visits-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="edit-visit-heading">
            <div class="ds-page-bar-copy">
                <h1 id="edit-visit-heading" class="admin-title">تعديل زيارة مجدولة</h1>
                <p class="admin-lead">
                    {{ $establishmentName }} —
                    إعادة جدولة الموعد فقط. مندوب المبيعات ثابت على مندوب المنشأة ولا يُغيَّر من هنا.
                    الأوقات حسب <span class="dir-ltr">{{ $timezone }}</span>.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('visits.show', $visit) }}" class="btn btn-glass">التفاصيل</a>
                <a href="{{ route('visits.index') }}" class="btn btn-glass">القائمة</a>
            </div>
        </header>

        <section class="visit-form-panel" aria-labelledby="visit-edit-fields-heading">
            <div class="visit-panel-head">
                <p class="visit-panel-kicker visit-panel-kicker-schedule">إعادة جدولة</p>
                <h2 id="visit-edit-fields-heading" class="visit-panel-title">الموعد</h2>
                <p class="visit-panel-lead">لا يغيّر هذا النموذج حالة الزيارة — تبقى مجدولة حتى الإكمال أو الإلغاء. يُشعر المندوب عند تغيّر الموعد فقط (دقة الدقيقة).</p>
            </div>

            <form method="POST" action="{{ route('visits.update', $visit) }}" class="auth-form admin-form customers-form visit-form" novalidate>
                @csrf
                @method('PUT')

                <div class="visit-assignee-block visit-assignee-block-compact" aria-labelledby="visit-edit-assignee-heading">
                    <h3 id="visit-edit-assignee-heading" class="visually-hidden">مندوب المبيعات المُعيَّن</h3>
                    <div class="visit-assignee-callout is-locked" role="note">
                        <span class="visit-assignee-label">مندوب المبيعات المُعيَّن</span>
                        <strong class="visit-assignee-name">{{ $repName }}</strong>
                        <span class="visit-assignee-hint">ثابت على مندوب المنشأة — إعادة الجدولة لا تغيّر التعيين.</span>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="scheduled_at">موعد الزيارة <span class="required-mark" title="مطلوب">*</span></label>
                    <input id="scheduled_at" name="scheduled_at" type="datetime-local" required
                           class="dir-ltr"
                           value="{{ $scheduledLocal }}"
                           aria-required="true"
                           aria-invalid="{{ $errors->has('scheduled_at') ? 'true' : 'false' }}">
                    @error('scheduled_at')
                        <p class="auth-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="auth-field">
                    <label for="notes">ملاحظات الجدولة</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="5000">{{ old('notes', $visit->notes) }}</textarea>
                    @error('notes')
                        <p class="auth-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="admin-form-actions">
                    <button type="submit" class="btn btn-glass-primary">حفظ التعديل</button>
                    <a href="{{ route('visits.show', $visit) }}" class="btn btn-glass">إلغاء</a>
                </div>
            </form>
        </section>
    </div>
@endsection
