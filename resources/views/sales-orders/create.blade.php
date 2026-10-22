@extends('layouts.app')

@section('title', 'أمر بيع جديد — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · أمر بيع جديد</span>
@endsection

@section('content')
    <div class="sales-orders-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="so-create-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">مسودة · يتطلب أمر شراء</p>
                <h1 id="so-create-heading" class="admin-title">أمر بيع جديد</h1>
                <p class="admin-lead">
                    اختر أمر شراء مفتوحًا. الكميات من المتبقي غير المخصّص فقط. لا يمكن إنشاء أمر بيع بلا أمر شراء (PF7).
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('sales-orders.index') }}" class="btn btn-glass">القائمة</a>
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($purchaseOrders->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا يوجد أمر شراء مفتوح</strong>
                <p>أنشئ أمر شراء عميل أولاً ثم عد لإنشاء أمر البيع.</p>
                <a href="{{ route('customer-pos.create') }}" class="btn btn-glass-primary">أمر شراء جديد</a>
            </div>
        @else
            <section class="so-form-card" aria-labelledby="so-create-fields">
                <form method="POST" action="{{ route('sales-orders.store') }}" class="auth-form admin-form customers-form" novalidate>
                    @csrf
                    <h2 id="so-create-fields" class="visually-hidden">بيانات أمر البيع</h2>

                    <div class="customers-form-section">
                        <h2 class="customers-form-section-title">أمر الشراء والصفقة</h2>
                        <p class="customers-form-section-lead">الصفقة تُعرض للقراءة فقط — موروثة من أمر الشراء.</p>

                        <div class="auth-field">
                            <label for="customer_purchase_order_id">أمر شراء العميل <span class="required-mark" title="مطلوب">*</span></label>
                            <select id="customer_purchase_order_id" name="customer_purchase_order_id" required
                                    onchange="window.location='{{ route('sales-orders.create') }}?customer_purchase_order_id='+this.value">
                                <option value="">— اختر —</option>
                                @foreach ($purchaseOrders as $po)
                                    <option value="{{ $po->id }}"
                                        @selected((int) old('customer_purchase_order_id', $selectedPo?->id) === (int) $po->id)>
                                        {{ $po->document_number }} — {{ $po->customer?->name_ar }} — صفقة #{{ $po->deal?->id }}
                                    </option>
                                @endforeach
                            </select>
                            @error('customer_purchase_order_id')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($selectedPo)
                            <div class="cpo-deal-notice" role="status">
                                <p class="cpo-deal-notice-title">سياق الصفقة</p>
                                <p class="cpo-deal-notice-body">
                                    صفقة <span class="dir-ltr">#{{ $selectedPo->deal?->id ?? '—' }}</span>
                                    — {{ $selectedPo->deal?->status?->labelAr() ?? '—' }}
                                    · منشأة: {{ $selectedPo->customer?->name_ar ?? '—' }}
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

                    @if ($selectedPo)
                        <div class="customers-form-section">
                            <h2 class="customers-form-section-title">البنود</h2>
                            <p class="customers-form-section-lead">
                                أدخل كمية ≤ المتبقي غير المخصّص. اترك فارغًا أو 0 لتجاهل البند. بند واحد على الأقل مطلوب.
                            </p>
                            <div class="ds-table-surface">
                                <div class="ds-data-body">
                                    <table class="admin-table so-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">المنتج</th>
                                                <th scope="col">كمية أمر الشراء</th>
                                                <th scope="col">متبقي</th>
                                                <th scope="col">كمية أمر البيع</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($selectedPo->lines as $index => $line)
                                                @php $remain = $unallocated[$line->id] ?? 0; @endphp
                                                <tr @class(['so-line-exhausted' => $remain <= 0])>
                                                    <td class="ds-cell-primary">
                                                        {{ $line->product?->name_ar }}
                                                        <input type="hidden" name="lines[{{ $index }}][customer_purchase_order_line_id]" value="{{ $line->id }}">
                                                    </td>
                                                    <td class="dir-ltr inventory-qty">{{ qty($line->quantity) }}</td>
                                                    <td class="dir-ltr inventory-qty {{ $remain > 0 ? 'cpo-remain-ok' : 'ds-cell-muted' }}">
                                                        {{ qty($remain) }}
                                                    </td>
                                                    <td>
                                                        @if ($remain > 0)
                                                            <label class="visually-hidden" for="so-qty-{{ $index }}">كمية أمر البيع {{ $index + 1 }}</label>
                                                            <input id="so-qty-{{ $index }}" type="number" step="0.001" min="0.001" max="{{ $remain }}"
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
                        <button type="submit" class="btn btn-glass-primary" @disabled(! $selectedPo)>حفظ المسودة</button>
                        <a href="{{ route('sales-orders.index') }}" class="btn btn-glass">إلغاء</a>
                    </div>
                </form>
            </section>
        @endif
    </div>
@endsection
