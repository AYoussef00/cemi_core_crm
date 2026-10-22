@extends('layouts.app')

@section('title', 'تعديل وحدة قياس — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المخزون · تعديل وحدة</span>
@endsection

@section('content')
    <div class="products-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="edit-unit-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">تعديل · وحدات القياس</p>
                <h1 id="edit-unit-heading" class="admin-title">تعديل وحدة القياس</h1>
                <p class="admin-lead">
                    {{ $unit->name_ar }}
                    @if ($productsCount > 0)
                        — مرتبطة بـ {{ $productsCount }} منتجًا
                    @endif
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('products.units.index') }}" class="btn btn-glass">رجوع للقائمة</a>
            </div>
        </header>

        <section class="products-form-card" aria-labelledby="unit-edit-fields-heading">
            <form method="POST" action="{{ route('products.units.update', $unit) }}" class="auth-form admin-form customers-form" novalidate>
                @csrf
                @method('PUT')

                <div class="customers-form-section">
                    <h2 id="unit-edit-fields-heading" class="customers-form-section-title">بيانات الوحدة</h2>
                    <p class="customers-form-section-lead">تعديل الاسم أو الرمز أو الحالة. لا توجد تحويلات وحدات في هذه المرحلة.</p>

                    <div class="auth-field">
                        <label for="name_ar">اسم الوحدة <span class="required-mark" title="مطلوب">*</span></label>
                        <input id="name_ar" name="name_ar" type="text"
                               value="{{ old('name_ar', $unit->name_ar) }}" required maxlength="255"
                               aria-required="true"
                               aria-invalid="{{ $errors->has('name_ar') ? 'true' : 'false' }}">
                        @error('name_ar')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="auth-field">
                        <label for="symbol_ar">الرمز</label>
                        <input id="symbol_ar" name="symbol_ar" type="text"
                               value="{{ old('symbol_ar', $unit->symbol_ar) }}" maxlength="32"
                               class="dir-ltr"
                               aria-invalid="{{ $errors->has('symbol_ar') ? 'true' : 'false' }}">
                        @error('symbol_ar')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="auth-field">
                        <label class="products-check-label">
                            <input type="checkbox" name="is_active" value="1"
                                   @checked(old('is_active', $unit->is_active))>
                            <span>
                                وحدة نشطة
                                <span class="field-hint products-check-hint">قابلة للاختيار عند إنشاء منتج جديد</span>
                            </span>
                        </label>
                        @if ($productsCount > 0 && ! old('is_active', $unit->is_active))
                            <p class="customers-assign-note" role="note">
                                تعطيل الوحدة يمنع اختيارها لمنتجات جديدة؛ المنتجات المرتبطة الحالية تحتفظ بها.
                            </p>
                        @endif
                    </div>
                </div>

                <div class="admin-form-actions">
                    <button type="submit" class="btn btn-glass-primary">حفظ التعديلات</button>
                    <a href="{{ route('products.units.index') }}" class="btn btn-glass">إلغاء</a>
                </div>
            </form>
        </section>
    </div>
@endsection
