@extends('layouts.app')

@section('title', 'تسليم جديد — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المستودع · تسليم جديد</span>
@endsection

@section('content')
    <div class="deliveries-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="del-create-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">مسودة · كمية ≤ المتبقي غير المسلَّم</p>
                <h1 id="del-create-heading" class="admin-title">تسليم جديد</h1>
                <p class="admin-lead">
                    اختر أمر بيع معتمدًا. الكميات من المتبقي غير المسلَّم فقط (يشمل المسودات المفتوحة).
                    إثبات التسليم مطلوب قبل التأكيد والخصم. بلا أسعار.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('deliveries.index') }}" class="btn btn-glass">القائمة</a>
                <a href="{{ route('warehouse.preparation.index') }}" class="btn btn-glass">التجهيز</a>
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($orders->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا أوامر بيع جاهزة للتسليم</strong>
                <p>يلزم أمر بيع معتمد غير مكتمل التسليم. راجع مهام التجهيز إن وُجدت.</p>
                <a href="{{ route('warehouse.preparation.index') }}" class="btn btn-glass-primary">مهام التجهيز</a>
            </div>
        @else
            <section class="del-form-card" aria-labelledby="del-create-fields">
                <form method="POST" action="{{ route('deliveries.store') }}" class="auth-form admin-form customers-form" novalidate>
                    @csrf
                    <h2 id="del-create-fields" class="visually-hidden">بيانات التسليم</h2>

                    <div class="customers-form-section">
                        <h2 class="customers-form-section-title">أمر البيع</h2>
                        <p class="customers-form-section-lead">يمكن إنشاء أكثر من تسليم لنفس الأمر حتى اكتمال الكميات.</p>

                        <div class="auth-field">
                            <label for="sales_order_id">أمر البيع المعتمد <span class="required-mark" title="مطلوب">*</span></label>
                            <select id="sales_order_id" name="sales_order_id" required
                                    onchange="window.location='{{ route('deliveries.create') }}?sales_order_id='+this.value">
                                <option value="">— اختر —</option>
                                @foreach ($orders as $order)
                                    <option value="{{ $order->id }}"
                                        @selected((int) old('sales_order_id', $selected?->id) === (int) $order->id)>
                                        #{{ $order->id }} — {{ $order->customer?->name_ar }} — {{ $order->fulfillment_status->labelAr() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('sales_order_id')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($selected)
                            <div class="cpo-deal-notice" role="status">
                                <p class="cpo-deal-notice-title">حدود الكمية</p>
                                <p class="cpo-deal-notice-body">
                                    كمية كل بند ≤ المتبقي غير المسلَّم (أمر البيع − المؤكَّد − المسودات/بانتظار الإثبات الأخرى).
                                    التأكيد يخصم مرة واحدة عبر المخزون المحجوز لأمر البيع.
                                </p>
                            </div>
                        @endif

                        <div class="auth-field">
                            <label for="notes_ar">ملاحظات</label>
                            <textarea id="notes_ar" name="notes_ar" rows="2" maxlength="2000">{{ old('notes_ar') }}</textarea>
                            @error('notes_ar')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    @if ($selected)
                        <div class="customers-form-section">
                            <h2 class="customers-form-section-title">البنود</h2>
                            <p class="customers-form-section-lead">
                                كميات فقط
                                @unless ($canViewPrices)
                                    — بلا أسعار
                                @endunless
                                .
                            </p>
                            <div class="ds-table-surface">
                                <div class="ds-data-body">
                                    <table class="admin-table del-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">المنتج</th>
                                                <th scope="col">كمية أمر البيع</th>
                                                <th scope="col">متبقي للتسليم</th>
                                                <th scope="col">كمية هذا التسليم</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($selected->lines as $index => $line)
                                                @php $remain = $undelivered[$line->id] ?? 0; @endphp
                                                <tr @class(['so-line-exhausted' => $remain <= 0])>
                                                    <td class="ds-cell-primary">
                                                        {{ $line->product?->name_ar }}
                                                        @if ($remain > 0)
                                                            <input type="hidden" name="lines[{{ $index }}][sales_order_line_id]" value="{{ $line->id }}">
                                                        @endif
                                                    </td>
                                                    <td class="dir-ltr inventory-qty">{{ qty($line->quantity) }}</td>
                                                    <td class="dir-ltr inventory-qty {{ $remain > 0 ? 'cpo-remain-ok' : 'ds-cell-muted' }}">
                                                        {{ qty($remain) }}
                                                    </td>
                                                    <td>
                                                        @if ($remain > 0)
                                                            <label class="visually-hidden" for="del-qty-{{ $index }}">كمية التسليم {{ $index + 1 }}</label>
                                                            <input id="del-qty-{{ $index }}" type="number" step="0.001" min="0.001" max="{{ $remain }}"
                                                                   name="lines[{{ $index }}][quantity]"
                                                                   value="{{ old('lines.'.$index.'.quantity', number_format($remain, 3, '.', '')) }}"
                                                                   class="dir-ltr cpo-qty-input">
                                                            @error('lines.'.$index.'.quantity')
                                                                <p class="auth-error" role="alert">{{ $message }}</p>
                                                            @enderror
                                                        @else
                                                            <span class="ds-cell-muted">لا متبقي</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="admin-form-actions">
                        <button type="submit" class="btn btn-glass-primary" @disabled(! $selected)>حفظ المسودة</button>
                        <a href="{{ route('deliveries.index') }}" class="btn btn-glass">إلغاء</a>
                    </div>
                </form>
            </section>
        @endif
    </div>
@endsection
