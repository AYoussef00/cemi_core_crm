@extends('layouts.app')

@section('title', 'تسعير عرض '.$quotation->document_number.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · تسعير العرض</span>
@endsection

@section('content')
    <div class="quotations-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="quote-edit-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">مسودة · تسعير مدير المبيعات</p>
                <h1 id="quote-edit-heading" class="admin-title">تسعير عرض {{ $quotation->document_number }}</h1>
                <p class="admin-lead">
                    المدير يضع هامش الربح فقط (PM1/PM2). سعر البيع الشامل = تكلفة المورد/الشراء + الهامش. ضريبة 15% · بلا خصومات. بعد الحفظ اعتمد لإتاحة العرض للمندوب.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('quotations.show', $quotation) }}" class="btn btn-glass">عرض</a>
                <a href="{{ route('quotations.index') }}" class="btn btn-glass">القائمة</a>
            </div>
        </header>

        <section class="quote-form-card" aria-labelledby="quote-edit-fields-heading">
            <form method="POST" action="{{ route('quotations.update', $quotation) }}"
                  class="auth-form admin-form customers-form" novalidate data-quote-margin-form>
                @csrf
                @method('PUT')
                <h2 id="quote-edit-fields-heading" class="visually-hidden">بيانات التسعير</h2>

                <div class="customers-form-section">
                    <h2 class="customers-form-section-title">صلاحية وملاحظات</h2>
                    <p class="customers-form-section-lead">الانتهاء يمنع الإرسال والاعتماد بعد التاريخ.</p>

                    <div class="auth-field">
                        <label for="expires_at">تاريخ انتهاء الصلاحية</label>
                        <input id="expires_at" type="datetime-local" name="expires_at" class="dir-ltr"
                               value="{{ old('expires_at', $quotation->expires_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i')) }}">
                        @error('expires_at')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="auth-field">
                        <label for="notes_ar">ملاحظات داخلية</label>
                        <textarea id="notes_ar" name="notes_ar" rows="2" maxlength="2000">{{ old('notes_ar', $quotation->notes_ar) }}</textarea>
                        @error('notes_ar')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="customers-form-section">
                    <h2 class="customers-form-section-title">بنود التسعير</h2>
                    <p class="customers-form-section-lead">
                        التكلفة لقطة محفوظة. أدخل الهامش بالريال — المعاينة أدناه تقديرية حتى الحفظ.
                    </p>

                    @error('lines')
                        <p class="auth-error" role="alert">{{ $message }}</p>
                    @enderror

                    <div class="ds-table-surface quote-edit-table-wrap">
                        <div class="ds-data-body">
                            <table class="admin-table quotes-table" data-quote-lines>
                                <thead>
                                    <tr>
                                        <th scope="col">المنتج</th>
                                        <th scope="col">الكمية</th>
                                        <th scope="col">التكلفة (ر.س)</th>
                                        <th scope="col">الهامش (ر.س)</th>
                                        <th scope="col">سعر الوحدة شامل الضريبة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($quotation->lines as $index => $line)
                                        @php
                                            $cost = number_format((float) $line->supplier_cost, 2, '.', '');
                                            $marginOld = old('lines.'.$index.'.margin_amount', $line->margin_amount);
                                        @endphp
                                        <tr data-quote-line data-cost="{{ $cost }}">
                                            <td class="ds-cell-primary">
                                                {{ $line->product_name_ar }}
                                                <input type="hidden" name="lines[{{ $index }}][id]" value="{{ $line->id }}">
                                            </td>
                                            <td class="dir-ltr inventory-qty">{{ qty($line->quantity) }}</td>
                                            <td class="dir-ltr inventory-qty">{{ money($line->supplier_cost) }}</td>
                                            <td>
                                                <label class="visually-hidden" for="margin-{{ $index }}">هامش البند {{ $index + 1 }}</label>
                                                <input id="margin-{{ $index }}" type="text" inputmode="decimal"
                                                       class="dir-ltr quote-margin-input"
                                                       name="lines[{{ $index }}][margin_amount]"
                                                       value="{{ $marginOld }}" required
                                                       data-quote-margin
                                                       aria-invalid="{{ $errors->has('lines.'.$index.'.margin_amount') ? 'true' : 'false' }}">
                                                @error('lines.'.$index.'.margin_amount')
                                                    <p class="auth-error" role="alert">{{ $message }}</p>
                                                @enderror
                                            </td>
                                            <td class="dir-ltr inventory-qty">
                                                <span class="quote-selling-preview" data-quote-selling>
                                                    {{ money($line->selling_price_vat_inclusive) }}
                                                </span>
                                                <span class="ds-cell-muted"> ر.س</span>
                                                <span class="quote-preview-hint ds-cell-muted">(معاينة)</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="admin-form-actions">
                    <button type="submit" class="btn btn-glass-primary">حفظ الهوامش</button>
                    <a href="{{ route('quotations.show', $quotation) }}" class="btn btn-glass">إلغاء</a>
                </div>
            </form>
        </section>
    </div>

    <script>
    (() => {
        const form = document.querySelector('[data-quote-margin-form]');
        if (!form) return;

        const formatMoney = (n) => {
            const x = Math.round((n + Number.EPSILON) * 100) / 100;
            if (Math.abs(x - Math.round(x)) < 1e-9) {
                return Math.round(x).toLocaleString('en-US');
            }
            return x.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        const updateRow = (row) => {
            const cost = parseFloat(row.dataset.cost || '0') || 0;
            const input = row.querySelector('[data-quote-margin]');
            const out = row.querySelector('[data-quote-selling]');
            if (!input || !out) return;
            const raw = String(input.value || '').trim().replace(',', '.');
            const margin = raw === '' || Number.isNaN(Number(raw)) ? null : Number(raw);
            out.textContent = margin === null ? '—' : formatMoney(cost + margin);
        };

        form.querySelectorAll('[data-quote-line]').forEach((row) => {
            updateRow(row);
            row.querySelector('[data-quote-margin]')?.addEventListener('input', () => updateRow(row));
        });
    })();
    </script>
@endsection
