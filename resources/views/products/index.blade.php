@extends('layouts.app')

@section('title', 'المنتجات — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المخزون · المنتجات</span>
@endsection

@section('content')
    <div class="products-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="products-heading">
            <div class="ds-page-bar-copy">
                <h1 id="products-heading" class="admin-title">المنتجات</h1>
            </div>
            <div class="ds-page-bar-tools">
                <form method="GET" action="{{ route('products.index') }}" class="ds-data-search" role="search">
                    <svg class="ds-data-search-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.7"/>
                        <path d="M16.2 16.2 20 20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>
                    <label class="visually-hidden" for="product-q">بحث في المنتجات</label>
                    <input id="product-q" type="search" name="q" value="{{ $q }}"
                           placeholder="بحث بالاسم أو الوحدة أو المواصفة…"
                           autocomplete="off">
                </form>
                @if ($q !== '')
                    <a href="{{ route('products.index') }}" class="btn btn-glass">مسح</a>
                @endif
                @if ($canManageUnits)
                    <a href="{{ route('products.units.index') }}" class="btn btn-glass">وحدات القياس</a>
                @endif
                @if ($canManage)
                    <a href="{{ route('products.create') }}" class="btn btn-glass-primary">+ منتج جديد</a>
                @endif
            </div>
        </header>

        @if ($products->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>
                    @if ($q !== '')
                        لا توجد نتائج مطابقة
                    @else
                        لا توجد منتجات
                    @endif
                </strong>
                <p>
                    @if ($q !== '')
                        لا توجد نتائج مطابقة لبحثك. جرّب اسمًا أو وحدة مختلفة، أو امسح البحث.
                    @else
                        لا توجد سجلات منتجات بعد. لن تُعرض بيانات تجريبية هنا.
                        @if ($canManage)
                            ابدأ بإضافة وحدة قياس نشطة ثم منتج جديد.
                        @endif
                    @endif
                </p>
            </div>
        @else
            <div class="ds-table-surface">
                <div class="ds-data-body">
                    <table class="admin-table products-table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">اسم المنتج</th>
                                <th scope="col">وحدة القياس</th>
                                @if ($canViewCost)
                                    <th scope="col">تكلفة الشراء</th>
                                @endif
                                <th scope="col">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $product)
                                <tr>
                                    <td class="ds-cell-muted dir-ltr">{{ $products->firstItem() + $loop->index }}</td>
                                    <td>
                                        <a href="{{ route('products.show', $product) }}" class="customers-name-link ds-cell-primary">
                                            {{ $product->name_ar }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="products-unit-chip">{{ $product->unit?->displayLabel() ?? '—' }}</span>
                                    </td>
                                    @if ($canViewCost)
                                        <td class="dir-ltr inventory-qty products-cost-cell">
                                            @if ($product->purchase_cost !== null)
                                                {{ money($product->purchase_cost) }}
                                            @else
                                                <span class="ds-cell-muted">—</span>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="ds-cell-actions">
                                        <details class="ds-row-menu">
                                            <summary aria-label="إجراءات {{ $product->name_ar }}">⋮</summary>
                                            <div class="ds-row-menu-panel">
                                                <a href="{{ route('products.show', $product) }}">عرض</a>
                                                @can('update', $product)
                                                    <a href="{{ route('products.edit', $product) }}">تعديل</a>
                                                @endcan
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($products->hasPages())
                    <div class="ds-data-footer">
                        {{ $products->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection
