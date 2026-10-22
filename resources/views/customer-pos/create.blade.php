@extends('layouts.app')

@section('title', 'أمر شراء عميل جديد — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · أمر شراء جديد</span>
@endsection

@section('content')
    <div class="customer-pos-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="cpo-create-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">إنشاء · صفقة 1:1</p>
                <h1 id="cpo-create-heading" class="admin-title">أمر شراء عميل جديد</h1>
                <p class="admin-lead">
                    اختر عرض سعر معتمد أو مُرسل لنفس المنشأة. كميات البنود ≤ كميات العرض.
                    عند الحفظ تُنشأ صفقة مفتوحة مرتبطة بهذا الأمر (ORG3) — للقراءة فقط حتى إغلاق الصفقة لاحقًا.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('customer-pos.index') }}" class="btn btn-glass">القائمة</a>
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($customers->isEmpty() || $quotations->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>تعذر الإنشاء</strong>
                <p>
                    @if ($customers->isEmpty())
                        لا توجد منشآت ضمن نطاقك.
                    @else
                        لا توجد عروض أسعار معتمدة أو مُرسلة ضمن نطاقك لإنشاء أمر شراء.
                    @endif
                </p>
            </div>
        @else
            <section class="cpo-form-card" aria-labelledby="cpo-create-fields">
                <form method="POST" action="{{ route('customer-pos.store') }}" class="auth-form admin-form customers-form" novalidate>
                    @csrf
                    <h2 id="cpo-create-fields" class="visually-hidden">بيانات أمر الشراء</h2>

                    <div class="customers-form-section">
                        <h2 class="customers-form-section-title">المنشأة وعرض السعر</h2>
                        <p class="customers-form-section-lead">بعد اختيار المنشأة والعرض تظهر البنود. الحفظ ينشئ الصفقة تلقائيًا.</p>

                        <div class="auth-field">
                            <label for="customer_id">المنشأة <span class="required-mark" title="مطلوب">*</span></label>
                            <select id="customer_id" name="customer_id" required
                                    onchange="window.location='{{ route('customer-pos.create') }}?customer_id='+this.value">
                                <option value="">— اختر —</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}"
                                        @selected((int) old('customer_id', $selectedCustomerId) === (int) $customer->id)>
                                        {{ $customer->name_ar }}
                                    </option>
                                @endforeach
                            </select>
                            @error('customer_id')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="auth-field">
                            <label for="quotation_id">عرض السعر (معتمد/مُرسل) <span class="required-mark" title="مطلوب">*</span></label>
                            <select id="quotation_id" name="quotation_id" required
                                    onchange="if(this.value){window.location='{{ route('customer-pos.create') }}?customer_id={{ (int) ($selectedCustomerId ?? 0) }}&quotation_id='+this.value}">
                                <option value="">— اختر —</option>
                                @foreach ($quotations as $quote)
                                    <option value="{{ $quote->id }}"
                                        @selected((int) old('quotation_id', $selectedQuotation?->id) === (int) $quote->id)>
                                        {{ $quote->document_number }} — {{ $quote->customer?->name_ar }} — {{ $quote->status->labelAr() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('quotation_id')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="auth-field">
                            <label for="reference_ar">مرجع أمر الشراء (اختياري)</label>
                            <input id="reference_ar" type="text" name="reference_ar" value="{{ old('reference_ar') }}" maxlength="255">
                            @error('reference_ar')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="auth-field">
                            <label for="notes_ar">ملاحظات</label>
                            <textarea id="notes_ar" name="notes_ar" rows="2" maxlength="2000">{{ old('notes_ar') }}</textarea>
                            @error('notes_ar')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    @if ($selectedQuotation)
                        <div class="cpo-deal-notice" role="status">
                            <p class="cpo-deal-notice-title">ستُنشأ صفقة مفتوحة</p>
                            <p class="cpo-deal-notice-body">
                                عند الحفظ تُنشأ صفقة واحدة مرتبطة بهذا الأمر (1:1). لا يوجد إغلاق صفقة في هذه المرحلة.
                            </p>
                        </div>

                        <div class="customers-form-section">
                            <h2 class="customers-form-section-title">البنود</h2>
                            <p class="customers-form-section-lead">
                                الكمية الافتراضية = كمية العرض. لا تتجاوز كمية بند العرض. البنود ثابتة بعد الإنشاء.
                            </p>
                            <div class="ds-table-surface">
                                <div class="ds-data-body">
                                    <table class="admin-table cpo-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">المنتج</th>
                                                <th scope="col">كمية العرض (حد أقصى)</th>
                                                <th scope="col">كمية أمر الشراء</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($selectedQuotation->lines as $index => $line)
                                                <tr>
                                                    <td class="ds-cell-primary">
                                                        {{ $line->product_name_ar ?? $line->product?->name_ar }}
                                                        <input type="hidden" name="lines[{{ $index }}][quotation_line_id]" value="{{ $line->id }}">
                                                    </td>
                                                    <td class="dir-ltr inventory-qty">{{ qty($line->quantity) }}</td>
                                                    <td>
                                                        <label class="visually-hidden" for="cpo-qty-{{ $index }}">كمية البند {{ $index + 1 }}</label>
                                                        <input id="cpo-qty-{{ $index }}" type="number" step="0.001" min="0.001"
                                                               max="{{ number_format((float) $line->quantity, 3, '.', '') }}"
                                                               name="lines[{{ $index }}][quantity]"
                                                               value="{{ old('lines.'.$index.'.quantity', number_format((float) $line->quantity, 3, '.', '')) }}"
                                                               required class="dir-ltr cpo-qty-input">
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
                    @endif

                    <div class="admin-form-actions">
                        <button type="submit" class="btn btn-glass-primary" @disabled(! $selectedQuotation)>حفظ وإنشاء الصفقة</button>
                        <a href="{{ route('customer-pos.index') }}" class="btn btn-glass">إلغاء</a>
                    </div>
                </form>
            </section>
        @endif
    </div>
@endsection
