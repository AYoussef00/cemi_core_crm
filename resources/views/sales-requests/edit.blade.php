@extends('layouts.app')

@section('title', 'تعديل طلب بيع — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · تعديل طلب بيع</span>
@endsection

@section('content')
    <div class="sales-requests-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="sr-edit-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">تعديل · مسودة</p>
                <h1 id="sr-edit-heading" class="admin-title">تعديل المسودة #{{ $salesRequest->id }}</h1>
                <p class="admin-lead">يُسمح بالتعديل في حالة المسودة فقط. التوفر لكل بند اختيار صريح.</p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('sales-requests.show', $salesRequest) }}" class="btn btn-glass">عرض</a>
                <a href="{{ route('sales-requests.index') }}" class="btn btn-glass">القائمة</a>
            </div>
        </header>

        <section class="sr-form-card" aria-labelledby="sr-edit-fields-heading">
            <form method="POST" action="{{ route('sales-requests.update', $salesRequest) }}" class="auth-form admin-form customers-form" novalidate>
                @csrf
                @method('PUT')
                <h2 id="sr-edit-fields-heading" class="visually-hidden">تعديل بيانات الطلب</h2>
                @include('sales-requests._form')
                <div class="admin-form-actions">
                    <button type="submit" class="btn btn-glass-primary">حفظ التعديلات</button>
                    <a href="{{ route('sales-requests.show', $salesRequest) }}" class="btn btn-glass">إلغاء</a>
                </div>
            </form>
        </section>
    </div>
@endsection
