@extends('layouts.app')

@section('title', 'طلب بيع جديد — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · طلب بيع جديد</span>
@endsection

@section('content')
    <div class="sales-requests-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="sr-create-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">إنشاء · مسودة</p>
                <h1 id="sr-create-heading" class="admin-title">طلب بيع جديد</h1>
                <p class="admin-lead">
                    مسودة مرتبطة بمنشأة. حدّد التوفر لكل بند صراحةً — الرصيد استرشادي فقط.
                    الإرسال من شاشة التفاصيل لاحقًا.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('sales-requests.index') }}" class="btn btn-glass">القائمة</a>
            </div>
        </header>

        @if ($customers->isEmpty() || $products->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>تعذر إنشاء الطلب</strong>
                <p>
                    @if ($customers->isEmpty())
                        لا توجد منشآت ضمن نطاقك.
                    @else
                        لا توجد منتجات في الكتالوج بعد.
                    @endif
                </p>
            </div>
        @else
            <section class="sr-form-card" aria-labelledby="sr-create-fields-heading">
                <form method="POST" action="{{ route('sales-requests.store') }}" class="auth-form admin-form customers-form" novalidate>
                    @csrf
                    <h2 id="sr-create-fields-heading" class="visually-hidden">بيانات طلب البيع</h2>
                    @include('sales-requests._form', ['salesRequest' => null, 'preselectCustomerId' => $preselectCustomerId])
                    <div class="admin-form-actions">
                        <button type="submit" class="btn btn-glass-primary">حفظ المسودة</button>
                        <a href="{{ route('sales-requests.index') }}" class="btn btn-glass">إلغاء</a>
                    </div>
                </form>
            </section>
        @endif
    </div>
@endsection
