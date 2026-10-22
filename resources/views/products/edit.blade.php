@extends('layouts.app')

@section('title', 'تعديل منتج — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المخزون · تعديل منتج</span>
@endsection

@section('content')
    <div class="products-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="edit-product-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">تعديل · كتالوج</p>
                <h1 id="edit-product-heading" class="admin-title">تعديل المنتج</h1>
                <p class="admin-lead">
                    {{ $product->name_ar }} — حدّث البيانات الأساسية والمواصفات.
                    لا تُدار أرصدة المخزون من هذه الشاشة.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('products.show', $product) }}" class="btn btn-glass">رجوع للتفاصيل</a>
            </div>
        </header>

        <section class="products-form-card" aria-labelledby="product-edit-fields-heading">
            <form method="POST" action="{{ route('products.update', $product) }}" class="auth-form admin-form customers-form" novalidate>
                @csrf
                @method('PUT')

                <div class="customers-form-section">
                    <h2 id="product-edit-fields-heading" class="customers-form-section-title">بيانات المنتج</h2>
                    <p class="customers-form-section-lead">الاسم ووحدة القياس مطلوبان. يمكن الإبقاء على وحدة غير نشطة إن كانت مرتبطة حاليًا بهذا المنتج.</p>

                    <div class="auth-field">
                        <label for="name_ar">اسم المنتج <span class="required-mark" title="مطلوب">*</span></label>
                        <input id="name_ar" name="name_ar" type="text"
                               value="{{ old('name_ar', $product->name_ar) }}" required maxlength="255"
                               aria-required="true"
                               aria-invalid="{{ $errors->has('name_ar') ? 'true' : 'false' }}">
                        @error('name_ar')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="auth-field">
                        <label for="unit_id">وحدة القياس <span class="required-mark" title="مطلوب">*</span></label>
                        <select id="unit_id" name="unit_id" required aria-required="true"
                                aria-describedby="unit-id-hint"
                                aria-invalid="{{ $errors->has('unit_id') ? 'true' : 'false' }}">
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}"
                                    @selected(old('unit_id', $product->unit_id) == $unit->id)>
                                    {{ $unit->displayLabel() }}
                                    @if (! $unit->is_active) (غير نشطة) @endif
                                </option>
                            @endforeach
                        </select>
                        <p id="unit-id-hint" class="field-hint">وحدة أساسية واحدة — بلا تحويلات بين الوحدات.</p>
                        @error('unit_id')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="auth-field">
                        <label for="purchase_cost">تكلفة الشراء</label>
                        <input id="purchase_cost" name="purchase_cost" type="number" step="0.01" min="0"
                               value="{{ old('purchase_cost', $product->purchase_cost) }}"
                               class="dir-ltr"
                               aria-describedby="purchase-cost-hint"
                               aria-invalid="{{ $errors->has('purchase_cost') ? 'true' : 'false' }}">
                        <p id="purchase-cost-hint" class="field-hint">اختياري. اتركه فارغًا إن لم تُعرَّف تكلفة بعد.</p>
                        @error('purchase_cost')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @include('products.partials.spec-fields', [
                    'density' => $density,
                    'existing' => $otherSpecs,
                    'blankSpecRows' => $blankSpecRows,
                ])

                <div class="admin-form-actions">
                    <button type="submit" class="btn btn-glass-primary">حفظ التعديلات</button>
                    <a href="{{ route('products.show', $product) }}" class="btn btn-glass">إلغاء</a>
                </div>
            </form>
        </section>
    </div>
@endsection
