@extends('layouts.app')

@section('title', 'عرض سعر '.$quotation->document_number.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · تفاصيل عرض السعر</span>
@endsection

@section('content')
    @php
        $st = $quotation->status instanceof \BackedEnum
            ? $quotation->status->value
            : (string) $quotation->status;
        $badge = match ($st) {
            'approved', 'sent' => 'badge-success',
            'pending_price_approval' => 'badge-warning',
            'cancelled', 'expired' => 'badge-danger',
            'superseded' => 'badge-neutral',
            default => 'badge-neutral',
        };
        $canDecide = $canApprove || $canReject;
        $canAct = $canSubmit || $canDecide || $canSend || $canSupersede || $canCancel || ($canConvert ?? false);
        $total = $quotation->totalVatInclusive();
    @endphp

    <div class="quotations-page quotation-show-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="quote-show-heading">
            <div class="ds-page-bar-copy">
                <h1 id="quote-show-heading" class="admin-title">{{ $quotation->customer?->name_ar ?? 'عرض سعر' }}</h1>
                <p class="procurement-show-status">
                    <span class="badge {{ $badge }}">{{ $quotation->status->labelAr() }}</span>
                    @if ($canUpdate)
                        <span class="badge badge-warning">بانتظار تسعيرك</span>
                    @endif
                </p>
            </div>
            @if ($canPrint)
                <div class="ds-page-bar-tools">
                    <a href="{{ route('quotations.print', $quotation) }}" class="btn btn-glass" target="_blank" rel="noopener">عرض عرض السعر PDF</a>
                </div>
            @endif
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($canUpdate)
            @include('quotations.partials.margin-pricing')
        @endif

        @if ($canAct)
            <section class="so-detail-section quotation-action-section" aria-labelledby="quote-decide-heading">
                <div class="so-detail-section-head">
                    <h2 id="quote-decide-heading" class="customers-form-section-title">الإجراءات</h2>
                </div>
                <div class="quote-decide-actions">
                    @if ($canDecide)
                        <form method="POST" action="{{ route('quotations.decide', $quotation) }}"
                              class="auth-form quote-decide-form">
                            @csrf
                            <div class="auth-field">
                                <label for="decision_notes">ملاحظات القرار</label>
                                <input id="decision_notes" type="text" name="decision_notes"
                                       value="{{ old('decision_notes') }}" maxlength="1000">
                            </div>
                            <div class="quote-decide-btns">
                                @if ($canApprove)
                                    <button type="submit" name="decision" value="approve" class="btn btn-glass-primary"
                                            onclick="return confirm('اعتماد التسعير وإتاحة العرض للمندوب؟');">
                                        اعتماد التسعير
                                    </button>
                                @endif
                                @if ($canReject)
                                    <button type="submit" name="decision" value="reject" class="btn btn-glass inventory-reject-btn"
                                            onclick="return confirm('رفض الاعتماد وإعادة العرض إلى مسودة؟');">رفض</button>
                                @endif
                            </div>
                        </form>
                    @endif

                    @if ($canSend)
                        <form method="POST" action="{{ route('quotations.send', $quotation) }}"
                              onsubmit="return confirm('إرسال العرض المعتمد بالبريد؟');">
                            @csrf
                            <button type="submit" class="btn btn-glass-primary">إرسال بالبريد</button>
                        </form>
                    @endif

                    @if ($canConvert ?? false)
                        <button
                            type="button"
                            class="btn btn-glass-primary"
                            data-quote-convert-open
                            data-quote-id="{{ $quotation->id }}"
                            data-quote-number="{{ $quotation->document_number }}"
                            data-quote-customer="{{ e($quotation->customer?->name_ar ?? '') }}"
                            data-quote-total="{{ money($total) }}"
                            data-quote-action="{{ route('quotations.convert.store', $quotation) }}"
                        >تحويل إلى أمر بيع</button>
                    @endif

                    @if ($canSupersede)
                        <form method="POST" action="{{ route('quotations.supersede', $quotation) }}"
                              onsubmit="return confirm('استبدال بنسخة مسودة جديدة؟ سيُعلَّم العرض الحالي كمُستبدل.');">
                            @csrf
                            <button type="submit" class="btn btn-glass">استبدال بنسخة جديدة</button>
                        </form>
                    @endif

                    @if ($canCancel)
                        <form method="POST" action="{{ route('quotations.cancel', $quotation) }}"
                              onsubmit="return confirm('إلغاء عرض السعر؟');">
                            @csrf
                            <button type="submit" class="btn btn-glass inventory-reject-btn">إلغاء</button>
                        </form>
                    @endif
                </div>
            </section>
        @endif

        <section class="so-detail-section" aria-labelledby="quote-summary-heading">
            <div class="so-detail-section-head">
                <h2 id="quote-summary-heading" class="customers-form-section-title">ملخص العرض</h2>
            </div>
            <div class="so-detail-grid" role="list">
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">رقم العرض</p>
                    <p class="so-detail-value"><span class="so-detail-ltr">{{ $quotation->document_number }}</span></p>
                </article>
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">الحالة</p>
                    <p class="so-detail-value"><span class="badge {{ $badge }}">{{ $quotation->status->labelAr() }}</span></p>
                </article>
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">الإجمالي شامل الضريبة</p>
                    <p class="so-detail-value dir-ltr">
                        {{ money($total) }}
                        <span class="so-detail-unit">ر.س</span>
                    </p>
                </article>
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">طلب البيع</p>
                    <p class="so-detail-value">
                        <a href="{{ route('sales-requests.show', $quotation->sales_request_id) }}" class="quotes-sr-chip dir-ltr">#{{ $quotation->sales_request_id }}</a>
                    </p>
                </article>
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">ينتهي في</p>
                    <p class="so-detail-value dir-ltr">
                        {{ $quotation->expires_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                    </p>
                </article>
                <article class="so-detail-field" role="listitem">
                    <p class="so-detail-label">مستلم البريد</p>
                    <p class="so-detail-value dir-ltr">{{ $recipientPreview ?? '—' }}</p>
                </article>
                @if ($quotation->sent_to_email)
                    <article class="so-detail-field" role="listitem">
                        <p class="so-detail-label">أُرسل إلى</p>
                        <p class="so-detail-value dir-ltr">{{ $quotation->sent_to_email }}</p>
                    </article>
                @endif
                @if ($quotation->isDraft() && ! $canUpdate)
                    <article class="so-detail-field so-detail-field-wide" role="listitem">
                        <p class="so-detail-label">التسعير</p>
                        <p class="so-detail-value">بانتظار تسعير مدير المبيعات</p>
                    </article>
                @endif
            </div>
        </section>

        @if (! $canUpdate && ($quotation->notes_ar || $quotation->decision_notes))
            <section class="so-detail-section" aria-labelledby="quote-notes-heading">
                <div class="so-detail-section-head">
                    <h2 id="quote-notes-heading" class="customers-form-section-title">ملاحظات</h2>
                </div>
                <div class="so-detail-grid" role="list">
                    @if ($quotation->notes_ar)
                        <article class="so-detail-field so-detail-field-wide" role="listitem">
                            <p class="so-detail-label">داخلية</p>
                            <p class="so-detail-value">{{ $quotation->notes_ar }}</p>
                        </article>
                    @endif
                    @if ($quotation->decision_notes)
                        <article class="so-detail-field so-detail-field-wide" role="listitem">
                            <p class="so-detail-label">قرار الاعتماد</p>
                            <p class="so-detail-value">{{ $quotation->decision_notes }}</p>
                        </article>
                    @endif
                </div>
            </section>
        @endif

        @if (! $canUpdate)
            <section class="so-detail-section" aria-labelledby="qlines">
                <div class="so-detail-section-head">
                    <h2 id="qlines" class="customers-form-section-title">البنود</h2>
                </div>
                <div class="ds-list-panel quote-lines-panel">
                    <div class="ds-list-body">
                        <table class="admin-table admin-table--sheet quotes-table">
                            <thead>
                                <tr>
                                    <th scope="col">المنتج</th>
                                    <th scope="col">الكمية</th>
                                    <th scope="col" class="quotes-col-amount">التكلفة</th>
                                    <th scope="col" class="quotes-col-amount">الهامش</th>
                                    <th scope="col" class="quotes-col-amount">سعر الوحدة شامل الضريبة</th>
                                    <th scope="col" class="quotes-col-amount">ضريبة الوحدة</th>
                                    <th scope="col" class="quotes-col-amount">إجمالي البند</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($quotation->lines as $line)
                                    <tr>
                                        <td>
                                            <span class="ds-cell-primary">{{ $line->product_name_ar }}</span>
                                        </td>
                                        <td class="dir-ltr inventory-qty">{{ qty($line->quantity) }}</td>
                                        <td class="quotes-col-amount">
                                            <span class="dir-ltr inventory-qty">{{ money($line->supplier_cost) }}</span>
                                        </td>
                                        <td class="quotes-col-amount">
                                            <span class="dir-ltr inventory-qty">{{ money($line->margin_amount) }}</span>
                                        </td>
                                        <td class="quotes-col-amount">
                                            <span class="quotes-amount dir-ltr quote-selling-cell">
                                                {{ money($line->selling_price_vat_inclusive) }}
                                                <span class="quotes-amount-unit">ر.س</span>
                                            </span>
                                        </td>
                                        <td class="quotes-col-amount">
                                            <span class="dir-ltr inventory-qty">{{ money($line->vatPortionUnit()) }}</span>
                                        </td>
                                        <td class="quotes-col-amount">
                                            <span class="quotes-amount dir-ltr">
                                                {{ money($line->lineTotalVatInclusive()) }}
                                                <span class="quotes-amount-unit">ر.س</span>
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @elseif ($quotation->decision_notes)
            <section class="so-detail-section" aria-labelledby="quote-decision-notes-heading">
                <div class="so-detail-section-head">
                    <h2 id="quote-decision-notes-heading" class="customers-form-section-title">ملاحظات القرار</h2>
                </div>
                <p class="quote-notes-body">{{ $quotation->decision_notes }}</p>
            </section>
        @endif

        @if ($quotation->sendLogs->isNotEmpty())
            <section class="so-detail-section" aria-labelledby="quote-send-log-heading">
                <div class="so-detail-section-head">
                    <h2 id="quote-send-log-heading" class="customers-form-section-title">سجل الإرسال</h2>
                </div>
                <div class="ds-list-panel quote-lines-panel">
                    <div class="ds-list-body">
                        <table class="admin-table admin-table--sheet quotes-table">
                            <thead>
                                <tr>
                                    <th scope="col">الوقت</th>
                                    <th scope="col">الحالة</th>
                                    <th scope="col">المستلم</th>
                                    <th scope="col">الخطأ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($quotation->sendLogs as $log)
                                    @php
                                        $logOk = $log->status === \App\Models\QuotationSendLog::STATUS_SENT
                                            || $log->status === 'sent';
                                    @endphp
                                    <tr>
                                        <td class="dir-ltr ds-cell-muted">
                                            {{ $log->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                                        </td>
                                        <td>
                                            <span class="badge {{ $logOk ? 'badge-success' : 'badge-danger' }}">
                                                {{ $logOk ? 'أُرسل' : 'فشل' }}
                                            </span>
                                        </td>
                                        <td class="dir-ltr">{{ $log->recipient_email }}</td>
                                        <td>{{ $log->error_message ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif

        @if ($canConvert ?? false)
            @php $convertQuotation = $quotation; @endphp
            @include('quotations.partials.convert-modal')
        @endif
    </div>
@endsection
