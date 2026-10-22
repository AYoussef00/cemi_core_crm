@extends('layouts.app')

@section('title', 'منتج جديد — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المخزون · منتج جديد</span>
@endsection

@section('content')
    <div class="products-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="create-product-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">إنشاء · كتالوج</p>
                <h1 id="create-product-heading" class="admin-title">منتج جديد</h1>
                <p class="admin-lead">
                    أدخل اسم المنتج ووحدة القياس الأساسية والمواصفات الفنية.
                    لا يُنشأ رقم منتج تلقائيًا، ولا تُدار أرصدة المخزون أو أسعار البيع هنا.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('products.index') }}" class="btn btn-glass">رجوع للقائمة</a>
            </div>
        </header>

        @if ($units->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface products-units-gate" role="status">
                <strong>لا توجد وحدات قياس نشطة</strong>
                <p>أنشئ وحدة قياس واحدة على الأقل قبل إضافة منتج. لا يمكن المتابعة بلا وحدة أساسية.</p>
                <a href="{{ route('products.units.create') }}" class="btn btn-glass-primary">+ وحدة قياس</a>
            </div>
        @else
            <section class="products-form-card" aria-labelledby="product-create-fields-heading">
                <form method="POST" action="{{ route('products.store') }}" class="auth-form admin-form customers-form" novalidate>
                    @csrf

                    <div class="customers-form-section">
                        <h2 id="product-create-fields-heading" class="customers-form-section-title">بيانات المنتج</h2>
                        <p class="customers-form-section-lead">الاسم ووحدة القياس مطلوبان. تكلفة الشراء اختيارية وتظهر فقط لمن لديهم صلاحية التكلفة.</p>

                        <div class="auth-field">
                            <label for="name_ar">اسم المنتج <span class="required-mark" title="مطلوب">*</span></label>
                            <input id="name_ar" name="name_ar" type="text" value="{{ old('name_ar') }}" required maxlength="255"
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
                                <option value="">— اختر وحدة —</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}" @selected(old('unit_id') == $unit->id)>
                                        {{ $unit->displayLabel() }}
                                    </option>
                                @endforeach
                            </select>
                            <p id="unit-id-hint" class="field-hint">وحدة أساسية واحدة لكل منتج — بلا تحويلات بين الوحدات في هذه المرحلة.</p>
                            @error('unit_id')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="auth-field">
                            <label for="purchase_cost">تكلفة الشراء</label>
                            <input id="purchase_cost" name="purchase_cost" type="number" step="0.01" min="0"
                                   value="{{ old('purchase_cost') }}"
                                   class="dir-ltr"
                                   aria-describedby="purchase-cost-hint"
                                   aria-invalid="{{ $errors->has('purchase_cost') ? 'true' : 'false' }}">
                            <p id="purchase-cost-hint" class="field-hint">اختياري. قيمة غير سالبة بدقة منزلتين عشريتين.</p>
                            @error('purchase_cost')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    @include('products.partials.spec-fields', [
                        'density' => null,
                        'existing' => collect(),
                        'blankSpecRows' => $blankSpecRows,
                    ])

                    <div class="admin-form-actions">
                        <button type="submit" class="btn btn-glass-primary">حفظ المنتج</button>
                        <a href="{{ route('products.index') }}" class="btn btn-glass">إلغاء</a>
                    </div>
                </form>
            </section>
        @endif
    </div>
@endsection
