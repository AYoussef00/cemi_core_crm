@extends('layouts.app')

@section('title', 'إشعار جديد — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المالية · إشعار دائن/مدين</span>
@endsection

@section('content')
    <div class="notes-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="note-create-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">مسودة · الحسابات</p>
                <h1 id="note-create-heading" class="admin-title">إشعار دائن / مدين</h1>
                <p class="admin-lead">
                    الدائن يخفض المتبقي بعد الاعتماد · المدين يزيده.
                    مبلغ الدائن لا يتجاوز متبقي الفاتورة الحالي. بلا خصومات تجارية منفصلة.
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('invoice-notes.index') }}" class="btn btn-glass">القائمة</a>
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="fin-form-card" aria-labelledby="note-create-fields">
            <form method="POST" action="{{ route('invoice-notes.store') }}" class="auth-form admin-form customers-form" novalidate>
                @csrf
                <h2 id="note-create-fields" class="visually-hidden">بيانات الإشعار</h2>

                <div class="customers-form-section">
                    <h2 class="customers-form-section-title">الفاتورة والنوع</h2>

                    <div class="auth-field">
                        <label for="invoice_id">الفاتورة <span class="required-mark" title="مطلوب">*</span></label>
                        <select id="invoice_id" name="invoice_id" required>
                            <option value="">— اختر —</option>
                            @foreach ($invoices as $inv)
                                <option value="{{ $inv->id }}" @selected((int) old('invoice_id', $selectedInvoiceId) === (int) $inv->id)>
                                    {{ $inv->document_number }} — {{ $inv->customer?->name_ar }}
                                    — متبقي {{ money($inv->unpaidBalance()) }} ر.س
                                </option>
                            @endforeach
                        </select>
                        @error('invoice_id')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="auth-field">
                        <label for="type">النوع <span class="required-mark" title="مطلوب">*</span></label>
                        <select id="type" name="type" required>
                            @foreach ($types as $t)
                                <option value="{{ $t->value }}" @selected(old('type', 'credit') === $t->value)>{{ $t->labelAr() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="auth-field">
                        <label for="amount">المبلغ (ر.س) <span class="required-mark" title="مطلوب">*</span></label>
                        <input id="amount" type="number" step="0.01" min="0.01" name="amount"
                               value="{{ old('amount') }}" required class="dir-ltr cpo-qty-input"
                               placeholder="0.00">
                        <p class="field-hint">منزلتان عشريتان · للدائن: ≤ المتبقي الحالي</p>
                        @error('amount')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="auth-field">
                        <label for="reason_ar">السبب</label>
                        <textarea id="reason_ar" name="reason_ar" rows="2" maxlength="2000">{{ old('reason_ar') }}</textarea>
                    </div>
                </div>

                <div class="admin-form-actions">
                    <button type="submit" class="btn btn-glass-primary">حفظ المسودة</button>
                    <a href="{{ route('invoice-notes.index') }}" class="btn btn-glass">إلغاء</a>
                </div>
            </form>
        </section>
    </div>
@endsection
