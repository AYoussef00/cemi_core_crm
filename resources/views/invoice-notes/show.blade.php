@extends('layouts.app')

@section('title', 'إشعار #'.$note->id.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المالية · تفاصيل الإشعار</span>
@endsection

@section('content')
    @php
        $typeVal = $note->type instanceof \BackedEnum ? $note->type->value : (string) $note->type;
        $st = $note->status instanceof \BackedEnum ? $note->status->value : (string) $note->status;
        $typeBadge = $typeVal === 'credit' ? 'badge-success' : 'badge-warning';
        $stBadge = match ($st) {
            'approved' => 'badge-success',
            'pending_approval' => 'badge-warning',
            'rejected' => 'badge-danger',
            default => 'badge-neutral',
        };
        $effectCopy = $typeVal === 'credit'
            ? 'بعد الاعتماد: يخفض متبقي الفاتورة (−)'
            : 'بعد الاعتماد: يزيد متبقي الفاتورة (+)';
        $canAct = $canSubmit || $canDecide;
    @endphp

    <div class="notes-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="note-show-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">إشعار · #{{ $note->id }}</p>
                <h1 id="note-show-heading" class="admin-title">
                    <span class="badge {{ $typeBadge }}">{{ $note->type->labelAr() }}</span>
                    <span class="badge {{ $stBadge }}">{{ $note->status->labelAr() }}</span>
                </h1>
                <p class="admin-lead">
                    فاتورة
                    <a href="{{ route('invoices.show', $note->invoice_id) }}" class="sr-task-link dir-ltr">{{ doc_no('INV', $note->invoice_id) }}</a>
                    · {{ money($note->amount) }} ر.س
                    · {{ $effectCopy }}
                </p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('invoice-notes.index') }}" class="btn btn-glass">القائمة</a>
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="inventory-metrics" aria-label="ملخص الإشعار">
            <article class="inventory-metric">
                <p class="inventory-metric-label">النوع</p>
                <p class="inventory-metric-value inventory-metric-value-sm">{{ $note->type->labelAr() }}</p>
            </article>
            <article class="inventory-metric">
                <p class="inventory-metric-label">المبلغ</p>
                <p class="inventory-metric-value dir-ltr">
                    {{ money($note->amount) }}
                    <span class="inventory-metric-unit">ر.س</span>
                </p>
            </article>
            <article class="inventory-metric @if ($st === 'pending_approval') inventory-metric-available @endif">
                <p class="inventory-metric-label">الحالة</p>
                <p class="inventory-metric-value inventory-metric-value-sm">{{ $note->status->labelAr() }}</p>
            </article>
        </section>

        @if ($note->reason_ar)
            <section class="quote-notes-card" aria-labelledby="note-reason-heading">
                <h2 id="note-reason-heading" class="customers-form-section-title">السبب</h2>
                <p class="quote-notes-body">{{ $note->reason_ar }}</p>
            </section>
        @endif

        @if ($canAct)
            <section class="quote-decide-panel" aria-labelledby="note-act-heading">
                <div class="sr-decide-copy">
                    <h2 id="note-act-heading" class="customers-form-section-title">إجراءات الإشعار</h2>
                    <p class="customers-form-section-lead">
                        الاعتماد لمدير المبيعات فقط — لا اعتماد ذاتي لمن أنشأ الإشعار.
                    </p>
                </div>

                <div class="quote-decide-actions">
                    @if ($canSubmit)
                        <form method="POST" action="{{ route('invoice-notes.submit', $note) }}"
                              onsubmit="return confirm('إرسال الإشعار للاعتماد؟');">
                            @csrf
                            <button type="submit" class="btn btn-glass-primary">إرسال للاعتماد</button>
                        </form>
                    @endif

                    @if ($canDecide)
                        <form method="POST" action="{{ route('invoice-notes.decide', $note) }}"
                              class="auth-form quote-decide-form">
                            @csrf
                            <div class="auth-field">
                                <label for="decision_notes">ملاحظات القرار</label>
                                <input id="decision_notes" type="text" name="decision_notes"
                                       value="{{ old('decision_notes') }}" maxlength="1000">
                            </div>
                            <div class="quote-decide-btns">
                                <button type="submit" name="decision" value="approve" class="btn btn-glass-primary"
                                        onclick="return confirm('اعتماد الإشعار وتحديث المتبقي؟');">اعتماد</button>
                                <button type="submit" name="decision" value="reject" class="btn btn-glass inventory-reject-btn"
                                        onclick="return confirm('رفض الإشعار؟');">رفض</button>
                            </div>
                        </form>
                    @endif
                </div>
            </section>
        @endif
    </div>
@endsection
