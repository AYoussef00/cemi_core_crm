{{-- Shared convert-to-sales-order popup (PDF upload required). --}}
@php
    $maxMb = round(((int) config('attachments.max_kilobytes', 5120)) / 1024, 1);
    $openId = old('_convert_quotation_id', session('open_convert_quotation_id', request('convert')));
@endphp

<dialog
    id="quote-convert-modal"
    class="ds-modal"
    aria-labelledby="quote-convert-modal-title"
    @if ($errors->has('customer_po_pdf') || $errors->has('convert') || filled($openId)) data-auto-open="1" @endif
>
    <form
        id="quote-convert-form"
        method="POST"
        action="{{ filled($openId) ? route('quotations.convert.store', $openId) : '#' }}"
        enctype="multipart/form-data"
        class="ds-modal-card"
        novalidate
    >
        @csrf
        <input type="hidden" name="_convert_quotation_id" id="quote-convert-id-field" value="{{ $openId }}">

        <header class="ds-modal-head">
            <div class="ds-modal-head-copy">
                <p class="ds-modal-kicker">
                    <span class="ds-modal-kicker-dot" aria-hidden="true"></span>
                    عرض معتمد · PDF إلزامي
                </p>
                <h2 id="quote-convert-modal-title" class="ds-modal-title">
                    تحويل إلى أمر بيع
                    <span id="quote-convert-id-label" class="ds-modal-id dir-ltr">@if(filled($openId)){{ doc_no('QA', $openId) }}@endif</span>
                </h2>
            </div>
            <button type="button" class="ds-modal-close" data-quote-convert-close aria-label="إغلاق">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" aria-hidden="true">
                    <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </header>

        <div class="ds-modal-body">
            <p class="ds-modal-lead">
                ارفع أمر الشراء من العميل (PDF) لإتمام التحويل.
                يُنشأ أمر شراء العميل والصفقة ثم مسودة أمر البيع.
            </p>

            <div
                class="ds-modal-summary"
                id="quote-convert-meta"
                @if (filled($openId) && isset($convertQuotation) && $convertQuotation)
                    data-customer="{{ $convertQuotation->customer?->name_ar ?? '' }}"
                    data-total="{{ money($convertQuotation->totalVatInclusive()) }}"
                @endif
            >
                @if (filled($openId) && isset($convertQuotation) && $convertQuotation)
                    <div class="ds-modal-summary-item">
                        <span class="ds-modal-summary-label">المنشأة</span>
                        <strong class="ds-modal-summary-value">{{ $convertQuotation->customer?->name_ar ?? '—' }}</strong>
                    </div>
                    <div class="ds-modal-summary-item">
                        <span class="ds-modal-summary-label">الإجمالي شامل الضريبة</span>
                        <strong class="ds-modal-summary-value dir-ltr">{{ money($convertQuotation->totalVatInclusive()) }} ر.س</strong>
                    </div>
                @endif
            </div>

            @if ($errors->any())
                <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif

            <div class="ds-modal-field">
                <label for="customer_po_pdf" class="ds-modal-label">
                    ملف أمر الشراء
                    <span class="required-mark" title="مطلوب">*</span>
                </label>
                <label class="ds-modal-dropzone" for="customer_po_pdf" id="quote-convert-dropzone">
                    <span class="ds-modal-dropzone-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none">
                            <path d="M12 16V7m0 0 3.5 3.5M12 7 8.5 10.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M5 16.5V18a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-1.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span class="ds-modal-dropzone-copy">
                        <strong id="quote-convert-file-label">اختر ملف PDF</strong>
                        <span>أو اسحبه وأفلته هنا · حد أقصى {{ $maxMb }} ميجابايت</span>
                    </span>
                    <input
                        id="customer_po_pdf"
                        class="ds-modal-file-input"
                        type="file"
                        name="customer_po_pdf"
                        accept="application/pdf,.pdf"
                        required
                    >
                </label>
                @error('customer_po_pdf')
                    <p class="ds-modal-error" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="ds-modal-field">
                <label for="convert_reference_ar" class="ds-modal-label">
                    مرجع أمر الشراء
                    <span class="optional-mark">اختياري</span>
                </label>
                <input
                    id="convert_reference_ar"
                    class="ds-modal-input"
                    type="text"
                    name="reference_ar"
                    value="{{ old('reference_ar') }}"
                    maxlength="255"
                    placeholder="مرجع العميل (اختياري)"
                    autocomplete="off"
                >
            </div>

            <div class="ds-modal-field">
                <label for="convert_notes_ar" class="ds-modal-label">
                    ملاحظات
                    <span class="optional-mark">اختياري</span>
                </label>
                <textarea
                    id="convert_notes_ar"
                    class="ds-modal-input ds-modal-textarea"
                    name="notes_ar"
                    rows="3"
                    maxlength="2000"
                    placeholder="أي ملاحظة مرتبطة بأمر الشراء…"
                >{{ old('notes_ar') }}</textarea>
            </div>
        </div>

        <footer class="ds-modal-foot">
            <button type="button" class="btn btn-glass" data-quote-convert-close>إلغاء</button>
            <button type="submit" class="btn btn-glass-primary">تحويل إلى أمر بيع</button>
        </footer>
    </form>
