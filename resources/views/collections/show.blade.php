@extends('layouts.app')

@section('title', 'تحصيل #'.$collection->id.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المالية · تفاصيل التحصيل</span>
@endsection

@section('content')
    @php
        $st = $collection->status instanceof \BackedEnum
            ? $collection->status->value
            : (string) $collection->status;
        $badge = match ($st) {
            'confirmed' => 'badge-success',
            'draft' => 'badge-warning',
            'reversed' => 'badge-danger',
            default => 'badge-neutral',
        };
        $statusKicker = match ($st) {
            'confirmed' => 'مؤكد · خفّض المتبقي',
            'draft' => 'مسودة · لا يؤثر على المتبقي',
            'reversed' => 'معكوس',
            default => $collection->status->labelAr(),
        };
        $canAct = $canConfirm || $canRequestReversal || ($canDecideReversal && $collection->reversal);
    @endphp

    <div class="collections-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="col-show-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">تحصيل · #{{ $collection->id }}</p>
                <h1 id="col-show-heading" class="admin-title">
                    {{ $collection->customer?->name_ar ?? 'تحصيل' }}
                    <span class="badge {{ $badge }}">{{ $collection->status->labelAr() }}</span>
                </h1>
                <p class="admin-lead">
                    {{ money($collection->amount_total) }} ر.س
                    · {{ $statusKicker }}
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('collections.index') }}" class="btn btn-glass">القائمة</a>
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($st === 'draft')
            <div class="col-draft-banner" role="status">
                <p class="col-draft-banner-title">مسودة — لم يُخفض المتبقي</p>
                <p class="col-draft-banner-body">
                    التخصيصات ظاهرة أدناه للتحضير فقط. اضغط «تأكيد التحصيل» لتطبيق CR7 على الفواتير.
                </p>
            </div>
        @endif

        <section class="inventory-metrics" aria-label="ملخص التحصيل">
            <article class="inventory-metric @if ($st === 'draft') inventory-metric-available @endif">
                <p class="inventory-metric-label">الحالة</p>
                <p class="inventory-metric-value inventory-metric-value-sm">{{ $statusKicker }}</p>
            </article>
            <article class="inventory-metric">
                <p class="inventory-metric-label">المبلغ الإجمالي</p>
                <p class="inventory-metric-value dir-ltr">
                    {{ money($collection->amount_total) }}
                    <span class="inventory-metric-unit">ر.س</span>
                </p>
            </article>
            <article class="inventory-metric">
                <p class="inventory-metric-label">التخصيصات</p>
                <p class="inventory-metric-value dir-ltr">{{ $collection->allocations->count() }}</p>
            </article>
        </section>

        <section class="fin-lines-card" aria-labelledby="col-alloc-heading">
            <h2 id="col-alloc-heading" class="customers-form-section-title">التخصيصات</h2>
            <div class="ds-table-surface">
                <div class="ds-data-body">
                    <table class="admin-table fin-table">
                        <thead>
                            <tr>
                                <th scope="col">فاتورة</th>
                                <th scope="col">المبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($collection->allocations as $a)
                                <tr>
                                    <td>
                                        <a href="{{ route('invoices.show', $a->invoice_id) }}" class="sr-task-link dir-ltr">{{ doc_no('INV', $a->invoice_id) }}</a>
                                    </td>
                                    <td class="dir-ltr inventory-qty">{{ money($a->amount) }} ر.س</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @if ($canAct)
            <section class="quote-decide-panel" aria-labelledby="col-act-heading">
                <div class="sr-decide-copy">
                    <h2 id="col-act-heading" class="customers-form-section-title">إجراءات التحصيل</h2>
                    <p class="customers-form-section-lead">
                        التأكيد يخفض المتبقي. العكس يعيده بعد اعتماد مدير المبيعات.
                    </p>
                </div>

                <div class="quote-decide-actions">
                    @if ($canConfirm)
                        <form method="POST" action="{{ route('collections.confirm', $collection) }}"
                              onsubmit="return confirm('تأكيد التحصيل؟ سيُخفض متبقي الفواتير المخصصة.');">
                            @csrf
                            <button type="submit" class="btn btn-glass-primary">تأكيد التحصيل (يخفض المتبقي)</button>
                        </form>
                    @endif

                    @if ($canRequestReversal)
                        <form method="POST" action="{{ route('collections.request-reversal', $collection) }}"
                              class="auth-form quote-decide-form"
                              onsubmit="return confirm('طلب عكس التحصيل؟');">
                            @csrf
                            <div class="auth-field">
                                <label for="reason_ar">سبب طلب العكس</label>
                                <input id="reason_ar" type="text" name="reason_ar" value="{{ old('reason_ar') }}" maxlength="1000">
                            </div>
                            <button type="submit" class="btn btn-glass">طلب عكس التحصيل</button>
                        </form>
                    @endif

                    @if ($canDecideReversal && $collection->reversal)
                        <form method="POST" action="{{ route('collections.decide-reversal', $collection) }}"
                              class="auth-form quote-decide-form">
                            @csrf
                            <div class="auth-field">
                                <label for="decision_notes">ملاحظات القرار</label>
                                <input id="decision_notes" type="text" name="decision_notes" value="{{ old('decision_notes') }}" maxlength="1000">
                            </div>
                            <div class="quote-decide-btns">
                                <button type="submit" name="decision" value="approve" class="btn btn-glass-primary"
                                        onclick="return confirm('اعتماد العكس وإعادة المتبقي؟');">اعتماد العكس</button>
                                <button type="submit" name="decision" value="reject" class="btn btn-glass inventory-reject-btn"
                                        onclick="return confirm('رفض طلب العكس؟');">رفض</button>
                            </div>
                        </form>
                    @endif
                </div>
            </section>
        @endif
    </div>
@endsection
