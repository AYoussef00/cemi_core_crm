@extends('layouts.app')

@section('title', 'الزيارات — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · الزيارات</span>
@endsection

@section('content')
    <div class="visits-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="visits-heading">
            <div class="ds-page-bar-copy">
                <h1 id="visits-heading" class="admin-title">الزيارات</h1>
                <p class="admin-lead">
                    جدولة وتسجيل نتائج الزيارات للمنشآت ضمن نطاق صلاحيتك.
                    الحالات: مجدولة → مكتملة أو ملغاة (نهائية).
                    المندوب دائمًا مندوب المنشأة المُعيَّن — بلا تجاوز أو تكرار أو GPS.
                </p>
            </div>
            @if ($canCreate)
                <div class="ds-page-bar-tools">
                    <a href="{{ route('visits.create') }}" class="btn btn-glass-primary">+ جدولة زيارة</a>
                </div>
            @endif
        </header>

        @php $visitsFilteredEmpty = $visits->isEmpty() && ($q !== '' || $statusFilter !== ''); @endphp

        @if ($visits->isEmpty() && ! $visitsFilteredEmpty)
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا توجد زيارات</strong>
                <p>لا توجد زيارات ضمن نطاق صلاحيتك بعد. لن تُعرض سجلات وهمية هنا.</p>
            </div>
        @else
            <section class="ds-list-panel" aria-label="قائمة الزيارات">
                <div class="ds-list-toolbar">
                    <div class="ds-list-meta">
                        <span class="ds-list-count dir-ltr">{{ number_format($visits->total()) }}</span>
                        <span class="ds-list-count-label">زيارة ضمن نطاقك</span>
                    </div>
                    <form method="GET" action="{{ route('visits.index') }}" class="ds-list-filters" role="search">
                        <div class="ds-data-search">
                            <svg class="ds-data-search-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M16.2 16.2 20 20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                            <label class="visually-hidden" for="visit-q">بحث في الزيارات</label>
                            <input id="visit-q" type="search" name="q" value="{{ $q }}"
                                   placeholder="بحث باسم المنشأة أو المندوب أو الملاحظات…"
                                   autocomplete="off">
                        </div>
                        <label class="visually-hidden" for="visit-status">الحالة</label>
                        <select id="visit-status" name="status" class="visits-status-filter" aria-label="تصفية حسب الحالة"
                                onchange="this.form.submit()">
                            <option value="">كل الحالات</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected($statusFilter === $status->value)>
                                    {{ $status->labelAr() }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-glass btn-sm">تصفية</button>
                        @if ($q !== '' || $statusFilter !== '')
                            <a href="{{ route('visits.index') }}" class="btn btn-glass btn-sm">مسح</a>
                        @endif
                    </form>
                </div>

                @if ($visits->isEmpty())
                    <div class="ds-data-empty ds-list-empty" role="status">
                        <strong>لا نتائج مطابقة</strong>
                        <p>لا زيارات ضمن التصفية الحالية. امسح البحث أو غيّر الحالة.</p>
                    </div>
                @else
                    <div class="ds-list-body">
                        <table class="admin-table admin-table--sheet">
                            <thead>
                                <tr>
                                    <th scope="col">الزيارة</th>
                                    <th scope="col">المنشأة</th>
                                    <th scope="col">المندوب</th>
                                    <th scope="col">الحالة</th>
                                    <th scope="col" class="quotes-col-actions"><span class="visually-hidden">إجراء</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($visits as $visit)
                                    @php
                                        $statusValue = $visit->status instanceof \BackedEnum
                                            ? $visit->status->value
                                            : (string) $visit->status;
                                        $badge = match ($statusValue) {
                                            'completed' => 'badge-success',
                                            'cancelled' => 'badge-danger',
                                            default => 'badge-warning',
                                        };
                                        $when = $visit->scheduled_at?->timezone(config('app.timezone'))->format('Y-m-d H:i');
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="quotes-id-block">
                                                <a href="{{ route('visits.show', $visit) }}" class="quotes-id-link dir-ltr">#{{ $visit->id }}</a>
                                                <span class="quotes-meta-line dir-ltr">{{ $when ?? '—' }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="quotes-customer-block">
                                                <span class="ds-cell-primary">{{ $visit->customer?->name_ar ?? '—' }}</span>
                                            </div>
                                        </td>
                                        <td>{{ $visit->assignedUser?->name ?? '—' }}</td>
                                        <td>
                                            <span class="badge {{ $badge }}">{{ $visit->statusLabelAr() }}</span>
                                        </td>
                                        <td class="ds-cell-actions quotes-col-actions">
                                            <div class="quotes-row-actions">
                                                <a href="{{ route('visits.show', $visit) }}" class="btn btn-glass btn-sm">عرض</a>
                                                <details class="ds-row-menu">
                                                    <summary aria-label="إجراءات الزيارة">⋮</summary>
                                                    <div class="ds-row-menu-panel">
                                                        <a href="{{ route('visits.show', $visit) }}">عرض التفاصيل</a>
                                                        @can('update', $visit)
                                                            <a href="{{ route('visits.edit', $visit) }}">تعديل الموعد</a>
                                                        @endcan
                                                    </div>
                                                </details>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($visits->hasPages())
                    <div class="ds-list-footer">{{ $visits->links() }}</div>
                @endif
            </section>
        @endif
    </div>
@endsection
