@extends('layouts.app')

@section('title', 'تسوية مخزون #'.$adjustment->id.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المخزون · تسوية</span>
@endsection

@section('content')
    @php
        $statusValue = $adjustment->status instanceof \BackedEnum
            ? $adjustment->status->value
            : (string) $adjustment->status;
        $statusBadge = match ($statusValue) {
            'approved' => 'badge-success',
            'rejected' => 'badge-danger',
            default => 'badge-warning',
        };
        $statusTone = match ($statusValue) {
            'approved' => 'inventory-status-approved',
            'rejected' => 'inventory-status-rejected',
            default => 'inventory-status-pending',
        };
        $statusKicker = match ($statusValue) {
            'approved' => 'معتمدة',
            'rejected' => 'مرفوضة',
            default => 'بانتظار الاعتماد',
        };
        $delta = (float) $adjustment->quantity_delta;
        $deltaClass = $delta > 0
            ? 'inventory-delta is-increase'
            : ($delta < 0 ? 'inventory-delta is-decrease' : 'inventory-delta');
        $onHand = (float) ($balance?->on_hand ?? 0);
        $reserved = (float) ($balance?->reserved ?? 0);
        $available = (float) ($balance?->available() ?? '0.000');
    @endphp

    <div class="inventory-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="adj-show-heading">
            <div class="ds-page-bar-copy">
                <h1 id="adj-show-heading" class="admin-title">
                    تسوية #{{ $adjustment->id }}
                    <span class="badge {{ $statusBadge }}">{{ $adjustment->status->labelAr() }}</span>
                </h1>
                <p class="admin-lead">{{ $adjustment->product?->name_ar ?? '—' }}</p>
            </div>
            <div class="ds-page-bar-tools">
                <a href="{{ route('inventory.adjustments.index') }}" class="btn btn-glass">القائمة</a>
                <a href="{{ route('inventory.stock.index') }}" class="btn btn-glass">الأرصدة</a>
            </div>
        </header>

        @error('decision')
            <div class="app-alert app-alert-danger" role="alert">{{ $message }}</div>
        @enderror

        <section class="inventory-summary {{ $statusTone }}" aria-labelledby="adj-summary-heading">
            <div class="visit-panel-head">
                <p class="visit-panel-kicker visit-panel-kicker-{{ $statusValue === 'approved' ? 'ok' : ($statusValue === 'rejected' ? 'danger' : 'schedule') }}">
                    {{ $statusKicker }}
                </p>
                <h2 id="adj-summary-heading" class="visit-panel-title">ملخص التسوية</h2>
                <p class="visit-panel-lead">
                    @if ($statusValue === 'approved')
                        طُبّقت الكمية على الرصيد مع قيد دفتر. الحالة نهائية.
                    @elseif ($statusValue === 'rejected')
                        رُفضت التسوية — لم يتغيّر الرصيد. الحالة نهائية.
                    @else
                        بانتظار قرار مدير المبيعات. الاعتماد يحدّث الرصيد؛ النقصان يُرفض إذا أصبح المتاح سالبًا.
                    @endif
                </p>
            </div>

            <dl class="customers-detail products-detail inventory-detail">
                <div class="customers-detail-item">
                    <dt>المنتج</dt>
                    <dd>
                        @if ($adjustment->product)
                            <a href="{{ route('products.show', $adjustment->product) }}">{{ $adjustment->product->name_ar }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="customers-detail-item">
                    <dt>كمية التسوية</dt>
                    <dd class="dir-ltr">
                        <span class="{{ $deltaClass }}">
                            {{ $delta > 0 ? '+' : '' }}{{ qty($delta) }}
                        </span>
                    </dd>
                </div>
                <div class="customers-detail-item inventory-detail-wide">
                    <dt>السبب</dt>
                    <dd>{{ $adjustment->reason_ar }}</dd>
                </div>
                <div class="customers-detail-item">
                    <dt>الحالة</dt>
                    <dd><span class="badge {{ $statusBadge }}">{{ $adjustment->status->labelAr() }}</span></dd>
                </div>
                <div class="customers-detail-item">
                    <dt>سجّلها</dt>
                    <dd>{{ $adjustment->recordedBy?->name ?? '—' }}</dd>
                </div>
                @if ($adjustment->decidedBy)
                    <div class="customers-detail-item">
                        <dt>القرار بواسطة</dt>
                        <dd>{{ $adjustment->decidedBy->name }}</dd>
                    </div>
                @endif
                @if ($adjustment->decision_notes)
                    <div class="customers-detail-item inventory-detail-wide">
                        <dt>ملاحظات القرار</dt>
                        <dd>{{ $adjustment->decision_notes }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        <section class="inventory-balance-snapshot" aria-labelledby="adj-balance-heading">
            <div class="visit-panel-head">
                <p class="visit-panel-kicker visit-panel-kicker-locked">لقطة الرصيد</p>
                <h2 id="adj-balance-heading" class="visit-panel-title">الرصيد الحالي</h2>
                <p class="visit-panel-lead">
                    @if ($statusValue === 'approved')
                        بعد تطبيق هذه التسوية (إن نجحت).
                    @else
                        قبل الاعتماد — لم تُطبَّق كمية هذه التسوية بعد.
                    @endif
                </p>
            </div>
            <div class="inventory-metrics" role="group" aria-label="أرقام الرصيد">
                <div class="inventory-metric">
                    <span class="inventory-metric-label">الرصيد</span>
                    <span class="inventory-metric-value dir-ltr">{{ qty($onHand) }}</span>
                </div>
                <div class="inventory-metric">
                    <span class="inventory-metric-label">المحجوز</span>
                    <span class="inventory-metric-value dir-ltr">{{ qty($reserved) }}</span>
                </div>
                <div class="inventory-metric inventory-metric-available">
                    <span class="inventory-metric-label">المتاح</span>
                    <span class="inventory-metric-value dir-ltr">{{ qty($available) }}</span>
                </div>
            </div>
            <p class="muted inventory-balance-formula" role="note">
                رصيد {{ qty($onHand) }}
                · محجوز {{ qty($reserved) }}
                · متاح {{ qty($available) }}
            </p>
        </section>

        @if ($canApprove || $canReject)
            <section class="inventory-decide-panel" aria-labelledby="adj-decide-heading">
                <div class="visit-panel-head">
                    <p class="visit-panel-kicker visit-panel-kicker-ok">قرار الاعتماد</p>
                    <h2 id="adj-decide-heading" class="visit-panel-title">قرار الاعتماد</h2>
                    <p class="visit-panel-lead">
                        عند الاعتماد تُطبَّق الكمية على الرصيد مع قيد دفتر.
                        النقصان يُرفض إذا أصبح المتاح سالبًا.
                        لا يمكن لمسجل التسوية اعتمادها بنفسه.
                    </p>
                </div>
                <form method="POST" action="{{ route('inventory.adjustments.decide', $adjustment) }}"
                      class="auth-form admin-form inventory-decide-form" novalidate>
                    @csrf
                    <div class="auth-field">
                        <label for="decision_notes">ملاحظات (اختياري)</label>
                        <textarea id="decision_notes" name="decision_notes" rows="2" maxlength="1000">{{ old('decision_notes') }}</textarea>
                    </div>
                    <div class="admin-form-actions">
                        @if ($canApprove)
                            <button type="submit" name="decision" value="approve" class="btn btn-glass-primary"
                                    onclick="return confirm('تأكيد اعتماد هذه التسوية وتطبيقها على الرصيد؟');">
                                اعتماد
                            </button>
                        @endif
                        @if ($canReject)
                            <button type="submit" name="decision" value="reject" class="btn btn-glass inventory-reject-btn"
                                    onclick="return confirm('تأكيد رفض هذه التسوية؟ لن يتغيّر الرصيد.');">
                                رفض
                            </button>
                        @endif
                    </div>
                </form>
            </section>
        @endif
    </div>
@endsection
