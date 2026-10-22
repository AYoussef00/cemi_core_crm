@extends('layouts.app')

@section('title', 'تحصيل جديد — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المالية · تحصيل جديد</span>
@endsection

@section('content')
    <div class="collections-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="col-create-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">مسودة · لا تخفض المتبقي بعد</p>
                <h1 id="col-create-heading" class="admin-title">تحصيل جديد</h1>
                <p class="admin-lead">
                    خصّص مبالغًا لفواتير غير مسددة. الحفظ ينشئ مسودة فقط — التأكيد لاحقًا يخفض المتبقي (CR7).
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('collections.index') }}" class="btn btn-glass">القائمة</a>
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="fin-form-card" aria-labelledby="col-create-fields">
            <form method="POST" action="{{ route('collections.store') }}" class="auth-form admin-form customers-form" novalidate>
                @csrf
                <h2 id="col-create-fields" class="visually-hidden">بيانات التحصيل</h2>

                <div class="customers-form-section">
                    <h2 class="customers-form-section-title">المنشأة</h2>
                    <div class="auth-field">
                        <label for="customer_id">المنشأة <span class="required-mark" title="مطلوب">*</span></label>
                        <select id="customer_id" name="customer_id" required
                                onchange="window.location='{{ route('collections.create') }}?customer_id='+this.value">
                            <option value="">— اختر —</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}" @selected((int) old('customer_id', $selectedCustomerId) === (int) $c->id)>
                                    {{ $c->name_ar }}
                                </option>
                            @endforeach
                        </select>
                        @error('customer_id')
                            <p class="auth-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="auth-field">
                        <label for="notes_ar">ملاحظات</label>
                        <textarea id="notes_ar" name="notes_ar" rows="2" maxlength="2000">{{ old('notes_ar') }}</textarea>
                    </div>
                </div>

                @if ($selectedCustomerId && $invoices->isNotEmpty())
                    <div class="customers-form-section">
                        <h2 class="customers-form-section-title">التخصيص على الفواتير</h2>
                        <p class="customers-form-section-lead">
                            أدخل مبلغًا ≤ المتبقي الحالي لكل فاتورة. اترك فارغًا لتجاهل الفاتورة.
                        </p>
                        <div class="cpo-deal-notice" role="status">
                            <p class="cpo-deal-notice-title">مسودة فقط</p>
                            <p class="cpo-deal-notice-body">لن يتغير متبقي الفاتورة حتى تؤكد التحصيل من شاشة التفاصيل.</p>
                        </div>
                        <div class="ds-table-surface">
                            <div class="ds-data-body">
                                <table class="admin-table fin-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">فاتورة</th>
                                            <th scope="col">المتبقي الآن</th>
                                            <th scope="col">مبلغ التحصيل</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($invoices as $i => $inv)
                                            <tr>
                                                <td class="ds-cell-primary">
                                                    <a href="{{ route('invoices.show', $inv) }}" class="sr-task-link dir-ltr">{{ $inv->document_number }}</a>
                                                    <input type="hidden" name="allocations[{{ $i }}][invoice_id]" value="{{ $inv->id }}">
                                                </td>
                                                <td class="dir-ltr inventory-qty fin-unpaid-emph">
                                                    {{ money($inv->unpaidBalance()) }} ر.س
                                                </td>
                                                <td>
                                                    <label class="visually-hidden" for="alloc-{{ $i }}">مبلغ فاتورة {{ $inv->id }}</label>
                                                    <input id="alloc-{{ $i }}" type="number" step="0.01" min="0.01"
                                                           max="{{ $inv->unpaidBalance() }}"
                                                           name="allocations[{{ $i }}][amount]" class="dir-ltr cpo-qty-input"
                                                           value="{{ old('allocations.'.$i.'.amount') }}"
                                                           placeholder="0.00">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @elseif ($selectedCustomerId)
                    <p class="customers-form-section-lead">لا فواتير غير مسددة لهذه المنشأة.</p>
                @endif

                <div class="admin-form-actions">
                    <button type="submit" class="btn btn-glass-primary"
                            @disabled(! $selectedCustomerId || $invoices->isEmpty())>
                        حفظ المسودة
                    </button>
                    <a href="{{ route('collections.index') }}" class="btn btn-glass">إلغاء</a>
                </div>
            </form>
        </section>
    </div>
@endsection
