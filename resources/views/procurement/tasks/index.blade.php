@extends('layouts.app')

@section('title', 'مهام المشتريات — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المشتريات · قائمة المهام</span>
@endsection

@section('content')
    <div class="procurement-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="proc-heading">
            <div class="ds-page-bar-copy">
                <h1 id="proc-heading" class="admin-title">مهام المشتريات</h1>
            </div>
        </header>

        @php $procFilteredEmpty = $tasks->isEmpty() && ($q !== '' || $status !== ''); @endphp

        @if ($tasks->isEmpty() && ! $procFilteredEmpty)
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا توجد مهام مشتريات</strong>
                <p>تظهر المهام بعد إرسال طلب بيع يتضمن بنودًا تحتاج توريدًا، ضمن نطاق صلاحيتك.</p>
            </div>
        @else
            <section class="ds-list-panel" aria-label="قائمة مهام المشتريات">
                <div class="ds-list-toolbar">
                    <div class="ds-list-meta">
                        <span class="ds-list-count dir-ltr">{{ number_format($tasks->total()) }}</span>
                        <span class="ds-list-count-label">مهمة ضمن نطاقك</span>
                    </div>
                    <form method="GET" action="{{ route('procurement.tasks.index') }}" class="ds-list-filters" role="search">
                        <div class="ds-data-search">
                            <svg class="ds-data-search-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M16.2 16.2 20 20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                            <label class="visually-hidden" for="proc-q">بحث</label>
                            <input id="proc-q" type="search" name="q" value="{{ $q }}" placeholder="بحث بالمنتج أو المنشأة…" autocomplete="off">
                        </div>
                        <label class="visually-hidden" for="proc-status">الحالة</label>
                        <select id="proc-status" name="status" class="visits-status-filter" onchange="this.form.submit()" aria-label="تصفية الحالة">
                            <option value="">كل الحالات</option>
                            @foreach ($statuses as $st)
                                <option value="{{ $st->value }}" @selected($status === $st->value)>{{ $st->labelAr() }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-glass btn-sm">تصفية</button>
                        @if ($q !== '' || $status !== '')
                            <a href="{{ route('procurement.tasks.index') }}" class="btn btn-glass btn-sm">مسح</a>
                        @endif
                    </form>
                </div>

                @if ($tasks->isEmpty())
                    <div class="ds-data-empty ds-list-empty" role="status">
                        <strong>لا نتائج مطابقة</strong>
                        <p>لا مهام ضمن التصفية الحالية. امسح البحث أو غيّر الحالة.</p>
                    </div>
                @else
                    <div class="ds-list-body">
                        <table class="admin-table admin-table--sheet">
                            <thead>
                                <tr>
                                    <th scope="col">المهمة</th>
                                    <th scope="col">المنتج</th>
                                    <th scope="col">المنشأة</th>
                                    <th scope="col">الحالة</th>
                                    @if ($canViewCosts)
                                        <th scope="col" class="quotes-col-amount">تكلفة المورد</th>
                                    @endif
                                    <th scope="col" class="quotes-col-actions"><span class="visually-hidden">إجراء</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tasks as $task)
                                    @php
                                        $stValue = $task->status instanceof \BackedEnum
                                            ? $task->status->value
                                            : (string) $task->status;
                                        $stBadge = match ($stValue) {
                                            'open' => 'badge-warning',
                                            'cost_recorded' => 'badge-success',
                                            'cancelled' => 'badge-danger',
                                            default => 'badge-neutral',
                                        };
                                        $request = $task->salesRequest;
                                        $qty = $task->line?->quantity !== null ? qty($task->line->quantity) : null;
                                    @endphp
                                    <tr @class(['quotes-row-pending' => $stValue === 'open'])>
                                        <td>
                                            <div class="quotes-id-block">
                                                <a href="{{ route('procurement.tasks.show', $task) }}" class="quotes-id-link dir-ltr">#{{ $task->id }}</a>
                                                @if ($request)
                                                    @can('view', $request)
                                                        <a href="{{ route('sales-requests.show', $request) }}" class="quotes-sr-chip dir-ltr">طلب #{{ $request->id }}</a>
                                                    @else
                                                        <span class="quotes-sr-chip is-static dir-ltr">طلب #{{ $request->id }}</span>
                                                    @endcan
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="quotes-customer-block">
                                                <span class="ds-cell-primary">{{ $task->product?->name_ar ?? '—' }}</span>
                                                @if ($qty !== null)
                                                    <span class="quotes-meta-line dir-ltr">كمية {{ $qty }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="ds-cell-primary">{{ $request?->customer?->name_ar ?? '—' }}</span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $stBadge }}">{{ $task->status->labelAr() }}</span>
                                        </td>
                                        @if ($canViewCosts)
                                            <td class="quotes-col-amount">
                                                @if ($task->supplier_cost !== null)
                                                    <span class="quotes-amount dir-ltr">
                                                        {{ money($task->supplier_cost) }}
                                                        <span class="quotes-amount-unit">ر.س</span>
                                                    </span>
                                                @else
                                                    <span class="ds-cell-muted">—</span>
                                                @endif
                                            </td>
                                        @endif
                                        <td class="ds-cell-actions quotes-col-actions">
                                            <div class="quotes-row-actions">
                                                <a href="{{ route('procurement.tasks.show', $task) }}" class="btn btn-glass btn-sm">عرض</a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($tasks->hasPages())
                    <div class="ds-list-footer">{{ $tasks->links() }}</div>
                @endif
            </section>
        @endif
    </div>
@endsection
