@extends('layouts.app')

@section('title', 'العملاء — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · العملاء</span>
@endsection

@section('content')
    <div class="customers-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="customers-heading">
            <div class="ds-page-bar-copy">
                <h1 id="customers-heading" class="admin-title">العملاء</h1>
            </div>
            @if ($canManage)
                <div class="ds-page-bar-tools">
                    <a href="{{ route('customers.create') }}" class="btn btn-glass-primary">+ منشأة جديدة</a>
                </div>
            @endif
        </header>

        @php $customersFilteredEmpty = $customers->isEmpty() && $q !== ''; @endphp

        @if ($customers->isEmpty() && ! $customersFilteredEmpty)
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا توجد منشآت</strong>
                <p>لا توجد منشآت ضمن نطاق صلاحيتك بعد. لن تُعرض بيانات تجريبية هنا.</p>
            </div>
        @else
            <section class="ds-list-panel" aria-label="قائمة العملاء">
                <div class="ds-list-toolbar">
                    <div class="ds-list-meta">
                        <span class="ds-list-count dir-ltr">{{ number_format($customers->total()) }}</span>
                        <span class="ds-list-count-label">منشأة ضمن نطاقك</span>
                    </div>
                    <form method="GET" action="{{ route('customers.index') }}" class="ds-list-filters" role="search">
                        <div class="ds-data-search">
                            <svg class="ds-data-search-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M16.2 16.2 20 20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                            <label class="visually-hidden" for="customer-q">بحث في العملاء</label>
                            <input id="customer-q" type="search" name="q" value="{{ $q }}"
                                   placeholder="بحث باسم المنشأة أو الهاتف أو البريد…"
                                   autocomplete="off">
                        </div>
                        <button type="submit" class="btn btn-glass btn-sm">تصفية</button>
                        @if ($q !== '')
                            <a href="{{ route('customers.index') }}" class="btn btn-glass btn-sm">مسح</a>
                        @endif
                    </form>
                </div>

                @if ($customers->isEmpty())
                    <div class="ds-data-empty ds-list-empty" role="status">
                        <strong>لا نتائج مطابقة</strong>
                        <p>لا منشآت ضمن التصفية الحالية. امسح البحث أو جرّب اسمًا مختلفًا.</p>
                    </div>
                @else
                    <div class="ds-list-body">
                        <table class="admin-table admin-table--sheet">
                            <thead>
                                <tr>
                                    <th scope="col">المنشأة</th>
                                    <th scope="col">مندوب المبيعات</th>
                                    <th scope="col">جهات الاتصال</th>
                                    <th scope="col" class="quotes-col-actions"><span class="visually-hidden">إجراء</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($customers as $customer)
                                    <tr>
                                        <td>
                                            <div class="quotes-customer-block">
                                                <a href="{{ route('customers.show', $customer) }}" class="quotes-id-link">
                                                    {{ $customer->name_ar }}
                                                </a>
                                                <span class="quotes-meta-line dir-ltr">#{{ $customer->id }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            @if ($customer->assignedSalesRep)
                                                <span class="ds-cell-primary">{{ $customer->assignedSalesRep->name }}</span>
                                            @else
                                                <span class="ds-cell-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="quotes-sr-chip is-static dir-ltr" title="عدد جهات الاتصال المحفوظة">
                                                {{ $customer->contacts_count }}
                                            </span>
                                        </td>
                                        <td class="ds-cell-actions quotes-col-actions">
                                            <div class="quotes-row-actions">
                                                <a href="{{ route('customers.show', $customer) }}" class="btn btn-glass btn-sm">عرض</a>
                                                <details class="ds-row-menu">
                                                    <summary aria-label="إجراءات {{ $customer->name_ar }}">⋮</summary>
                                                    <div class="ds-row-menu-panel">
                                                        <a href="{{ route('customers.show', $customer) }}">عرض التفاصيل</a>
                                                        @can('update', $customer)
                                                            <a href="{{ route('customers.edit', $customer) }}">تعديل</a>
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

                @if ($customers->hasPages())
                    <div class="ds-list-footer">{{ $customers->links() }}</div>
                @endif
            </section>
        @endif
    </div>
@endsection
