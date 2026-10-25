@extends('layouts.app')

@section('title', 'طلب بيع #'.$salesRequest->id.' — '.config('app.name'))

@section('page_heading')
    <span class="topbar-muted">المبيعات · تفاصيل طلب البيع</span>
@endsection

@section('content')
    @php
        $statusValue = $salesRequest->status instanceof \BackedEnum
            ? $salesRequest->status->value
            : (string) $salesRequest->status;
        $statusBadge = match ($statusValue) {
            'ready_for_pricing' => 'badge-success',
            'awaiting_procurement' => 'badge-warning',
            'cancelled' => 'badge-danger',
            default => 'badge-neutral',
        };
        $canDecide = $canSubmit || $canCancel;
        $tasksByLine = $salesRequest->procurementTasks->keyBy('sales_request_line_id');
    @endphp

    <div class="sales-requests-page panel-enter">
        <header class="ds-page-bar" aria-labelledby="sr-show-heading">
            <div class="ds-page-bar-copy">
                <p class="page-kicker">طلب بيع · #{{ $salesRequest->id }}</p>
                <h1 id="sr-show-heading" class="admin-title">{{ $salesRequest->customer?->name_ar ?? 'طلب بيع' }}</h1>
                <p class="admin-lead">
                    التوفر لكل بند اختيار صريح. بعد الإرسال: كل البنود متوفرة → جاهز للتسعير؛
                    وإلا → بانتظار المشتريات (مهمة توريد لكل بند يحتاج توريد).
                </p>
            </div>
            <div class="ds-page-bar-tools">
                @if ($canUpdate)
                    <a href="{{ route('sales-requests.edit', $salesRequest) }}" class="btn btn-glass">تعديل المسودة</a>
                @endif
                <a href="{{ route('sales-requests.index') }}" class="btn btn-glass">القائمة</a>
            </div>
        </header>

        @if ($errors->any())
            <div class="app-alert app-alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="inventory-metrics" aria-label="ملخص الطلب">
            <article class="inventory-metric">
                <p class="inventory-metric-label">الحالة</p>
                <p class="inventory-metric-value">
                    <span class="badge {{ $statusBadge }}">{{ $salesRequest->status->labelAr() }}</span>
                </p>
            </article>
            <article class="inventory-metric">
                <p class="inventory-metric-label">البنود</p>
                <p class="inventory-metric-value dir-ltr">{{ $salesRequest->lines->count() }}</p>
            </article>
            <article class="inventory-metric">
                <p class="inventory-metric-label">أُنشئ بواسطة</p>
                <p class="inventory-metric-value inventory-metric-value-sm">{{ $salesRequest->createdBy?->name ?? '—' }}</p>
            </article>
            <article class="inventory-metric">
                <p class="inventory-metric-label">تاريخ الإرسال</p>
                <p class="inventory-metric-value inventory-metric-value-sm dir-ltr">
                    {{ $salesRequest->submitted_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                </p>
            </article>
            @if ($salesRequest->origin_visit_id)
                <article class="inventory-metric">
                    <p class="inventory-metric-label">من زيارة</p>
                    <p class="inventory-metric-value inventory-metric-value-sm">
                        @can('view', $salesRequest->originVisit)
                            <a href="{{ route('visits.show', $salesRequest->originVisit) }}" class="quotes-id-link dir-ltr">#{{ $salesRequest->origin_visit_id }}</a>
                        @else
                            <span class="dir-ltr">#{{ $salesRequest->origin_visit_id }}</span>
                        @endcan
                    </p>
                </article>
            @endif
        </section>

        @if ($salesRequest->notes_ar)
            <section class="sr-notes-card" aria-labelledby="sr-notes-heading">
                <h2 id="sr-notes-heading" class="customers-form-section-title">ملاحظات</h2>
                <p class="sr-notes-body">{{ $salesRequest->notes_ar }}</p>
            </section>
        @endif

        <section class="sr-lines-card" aria-labelledby="sr-lines-heading">
            <div class="sr-section-head">
                <h2 id="sr-lines-heading" class="customers-form-section-title">بنود الطلب</h2>
                <p class="customers-form-section-lead">الرصيد استرشادي. مسار المشتريات يتبع اختيار التوفر فقط.</p>
            </div>
            <div class="ds-table-surface">
                <div class="ds-data-body">
                    <table class="admin-table sr-table">
                        <thead>
                            <tr>
                                <th scope="col">المنتج</th>
                                <th scope="col">الكمية</th>
                                <th scope="col">التوفر</th>
                                <th scope="col">المتاح (استرشادي)</th>
                                @if ($canViewCosts)
                                    <th scope="col">تكلفة المورد</th>
                                @endif
                                <th scope="col">المشتريات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($salesRequest->lines as $line)
                                @php
                                    $availValue = $line->availability instanceof \BackedEnum
                                        ? $line->availability->value
                                        : (string) $line->availability;
                                    $availBadge = $availValue === 'needs_procurement' ? 'badge-warning' : 'badge-success';
                                    $task = $tasksByLine->get($line->id);
                                @endphp
                                <tr>
                                    <td class="ds-cell-primary">
                                        {{ $line->product?->name_ar ?? '—' }}
                                        @if ($line->product?->unit)
                                            <span class="ds-cell-muted">({{ $line->product->unit->name_ar }})</span>
                                        @endif
                                    </td>
                                    <td class="dir-ltr inventory-qty">{{ qty($line->quantity) }}</td>
                                    <td>
                                        <span class="badge {{ $availBadge }}">{{ $line->availability->labelAr() }}</span>
                                    </td>
                                    <td class="dir-ltr inventory-qty">{{ $stockHints[$line->product_id] ?? '0.000' }}</td>
                                    @if ($canViewCosts)
                                        <td class="dir-ltr inventory-qty">
                                            @if ($line->supplier_cost !== null)
                                                {{ money($line->supplier_cost) }} ر.س
                                            @else
                                                <span class="ds-cell-muted">—</span>
                                            @endif
                                        </td>
                                    @endif
                                    <td>
                                        @if ($task)
                                            @can('view', $task)
                                                <a href="{{ route('procurement.tasks.show', $task) }}" class="sr-task-link">
                                                    مهمة #{{ $task->id }}
                                                    <span class="badge {{ $task->status->value === 'open' ? 'badge-warning' : ($task->status->value === 'cost_recorded' ? 'badge-success' : 'badge-danger') }}">
                                                        {{ $task->status->labelAr() }}
                                                    </span>
                                                </a>
                                            @else
                                                <span class="ds-cell-muted">مهمة #{{ $task->id }} · {{ $task->status->labelAr() }}</span>
                                            @endcan
                                        @elseif ($availValue === 'needs_procurement')
                                            <span class="ds-cell-muted">تُنشأ عند الإرسال</span>
                                        @else
                                            <span class="ds-cell-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @if ($salesRequest->quotations->isNotEmpty() || ($canCreateQuotation ?? false))
            <section class="sr-decide-panel sr-quote-panel" aria-labelledby="sr-quote-heading">
                <div class="sr-decide-copy">
                    <h2 id="sr-quote-heading" class="customers-form-section-title">عروض الأسعار</h2>
                    <p class="customers-form-section-lead">
                        بعد تكلفة المشتريات (أو الإرسال للمسار المتوفر) تُفتح مسودة العرض تلقائيًا لمدير المبيعات. المدير يضع الهامش ويعتمد، ثم المندوب يرسل للعميل. شامل ضريبة 15% · بلا خصومات.
                    </p>
                </div>
                @if ($salesRequest->quotations->isNotEmpty())
                    <ul class="sr-quote-list">
                        @foreach ($salesRequest->quotations as $quote)
                            @php
                                $qSt = $quote->status instanceof \BackedEnum
                                    ? $quote->status->value
                                    : (string) $quote->status;
                                $qBadge = match ($qSt) {
                                    'approved', 'sent' => 'badge-success',
                                    'pending_price_approval' => 'badge-warning',
                                    'cancelled', 'expired' => 'badge-danger',
                                    default => 'badge-neutral',
                                };
                            @endphp
                            <li class="sr-quote-item">
                                @can('view', $quote)
                                    <a href="{{ route('quotations.show', $quote) }}" class="sr-task-link">
                                        عرض #{{ $quote->id }}
                                        <span class="ds-cell-muted">v{{ $quote->version }}</span>
                                        <span class="badge {{ $qBadge }}">{{ $quote->status->labelAr() }}</span>
                                    </a>
                                @else
                                    <span class="ds-cell-muted">
                                        عرض #{{ $quote->id }}
                                        <span class="badge {{ $qBadge }}">{{ $quote->status->labelAr() }}</span>
                                    </span>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($canCreateQuotation ?? false)
                    <form method="POST" action="{{ route('sales-requests.quotations.store', $salesRequest) }}" class="sr-decide-actions">
                        @csrf
                        <button type="submit" class="btn btn-glass-primary">إعادة فتح مسودة عرض للمدير</button>
                    </form>
                    <p class="ds-cell-muted" style="margin-top:0.5rem">يظهر هذا الزر فقط إذا تعذّر الإنشاء التلقائي بعد التكلفة.</p>
                @endif
            </section>
        @endif

        @if ($canDecide)
            <section class="sr-decide-panel" aria-labelledby="sr-decide-heading">
                <div class="sr-decide-copy">
                    <h2 id="sr-decide-heading" class="customers-form-section-title">إجراءات الطلب</h2>
                    <p class="customers-form-section-lead">
                        الإرسال نهائي للمسودة. الإلغاء يوقف مسار المشتريات المرتبطة إن وُجدت.
                    </p>
                </div>
                <div class="sr-decide-actions">
                    @if ($canSubmit)
                        <form method="POST" action="{{ route('sales-requests.submit', $salesRequest) }}"
                              onsubmit="return confirm('إرسال الطلب؟ لا يمكن تعديل البنود بعد الإرسال.');">
                            @csrf
                            <button type="submit" class="btn btn-glass-primary">إرسال الطلب</button>
                        </form>
                    @endif
                    @if ($canCancel)
                        <form method="POST" action="{{ route('sales-requests.cancel', $salesRequest) }}"
                              onsubmit="return confirm('تأكيد إلغاء طلب البيع؟');">
                            @csrf
                            <button type="submit" class="btn btn-glass inventory-reject-btn">إلغاء الطلب</button>
                        </form>
                    @endif
                </div>
            </section>
        @endif
    </div>
@endsection
