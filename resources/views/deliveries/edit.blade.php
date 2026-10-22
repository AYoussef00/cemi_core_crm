@extends('layouts.app')

@section('title', 'تعديل تسليم #'.$delivery->id.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المستودع · تعديل مسودة التسليم</span>
@endsection

@section('content')
    <div class="deliveries-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="del-edit-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">تعديل · مسودة</p>
                <h1 id="del-edit-heading" class="admin-title">تعديل مسودة تسليم #{{ $delivery->id }}</h1>
                <p class="admin-lead">
                    أمر بيع
                    <a href="{{ route('sales-orders.show', $delivery->sales_order_id) }}" class="sr-task-link">#{{ $delivery->sales_order_id }}</a>
                    · الكمية ≤ المتبقي (شامل هذه المسودة) · بلا أسعار
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-glass">عرض</a>
                <a href="{{ route('deliveries.index') }}" class="btn btn-glass">القائمة</a>
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="del-form-card" aria-labelledby="del-edit-fields">
            <form method="POST" action="{{ route('deliveries.update', $delivery) }}" class="auth-form admin-form customers-form" novalidate>
                @csrf
                @method('PUT')
                <h2 id="del-edit-fields" class="visually-hidden">تعديل المسودة</h2>

                <div class="customers-form-section">
                    <h2 class="customers-form-section-title">ملاحظات</h2>
                    <div class="auth-field">
                        <label for="notes_ar">ملاحظات</label>
                        <textarea id="notes_ar" name="notes_ar" rows="2" maxlength="2000">{{ old('notes_ar', $delivery->notes_ar) }}</textarea>
                        @error('notes_ar')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="customers-form-section">
                    <h2 class="customers-form-section-title">البنود</h2>
                    <p class="customers-form-section-lead">الحد الأقصى = المتبقي غير المسلَّم شاملاً كمية هذه المسودة.</p>
                    <div class="ds-table-surface">
                        <div class="ds-data-body">
                            <table class="admin-table del-table">
                                <thead>
                                    <tr>
                                        <th scope="col">المنتج</th>
                                        <th scope="col">حد المتبقي</th>
                                        <th scope="col">الكمية</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($delivery->salesOrder->lines as $index => $line)
                                        @php
                                            $current = $delivery->lines->firstWhere('sales_order_line_id', $line->id);
                                            $max = $undelivered[$line->id] ?? 0;
                                        @endphp
                                        <tr>
                                            <td class="ds-cell-primary">
                                                {{ $line->product?->name_ar }}
                                                <input type="hidden" name="lines[{{ $index }}][sales_order_line_id]" value="{{ $line->id }}">
                                            </td>
                                            <td class="dir-ltr inventory-qty">{{ qty($max) }}</td>
                                            <td>
                                                <label class="visually-hidden" for="del-edit-qty-{{ $index }}">كمية البند {{ $index + 1 }}</label>
                                                <input id="del-edit-qty-{{ $index }}" type="number" step="0.001" min="0.001" max="{{ $max }}"
                                                       name="lines[{{ $index }}][quantity]"
                                                       value="{{ old('lines.'.$index.'.quantity', $current ? number_format((float) $current->quantity, 3, '.', '') : '') }}"
                                                       class="dir-ltr cpo-qty-input">
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
                    <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-glass">إلغاء</a>
                </div>
            </form>
        </section>
    </div>
@endsection
