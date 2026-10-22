@extends('layouts.app')

@section('title', 'أرصدة المخزون — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المخزون · الأرصدة</span>
@endsection

@section('content')
    <div class="inventory-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="stock-heading">
            <div class="ds-page-bar-copy">
                <h1 id="stock-heading" class="admin-title">أرصدة المخزون</h1>
                <p class="admin-lead">
                    @if ($warehouse)
                        المستودع: <strong>{{ $warehouse->name_ar ?: 'المستودع الوحيد' }}</strong>.
                    @else
                        مستودع واحد.
                    @endif
                    المتاح = الرصيد − المحجوز، ولا يُسمح بأن يصبح سالبًا.
                    لا تُعرض أسعار البيع هنا.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                @if ($warehouse)
                    <form method="GET" action="{{ route('inventory.stock.index') }}" class="ds-data-search" role="search">
                        <svg class="ds-data-search-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.7"/>
                            <path d="M16.2 16.2 20 20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                        </svg>
                        <label class="visually-hidden" for="stock-q">بحث في المنتجات</label>
                        <input id="stock-q" type="search" name="q" value="{{ $q }}"
                               placeholder="بحث باسم المنتج…"
                               autocomplete="off">
                    </form>
                    @if ($q !== '')
                        <a href="{{ route('inventory.stock.index') }}" class="btn btn-glass">مسح</a>
                    @endif
                @endif
                @if ($canRecordAdjustment)
                    <a href="{{ route('inventory.adjustments.create') }}" class="btn btn-glass-primary">+ تسوية مخزون</a>
                @endif
                @can('viewAny', \App\Models\InventoryAdjustment::class)
                    <a href="{{ route('inventory.adjustments.index') }}" class="btn btn-glass">التسويات</a>
                @endcan
            </div>
        </header>

        @unless ($warehouse)
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>المستودع غير مُعدّ</strong>
                <p>أكمل إعداد المستودع من الإعدادات قبل عرض الأرصدة.</p>
            </div>
        @elseif ($rows === null || $rows->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>
                    @if ($q !== '')
                        لا توجد نتائج مطابقة
                    @else
                        لا توجد منتجات في الكتالوج
                    @endif
                </strong>
                <p>لن تُعرض أرصدة تجريبية. أنشئ منتجات أولاً إن لزم.</p>
            </div>
        @else
            <div class="ds-table-surface">
                <div class="ds-data-body">
                    <table class="admin-table inventory-stock-table">
                        <thead>
                            <tr>
                                <th scope="col">المنتج</th>
                                <th scope="col">الوحدة</th>
                                <th scope="col">الرصيد</th>
                                <th scope="col">المحجوز</th>
                                <th scope="col">المتاح</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php
                                    $onHand = (float) ($row->on_hand ?? 0);
                                    $reserved = (float) ($row->reserved ?? 0);
                                    $available = $onHand - $reserved;
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('products.show', $row->id) }}" class="customers-name-link ds-cell-primary">
                                            {{ $row->name_ar }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="products-unit-chip">{{ $row->unit?->displayLabel() ?? '—' }}</span>
                                    </td>
                                    <td class="dir-ltr inventory-qty">{{ qty($onHand) }}</td>
                                    <td class="dir-ltr inventory-qty inventory-qty-reserved">{{ qty($reserved) }}</td>
                                    <td class="dir-ltr inventory-qty inventory-qty-available">{{ qty($available) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($rows->hasPages())
                    <div class="ds-data-footer">
                        {{ $rows->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection
