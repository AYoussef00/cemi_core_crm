@php
    /** @var array $commercial */
    $counts = $commercial['counts'];
    $perms = $commercial['permissions'];
    $showMoney = $perms['prices'] || $perms['invoices'];

    $tabs = [];
    if ($perms['sales_requests']) {
        $tabs[] = ['id' => 'sales_requests', 'label' => 'طلبات البيع', 'count' => $counts['sales_requests']];
    }
    if ($perms['quotations']) {
        $tabs[] = ['id' => 'quotations', 'label' => 'عروض الأسعار', 'count' => $counts['quotations']];
    }
    if ($perms['customer_pos']) {
        $tabs[] = ['id' => 'purchase_orders', 'label' => 'أوامر الشراء', 'count' => $counts['purchase_orders']];
    }
    if ($perms['sales_orders']) {
        $tabs[] = ['id' => 'sales_orders', 'label' => 'أوامر البيع', 'count' => $counts['sales_orders']];
    }
    if ($perms['delivery']) {
        $tabs[] = ['id' => 'deliveries', 'label' => 'التسليمات', 'count' => $counts['deliveries']];
    }
    if ($perms['invoices']) {
        $tabs[] = ['id' => 'outstanding', 'label' => 'مستحقات', 'count' => $commercial['unpaid_invoices_count']];
        $tabs[] = ['id' => 'invoices', 'label' => 'الفواتير', 'count' => $counts['invoices']];
    }
    if ($perms['collections']) {
        $tabs[] = ['id' => 'collections', 'label' => 'التحصيلات', 'count' => $counts['collections']];
    }
    if ($perms['deals']) {
        $tabs[] = ['id' => 'deals', 'label' => 'الصفقات', 'count' => $counts['deals']];
    }
    $firstTab = $tabs[0]['id'] ?? null;
@endphp

<section class="so-detail-section customer-commercial" aria-labelledby="cust-commercial-heading">
    <div class="so-detail-section-head">
        <h2 id="cust-commercial-heading" class="customers-form-section-title">الملف التجاري</h2>
    </div>

    <div class="customers-commercial-metrics" role="list">
        @if ($perms['quotations'])
            <div class="customers-commercial-metric" role="listitem">
                <span class="customers-commercial-metric-label">عروض أسعار</span>
                <span class="customers-commercial-metric-value dir-ltr">{{ number_format($counts['quotations']) }}</span>
            </div>
        @endif
        @if ($perms['customer_pos'])
            <div class="customers-commercial-metric" role="listitem">
                <span class="customers-commercial-metric-label">أوامر شراء</span>
                <span class="customers-commercial-metric-value dir-ltr">{{ number_format($counts['purchase_orders']) }}</span>
            </div>
        @endif
        @if ($perms['sales_orders'])
            <div class="customers-commercial-metric" role="listitem">
                <span class="customers-commercial-metric-label">أوامر بيع</span>
                <span class="customers-commercial-metric-value dir-ltr">{{ number_format($counts['sales_orders']) }}</span>
            </div>
        @endif
        @if ($perms['delivery'])
            <div class="customers-commercial-metric" role="listitem">
                <span class="customers-commercial-metric-label">تسليمات</span>
                <span class="customers-commercial-metric-value dir-ltr">{{ number_format($counts['deliveries']) }}</span>
            </div>
        @endif
        @if ($perms['invoices'])
            <div class="customers-commercial-metric" role="listitem">
                <span class="customers-commercial-metric-label">فواتير</span>
                <span class="customers-commercial-metric-value dir-ltr">{{ number_format($counts['invoices']) }}</span>
            </div>
            <div class="customers-commercial-metric {{ $commercial['unpaid_invoices_count'] > 0 ? 'is-warn' : '' }}" role="listitem">
                <span class="customers-commercial-metric-label">مستحقات مفتوحة</span>
                <span class="customers-commercial-metric-value dir-ltr">{{ number_format($commercial['unpaid_invoices_count']) }}</span>
            </div>
        @endif
    </div>

    @if ($tabs !== [])
        <div class="customer-tabs" data-customer-tabs>
            <div class="customer-tabs-nav" role="tablist" aria-label="السجل التجاري">
                @foreach ($tabs as $tab)
                    <button
                        type="button"
                        class="customer-tab-btn {{ $tab['id'] === $firstTab ? 'is-active' : '' }}"
                        role="tab"
                        id="cust-tab-{{ $tab['id'] }}"
                        aria-selected="{{ $tab['id'] === $firstTab ? 'true' : 'false' }}"
                        aria-controls="cust-panel-{{ $tab['id'] }}"
                        data-tab="{{ $tab['id'] }}"
                    >
                        {{ $tab['label'] }}
                        <span class="customer-tab-count dir-ltr">{{ number_format($tab['count']) }}</span>
                    </button>
                @endforeach
            </div>

            <div class="customer-tabs-panels">
                @if ($perms['sales_requests'])
                    <div
                        class="customer-tab-panel {{ $firstTab === 'sales_requests' ? 'is-active' : '' }}"
                        id="cust-panel-sales_requests"
                        role="tabpanel"
                        aria-labelledby="cust-tab-sales_requests"
                        @unless ($firstTab === 'sales_requests') hidden @endunless
                    >
                        @if ($commercial['sales_requests']->isEmpty())
                            <div class="ds-data-empty is-compact" role="status">
                                <strong>لا طلبات بيع</strong>
                                @if ($canCreateSalesRequest ?? false)
                                    <p class="ds-data-empty-lead">الزيارة ليست شرطًا. ابدأ مسودة لهذه المنشأة عند الحاجة.</p>
                                    <a href="{{ route('sales-requests.create', ['customer_id' => $customer->id]) }}" class="btn btn-glass-primary btn-sm">طلب بيع</a>
                                @endif
                            </div>
                        @else
                            <div class="ds-table-surface">
                                <div class="ds-data-body">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">الطلب</th>
                                                <th scope="col">الحالة</th>
                                                <th scope="col">التاريخ</th>
                                                <th scope="col">إجراء</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($commercial['sales_requests'] as $sr)
                                                <tr>
                                                    <td class="dir-ltr">#{{ $sr->id }}</td>
                                                    <td><span class="badge badge-warning">{{ $sr->status->labelAr() }}</span></td>
                                                    <td class="dir-ltr ds-cell-muted">{{ $sr->created_at?->timezone(config('app.timezone'))->format('Y-m-d') }}</td>
                                                    <td class="ds-cell-actions">
                                                        <a href="{{ route('sales-requests.show', $sr) }}" class="customers-name-link">عرض</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if ($perms['quotations'])
                    <div
                        class="customer-tab-panel {{ $firstTab === 'quotations' ? 'is-active' : '' }}"
                        id="cust-panel-quotations"
                        role="tabpanel"
                        aria-labelledby="cust-tab-quotations"
                        @unless ($firstTab === 'quotations') hidden @endunless
                    >
                        @if ($commercial['quotations']->isEmpty())
                            <div class="ds-data-empty is-compact" role="status"><strong>لا عروض أسعار</strong></div>
                        @else
                            <div class="ds-table-surface">
                                <div class="ds-data-body">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">العرض</th>
                                                <th scope="col">الحالة</th>
                                                @if ($showMoney)
                                                    <th scope="col">الإجمالي</th>
                                                @endif
                                                <th scope="col">التاريخ</th>
                                                <th scope="col">إجراء</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($commercial['quotations'] as $quote)
                                                <tr>
                                                    <td class="dir-ltr">{{ $quote->document_number }} · v{{ $quote->version }}</td>
                                                    <td><span class="badge badge-warning">{{ $quote->status->labelAr() }}</span></td>
                                                    @if ($showMoney)
                                                        <td class="dir-ltr">{{ money($quote->totalVatInclusive()) }}</td>
                                                    @endif
                                                    <td class="dir-ltr ds-cell-muted">{{ $quote->created_at?->timezone(config('app.timezone'))->format('Y-m-d') }}</td>
                                                    <td class="ds-cell-actions">
                                                        <a href="{{ route('quotations.show', $quote) }}" class="customers-name-link">عرض</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if ($perms['customer_pos'])
                    <div
                        class="customer-tab-panel {{ $firstTab === 'purchase_orders' ? 'is-active' : '' }}"
                        id="cust-panel-purchase_orders"
                        role="tabpanel"
                        aria-labelledby="cust-tab-purchase_orders"
                        @unless ($firstTab === 'purchase_orders') hidden @endunless
                    >
                        @if ($commercial['purchase_orders']->isEmpty())
                            <div class="ds-data-empty is-compact" role="status"><strong>لا أوامر شراء</strong></div>
                        @else
                            <div class="ds-table-surface">
                                <div class="ds-data-body">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">أمر الشراء</th>
                                                <th scope="col">المرجع</th>
                                                <th scope="col">الحالة</th>
                                                <th scope="col">عرض السعر</th>
                                                <th scope="col">إجراء</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($commercial['purchase_orders'] as $po)
                                                <tr>
                                                    <td class="dir-ltr">{{ $po->document_number }}</td>
                                                    <td>{{ $po->reference_ar ?: '—' }}</td>
                                                    <td><span class="badge badge-warning">{{ $po->status->labelAr() }}</span></td>
                                                    <td class="dir-ltr">{{ $po->quotation_id ? doc_no('QA', $po->quotation_id) : '—' }}</td>
                                                    <td class="ds-cell-actions">
                                                        <a href="{{ route('customer-pos.show', $po) }}" class="customers-name-link">عرض</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if ($perms['sales_orders'])
                    <div
                        class="customer-tab-panel {{ $firstTab === 'sales_orders' ? 'is-active' : '' }}"
                        id="cust-panel-sales_orders"
                        role="tabpanel"
                        aria-labelledby="cust-tab-sales_orders"
                        @unless ($firstTab === 'sales_orders') hidden @endunless
                    >
                        @if ($commercial['sales_orders']->isEmpty())
                            <div class="ds-data-empty is-compact" role="status"><strong>لا أوامر بيع</strong></div>
                        @else
                            <div class="ds-table-surface">
                                <div class="ds-data-body">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">أمر البيع</th>
                                                <th scope="col">الحالة</th>
                                                <th scope="col">أمر الشراء</th>
                                                <th scope="col">التاريخ</th>
                                                <th scope="col">إجراء</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($commercial['sales_orders'] as $so)
                                                <tr>
                                                    <td class="dir-ltr">#{{ $so->id }}</td>
                                                    <td><span class="badge badge-warning">{{ $so->status->labelAr() }}</span></td>
                                                    <td class="dir-ltr">{{ $so->customer_purchase_order_id ? '#'.$so->customer_purchase_order_id : '—' }}</td>
                                                    <td class="dir-ltr ds-cell-muted">{{ $so->created_at?->timezone(config('app.timezone'))->format('Y-m-d') }}</td>
                                                    <td class="ds-cell-actions">
                                                        <a href="{{ route('sales-orders.show', $so) }}" class="customers-name-link">عرض</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if ($perms['delivery'])
                    <div
                        class="customer-tab-panel {{ $firstTab === 'deliveries' ? 'is-active' : '' }}"
                        id="cust-panel-deliveries"
                        role="tabpanel"
                        aria-labelledby="cust-tab-deliveries"
                        @unless ($firstTab === 'deliveries') hidden @endunless
                    >
                        @if ($commercial['deliveries']->isEmpty())
                            <div class="ds-data-empty is-compact" role="status"><strong>لا تسليمات</strong></div>
                        @else
                            <div class="ds-table-surface">
                                <div class="ds-data-body">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">التسليم</th>
                                                <th scope="col">الحالة</th>
                                                <th scope="col">أمر البيع</th>
                                                <th scope="col">التاريخ</th>
                                                <th scope="col">إجراء</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($commercial['deliveries'] as $delivery)
                                                <tr>
                                                    <td class="dir-ltr">{{ $delivery->document_number }}</td>
                                                    <td><span class="badge badge-warning">{{ $delivery->status->labelAr() }}</span></td>
                                                    <td class="dir-ltr">{{ $delivery->sales_order_id ? '#'.$delivery->sales_order_id : '—' }}</td>
                                                    <td class="dir-ltr ds-cell-muted">{{ $delivery->created_at?->timezone(config('app.timezone'))->format('Y-m-d') }}</td>
                                                    <td class="ds-cell-actions">
                                                        <a href="{{ route('deliveries.show', $delivery) }}" class="customers-name-link">عرض</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if ($perms['invoices'])
                    <div
                        class="customer-tab-panel {{ $firstTab === 'outstanding' ? 'is-active' : '' }}"
                        id="cust-panel-outstanding"
                        role="tabpanel"
                        aria-labelledby="cust-tab-outstanding"
                        @unless ($firstTab === 'outstanding') hidden @endunless
                    >
                        @if ($commercial['outstanding_invoices']->isEmpty())
                            <div class="ds-data-empty is-compact" role="status"><strong>لا مستحقات مفتوحة</strong></div>
                        @else
                            <div class="ds-table-surface">
                                <div class="ds-data-body">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">الفاتورة</th>
                                                <th scope="col">الاستحقاق</th>
                                                @if ($showMoney)
                                                    <th scope="col">المتبقي</th>
                                                    <th scope="col">الإجمالي</th>
                                                @endif
                                                <th scope="col">إجراء</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($commercial['outstanding_invoices'] as $invoice)
                                                <tr class="{{ $invoice->isOverdue() ? 'customers-row-overdue' : '' }}">
                                                    <td class="dir-ltr">{{ $invoice->document_number }}</td>
                                                    <td class="dir-ltr ds-cell-muted">
                                                        {{ $invoice->due_at?->timezone(config('app.timezone'))->format('Y-m-d') ?? '—' }}
                                                        @if ($invoice->isOverdue())
                                                            <span class="badge badge-danger">متأخر</span>
                                                        @endif
                                                    </td>
                                                    @if ($showMoney)
                                                        <td class="dir-ltr">{{ money($invoice->profile_unpaid_balance) }}</td>
                                                        <td class="dir-ltr">{{ money($invoice->total_vat_inclusive) }}</td>
                                                    @endif
                                                    <td class="ds-cell-actions">
                                                        <a href="{{ route('invoices.show', $invoice) }}" class="customers-name-link">عرض</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div
                        class="customer-tab-panel {{ $firstTab === 'invoices' ? 'is-active' : '' }}"
                        id="cust-panel-invoices"
                        role="tabpanel"
                        aria-labelledby="cust-tab-invoices"
                        @unless ($firstTab === 'invoices') hidden @endunless
                    >
                        @if ($commercial['invoices']->isEmpty())
                            <div class="ds-data-empty is-compact" role="status"><strong>لا فواتير</strong></div>
                        @else
                            <div class="ds-table-surface">
                                <div class="ds-data-body">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">الفاتورة</th>
                                                <th scope="col">الحالة</th>
                                                @if ($showMoney)
                                                    <th scope="col">الإجمالي</th>
                                                @endif
                                                <th scope="col">الإصدار</th>
                                                <th scope="col">إجراء</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($commercial['invoices'] as $invoice)
                                                <tr>
                                                    <td class="dir-ltr">{{ $invoice->document_number }}</td>
                                                    <td><span class="badge badge-warning">{{ $invoice->status->labelAr() }}</span></td>
                                                    @if ($showMoney)
                                                        <td class="dir-ltr">{{ money($invoice->total_vat_inclusive) }}</td>
                                                    @endif
                                                    <td class="dir-ltr ds-cell-muted">{{ $invoice->issued_at?->timezone(config('app.timezone'))->format('Y-m-d') ?? '—' }}</td>
                                                    <td class="ds-cell-actions">
                                                        <a href="{{ route('invoices.show', $invoice) }}" class="customers-name-link">عرض</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if ($perms['collections'])
                    <div
                        class="customer-tab-panel {{ $firstTab === 'collections' ? 'is-active' : '' }}"
                        id="cust-panel-collections"
                        role="tabpanel"
                        aria-labelledby="cust-tab-collections"
                        @unless ($firstTab === 'collections') hidden @endunless
                    >
                        @if ($commercial['collections']->isEmpty())
                            <div class="ds-data-empty is-compact" role="status"><strong>لا تحصيلات</strong></div>
                        @else
                            <div class="ds-table-surface">
                                <div class="ds-data-body">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">التحصيل</th>
                                                <th scope="col">الحالة</th>
                                                @if ($showMoney)
                                                    <th scope="col">المبلغ</th>
                                                @endif
                                                <th scope="col">التاريخ</th>
                                                <th scope="col">إجراء</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($commercial['collections'] as $collection)
                                                <tr>
                                                    <td class="dir-ltr">#{{ $collection->id }}</td>
                                                    <td><span class="badge badge-warning">{{ $collection->status->labelAr() }}</span></td>
                                                    @if ($showMoney)
                                                        <td class="dir-ltr">{{ money($collection->amount_total) }}</td>
                                                    @endif
                                                    <td class="dir-ltr ds-cell-muted">{{ $collection->created_at?->timezone(config('app.timezone'))->format('Y-m-d') }}</td>
                                                    <td class="ds-cell-actions">
                                                        <a href="{{ route('collections.show', $collection) }}" class="customers-name-link">عرض</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if ($perms['deals'])
                    <div
                        class="customer-tab-panel {{ $firstTab === 'deals' ? 'is-active' : '' }}"
                        id="cust-panel-deals"
                        role="tabpanel"
                        aria-labelledby="cust-tab-deals"
                        @unless ($firstTab === 'deals') hidden @endunless
                    >
                        @if ($commercial['deals']->isEmpty())
                            <div class="ds-data-empty is-compact" role="status"><strong>لا صفقات</strong></div>
                        @else
                            <div class="ds-table-surface">
                                <div class="ds-data-body">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">الصفقة</th>
                                                <th scope="col">الحالة</th>
                                                <th scope="col">أمر الشراء</th>
                                                <th scope="col">إجراء</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($commercial['deals'] as $deal)
                                                <tr>
                                                    <td class="dir-ltr">#{{ $deal->id }}</td>
                                                    <td><span class="badge badge-warning">{{ $deal->status->labelAr() }}</span></td>
                                                    <td class="dir-ltr">{{ $deal->customer_purchase_order_id ? '#'.$deal->customer_purchase_order_id : '—' }}</td>
                                                    <td class="ds-cell-actions">
                                                        <a href="{{ route('deals.show', $deal) }}" class="customers-name-link">عرض</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif
</section>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-customer-tabs]').forEach((root) => {
                const buttons = root.querySelectorAll('[data-tab]');
                const panels = root.querySelectorAll('.customer-tab-panel');

                buttons.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const id = btn.getAttribute('data-tab');
                        buttons.forEach((b) => {
                            const active = b === btn;
                            b.classList.toggle('is-active', active);
                            b.setAttribute('aria-selected', active ? 'true' : 'false');
                        });
                        panels.forEach((panel) => {
                            const match = panel.id === 'cust-panel-' + id;
                            panel.classList.toggle('is-active', match);
                            panel.hidden = !match;
                        });
                    });
                });
            });
        </script>
    @endpush
@endonce
