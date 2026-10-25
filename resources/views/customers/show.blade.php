@extends('layouts.app')

@section('title', $customer->name_ar.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · العملاء</span>
@endsection

@section('content')
    @php
        $commercial = $commercial ?? [];
        $showMoney = ($commercial['permissions']['prices'] ?? false)
            || ($commercial['permissions']['invoices'] ?? false);
    @endphp

    <div class="customer-show-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="customer-show-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">منشأة · #{{ $customer->id }}</p>
                <h1 id="customer-show-heading" class="admin-title">{{ $customer->name_ar }}</h1>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('customers.index') }}" class="btn btn-glass">القائمة</a>
                @if ($canUpdate)
                    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-glass-primary">تعديل</a>
                @endif
            </div>
        </header>

        <nav class="customer-quick-nav" aria-label="اختصارات المنشأة">
            <a href="{{ route('customers.location.edit', $customer) }}" class="customer-quick-link">الموقع</a>
            <a href="{{ route('customers.photos.index', $customer) }}" class="customer-quick-link">الصور</a>
            @can('viewProfile', [\App\Models\CreditLimitRequest::class, $customer])
                <a href="{{ route('customers.credit.show', $customer) }}" class="customer-quick-link">الملف الائتماني</a>
            @endcan
            @if ($canScheduleVisit ?? false)
                <a href="{{ route('visits.create', ['customer_id' => $customer->id]) }}" class="customer-quick-link">جدولة زيارة</a>
            @endif
            @if ($canCreateSalesRequest ?? false)
                <a href="{{ route('sales-requests.create', ['customer_id' => $customer->id]) }}" class="customer-quick-link">طلب بيع</a>
            @endif
        </nav>

        <section class="so-detail-section" aria-labelledby="cust-profile-heading">
            <div class="so-detail-section-head">
                <h2 id="cust-profile-heading" class="customers-form-section-title">بيانات المنشأة</h2>
            </div>
            <div class="so-detail-grid" role="list">
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">اسم المنشأة</p>
                    <p class="so-detail-value">{{ $customer->name_ar }}</p>
                </article>
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">مندوب المبيعات</p>
                    <p class="so-detail-value">{{ $customer->assignedSalesRep?->name ?? '—' }}</p>
                </article>
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">تاريخ الإنشاء</p>
                    <p class="so-detail-value dir-ltr">
                        {{ $customer->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                    </p>
                </article>
                @if ($showMoney)
                    <article class="so-detail-field {{ (float) ($commercial['exposure'] ?? 0) > 0 ? 'is-warn' : '' }}" role="listitem">
                        <p class="so-detail-label">رصيد المديونية</p>
                        <p class="so-detail-value dir-ltr">
                            {{ money($commercial['exposure'] ?? '0.00') }}
                            <span class="so-detail-unit">ر.س</span>
                        </p>
                    </article>
                    <article class="so-detail-field" role="listitem">
                        <p class="so-detail-label">حد الائتمان</p>
                        <p class="so-detail-value dir-ltr">
                            @if (($commercial['credit_limit'] ?? null) !== null)
                                {{ money($commercial['credit_limit']) }}
                                <span class="so-detail-unit">ر.س</span>
                            @else
                                غير محدد
                            @endif
                        </p>
                    </article>
                    <article class="so-detail-field {{ ($commercial['overdue_invoices_count'] ?? 0) > 0 ? 'is-warn' : '' }}" role="listitem">
                        <p class="so-detail-label">فواتير متأخرة</p>
                        <p class="so-detail-value">
                            @if (($commercial['overdue_invoices_count'] ?? 0) > 0)
                                <span class="badge badge-warning">{{ number_format($commercial['overdue_invoices_count']) }}</span>
                            @else
                                لا يوجد
                            @endif
                        </p>
                    </article>
                @endif
            </div>
        </section>

        <section class="so-detail-section" aria-labelledby="cust-contacts-heading">
            <div class="so-detail-section-head">
                <h2 id="cust-contacts-heading" class="customers-form-section-title">جهات الاتصال</h2>
            </div>

            @if ($customer->contacts->isEmpty())
                <div class="customers-empty-state is-compact" role="status">
                    <strong>لا توجد جهات اتصال</strong>
                    <p class="muted">لم تُضف أي جهة اتصال لهذه المنشأة بعد.</p>
                </div>
            @else
                <div class="customers-contact-cards" role="list">
                    @foreach ($customer->contacts as $contact)
                        @php
                            $roleValue = $contact->role instanceof \BackedEnum
                                ? $contact->role->value
                                : (string) $contact->role;
                            $roleClass = $roleValue === 'accounting' ? 'is-accounting' : 'is-account';
                        @endphp
                        <article class="customers-contact-card {{ $roleClass }}" role="listitem">
                            <div class="customers-contact-card-top">
                                <span class="customers-contact-role">{{ $contact->roleLabelAr() }}</span>
                            </div>
                            <h3 class="customers-contact-name">{{ $contact->name_ar }}</h3>
                            <ul class="customers-contact-meta">
                                <li>
                                    <span class="customers-contact-meta-label">الهاتف</span>
                                    <span class="dir-ltr">{{ $contact->phone ?: '—' }}</span>
                                </li>
                                <li>
                                    <span class="customers-contact-meta-label">البريد</span>
                                    <span class="dir-ltr">{{ $contact->email ?: '—' }}</span>
                                </li>
                            </ul>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        @include('customers.partials.commercial-hub', ['commercial' => $commercial])

        @if ($canViewVisits ?? false)
            <section class="so-detail-section" aria-labelledby="cust-visits-heading">
                <div class="so-detail-section-head customer-section-tools">
                    <div>
                        <h2 id="cust-visits-heading" class="customers-form-section-title">سجل الزيارات</h2>
                    </div>
                    @if ($canScheduleVisit ?? false)
                        <a href="{{ route('visits.create', ['customer_id' => $customer->id]) }}" class="btn btn-glass btn-sm">جدولة زيارة</a>
                    @endif
                </div>

                @if ($customer->visits->isEmpty())
                    <div class="ds-data-empty is-compact" role="status">
                        <strong>لا توجد زيارات</strong>
                    </div>
                @else
                    <div class="ds-table-surface">
                        <div class="ds-data-body">
                            <table class="admin-table visits-table">
                                <thead>
                                    <tr>
                                        <th scope="col">الموعد</th>
                                        <th scope="col">المندوب</th>
                                        <th scope="col">الحالة</th>
                                        <th scope="col">إجراء</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($customer->visits as $visit)
                                        @php
                                            $statusValue = $visit->status instanceof \BackedEnum
                                                ? $visit->status->value
                                                : (string) $visit->status;
                                            $badge = match ($statusValue) {
                                                'completed' => 'badge-success',
                                                'cancelled' => 'badge-danger',
                                                default => 'badge-warning',
                                            };
                                        @endphp
                                        <tr>
                                            <td class="dir-ltr ds-cell-muted">
                                                {{ $visit->scheduled_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                            </td>
                                            <td>{{ $visit->assignedUser?->name ?? '—' }}</td>
                                            <td><span class="badge {{ $badge }}">{{ $visit->statusLabelAr() }}</span></td>
                                            <td class="ds-cell-actions">
                                                <a href="{{ route('visits.show', $visit) }}" class="customers-name-link">عرض</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </section>
        @endif
    </div>
@endsection
