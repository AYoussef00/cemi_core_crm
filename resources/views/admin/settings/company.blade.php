@extends('layouts.app')

@section('title', ($isFirstTime ? 'إعداد الشركة' : 'تعديل الشركة').' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">الإعدادات · الشركة</span>
@endsection

@section('content')
    <section class="admin-panel panel-enter settings-form-panel" aria-labelledby="company-heading">
        <div class="admin-header">
            <div>
                <p class="settings-form-kicker">شركة واحدة فقط</p>
                <h1 id="company-heading" class="admin-title">{{ $isFirstTime ? 'إعداد ملف الشركة' : 'تعديل ملف الشركة' }}</h1>
                <p class="admin-lead">
                    @if ($isFirstTime)
                        هذه أول مرة تُحفظ فيها الشركة. لن تُنشأ بيانات تجريبية تلقائيًا.
                    @else
                        شركة واحدة فقط في الإصدار الحالي. الحقول أدناه هوية واتصال — دون قواعد ضريبة.
                    @endif
                </p>
            </div>
            <a href="{{ route('admin.settings.index') }}" class="btn btn-glass">رجوع للإعدادات</a>
        </div>

        @if ($isFirstTime)
            <div class="app-alert app-alert-warning" role="status">
                حالة أولية فارغة: أكمل الاسم على الأقل لحفظ ملف الشركة.
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.company.update') }}" class="auth-form admin-form settings-form" novalidate>
            @csrf
            @method('PUT')

            <div class="auth-field">
                <label for="name_ar">اسم الشركة (عربي) <span class="required-mark" title="مطلوب">*</span></label>
                <input
                    id="name_ar"
                    name="name_ar"
                    type="text"
                    value="{{ old('name_ar', $company?->name_ar) }}"
                    required
                    maxlength="255"
                    autocomplete="organization"
                    aria-required="true"
                    aria-invalid="{{ $errors->has('name_ar') ? 'true' : 'false' }}"
                    @if ($errors->has('name_ar')) aria-describedby="name_ar-error" @endif
                >
                @error('name_ar')
                    <p id="name_ar-error" class="auth-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="auth-field">
                <label for="address_ar">العنوان <span class="optional-mark">اختياري</span></label>
                <textarea
                    id="address_ar"
                    name="address_ar"
                    rows="4"
                    maxlength="500"
                    aria-invalid="{{ $errors->has('address_ar') ? 'true' : 'false' }}"
                    @if ($errors->has('address_ar')) aria-describedby="address_ar-error" @endif
                >{{ old('address_ar', $company?->address_ar) }}</textarea>
                @error('address_ar')
                    <p id="address_ar-error" class="auth-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="settings-form-row">
                <div class="auth-field">
                    <label for="contact_phone">هاتف التواصل <span class="optional-mark">اختياري</span></label>
                    <input
                        id="contact_phone"
                        name="contact_phone"
                        type="text"
                        value="{{ old('contact_phone', $company?->contact_phone) }}"
                        maxlength="50"
                        dir="ltr"
                        inputmode="tel"
                        autocomplete="tel"
                        aria-invalid="{{ $errors->has('contact_phone') ? 'true' : 'false' }}"
                        @if ($errors->has('contact_phone')) aria-describedby="contact_phone-error" @endif
                    >
                    @error('contact_phone')
                        <p id="contact_phone-error" class="auth-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="auth-field">
                    <label for="contact_email">بريد التواصل <span class="optional-mark">اختياري</span></label>
                    <input
                        id="contact_email"
                        name="contact_email"
                        type="email"
                        value="{{ old('contact_email', $company?->contact_email) }}"
                        maxlength="255"
                        dir="ltr"
                        autocomplete="email"
                        aria-invalid="{{ $errors->has('contact_email') ? 'true' : 'false' }}"
                        @if ($errors->has('contact_email')) aria-describedby="contact_email-error" @endif
                    >
                    @error('contact_email')
                        <p id="contact_email-error" class="auth-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="admin-form-actions">
                <button type="submit" class="btn btn-glass-primary">{{ $isFirstTime ? 'حفظ الشركة' : 'تحديث الشركة' }}</button>
                <a href="{{ route('admin.settings.index') }}" class="btn btn-glass">إلغاء</a>
            </div>
        </form>

        @unless ($isFirstTime)
            @include('admin.settings.partials.attachments', [
                'attachments' => $attachments,
                'attachmentLimits' => $attachmentLimits,
                'storeRoute' => route('admin.settings.company.attachments.store'),
                'downloadRouteName' => 'admin.settings.company.attachments.download',
                'destroyRouteName' => 'admin.settings.company.attachments.destroy',
            ])
        @endunless
    </section>
@endsection
