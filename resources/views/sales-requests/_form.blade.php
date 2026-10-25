@php
    /** @var \App\Models\SalesRequest|null $salesRequest */
    $oldLines = old('lines');
    if (! is_array($oldLines) || $oldLines === []) {
        if (isset($salesRequest) && $salesRequest->relationLoaded('lines') && $salesRequest->lines->isNotEmpty()) {
            $oldLines = $salesRequest->lines->map(fn ($line) => [
                'product_id' => $line->product_id,
                'quantity' => (string) $line->quantity,
                'availability' => $line->availability->value,
            ])->all();
        } else {
            $oldLines = [['product_id' => '', 'quantity' => '', 'availability' => 'available']];
        }
    }

    $availLabels = collect($availabilities)->mapWithKeys(
        fn ($avail) => [$avail->value => $avail->labelAr()]
    )->all();
@endphp

<div class="customers-form-section">
    <h2 class="customers-form-section-title">بيانات الطلب</h2>
    <p class="customers-form-section-lead">المنشأة مطلوبة. الملاحظات اختيارية.</p>

    @if ($originVisit ?? null)
        <input type="hidden" name="customer_id" value="{{ $originVisit->customer_id }}">
        <input type="hidden" name="origin_visit_id" value="{{ $originVisit->id }}">
        <div class="sr-origin-banner" role="note">
            <p class="sr-origin-kicker">من زيارة مكتملة</p>
            <p class="sr-origin-title">{{ $originVisit->customer?->name_ar ?? 'المنشأة' }}</p>
            <p class="sr-origin-lead">
                المنشأة ثابتة من الزيارة.
                <a href="{{ route('visits.show', $originVisit) }}" class="sr-origin-link">عرض الزيارة</a>
                — الطلب لا يُنشأ حتى تحفظ المسودة بالبنود.
            </p>
        </div>
        @error('origin_visit_id')
            <p class="auth-error" role="alert">{{ $message }}</p>
        @enderror
        @error('customer_id')
            <p class="auth-error" role="alert">{{ $message }}</p>
        @enderror
    @else
        <div class="auth-field">
            <label for="customer_id">المنشأة <span class="required-mark" title="مطلوب">*</span></label>
            <select id="customer_id" name="customer_id" required aria-required="true"
                    aria-invalid="{{ $errors->has('customer_id') ? 'true' : 'false' }}">
                <option value="">— اختر المنشأة —</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}"
                        @selected((string) old('customer_id', $salesRequest->customer_id ?? $preselectCustomerId ?? '') === (string) $customer->id)>
                        {{ $customer->name_ar }}
                    </option>
                @endforeach
            </select>
            @error('customer_id')
                <p class="auth-error" role="alert">{{ $message }}</p>
            @enderror
        </div>
    @endif

    <div class="auth-field">
        <label for="notes_ar">ملاحظات</label>
        <textarea id="notes_ar" name="notes_ar" rows="2" maxlength="2000">{{ old('notes_ar', $salesRequest->notes_ar ?? $prefillNotesAr ?? '') }}</textarea>
        @error('notes_ar')
            <p class="auth-error" role="alert">{{ $message }}</p>
        @enderror
    </div>
</div>

