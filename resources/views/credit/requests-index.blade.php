@extends('layouts.app')

@section('title', 'طلبات حد الائتمان — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المالية · طلبات الائتمان</span>
@endsection

@section('content')
    <div class="credit-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="credit-requests-heading">
            <div class="ds-page-bar-copy">
                <h1 id="credit-requests-heading" class="admin-title">طلبات حد الائتمان</h1>
                <p class="admin-lead">
                    مسار الاعتماد: مندوب ← مراجعة حسابات ← اعتماد نهائي لمدير المبيعات.
                    الطابور حسب صلاحيتك — بلا لوحة مؤشرات مالية.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <form method="GET" action="{{ route('credit-requests.index') }}" class="ds-page-bar-tools" role="search">
                    <label class="visually-hidden" for="status">الحالة</label>
                    <select id="status" name="status" onchange="this.form.submit()">
                        <option value="">كل الحالات</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($statusFilter === $status->value)>
                                {{ $status->labelAr() }}
                            </option>
                        @endforeach
                    </select>
                    <noscript>
                        <button type="submit" class="btn btn-glass">تصفية</button>
                    </noscript>
                </form>
            </div>
        </header>

        <div class="ds-table-surface">
            <div class="ds-data-body">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">العميل</th>
                            <th scope="col">الحالة</th>
                            <th scope="col">الحد المطلوب</th>
                            <th scope="col">مقدّم الطلب</th>
                            <th scope="col">التاريخ</th>
                            <th scope="col">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $req)
                            @php
                                $statusValue = $req->status instanceof \BackedEnum ? $req->status->value : (string) $req->status;
                                $statusBadge = match ($statusValue) {
                                    'approved' => 'badge-success',
                                    'rejected' => 'badge-danger',
                                    'pending_final' => 'badge-process',
                                    default => 'badge-warning',
                                };
                            @endphp
                            <tr>
                                <td class="ds-cell-muted dir-ltr">{{ $requests->firstItem() + $loop->index }}</td>
                                <td class="ds-cell-primary">{{ $req->customer?->name_ar ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $statusBadge }}">{{ $req->statusLabelAr() }}</span>
                                </td>
                                <td class="dir-ltr">{{ money($req->requested_credit_limit) }}</td>
                                <td>{{ $req->requestedBy?->name ?? '—' }}</td>
                                <td class="dir-ltr ds-cell-muted">
                                    {{ $req->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                </td>
                                <td class="ds-cell-actions">
                                    <details class="ds-row-menu">
                                        <summary aria-label="إجراءات الطلب">⋮</summary>
                                        <div class="ds-row-menu-panel">
                                            <a href="{{ route('credit-requests.show', $req) }}">عرض</a>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="ds-data-empty" role="status">
                                        <strong>لا توجد طلبات</strong>
                                        <p>لا توجد طلبات ضمن نطاق عرضك{{ $statusFilter ? ' لهذه الحالة' : '' }}.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($requests->hasPages())
                <div class="ds-data-footer">
                    {{ $requests->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
