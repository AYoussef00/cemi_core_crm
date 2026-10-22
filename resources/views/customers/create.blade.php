@extends('layouts.app')

@section('title', 'منشأة جديدة — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · منشأة جديدة</span>
@endsection

@section('content')
    <section class="admin-panel panel-enter customers-panel customers-form-panel" aria-labelledby="create-customer-heading">
        <div class="admin-header">
            <div>
                <p class="page-kicker">إنشاء · منشأة</p>
                <h1 id="create-customer-heading" class="admin-title">منشأة جديدة</h1>
                <p class="admin-lead">
                    سجّل منشأة/مؤسسة تجارية فقط — بلا حسابات أفراد في الإصدار الأول.
                    أدخل <strong>اسم المنشأة</strong> وجهات الاتصال الموثّقة. لا يُنشأ رقم عميل تلقائيًا، ولا تُطلب خريطة أو ائتمان هنا.
                </p>
            </div>
            <a href="{{ route('customers.index') }}" class="btn btn-glass">رجوع للقائمة</a>
        </div>

        <form method="POST" action="{{ route('customers.store') }}" class="auth-form admin-form customers-form" novalidate>
            @csrf

            <div class="customers-form-section">
                <h2 class="customers-form-section-title">بيانات المنشأة</h2>
                <p class="customers-form-section-lead">اسم المنشأة هو المعرّف التجاري للسجل. لا توجد حقول لنوع فردي أو رقم سجل تجاري في هذا الإصدار.</p>

                <div class="auth-field">
                    <label for="name_ar">اسم المنشأة <span class="required-mark" title="مطلوب">*</span></label>
                    <input id="name_ar" name="name_ar" type="text" value="{{ old('name_ar') }}" required maxlength="255"
                           autocomplete="organization"
                           placeholder="مثال: شركة …"
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
                                <option value="{{ $rep->id }}" @selected(old('assigned_sales_rep_id') == $rep->id)>
                                    {{ $rep->name }}
                                </option>
                            @endforeach
                        </select>
                        <p id="rep-assign-hint" class="field-hint">بدون مندوب لا يمكن جدولة زيارات لهذه المنشأة لاحقًا.</p>
                        @error('assigned_sales_rep_id')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                @else
                    <p class="customers-assign-note" role="note">
                        ستُسجَّل هذه المنشأة باسمك بصفتك مندوب المبيعات المُعيَّن، ولا يمكنك تغيير ذلك من هنا.
                    </p>
                @endif
            </div>

            @include('customers.partials.contact-fields', [
                'contactRoles' => $contactRoles,
                'existing' => collect(),
            ])

            <div class="admin-form-actions">
                <button type="submit" class="btn btn-glass-primary">حفظ المنشأة</button>
                <a href="{{ route('customers.index') }}" class="btn btn-glass">إلغاء</a>
            </div>
        </form>
    </section>
@endsection
