@extends('layouts.app')

@section('title', 'تسوية مخزون جديدة — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المخزون · تسوية جديدة</span>
@endsection

@section('content')
    <section class="admin-panel panel-enter customers-panel customers-form-panel inventory-form-panel" aria-labelledby="create-adj-heading">
        <div class="admin-header">
            <div>
                <p class="page-kicker">تسجيل · مستودع</p>
                <h1 id="create-adj-heading" class="admin-title">تسوية مخزون جديدة</h1>
                <p class="admin-lead">
                    أدخل كمية موجبة للزيادة أو سالبة للنقصان.
                    لن يتغيّر الرصيد حتى يعتمد مدير المبيعات التسوية.
                    النقصان يُرفض عند الاعتماد إذا أصبح المتاح سالبًا.
                </p>
            </div>
            <a href="{{ route('inventory.adjustments.index') }}" class="btn btn-glass">رجوع</a>
        </div>

        @unless ($warehouse)
            <div class="ds-data-empty is-compact" role="alert">
                <strong>المستودع غير مُعدّ</strong>
                <p>أكمل إعداد المستودع قبل تسجيل تسوية.</p>
            </div>
        @elseif ($products->isEmpty())
            <div class="ds-data-empty is-compact" role="status">
                <strong>لا توجد منتجات</strong>
                <p>أنشئ منتجًا في الكتالوج أولاً.</p>
            </div>
        @else
            <form method="POST" action="{{ route('inventory.adjustments.store') }}" class="auth-form admin-form customers-form" novalidate>
                @csrf
                <div class="customers-form-section">
                    <h2 class="customers-form-section-title">بيانات التسوية</h2>
                    <p class="customers-form-section-lead">المنتج والكمية الموقّعة والسبب مطلوبة. لا تُقبل كمية صفر.</p>

                    <div class="auth-field">
                        <label for="product_id">المنتج <span class="required-mark" title="مطلوب">*</span></label>
                        <select id="product_id" name="product_id" required
                                aria-required="true"
                                aria-invalid="{{ $errors->has('product_id') ? 'true' : 'false' }}">
                            <option value="">— اختر منتجًا —</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>
                                    {{ $product->name_ar }} ({{ $product->unit?->displayLabel() ?? '—' }})
                                </option>
                            @endforeach
                        </select>
                        @error('product_id')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="auth-field">
                        <label for="quantity_delta">كمية التسوية <span class="required-mark" title="مطلوب">*</span></label>
                        <input id="quantity_delta" name="quantity_delta" type="number" step="0.001" required
                               value="{{ old('quantity_delta') }}" class="dir-ltr"
                               aria-describedby="qty-hint"
                               aria-required="true"
                               aria-invalid="{{ $errors->has('quantity_delta') ? 'true' : 'false' }}">
                        <p id="qty-hint" class="field-hint">موجب = زيادة الرصيد · سالب = نقصان. لا تُقبل قيمة صفر.</p>
                        @error('quantity_delta')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="auth-field">
                        <label for="reason_ar">سبب التسوية <span class="required-mark" title="مطلوب">*</span></label>
                        <textarea id="reason_ar" name="reason_ar" rows="3" required maxlength="1000"
                                  aria-required="true"
                                  aria-invalid="{{ $errors->has('reason_ar') ? 'true' : 'false' }}"
                                  placeholder="مثال: جرد دوري / تلف / تصحيح إدخال…">{{ old('reason_ar') }}</textarea>
                        @error('reason_ar')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="admin-form-actions">
                    <button type="submit" class="btn btn-glass-primary">إرسال للاعتماد</button>
                    <a href="{{ route('inventory.adjustments.index') }}" class="btn btn-glass">إلغاء</a>
                </div>
            </form>
        @endif
    </section>
@endsection
