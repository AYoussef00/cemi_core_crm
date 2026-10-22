@extends('layouts.app')

@section('title', ($isFirstTime ? 'إعداد المستودع' : 'تعديل المستودع').' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">الإعدادات · المستودع</span>
@endsection

@section('content')
    <section class="admin-panel panel-enter settings-form-panel" aria-labelledby="warehouse-heading">
        <div class="admin-header">
            <div>
                <p class="settings-form-kicker">مستودع واحد فقط</p>
                <h1 id="warehouse-heading" class="admin-title">{{ $isFirstTime ? 'إعداد المستودع' : 'تعديل المستودع' }}</h1>
                <p class="admin-lead">
                    @if ($isFirstTime)
                        هذه أول مرة يُحفظ فيها المستودع. لن تُنشأ بيانات تجريبية تلقائيًا.
                    @else
                        مستودع واحد فقط في الإصدار الحالي. لا تُنشأ عمليات مخزون من هذه الشاشة.
                    @endif
                </p>
            </div>
            <a href="{{ route('admin.settings.index') }}" class="btn btn-glass">رجوع للإعدادات</a>
        </div>

        @if ($isFirstTime)
            <div class="app-alert app-alert-warning" role="status">
                حالة أولية فارغة: أكمل اسم المستودع على الأقل للحفظ.
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.warehouse.update') }}" class="auth-form admin-form settings-form" novalidate>
            @csrf
            @method('PUT')

            <div class="auth-field">
                <label for="name_ar">اسم المستودع (عربي) <span class="required-mark" title="مطلوب">*</span></label>
                <input
                    id="name_ar"
                    name="name_ar"
                    type="text"
                    value="{{ old('name_ar', $warehouse?->name_ar) }}"
                    required
                    maxlength="255"
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
                >{{ old('address_ar', $warehouse?->address_ar) }}</textarea>
                @error('address_ar')
                    <p id="address_ar-error" class="auth-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="auth-field">
                <label for="contact_phone">هاتف التواصل <span class="optional-mark">اختياري</span></label>
                <input
                    id="contact_phone"
                    name="contact_phone"
                    type="text"
                    value="{{ old('contact_phone', $warehouse?->contact_phone) }}"
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

            <div class="admin-form-actions">
                <button type="submit" class="btn btn-glass-primary">{{ $isFirstTime ? 'حفظ المستودع' : 'تحديث المستودع' }}</button>
                <a href="{{ route('admin.settings.index') }}" class="btn btn-glass">إلغاء</a>
            </div>
        </form>

        @unless ($isFirstTime)
            @include('admin.settings.partials.attachments', [
                'attachments' => $attachments,
                'attachmentLimits' => $attachmentLimits,
                'storeRoute' => route('admin.settings.warehouse.attachments.store'),
                'downloadRouteName' => 'admin.settings.warehouse.attachments.download',
                'destroyRouteName' => 'admin.settings.warehouse.attachments.destroy',
            ])
        @endunless
    </section>
@endsection
