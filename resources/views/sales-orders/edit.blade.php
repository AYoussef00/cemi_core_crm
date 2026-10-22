@extends('layouts.app')

@section('title', 'تعديل أمر بيع #'.$order->id.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · تعديل مسودة أمر البيع</span>
@endsection

@section('content')
    <div class="sales-orders-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="so-edit-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">تعديل · مسودة</p>
                <h1 id="so-edit-heading" class="admin-title">تعديل مسودة أمر بيع #{{ $order->id }}</h1>
                <p class="admin-lead">
                    أمر شراء
                    <a href="{{ route('customer-pos.show', $order->customer_purchase_order_id) }}" class="sr-task-link">#{{ $order->customer_purchase_order_id }}</a>
                    · صفقة <span class="dir-ltr">#{{ $order->deal_id }}</span>
                    ({{ $order->deal?->status?->labelAr() ?? '—' }})
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('sales-orders.show', $order) }}" class="btn btn-glass">عرض</a>
                <a href="{{ route('sales-orders.index') }}" class="btn btn-glass">القائمة</a>
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="so-form-card" aria-labelledby="so-edit-fields">
            <form method="POST" action="{{ route('sales-orders.update', $order) }}" class="auth-form admin-form customers-form" novalidate>
                @csrf
                @method('PUT')
                <h2 id="so-edit-fields" class="visually-hidden">تعديل المسودة</h2>

                <div class="customers-form-section">
                    <h2 class="customers-form-section-title">ملاحظات</h2>
                    <div class="auth-field">
                        <label for="notes_ar">ملاحظات</label>
                        <textarea id="notes_ar" name="notes_ar" rows="2" maxlength="2000">{{ old('notes_ar', $order->notes_ar) }}</textarea>
                        @error('notes_ar')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="customers-form-section">
                    <h2 class="customers-form-section-title">البنود</h2>
                    <p class="customers-form-section-lead">
                        الحد الأقصى = المتبقي غير المخصّص شاملاً كمية هذه المسودة الحالية.
                    </p>
                    <div class="ds-table-surface">
                        <div class="ds-data-body">
                            <table class="admin-table so-table">
                                <thead>
                                    <tr>
                                        <th scope="col">المنتج</th>
                                        <th scope="col">حد المتبقي</th>
                                        <th scope="col">الكمية</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($order->customerPurchaseOrder->lines as $index => $line)
                                        @php
                                            $current = $order->lines->firstWhere('customer_purchase_order_line_id', $line->id);
                                            $max = $unallocated[$line->id] ?? 0;
                                        @endphp
                                        <tr>
                                            <td class="ds-cell-primary">
                                                {{ $line->product?->name_ar }}
                                                <input type="hidden" name="lines[{{ $index }}][customer_purchase_order_line_id]" value="{{ $line->id }}">
                                            </td>
                                            <td class="dir-ltr inventory-qty">{{ qty($max) }}</td>
                                            <td>
                                                <label class="visually-hidden" for="so-edit-qty-{{ $index }}">كمية البند {{ $index + 1 }}</label>
                                                <input id="so-edit-qty-{{ $index }}" type="number" step="0.001" min="0.001" max="{{ $max }}"
                                                       name="lines[{{ $index }}][quantity]"
                                                       value="{{ old('lines.'.$index.'.quantity', $current ? number_format((float) $current->quantity, 3, '.', '') : '') }}"
                                                       class="dir-ltr cpo-qty-input"
                                                       @required($loop->first)>
                                                @error('lines.'.$index.'.quantity')
                                                    <p class="auth-error" role="alert">{{ $message }}</p>
                                                @enderror
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="admin-form-actions">
                    <button type="submit" class="btn btn-glass-primary">حفظ</button>
                    <a href="{{ route('sales-orders.show', $order) }}" class="btn btn-glass">إلغاء</a>
                </div>
            </form>
        </section>
    </div>
@endsection
