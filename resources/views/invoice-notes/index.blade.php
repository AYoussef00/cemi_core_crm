@extends('layouts.app')

@section('title', 'إشعارات دائن/مدين — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المالية · الإشعارات</span>
@endsection

@section('content')
    <div class="notes-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="notes-heading">
            <div class="ds-page-bar-copy">
                <h1 id="notes-heading" class="admin-title">الإشعارات الدائنة والمدينة</h1>
                <p class="admin-lead">
                    الحسابات تنشئ وتُرسل · مدير المبيعات يعتمد (لا اعتماد ذاتي).
                    المعتمد فقط يؤثر على CR7 — الدائن يخفض المتبقي، المدين يزيده.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                @if ($canCreate)
                    <a href="{{ route('invoice-notes.create') }}" class="btn btn-glass-primary">إشعار جديد</a>
                @endif
            </div>
        </header>

        @if ($notes->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا إشعارات</strong>
                <p>أنشئ إشعارًا لتصحيح فاتورة صادرة. لا سجلات وهمية.</p>
            </div>
        @else
            <div class="ds-table-surface">
                <div class="ds-data-body">
                    <table class="admin-table fin-table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">النوع</th>
                                <th scope="col">فاتورة</th>
                                <th scope="col">المبلغ</th>
                                <th scope="col">الحالة</th>
                                <th scope="col">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($notes as $item)
                                @php
                                    $typeVal = $item->type instanceof \BackedEnum ? $item->type->value : (string) $item->type;
                                    $st = $item->status instanceof \BackedEnum ? $item->status->value : (string) $item->status;
                                    $typeBadge = $typeVal === 'credit' ? 'badge-success' : 'badge-warning';
                                    $stBadge = match ($st) {
                                        'approved' => 'badge-success',
                                        'pending_approval' => 'badge-warning',
                                        'rejected' => 'badge-danger',
                                        default => 'badge-neutral',
                                    };
                                @endphp
                                <tr @class(['quotes-row-pending' => $st === 'pending_approval'])>
                                    <td class="dir-ltr ds-cell-muted">{{ $item->id }}</td>
                                    <td><span class="badge {{ $typeBadge }}">{{ $item->type->labelAr() }}</span></td>
                                    <td class="dir-ltr">
                                        <a href="{{ route('invoices.show', $item->invoice_id) }}" class="sr-task-link dir-ltr">{{ doc_no('INV', $item->invoice_id) }}</a>
                                    </td>
                                    <td class="dir-ltr inventory-qty">{{ money($item->amount) }} ر.س</td>
                                    <td><span class="badge {{ $stBadge }}">{{ $item->status->labelAr() }}</span></td>
                                    <td class="ds-cell-actions">
                                        <a href="{{ route('invoice-notes.show', $item) }}" class="btn btn-glass btn-sm">عرض</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($notes->hasPages())
                    <div class="ds-data-footer">{{ $notes->links() }}</div>
                @endif
            </div>
        @endif
    </div>
@endsection
