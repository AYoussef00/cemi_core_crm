@extends('layouts.app')

@section('title', 'الإعدادات — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">الإعدادات</span>
@endsection

@section('content')
    <section class="admin-panel panel-enter" aria-labelledby="settings-heading">
        <div class="admin-header settings-hub-header">
            <div>
                <h1 id="settings-heading" class="admin-title">إعدادات الشركة والمستودع</h1>
                <p class="admin-lead">
                    الإصدار الحالي يدعم شركة واحدة ومستودعًا واحدًا فقط.
                    لا تُخزَّن هنا قواعد ضريبة أو أسعار افتراضية — نموذج الضريبة ما زال قيد القرار.
                </p>
            </div>
        </div>

        @php
            $bothReady = $companyConfigured && $warehouseConfigured;
            $noneReady = ! $companyConfigured && ! $warehouseConfigured;
        @endphp

        @if ($noneReady)
            <div class="settings-empty-banner" role="status">
                <p class="settings-empty-banner-title">ابدأ بالإعداد الأساسي</p>
                <p class="settings-empty-banner-text">
                    لا توجد بيانات شركة أو مستودع محفوظة بعد. أكمل الملفّين مرة واحدة — بدون بيانات تجريبية تلقائية.
                </p>
            </div>
        @elseif ($bothReady)
            <div class="settings-ready-banner" role="status">
                <p class="settings-ready-banner-title">الأساسيات مكتملة</p>
                <p class="settings-ready-banner-text">الشركة والمستودع محفوظان. يمكنك تعديلهما في أي وقت من البطاقات أدناه.</p>
            </div>
        @endif

        <div class="settings-status-grid">
            <article class="settings-card {{ $companyConfigured ? 'is-complete' : 'is-pending' }}" aria-labelledby="company-status-heading">
                <div class="settings-card-top">
                    <div class="settings-card-icon" aria-hidden="true">ش</div>
                    <div>
                        <h2 id="company-status-heading" class="settings-card-title">ملف الشركة</h2>
                        @if ($companyConfigured)
                            <p class="settings-card-state settings-card-state-ok">مكتمل</p>
                        @else
                            <p class="settings-card-state settings-card-state-pending">لم يُعدَّ بعد</p>
                        @endif
                    </div>
                </div>

                @if ($companyConfigured)
                    <div class="settings-card-body">
                        <p class="settings-card-name"><strong>{{ $company->name_ar }}</strong></p>
                        @if ($company->address_ar)
                            <p class="settings-card-meta">{{ $company->address_ar }}</p>
                        @endif
                        @if ($company->contact_phone || $company->contact_email)
                            <ul class="settings-card-contacts">
                                @if ($company->contact_phone)
                                    <li class="dir-ltr">{{ $company->contact_phone }}</li>
                                @endif
                                @if ($company->contact_email)
                                    <li class="dir-ltr">{{ $company->contact_email }}</li>
                                @endif
                            </ul>
                        @endif
                    </div>
                    <a href="{{ route('admin.settings.company.edit') }}" class="btn btn-secondary settings-card-action">تعديل الشركة</a>
                @else
                    <div class="settings-card-body">
                        <p>لا توجد بيانات شركة محفوظة. ابدأ بإدخال الاسم والحقول الأساسية.</p>
                    </div>
                    <a href="{{ route('admin.settings.company.edit') }}" class="btn btn-primary settings-card-action">إعداد الشركة</a>
                @endif
            </article>

            <article class="settings-card {{ $warehouseConfigured ? 'is-complete' : 'is-pending' }}" aria-labelledby="warehouse-status-heading">
                <div class="settings-card-top">
                    <div class="settings-card-icon" aria-hidden="true">م</div>
                    <div>
                        <h2 id="warehouse-status-heading" class="settings-card-title">المستودع</h2>
                        @if ($warehouseConfigured)
                            <p class="settings-card-state settings-card-state-ok">مكتمل</p>
                        @else
                            <p class="settings-card-state settings-card-state-pending">لم يُعدَّ بعد</p>
                        @endif
                    </div>
                </div>

                @if ($warehouseConfigured)
                    <div class="settings-card-body">
                        <p class="settings-card-name"><strong>{{ $warehouse->name_ar }}</strong></p>
                        @if ($warehouse->address_ar)
                            <p class="settings-card-meta">{{ $warehouse->address_ar }}</p>
                        @endif
                        @if ($warehouse->contact_phone)
                            <ul class="settings-card-contacts">
                                <li class="dir-ltr">{{ $warehouse->contact_phone }}</li>
                            </ul>
                        @endif
                    </div>
                    <a href="{{ route('admin.settings.warehouse.edit') }}" class="btn btn-secondary settings-card-action">تعديل المستودع</a>
                @else
                    <div class="settings-card-body">
                        <p>لا يوجد مستودع محفوظ. يلزم مستودع واحد للعمليات لاحقًا.</p>
                    </div>
                    <a href="{{ route('admin.settings.warehouse.edit') }}" class="btn btn-primary settings-card-action">إعداد المستودع</a>
                @endif
            </article>

            @php
                $mailMode = $mailStatus->mode ?? 'incomplete';
                $mailCardClass = match ($mailMode) {
                    'provider' => 'is-complete',
                    'local' => 'is-local',
                    default => 'is-pending',
                };
                $mailStateClass = match ($mailMode) {
                    'provider' => 'settings-card-state-ok',
                    'local' => 'settings-card-state-local',
                    default => 'settings-card-state-pending',
                };
                $mailStateLabel = match ($mailMode) {
                    'provider' => 'جاهز للإرسال',
                    'local' => 'محلي فقط',
                    default => 'غير مكتمل',
                };
            @endphp
            <article class="settings-card {{ $mailCardClass }}" aria-labelledby="mail-status-heading">
                <div class="settings-card-top">
                    <div class="settings-card-icon" aria-hidden="true">ب</div>
                    <div>
                        <h2 id="mail-status-heading" class="settings-card-title">البريد الإلكتروني</h2>
                        <p class="settings-card-state {{ $mailStateClass }}">{{ $mailStateLabel }}</p>
                    </div>
                </div>
                <div class="settings-card-body">
                    <p class="settings-card-meta">{{ $mailStatus->readinessLabelAr }}</p>
                    <p>اختبار الإرسال إلى بريد حسابك فقط. لا يمكن تعديل بيانات الاعتماد من هنا.</p>
                </div>
                <a href="{{ route('admin.settings.mail.show') }}" class="btn btn-glass settings-card-action">حالة البريد</a>
            </article>
        </div>
    </section>
@endsection
