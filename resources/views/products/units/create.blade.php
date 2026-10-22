@extends('layouts.app')

@section('title', 'وحدة قياس جديدة — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المخزون · وحدة جديدة</span>
@endsection

@section('content')
    <div class="products-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="create-unit-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">إنشاء · وحدات القياس</p>
                <h1 id="create-unit-heading" class="admin-title">وحدة قياس جديدة</h1>
                <p class="admin-lead">
                    أضف وحدة يمكن ربطها كوحدة أساسية للمنتجات.
                    لا تُعرَّف تحويلات بين الوحدات هنا.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('products.units.index') }}" class="btn btn-glass">رجوع للقائمة</a>
            </div>
        </header>

        <section class="products-form-card" aria-labelledby="unit-create-fields-heading">
            <form method="POST" action="{{ route('products.units.store') }}" class="auth-form admin-form customers-form" novalidate>
                @csrf

                <div class="customers-form-section">
                    <h2 id="unit-create-fields-heading" class="customers-form-section-title">بيانات الوحدة</h2>
                    <p class="customers-form-section-lead">الاسم مطلوب. الرمز اختياري للعرض المختصر في الكتالوج.</p>

                    <div class="auth-field">
                        <label for="name_ar">اسم الوحدة <span class="required-mark" title="مطلوب">*</span></label>
                        <input id="name_ar" name="name_ar" type="text" value="{{ old('name_ar') }}" required maxlength="255"
                               placeholder="مثال: طن"
                               aria-required="true"
                               aria-invalid="{{ $errors->has('name_ar') ? 'true' : 'false' }}">
                        @error('name_ar')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="auth-field">
                        <label for="symbol_ar">الرمز</label>
                        <input id="symbol_ar" name="symbol_ar" type="text" value="{{ old('symbol_ar') }}" maxlength="32"
                               class="dir-ltr"
                               placeholder="اختياري"
                               aria-invalid="{{ $errors->has('symbol_ar') ? 'true' : 'false' }}">
                        @error('symbol_ar')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="auth-field">
                        <label class="products-check-label">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                            <span>
                                وحدة نشطة
                                <span class="field-hint products-check-hint">قابلة للاختيار عند إنشاء منتج جديد</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="admin-form-actions">
                    <button type="submit" class="btn btn-glass-primary">حفظ الوحدة</button>
                    <a href="{{ route('products.units.index') }}" class="btn btn-glass">إلغاء</a>
                </div>
            </form>
        </section>
    </div>
@endsection