<fieldset class="sr-lines-fieldset customers-form-section" data-sr-lines
          data-avail-available-label="{{ $availLabels['available'] ?? 'متوفر (بدون مشتريات)' }}"
          data-avail-procurement-label="{{ $availLabels['needs_procurement'] ?? 'يحتاج توريد / غير متوفر' }}">
    <legend class="customers-form-section-title">بنود الطلب <span class="required-mark" title="مطلوب">*</span></legend>
    <p class="customers-form-section-lead">
        التوفر يُحدَّد تلقائيًا من رصيد المستودع: إذا كانت الكمية أكبر من المتاح يصبح البند «يحتاج توريد / غير متوفر».
    </p>
    @error('lines')
        <p class="auth-error" role="alert">{{ $message }}</p>
    @enderror

    <div class="sr-lines-list" data-sr-lines-list>
        @foreach ($oldLines as $index => $line)
            @php
                $lineAvail = (string) ($line['availability'] ?? 'available');
                $lineAvailLabel = $availLabels[$lineAvail] ?? $lineAvail;
            @endphp
            <div class="sr-line-row" data-sr-line>
                <div class="sr-line-fields">
                    <div class="auth-field">
                        <label for="line-product-{{ $index }}">المنتج</label>
                        <select id="line-product-{{ $index }}" name="lines[{{ $index }}][product_id]" required data-sr-product>
                            <option value="">— اختر —</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}"
                                    data-stock="{{ $stockHints[$product->id] ?? '0.000' }}"
                                    @selected((string) ($line['product_id'] ?? '') === (string) $product->id)>
                                    {{ $product->name_ar }}
                                    @if ($product->unit)
                                        ({{ $product->unit->name_ar }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('lines.'.$index.'.product_id')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="auth-field">
                        <label for="line-qty-{{ $index }}">الكمية</label>
                        <input id="line-qty-{{ $index }}" type="text" inputmode="decimal" name="lines[{{ $index }}][quantity]"
                               value="{{ $line['quantity'] ?? '' }}" required class="dir-ltr" data-sr-qty>
                        @error('lines.'.$index.'.quantity')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="auth-field">
                        <label for="line-avail-{{ $index }}">التوفر</label>
                        <input type="hidden" id="line-avail-{{ $index }}" name="lines[{{ $index }}][availability]"
                               value="{{ $lineAvail }}" data-sr-availability>
                        <p class="sr-avail-badge" data-sr-avail-badge data-avail="{{ $lineAvail }}" aria-live="polite">
                            {{ $lineAvailLabel }}
                        </p>
                    </div>
                </div>
                <div class="sr-line-meta">
                    <p class="sr-stock-hint" data-sr-stock-hint>
                        المتاح في المستودع:
                        <span class="dir-ltr inventory-qty" data-sr-stock-value>
                            @php
                                $pid = (int) ($line['product_id'] ?? 0);
                            @endphp
                            {{ $pid > 0 ? ($stockHints[$pid] ?? '0.000') : '—' }}
                        </span>
                    </p>
                    <button type="button" class="btn btn-glass btn-sm" data-sr-remove-line>حذف البند</button>
                </div>
            </div>
        @endforeach
    </div>

    <button type="button" class="btn btn-glass" data-sr-add-line>+ بند</button>
</fieldset>

<template id="sr-line-template">
    <div class="sr-line-row" data-sr-line>
        <div class="sr-line-fields">
            <div class="auth-field">
                <label>المنتج</label>
                <select name="lines[__INDEX__][product_id]" required data-sr-product>
                    <option value="">— اختر —</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" data-stock="{{ $stockHints[$product->id] ?? '0.000' }}">
                            {{ $product->name_ar }}
                            @if ($product->unit)
                                ({{ $product->unit->name_ar }})
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="auth-field">
                <label>الكمية</label>
                <input type="text" inputmode="decimal" name="lines[__INDEX__][quantity]" required class="dir-ltr" data-sr-qty>
            </div>
            <div class="auth-field">
                <label>التوفر</label>
                <input type="hidden" name="lines[__INDEX__][availability]" value="available" data-sr-availability>
                <p class="sr-avail-badge" data-sr-avail-badge data-avail="available" aria-live="polite">
                    {{ $availLabels['available'] ?? 'متوفر (بدون مشتريات)' }}
                </p>
            </div>
        </div>
        <div class="sr-line-meta">
            <p class="sr-stock-hint" data-sr-stock-hint>
                المتاح في المستودع: <span class="dir-ltr inventory-qty" data-sr-stock-value>—</span>
            </p>
            <button type="button" class="btn btn-glass btn-sm" data-sr-remove-line>حذف البند</button>
        </div>
    </div>
</template>

<script>
(() => {
    const root = document.querySelector('[data-sr-lines]');
    if (!root) return;
    const list = root.querySelector('[data-sr-lines-list]');
    const tpl = document.getElementById('sr-line-template');
    const addBtn = root.querySelector('[data-sr-add-line]');
    const labelAvailable = root.dataset.availAvailableLabel || 'متوفر (بدون مشتريات)';
    const labelProcurement = root.dataset.availProcurementLabel || 'يحتاج توريد / غير متوفر';

    const parseQty = (raw) => {
        const n = parseFloat(String(raw || '').replace(',', '.'));
        return Number.isFinite(n) ? n : NaN;
    };

    const reindex = () => {
        list.querySelectorAll('[data-sr-line]').forEach((row, index) => {
            row.querySelectorAll('[name]').forEach((el) => {
                el.name = el.name.replace(/lines\[\d+\]/, `lines[${index}]`);
            });
        });
    };

    const syncAvailability = (row) => {
        const select = row.querySelector('[data-sr-product]');
        const qtyInput = row.querySelector('[data-sr-qty]');
        const valueEl = row.querySelector('[data-sr-stock-value]');
        const hidden = row.querySelector('[data-sr-availability]');
        const badge = row.querySelector('[data-sr-avail-badge]');
        const opt = select?.selectedOptions?.[0];
        const stockRaw = opt?.dataset?.stock;
        const hasProduct = Boolean(opt?.value);

        if (valueEl) {
            valueEl.textContent = hasProduct ? (stockRaw || '0.000') : '—';
        }

        const qty = parseQty(qtyInput?.value);
        const stock = parseQty(stockRaw);
        let availability = 'available';
        if (!hasProduct || !Number.isFinite(qty) || qty <= 0) {
            availability = 'available';
        } else if (!Number.isFinite(stock) || qty > stock + 1e-9) {
            availability = 'needs_procurement';
        }

        if (hidden) hidden.value = availability;
        if (badge) {
            badge.dataset.avail = availability;
            badge.textContent = availability === 'needs_procurement' ? labelProcurement : labelAvailable;
        }
    };

    const bindRow = (row) => {
        const select = row.querySelector('[data-sr-product]');
        const qtyInput = row.querySelector('[data-sr-qty]');
        select?.addEventListener('change', () => syncAvailability(row));
        qtyInput?.addEventListener('input', () => syncAvailability(row));
        qtyInput?.addEventListener('change', () => syncAvailability(row));
        row.querySelector('[data-sr-remove-line]')?.addEventListener('click', () => {
            if (list.querySelectorAll('[data-sr-line]').length <= 1) return;
            row.remove();
            reindex();
        });
        syncAvailability(row);
    };

    list.querySelectorAll('[data-sr-line]').forEach(bindRow);

    addBtn?.addEventListener('click', () => {
        const html = tpl.innerHTML.replaceAll('__INDEX__', String(list.querySelectorAll('[data-sr-line]').length));
        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        const row = wrap.firstElementChild;
        list.appendChild(row);
        bindRow(row);
        reindex();
    });
})();
</script>
