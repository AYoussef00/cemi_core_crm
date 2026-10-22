{{-- Inline SM margin pricing on quotation show (PM1). --}}
<section class="so-detail-section quote-pricing-panel" id="quote-pricing" aria-labelledby="quote-pricing-heading">
    <div class="so-detail-section-head">
        <h2 id="quote-pricing-heading" class="customers-form-section-title">تسعير الهامش</h2>
        <p class="customers-form-section-lead quote-pricing-lead">
            سعر البيع الشامل = التكلفة + الهامش. ضريبة 15% مدمجة في السعر · بلا خصومات.
            احفظ ثم اعتمد لإتاحة العرض للمندوب.
        </p>
    </div>

    <form method="POST" action="{{ route('quotations.update', $quotation) }}"
          class="auth-form admin-form customers-form quote-pricing-form" novalidate data-quote-margin-form>
        @csrf
        @method('PUT')

        <input type="hidden" name="notes_ar" value="{{ old('notes_ar', $quotation->notes_ar) }}">

        <div class="quote-pricing-meta">
            <div class="auth-field">
                <label for="expires_at">تاريخ انتهاء الصلاحية</label>
                <input id="expires_at" type="datetime-local" name="expires_at" class="dir-ltr"
                       value="{{ old('expires_at', $quotation->expires_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i')) }}">
                @error('expires_at')
                    <p class="auth-error" role="alert">{{ $message }}</p>
                @enderror
            </div>
        </div>

        @error('lines')
            <p class="auth-error" role="alert">{{ $message }}</p>
        @enderror

        <div class="ds-list-panel quote-lines-panel">
            <div class="ds-list-body">
                <table class="admin-table admin-table--sheet quotes-table" data-quote-lines>
                    <thead>
                        <tr>
                            <th scope="col">المنتج</th>
                            <th scope="col">الكمية</th>
                            <th scope="col" class="quotes-col-amount">التكلفة</th>
                            <th scope="col" class="quotes-col-amount">الهامش (ر.س)</th>
                            <th scope="col" class="quotes-col-amount">سعر الوحدة شامل الضريبة</th>
                            <th scope="col" class="quotes-col-amount">إجمالي البند</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quotation->lines as $index => $line)
                            @php
                                $cost = number_format((float) $line->supplier_cost, 2, '.', '');
                                $qty = number_format((float) $line->quantity, 3, '.', '');
                                $marginOld = old('lines.'.$index.'.margin_amount', $line->margin_amount);
                            @endphp
                            <tr data-quote-line data-cost="{{ $cost }}" data-qty="{{ $qty }}">
                                <td>
                                    <span class="ds-cell-primary">{{ $line->product_name_ar }}</span>
                                    <input type="hidden" name="lines[{{ $index }}][id]" value="{{ $line->id }}">
                                </td>
                                <td class="dir-ltr inventory-qty">{{ qty($line->quantity) }}</td>
                                <td class="quotes-col-amount">
                                    <span class="dir-ltr inventory-qty">{{ money($line->supplier_cost) }}</span>
                                </td>
                                <td class="quotes-col-amount">
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
                                <td class="quotes-col-amount">
                                    <span class="quotes-amount dir-ltr quote-selling-cell">
                                        <span class="quote-selling-preview" data-quote-selling>
                                            {{ money($line->selling_price_vat_inclusive) }}
                                        </span>
                                        <span class="quotes-amount-unit">ر.س</span>
                                    </span>
                                    <span class="quote-preview-hint ds-cell-muted">(معاينة)</span>
                                </td>
                                <td class="quotes-col-amount">
                                    <span class="quotes-amount dir-ltr">
                                        <span data-quote-line-total>{{ money($line->lineTotalVatInclusive()) }}</span>
                                        <span class="quotes-amount-unit">ر.س</span>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="quote-pricing-footer">
            <p class="quote-pricing-total" aria-live="polite">
                الإجمالي شامل الضريبة (معاينة):
                <span class="dir-ltr quote-selling-cell" data-quote-grand-total>{{ money($quotation->totalVatInclusive()) }}</span>
                <span class="so-detail-unit">ر.س</span>
            </p>
            <div class="admin-form-actions quote-pricing-actions">
                <button type="submit" class="btn btn-glass-primary">حفظ الهوامش</button>
            </div>
        </div>
    </form>
</section>

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

    const parseNum = (raw) => {
        const n = Number(String(raw || '').trim().replace(',', '.'));
        return Number.isFinite(n) ? n : null;
    };

    const grandEl = form.querySelector('[data-quote-grand-total]');

    const refresh = () => {
        let grand = 0;
        let complete = true;

        form.querySelectorAll('[data-quote-line]').forEach((row) => {
            const cost = parseFloat(row.dataset.cost || '0') || 0;
            const qty = parseFloat(row.dataset.qty || '0') || 0;
            const input = row.querySelector('[data-quote-margin]');
            const sellOut = row.querySelector('[data-quote-selling]');
            const lineOut = row.querySelector('[data-quote-line-total]');
            if (!input || !sellOut || !lineOut) return;

            const margin = parseNum(input.value);
            if (margin === null) {
                complete = false;
                sellOut.textContent = '—';
                lineOut.textContent = '—';
                return;
            }

            const unit = cost + margin;
            const lineTotal = unit * qty;
            sellOut.textContent = formatMoney(unit);
            lineOut.textContent = formatMoney(lineTotal);
            grand += lineTotal;
        });

        if (grandEl) {
            grandEl.textContent = complete ? formatMoney(grand) : '—';
        }
    };

    form.querySelectorAll('[data-quote-line]').forEach((row) => {
        row.querySelector('[data-quote-margin]')?.addEventListener('input', refresh);
    });
    refresh();
})();
</script>
