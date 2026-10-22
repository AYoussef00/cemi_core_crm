@extends('layouts.app')

@section('title', 'تعديل منشأة — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · تعديل منشأة</span>
@endsection

@section('content')
    <section class="admin-panel panel-enter customers-panel customers-form-panel" aria-labelledby="edit-customer-heading">
        <div class="admin-header">
            <div>
                <p class="page-kicker">تعديل · منشأة</p>
                <h1 id="edit-customer-heading" class="admin-title">تعديل المنشأة</h1>
                <p class="admin-lead">{{ $customer->name_ar }} — سجل منشأة/مؤسسة (بلا نوع فردي في الإصدار الأول).</p>
            </div>
            <a href="{{ route('customers.show', $customer) }}" class="btn btn-glass">رجوع للتفاصيل</a>
        </div>

        <form method="POST" action="{{ route('customers.update', $customer) }}" class="auth-form admin-form customers-form" novalidate>
            @csrf
            @method('PUT')

            <div class="customers-form-section">
                <h2 class="customers-form-section-title">بيانات المنشأة</h2>
                <p class="customers-form-section-lead">حدّث اسم المنشأة والتعيين. إزالة المندوب تمنع جدولة زيارات جديدة لهذه المنشأة.</p>

                <div class="auth-field">
                    <label for="name_ar">اسم المنشأة <span class="required-mark" title="مطلوب">*</span></label>
                    <input id="name_ar" name="name_ar" type="text" value="{{ old('name_ar', $customer->name_ar) }}"
                           required maxlength="255"
                           autocomplete="organization"
                           aria-required="true"
                           aria-describedby="name_ar-hint{{ $errors->has('name_ar') ? ' name_ar-error' : '' }}"
                           aria-invalid="{{ $errors->has('name_ar') ? 'true' : 'false' }}">
                    <p id="name_ar-hint" class="field-hint">الاسم الرسمي للمنشأة كما يُعرَف تجاريًا — ليس اسم شخص.</p>
                    @error('name_ar')
                        <p id="name_ar-error" class="auth-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                @if ($canAssignRep)
                    <div class="auth-field">
                        <label for="assigned_sales_rep_id">مندوب المبيعات المُعيَّن</label>
                        <select id="assigned_sales_rep_id" name="assigned_sales_rep_id"
                                aria-describedby="rep-assign-hint"
                                aria-invalid="{{ $errors->has('assigned_sales_rep_id') ? 'true' : 'false' }}">
                            <option value="">— بدون تعيين —</option>
                            @foreach ($salesReps as $rep)
                                <option value="{{ $rep->id }}"
                                    @selected(old('assigned_sales_rep_id', $customer->assigned_sales_rep_id) == $rep->id)>
                                    {{ $rep->name }}
                                </option>
                            @endforeach
                        </select>
                        <p id="rep-assign-hint" class="field-hint">هذا المندوب هو معيَّن كل زيارات المنشأة تلقائيًا — بلا تجاوز عند الجدولة.</p>
                        @error('assigned_sales_rep_id')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                @else
                    <p class="customers-assign-note" role="note">
                        مندوب المبيعات المُعيَّن: <strong>{{ $customer->assignedSalesRep?->name ?? '—' }}</strong>
                        — لا يمكن تغيير التعيين من حساب المندوب.
                    </p>
                @endif
            </div>

            @include('customers.partials.contact-fields', [
                'contactRoles' => $contactRoles,
                'existing' => $customer->contacts,
            ])

            <div class="admin-form-actions">
                <button type="submit" class="btn btn-glass-primary">حفظ التعديلات</button>
                <a href="{{ route('customers.show', $customer) }}" class="btn btn-glass">إلغاء</a>
            </div>
        </form>
    </section>
@endsection
