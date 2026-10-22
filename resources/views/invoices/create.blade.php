@extends('layouts.app')

@section('title', 'إصدار فاتورة — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المالية · إصدار فاتورة</span>
@endsection

@section('content')
    @php
        $selected = $selectedId
            ? $deliveries->firstWhere('id', $selectedId)
            : null;
    @endphp

    <div class="invoices-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="inv-create-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">إصدار · PF3</p>
                <h1 id="inv-create-heading" class="admin-title">إصدار فاتورة من تسليم مؤكد</h1>
                <p class="admin-lead">
                    تسليم واحد → فاتورة واحدة. الأسعار لقطة من عرض السعر (شامل الضريبة 15%) عند الإصدار.
                    بلا خصومات · لا تعديل للمبالغ بعد الإصدار (تصحيح عبر إشعار).
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('invoices.index') }}" class="btn btn-glass">القائمة</a>
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($deliveries->isEmpty())
            <div class="ds-data-empty ds-data-empty-surface" role="status">
                <strong>لا تسليمات جاهزة للفوترة</strong>
                <p>يلزم تسليم مؤكد بلا فاتورة مرتبطة. لا يمكن إصدار فاتورة مكررة لنفس التسليم.</p>
            </div>
        @else
            <section class="fin-form-card" aria-labelledby="inv-create-fields">
                <form method="POST" action="{{ route('invoices.store') }}" class="auth-form admin-form customers-form" novalidate>
                    @csrf
                    <h2 id="inv-create-fields" class="visually-hidden">بيانات الإصدار</h2>

                    <div class="customers-form-section">
                        <h2 class="customers-form-section-title">التسليم المؤكد</h2>
                        <p class="customers-form-section-lead">اختر تسليمًا مؤكدًا غير مُفوتر ضمن نطاقك.</p>

                        <div class="auth-field">
                            <label for="delivery_id">التسليم <span class="required-mark" title="مطلوب">*</span></label>
                            <select id="delivery_id" name="delivery_id" required
                                    onchange="if(this.value){window.location='{{ route('invoices.create') }}?delivery_id='+this.value}">
                                <option value="">— اختر —</option>
                                @foreach ($deliveries as $d)
                                    <option value="{{ $d->id }}" @selected((int) old('delivery_id', $selectedId) === (int) $d->id)>
                                        #{{ $d->id }} — {{ $d->customer?->name_ar }} — أمر بيع #{{ $d->sales_order_id }}
                                    </option>
                                @endforeach
                            </select>
                            @error('delivery_id')
                                <p class="auth-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($selected)
                            <div class="cpo-deal-notice" role="status">
                                <p class="cpo-deal-notice-title">معاينة البنود (كميات)</p>
                                <p class="cpo-deal-notice-body">
                                    الإجمالي يُحسب عند الإصدار من أسعار عرض السعر المرتبطة — لا معاينة سعرية هنا قبل الحفظ.
                                </p>
                            </div>
                            <div class="ds-table-surface">
                                <div class="ds-data-body">
                                    <table class="admin-table fin-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">المنتج</th>
                                                <th scope="col">الكمية</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($selected->lines as $line)
                                                <tr>
                                                    <td class="ds-cell-primary">{{ $line->product?->name_ar ?? '—' }}</td>
                                                    <td class="dir-ltr inventory-qty">{{ qty($line->quantity) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        <div class="auth-field">
                            <label for="notes_ar">ملاحظات</label>
                            <textarea id="notes_ar" name="notes_ar" rows="2" maxlength="2000">{{ old('notes_ar') }}</textarea>
                        </div>
                    </div>

                    <div class="admin-form-actions">
                        <button type="submit" class="btn btn-glass-primary"
                                onclick="return confirm('إصدار الفاتورة؟ المبالغ تصبح غير قابلة للتعديل المباشر.');">
                            إصدار الفاتورة
                        </button>
                        <a href="{{ route('invoices.index') }}" class="btn btn-glass">إلغاء</a>
                    </div>
                </form>
            </section>
        @endif
    </div>
@endsection
