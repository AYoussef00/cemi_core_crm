@extends('layouts.app')

@section('title', 'الصفقات — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المالية · الصفقات</span>
@endsection

@section('content')
    <div class="deals-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="deals-heading">
            <div class="ds-page-bar-copy">
                <h1 id="deals-heading" class="admin-title">الصفقات</h1>
                <p class="admin-lead">
                    صفقة واحدة لكل أمر شراء عميل. الإغلاق يدوي من الحسابات عندما كل الفواتير المرتبطة مسددة (TX5b).
                </p>
            </div>
        </header>

        @if ($deals->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا صفقات</strong>
                <p>تُنشأ الصفقة تلقائيًا مع أمر شراء العميل. لا سجلات وهمية.</p>
            </div>
        @else
            <div class="ds-table-surface">
                <div class="ds-data-body">
                    <table class="admin-table fin-table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">المنشأة</th>
                                <th scope="col">أمر الشراء</th>
                                <th scope="col">الحالة</th>
                                <th scope="col">فواتير</th>
                                <th scope="col">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deals as $deal)
                                @php
                                    $st = $deal->status instanceof \BackedEnum
                                        ? $deal->status->value
                                        : (string) $deal->status;
                                    $badge = $st === 'open' ? 'badge-success' : 'badge-neutral';
                                @endphp
                                <tr>
                                    <td class="dir-ltr ds-cell-muted">{{ $deal->id }}</td>
                                    <td class="ds-cell-primary">{{ $deal->customer?->name_ar ?? '—' }}</td>
                                    <td class="dir-ltr">
                                        @if ($deal->customer_purchase_order_id)
                                            <a href="{{ route('customer-pos.show', $deal->customer_purchase_order_id) }}" class="sr-task-link">
                                                #{{ $deal->customer_purchase_order_id }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td><span class="badge {{ $badge }}">{{ $deal->status->labelAr() }}</span></td>
                                    <td class="dir-ltr inventory-qty">{{ $deal->invoices->count() }}</td>
                                    <td class="ds-cell-actions">
                                        <a href="{{ route('deals.show', $deal) }}" class="btn btn-glass btn-sm">عرض</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($deals->hasPages())
                    <div class="ds-data-footer">{{ $deals->links() }}</div>
                @endif
            </div>
        @endif
    </div>
@endsection
