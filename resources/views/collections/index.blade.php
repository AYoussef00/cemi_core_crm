@extends('layouts.app')

@section('title', 'التحصيلات — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المالية · التحصيلات</span>
@endsection

@section('content')
    <div class="collections-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="col-heading">
            <div class="ds-page-bar-copy">
                <h1 id="col-heading" class="admin-title">التحصيلات</h1>
                <p class="admin-lead">
                    المسودة لا تخفض المتبقي على الفاتورة. التأكيد فقط يؤثر على CR7.
                    العكس يطلبه الحسابات ويعتمده مدير المبيعات.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                @if ($canCreate)
                    <a href="{{ route('collections.create') }}" class="btn btn-glass-primary">تحصيل جديد</a>
                @endif
            </div>
        </header>

        @if ($collections->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا تحصيلات</strong>
                <p>أنشئ مسودة تحصيل وخصّصها لفواتير غير مسددة. لا سجلات وهمية.</p>
            </div>
        @else
            <div class="ds-table-surface">
                <div class="ds-data-body">
                    <table class="admin-table fin-table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">المنشأة</th>
                                <th scope="col">المبلغ</th>
                                <th scope="col">الحالة</th>
                                <th scope="col">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($collections as $item)
                                @php
                                    $st = $item->status instanceof \BackedEnum
                                        ? $item->status->value
                                        : (string) $item->status;
                                    $badge = match ($st) {
                                        'confirmed' => 'badge-success',
                                        'draft' => 'badge-warning',
                                        'reversed' => 'badge-danger',
                                        default => 'badge-neutral',
                                    };
                                @endphp
                                <tr @class([
                                    'col-row-draft' => $st === 'draft',
                                    'quotes-row-pending' => $st === 'draft',
                                ])>
                                    <td class="dir-ltr ds-cell-muted">{{ $item->id }}</td>
                                    <td class="ds-cell-primary">{{ $item->customer?->name_ar ?? '—' }}</td>
                                    <td class="dir-ltr inventory-qty">{{ money($item->amount_total) }} ر.س</td>
                                    <td>
                                        <span class="badge {{ $badge }}">{{ $item->status->labelAr() }}</span>
                                        @if ($st === 'draft')
                                            <span class="col-draft-hint">لا يؤثر على المتبقي</span>
                                        @endif
                                    </td>
                                    <td class="ds-cell-actions">
                                        <a href="{{ route('collections.show', $item) }}" class="btn btn-glass btn-sm">عرض</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($collections->hasPages())
                    <div class="ds-data-footer">{{ $collections->links() }}</div>
                @endif
            </div>
        @endif
    </div>
@endsection
