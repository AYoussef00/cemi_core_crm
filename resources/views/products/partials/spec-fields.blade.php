@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\ProductSpecification>|\Illuminate\Support\Collection $existing */
    $existing = $existing ?? collect();
    $blankRows = (int) ($blankSpecRows ?? 2);
    $oldSpecs = old('specifications');
@endphp

<div class="customers-form-section products-specs-section">
    <h2 class="customers-form-section-title">المواصفات الفنية</h2>
    <p class="customers-form-section-lead">
        الكثافة حقل موثّق. مواصفات إضافية اختيارية كتسمية وقيمة ووحدة اختيارية — دون قائمة خصائص ثابتة غير موثّقة.
    </p>

    <div class="auth-field products-density-field">
        <label for="density_value">الكثافة</label>
        <p class="field-hint" id="density-hint">أدخل القيمة؛ الوحدة اختيارية. لا تُدخل وحدة بلا قيمة.</p>
        <div class="products-density-row" role="group" aria-labelledby="density_value" aria-describedby="density-hint">
            <div class="products-spec-cell">
                <span class="products-spec-cell-label" aria-hidden="true">القيمة</span>
                <input id="density_value" name="density_value" type="text"
                       value="{{ old('density_value', $density?->value ?? '') }}"
                       maxlength="255"
                       placeholder="القيمة"
                       aria-invalid="{{ $errors->has('density_value') ? 'true' : 'false' }}">
            </div>
            <div class="products-spec-cell">
                <span class="products-spec-cell-label" aria-hidden="true">الوحدة</span>
                <input id="density_unit_ar" name="density_unit_ar" type="text"
                       value="{{ old('density_unit_ar', $density?->unit_ar ?? '') }}"
                       maxlength="64"
                       placeholder="الوحدة (اختياري)"
                       aria-label="وحدة الكثافة"
                       aria-invalid="{{ $errors->has('density_unit_ar') ? 'true' : 'false' }}">
            </div>
        </div>
        @error('density_value')
            <p class="auth-error" role="alert">{{ $message }}</p>
        @enderror
        @error('density_unit_ar')
            <p class="auth-error" role="alert">{{ $message }}</p>
        @enderror
    </div>

    <div class="products-specs-list" role="group" aria-label="مواصفات إضافية">
        <p class="products-specs-list-title">مواصفات إضافية</p>
        <div class="products-spec-head" aria-hidden="true">
            <span>التسمية</span>
            <span>القيمة</span>
            <span>الوحدة</span>
        </div>

        @if (is_array($oldSpecs))
            @foreach ($oldSpecs as $index => $row)
                <div class="products-spec-row">
                    <div class="products-spec-cell">
                        <label class="products-spec-cell-label is-mobile-only" for="spec-{{ $index }}-label">التسمية</label>
                        <input id="spec-{{ $index }}-label" type="text" name="specifications[{{ $index }}][label_ar]"
                               value="{{ $row['label_ar'] ?? '' }}" maxlength="255" placeholder="التسمية"
                               aria-label="التسمية">
                    </div>
                    <div class="products-spec-cell">
                        <label class="products-spec-cell-label is-mobile-only" for="spec-{{ $index }}-value">القيمة</label>
                        <input id="spec-{{ $index }}-value" type="text" name="specifications[{{ $index }}][value]"
                               value="{{ $row['value'] ?? '' }}" maxlength="255" placeholder="القيمة"
                               aria-label="القيمة">
                    </div>
                    <div class="products-spec-cell">
                        <label class="products-spec-cell-label is-mobile-only" for="spec-{{ $index }}-unit">الوحدة</label>
                        <input id="spec-{{ $index }}-unit" type="text" name="specifications[{{ $index }}][unit_ar]"
                               value="{{ $row['unit_ar'] ?? '' }}" maxlength="64" placeholder="الوحدة"
                               aria-label="الوحدة">
                    </div>
                </div>
                @error("specifications.{$index}.label_ar")
                    <p class="auth-error" role="alert">{{ $message }}</p>
                @enderror
            @endforeach
        @else
            @foreach ($existing as $index => $spec)
                <div class="products-spec-row">
                    <div class="products-spec-cell">
                        <label class="products-spec-cell-label is-mobile-only" for="spec-{{ $index }}-label">التسمية</label>
                        <input id="spec-{{ $index }}-label" type="text" name="specifications[{{ $index }}][label_ar]"
                               value="{{ $spec->label_ar }}" maxlength="255" placeholder="التسمية"
                               aria-label="التسمية">
                    </div>
                    <div class="products-spec-cell">
                        <label class="products-spec-cell-label is-mobile-only" for="spec-{{ $index }}-value">القيمة</label>
                        <input id="spec-{{ $index }}-value" type="text" name="specifications[{{ $index }}][value]"
                               value="{{ $spec->value }}" maxlength="255" placeholder="القيمة"
                               aria-label="القيمة">
                    </div>
                    <div class="products-spec-cell">
                        <label class="products-spec-cell-label is-mobile-only" for="spec-{{ $index }}-unit">الوحدة</label>
                        <input id="spec-{{ $index }}-unit" type="text" name="specifications[{{ $index }}][unit_ar]"
                               value="{{ $spec->unit_ar }}" maxlength="64" placeholder="الوحدة"
                               aria-label="الوحدة">
                    </div>
                </div>
            @endforeach
            @php $start = $existing->count(); @endphp
            @for ($i = 0; $i < $blankRows; $i++)
                @php $index = $start + $i; @endphp
                <div class="products-spec-row">
                    <div class="products-spec-cell">
                        <label class="products-spec-cell-label is-mobile-only" for="spec-{{ $index }}-label">التسمية</label>
                        <input id="spec-{{ $index }}-label" type="text" name="specifications[{{ $index }}][label_ar]"
                               value="" maxlength="255" placeholder="التسمية"
                               aria-label="التسمية">
                    </div>
                    <div class="products-spec-cell">
                        <label class="products-spec-cell-label is-mobile-only" for="spec-{{ $index }}-value">القيمة</label>
                        <input id="spec-{{ $index }}-value" type="text" name="specifications[{{ $index }}][value]"
                               value="" maxlength="255" placeholder="القيمة"
                               aria-label="القيمة">
                    </div>
                    <div class="products-spec-cell">
                        <label class="products-spec-cell-label is-mobile-only" for="spec-{{ $index }}-unit">الوحدة</label>
                        <input id="spec-{{ $index }}-unit" type="text" name="specifications[{{ $index }}][unit_ar]"
                               value="" maxlength="64" placeholder="الوحدة"
                               aria-label="الوحدة">
                    </div>
                </div>
            @endfor
        @endif
    </div>
</div>
