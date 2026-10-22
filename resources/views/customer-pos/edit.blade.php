@extends('layouts.app')

@section('title', 'تعديل أمر شراء '.$po->document_number.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · تعديل أمر الشراء</span>
@endsection

@section('content')
    <div class="customer-pos-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="cpo-edit-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">تعديل · مفتوح</p>
                <h1 id="cpo-edit-heading" class="admin-title">تعديل أمر شراء {{ $po->document_number }}</h1>
                <p class="admin-lead">
                    صفقة <span class="dir-ltr">#{{ $po->deal?->id ?? '—' }}</span> ({{ $po->deal?->status?->labelAr() ?? '—' }}) · البنود ثابتة بعد الإنشاء في هذا الإصدار.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('customer-pos.show', $po) }}" class="btn btn-glass">عرض</a>
                <a href="{{ route('customer-pos.index') }}" class="btn btn-glass">القائمة</a>
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="cpo-form-card" aria-labelledby="cpo-edit-fields">
            <form method="POST" action="{{ route('customer-pos.update', $po) }}" class="auth-form admin-form customers-form" novalidate>
                @csrf
                @method('PUT')
                <h2 id="cpo-edit-fields" class="visually-hidden">تعديل المرجع والملاحظات</h2>
                <div class="customers-form-section">
                    <h2 class="customers-form-section-title">المرجع والملاحظات</h2>
                    <p class="customers-form-section-lead">لا يمكن تعديل البنود أو عرض السعر بعد الإنشاء.</p>
                    <div class="auth-field">
                        <label for="reference_ar">مرجع أمر الشراء</label>
                        <input id="reference_ar" type="text" name="reference_ar" value="{{ old('reference_ar', $po->reference_ar) }}" maxlength="255">
                        @error('reference_ar')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="auth-field">
                        <label for="notes_ar">ملاحظات</label>
                        <textarea id="notes_ar" name="notes_ar" rows="3" maxlength="2000">{{ old('notes_ar', $po->notes_ar) }}</textarea>
                        @error('notes_ar')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="admin-form-actions">
                    <button type="submit" class="btn btn-glass-primary">حفظ</button>
                    <a href="{{ route('customer-pos.show', $po) }}" class="btn btn-glass">إلغاء</a>
                </div>
            </form>
        </section>
    </div>
@endsection
