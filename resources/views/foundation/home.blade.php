@extends('layouts.app')

@section('title', config('app.name').' — الرئيسية')

@section('page_heading')
    <span class="topbar-muted">الرئيسية</span>
@endsection

@section('content')
    <div class="page-header">
        <div>
            <p class="page-kicker">منصة العمليات التجارية</p>
            <h1 class="page-title">{{ config('app.name') }}</h1>
            <p class="page-lead">
                مساحة عمل داخلية بالعربية لإدارة العملاء وحدود الائتمان وفريق العمل.
                تظهر لك الأقسام المتاحة حسب صلاحياتك.
            </p>
        </div>
        <div class="page-actions">
            @auth
                <a href="{{ route('account.profile') }}" class="btn btn-glass">حسابي</a>
                @can('settings.manage')
                    <a href="{{ route('admin.settings.index') }}" class="btn btn-glass-primary">الإعدادات</a>
                @endcan
            @else
                <a href="{{ route('login') }}" class="btn btn-glass-primary">تسجيل الدخول</a>
            @endauth
        </div>
    </div>

    <div class="status-grid">
        <article class="status-card">
            <div class="status-card-ico" aria-hidden="true">١</div>
            <strong>العملاء والائتمان</strong>
            <span>سجّل بيانات العملاء، وتابع حدود الائتمان وطلباتها، وحدّد مندوب المبيعات المُعيَّن لكل عميل.</span>
        </article>
        <article class="status-card">
            <div class="status-card-ico" aria-hidden="true">٢</div>
            <strong>الأدوار والصلاحيات</strong>
            <span>أدِر المستخدمين وأدوارهم وجهات الاعتماد، وراجع سجل التدقيق عند الحاجة.</span>
        </article>
        <article class="status-card">
            <div class="status-card-ico" aria-hidden="true">٣</div>
            <strong>الإشعارات وحسابك</strong>
            <span>تابع إشعاراتك، وحدّث بياناتك، واستعد كلمة المرور بأمان. الدخول للمستخدمين المعتمدين فقط.</span>
        </article>
    </div>
@endsection