</dialog>

<script>
(() => {
    const modal = document.getElementById('quote-convert-modal');
    const form = document.getElementById('quote-convert-form');
    if (!modal || !form) return;

    const idField = document.getElementById('quote-convert-id-field');
    const idLabel = document.getElementById('quote-convert-id-label');
    const meta = document.getElementById('quote-convert-meta');
    const fileInput = document.getElementById('customer_po_pdf');
    const fileLabel = document.getElementById('quote-convert-file-label');
    const dropzone = document.getElementById('quote-convert-dropzone');
    const storeTpl = @json(route('quotations.convert.store', ['quotation' => '__ID__']));

    const escapeHtml = (value) => String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');

    const renderMeta = (customer, total) => {
        if (!meta) return;
        const parts = [];
        if (customer) {
            parts.push(
                '<div class="ds-modal-summary-item">'
                + '<span class="ds-modal-summary-label">المنشأة</span>'
                + '<strong class="ds-modal-summary-value">' + escapeHtml(customer) + '</strong>'
                + '</div>'
            );
        }
        if (total) {
            parts.push(
                '<div class="ds-modal-summary-item">'
                + '<span class="ds-modal-summary-label">الإجمالي شامل الضريبة</span>'
                + '<strong class="ds-modal-summary-value dir-ltr">' + escapeHtml(total) + ' ر.س</strong>'
                + '</div>'
            );
        }
        meta.innerHTML = parts.join('');
        meta.hidden = parts.length === 0;
    };

    const syncFileLabel = () => {
        if (!fileInput || !fileLabel || !dropzone) return;
        const file = fileInput.files && fileInput.files[0];
        if (file) {
            fileLabel.textContent = file.name;
            dropzone.classList.add('has-file');
        } else {
            fileLabel.textContent = 'اختر ملف PDF';
            dropzone.classList.remove('has-file');
        }
    };

    const openModal = ({ id, number, customer, total, action }) => {
        const url = action || storeTpl.replace('__ID__', encodeURIComponent(id));
        form.action = url;
        if (idField) idField.value = id || '';
        if (idLabel) idLabel.textContent = number || '';
        renderMeta(customer || '', total || '');
        if (fileInput) {
            fileInput.value = '';
            syncFileLabel();
        }
        if (typeof modal.showModal === 'function') {
            modal.showModal();
        } else {
            modal.setAttribute('open', 'open');
        }
    };

    const closeModal = () => {
        if (typeof modal.close === 'function') {
            modal.close();
        } else {
            modal.removeAttribute('open');
        }
    };

    document.querySelectorAll('[data-quote-convert-open]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const row = btn.closest('details');
            if (row) row.removeAttribute('open');
            openModal({
                id: btn.getAttribute('data-quote-id'),
                number: btn.getAttribute('data-quote-number') || '',
                customer: btn.getAttribute('data-quote-customer') || '',
                total: btn.getAttribute('data-quote-total') || '',
                action: btn.getAttribute('data-quote-action') || '',
            });
        });
    });

    modal.querySelectorAll('[data-quote-convert-close]').forEach((btn) => {
        btn.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    if (fileInput) {
        fileInput.addEventListener('change', syncFileLabel);
    }

    if (dropzone) {
        ['dragenter', 'dragover'].forEach((evt) => {
            dropzone.addEventListener(evt, (e) => {
                e.preventDefault();
                dropzone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach((evt) => {
            dropzone.addEventListener(evt, (e) => {
                e.preventDefault();
                dropzone.classList.remove('is-dragover');
            });
        });
        dropzone.addEventListener('drop', (e) => {
            const files = e.dataTransfer?.files;
            if (!files || !files.length || !fileInput) return;
            try {
                const dt = new DataTransfer();
                dt.items.add(files[0]);
                fileInput.files = dt.files;
            } catch (_) {
                /* browsers that block FileList assignment still allow click-to-pick */
            }
            syncFileLabel();
        });
    }

    if (modal.hasAttribute('data-auto-open')) {
        openModal({
            id: idField?.value || '',
            number: idLabel?.textContent || '',
            customer: meta?.dataset?.customer || '',
            total: meta?.dataset?.total || '',
            action: form.action,
        });
    }
})();
</script>
