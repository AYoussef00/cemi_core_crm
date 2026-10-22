@extends('layouts.app')

@section('title', 'وحدات القياس — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المخزون · وحدات القياس</span>
@endsection

@section('content')
    <div class="products-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="units-heading">
            <div class="ds-page-bar-copy">
                <h1 id="units-heading" class="admin-title">وحدات القياس</h1>
                <p class="admin-lead">
                    كتالوج الوحدات التي تُربط بمنتج واحد كوحدة أساسية.
                    لا تُطبَّق تحويلات بين الوحدات في هذه المرحلة.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <form method="GET" action="{{ route('products.units.index') }}" class="ds-data-search" role="search">
                    <svg class="ds-data-search-ico" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.7"/>
                        <path d="M16.2 16.2 20 20" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    </svg>
                    <label class="visually-hidden" for="unit-q">بحث في الوحدات</label>
                    <input id="unit-q" type="search" name="q" value="{{ $q }}"
                           placeholder="بحث بالاسم أو الرمز…"
                           autocomplete="off">
                </form>
                @if ($q !== '')
                    <a href="{{ route('products.units.index') }}" class="btn btn-glass">مسح</a>
                @endif
                <a href="{{ route('products.index') }}" class="btn btn-glass">المنتجات</a>
                @if ($canManage)
                    <a href="{{ route('products.units.create') }}" class="btn btn-glass-primary">+ وحدة جديدة</a>
                @endif
            </div>
        </header>

        @if ($units->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>
                    @if ($q !== '')
                        لا توجد نتائج مطابقة
                    @else
                        لا توجد وحدات قياس
                    @endif
                </strong>
                <p>
                    @if ($q !== '')
                        لا توجد نتائج مطابقة لبحثك.
                    @else
                        أنشئ وحدة قياس قبل إضافة منتجات. لن تُعرض بيانات تجريبية هنا.
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
                                <th scope="col">الاسم</th>
                                <th scope="col">الرمز</th>
                                <th scope="col">الحالة</th>
                                <th scope="col">منتجات مرتبطة</th>
                                <th scope="col">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($units as $unit)
                                <tr @class(['products-unit-row-inactive' => ! $unit->is_active])>
                                    <td class="ds-cell-muted dir-ltr">{{ $units->firstItem() + $loop->index }}</td>
                                    <td class="ds-cell-primary">{{ $unit->name_ar }}</td>
                                    <td>
                                        @if ($unit->symbol_ar)
                                            <span class="products-unit-chip dir-ltr">{{ $unit->symbol_ar }}</span>
                                        @else
                                            <span class="ds-cell-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($unit->is_active)
                                            <span class="badge badge-success">نشطة</span>
                                        @else
                                            <span class="badge badge-neutral">غير نشطة</span>
                                        @endif
                                    </td>
                                    <td class="dir-ltr">{{ $unit->products_count }}</td>
                                    <td class="ds-cell-actions">
                                        @can('update', $unit)
                                            <details class="ds-row-menu">
                                                <summary aria-label="إجراءات {{ $unit->name_ar }}">⋮</summary>
                                                <div class="ds-row-menu-panel">
                                                    <a href="{{ route('products.units.edit', $unit) }}">تعديل</a>
                                                </div>
                                            </details>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($units->hasPages())
                    <div class="ds-data-footer">
                        {{ $units->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection
