{{--
  Full product IA sidebar (RTL).
  Enabled = real route + permission (when required).
  Disabled = future / not built yet — visible but not clickable.
--}}
@php
    $companyName = $shellCompany?->name_ar;
    $isHome = request()->routeIs('home');
    $isTasks = request()->routeIs('account.notifications.*');
    $isUsers = request()->routeIs('admin.users.*');
    $isRoles = request()->routeIs('admin.roles.*');
    $isApprovals = request()->routeIs('admin.approvals.*');
    $isCompany = request()->routeIs('admin.settings.company.*');
    $isWarehouse = request()->routeIs('admin.settings.warehouse.*');
    $isSettingsHub = request()->routeIs('admin.settings.index');
    $isAudit = request()->routeIs('admin.audit.*');
    $isCustomers = request()->routeIs('customers.*') && ! request()->routeIs('customers.credit.*');
    $isVisits = request()->routeIs('visits.*');
    $isSalesRequests = request()->routeIs('sales-requests.*');
    $isQuotations = request()->routeIs('quotations.*');
    $isCustomerPos = request()->routeIs('customer-pos.*');
    $isSalesOrders = request()->routeIs('sales-orders.*');
    $isProcurement = request()->routeIs('procurement.*');
    $isSupplierPos = request()->routeIs('supplier-pos.*') || request()->routeIs('supplier-goods-receipts.*');
    $isSupplierInbound = $isSupplierPos && request()->query('inbox') === 'inbound';
    $isProcurementNav = $isProcurement || ($isSupplierPos && ! $isSupplierInbound);
    $isProducts = request()->routeIs('products.*');
    $isStock = request()->routeIs('inventory.stock.*');
    $isAdjustments = request()->routeIs('inventory.adjustments.*');
    $isPreparation = request()->routeIs('warehouse.preparation.*');
    $isDeliveries = request()->routeIs('deliveries.*');
    $isCredit = request()->routeIs('customers.credit.*') || request()->routeIs('credit-requests.*');
    $isInvoices = request()->routeIs('invoices.*');
    $isCollections = request()->routeIs('collections.*');
    $isInvoiceNotes = request()->routeIs('invoice-notes.*');
    $isDeals = request()->routeIs('deals.*');
    $isReportsAr = request()->routeIs('reports.ar.*');
    $isReportsStatements = request()->routeIs('reports.statements.*');
    $isReports = $isReportsAr || $isReportsStatements;
    $isMail = request()->routeIs('admin.settings.mail.*');
    $isAdmin = $isUsers || $isRoles || $isApprovals || $isCompany || $isWarehouse || $isSettingsHub || $isAudit || $isMail
        || request()->routeIs('admin.settings.*');
    $isSalesNav = $isCustomers || $isVisits || $isSalesRequests || $isQuotations || $isCustomerPos || $isSalesOrders;
    $isInventoryNav = $isProducts || $isStock || $isAdjustments || $isPreparation || $isSupplierInbound
        || ($isSupplierPos && auth()->check() && auth()->user()->can('delivery.manage') && ! auth()->user()->can('procurement.manage'));
    $isFulfillmentNav = $isDeliveries || $isPreparation;
    $isFinanceNav = $isCredit || $isInvoices || $isCollections || $isInvoiceNotes || $isDeals;

    $canUsers = auth()->check() && auth()->user()->can('users.view');
    $canRoles = auth()->check() && auth()->user()->can('roles.manage');
    $canApprovals = auth()->check() && auth()->user()->can('approval_authority.configure');
    $canSettings = auth()->check() && auth()->user()->can('settings.manage');
    $canAudit = auth()->check() && auth()->user()->can('audit.view');
    $customersRouteExists = \Illuminate\Support\Facades\Route::has('customers.index');
    $canCustomers = $customersRouteExists && auth()->check() && auth()->user()->can('customers.view');
    $visitsRouteExists = \Illuminate\Support\Facades\Route::has('visits.index');
    $canVisits = $visitsRouteExists && auth()->check() && auth()->user()->can('visits.view');
    $productsRouteExists = \Illuminate\Support\Facades\Route::has('products.index');
    $canProducts = $productsRouteExists && auth()->check() && auth()->user()->can('products.view');
    $stockRouteExists = \Illuminate\Support\Facades\Route::has('inventory.stock.index');
    $canStock = $stockRouteExists && auth()->check() && auth()->user()->can('stock.view');
    $adjustmentsRouteExists = \Illuminate\Support\Facades\Route::has('inventory.adjustments.index');
    $canAdjustments = $adjustmentsRouteExists && auth()->check()
        && auth()->user()->can('viewAny', \App\Models\InventoryAdjustment::class);
    $preparationRouteExists = \Illuminate\Support\Facades\Route::has('warehouse.preparation.index');
    $canPreparation = $preparationRouteExists && auth()->check() && auth()->user()->can('delivery.view');
    $deliveriesRouteExists = \Illuminate\Support\Facades\Route::has('deliveries.index');
    $canDeliveries = $deliveriesRouteExists && auth()->check() && auth()->user()->can('delivery.view');
    $canCreditNav = \Illuminate\Support\Facades\Route::has('credit-requests.index')
        && auth()->check()
        && auth()->user()->can('viewAny', \App\Models\CreditLimitRequest::class);
    $invoicesRouteExists = \Illuminate\Support\Facades\Route::has('invoices.index');
    $canInvoices = $invoicesRouteExists && auth()->check() && auth()->user()->can('invoices.view');
    $collectionsRouteExists = \Illuminate\Support\Facades\Route::has('collections.index');
    $canCollections = $collectionsRouteExists && auth()->check() && auth()->user()->can('collections.record');
    $notesRouteExists = \Illuminate\Support\Facades\Route::has('invoice-notes.index');
    $canInvoiceNotes = $notesRouteExists && auth()->check()
        && (auth()->user()->can('credit_notes.create') || auth()->user()->can('credit_notes.approve') || auth()->user()->can('invoices.view'));
    $dealsRouteExists = \Illuminate\Support\Facades\Route::has('deals.index');
    $canDeals = $dealsRouteExists && auth()->check()
        && (auth()->user()->can('deals.close') || auth()->user()->can('invoices.view'));
    $arReportRouteExists = \Illuminate\Support\Facades\Route::has('reports.ar.index');
    $statementsRouteExists = \Illuminate\Support\Facades\Route::has('reports.statements.index');
    $canArReports = auth()->check()
        && (auth()->user()->can('reports.ar') || auth()->user()->can('reports.financial') || auth()->user()->can('invoices.view'));
    $canReportsAr = $arReportRouteExists && $canArReports;
    $canReportsStatements = $statementsRouteExists && $canArReports;
    $salesRequestsRouteExists = \Illuminate\Support\Facades\Route::has('sales-requests.index');
    $canSalesRequests = $salesRequestsRouteExists && auth()->check() && auth()->user()->can('sales_requests.view');
    $quotationsRouteExists = \Illuminate\Support\Facades\Route::has('quotations.index');
    $canQuotations = $quotationsRouteExists && auth()->check() && auth()->user()->can('quotations.view');
    $customerPosRouteExists = \Illuminate\Support\Facades\Route::has('customer-pos.index');
    $canCustomerPos = $customerPosRouteExists && auth()->check() && auth()->user()->can('customer_pos.view');
    $salesOrdersRouteExists = \Illuminate\Support\Facades\Route::has('sales-orders.index');
    $canSalesOrders = $salesOrdersRouteExists && auth()->check() && auth()->user()->can('sales_orders.view');
    $procurementRouteExists = \Illuminate\Support\Facades\Route::has('procurement.tasks.index');
    $canProcurement = $procurementRouteExists && auth()->check() && auth()->user()->can('procurement.view');
    $supplierPosRouteExists = \Illuminate\Support\Facades\Route::has('supplier-pos.index');
    $canSupplierPos = $supplierPosRouteExists && auth()->check()
        && auth()->user()->can('viewAny', \App\Models\SupplierPurchaseOrder::class);
    $hasAdminAccess = $canUsers || $canRoles || $canApprovals || $canSettings || $canAudit;
@endphp

<aside class="app-sidebar" role="banner">
    <div class="sidebar-org" @if (! $shellCompany) data-empty="true" @endif>
        <span class="sidebar-org-mark" aria-hidden="true">
            <img src="{{ asset('branding/cemi-logo.png') }}" alt="" width="36" height="36" class="sidebar-org-logo" decoding="async">
        </span>
        <div class="sidebar-org-copy">
            @if ($shellCompany)
                <p class="sidebar-org-name">{{ $companyName }}</p>
                <p class="sidebar-org-meta">شركة واحدة · مستودع واحد</p>
            @else
                <p class="sidebar-org-name">ملف الشركة</p>
                <p class="sidebar-org-meta">لم يُعدَّ بعد</p>
            @endif
        </div>
        @can('settings.manage')
            <a href="{{ route('admin.settings.index') }}" class="sidebar-org-gear" title="الإعدادات" aria-label="إعدادات الشركة والمستودع">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.7"/>
                    <path d="M19.4 13a7.8 7.8 0 0 0 .05-2l2.05-1.6-2-3.46-2.45.8a7.9 7.9 0 0 0-1.73-1L15 3h-6l-.32 2.74a7.9 7.9 0 0 0-1.73 1l-2.45-.8-2 3.46L4.55 11a7.8 7.8 0 0 0 0 2l-2.05 1.6 2 3.46 2.45-.8a7.9 7.9 0 0 0 1.73 1L9 21h6l.32-2.74a7.9 7.9 0 0 0 1.73-1l2.45.8 2-3.46L19.4 13Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                </svg>
            </a>
        @endcan
    </div>

    <nav class="sidebar-nav" aria-label="التنقل الرئيسي">
        {{-- الرئيسية --}}
        <p class="sidebar-section">القائمة الرئيسية</p>
        <details class="nav-group" @if ($isHome) open @endif>
            <summary class="nav-group-summary {{ $isHome ? 'is-active' : '' }}">
                <span class="nav-group-ico" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                </span>
                <span class="nav-group-label">الرئيسية</span>
                <span class="nav-chevron" aria-hidden="true"></span>
            </summary>
            <ul class="nav-tree">
                <li>
                    <a href="{{ route('home') }}" class="nav-sub {{ $isHome ? 'is-active' : '' }}" @if ($isHome) aria-current="page" @endif>
                        <span class="nav-dot" aria-hidden="true"></span>
                        الصفحة الرئيسية
                    </a>
                </li>
            </ul>
        </details>

        @auth
            {{-- مهامي --}}
            <details class="nav-group" @if ($isTasks) open @endif>
                <summary class="nav-group-summary {{ $isTasks ? 'is-active' : '' }}">
                    <span class="nav-group-ico" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M9 11.5 11 13.5 15.5 9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><rect x="3.5" y="3.5" width="17" height="17" rx="3" stroke="currentColor" stroke-width="1.7"/></svg>
                    </span>
                    <span class="nav-group-label">الإشعارات</span>
                    <span class="nav-chevron" aria-hidden="true"></span>
                </summary>
                <ul class="nav-tree">
                    <li>
                        <a href="{{ route('account.notifications.index') }}" class="nav-sub {{ $isTasks ? 'is-active' : '' }}" @if ($isTasks) aria-current="page" @endif>
                            <span class="nav-dot" aria-hidden="true"></span>
                            الإشعارات
                            @if (($shellUnread ?? 0) > 0)
                                <span class="nav-badge">{{ $shellUnread > 99 ? '99+' : $shellUnread }}</span>
                            @endif
                        </a>
                    </li>
                    <li>
                        <span class="nav-sub is-disabled" aria-disabled="true" title="غير متاح حاليًا">
                            <span class="nav-dot" aria-hidden="true"></span>
                            طلبات بانتظار اعتمادي
                        </span>
                    </li>
                    <li>
                        <span class="nav-sub is-disabled" aria-disabled="true" title="غير متاح حاليًا">
                            <span class="nav-dot" aria-hidden="true"></span>
                            الزيارات القادمة
                        </span>
                    </li>
                </ul>
            </details>

            {{-- المبيعات --}}
            <details class="nav-group" @if ($isSalesNav) open @endif>
                <summary class="nav-group-summary {{ $isSalesNav ? 'is-active' : '' }}">
                    <span class="nav-group-ico" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M6 7v12a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    </span>
                    <span class="nav-group-label">المبيعات</span>
                    <span class="nav-chevron" aria-hidden="true"></span>
                </summary>
                <ul class="nav-tree">
                    <li>
                        @if ($canCustomers)
                            <a href="{{ route('customers.index') }}" class="nav-sub {{ $isCustomers ? 'is-active' : '' }}"
                               @if ($isCustomers) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>
                                العملاء
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true" title="{{ $customersRouteExists ? 'ليست لديك صلاحية عرض العملاء' : 'غير متاح حاليًا' }}">
                                <span class="nav-dot" aria-hidden="true"></span>
                                العملاء
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canVisits)
                            <a href="{{ route('visits.index') }}" class="nav-sub {{ $isVisits ? 'is-active' : '' }}"
                               @if ($isVisits) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>
                                الزيارات
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true" title="{{ $visitsRouteExists ? 'ليست لديك صلاحية عرض الزيارات' : 'غير متاح حاليًا' }}">
                                <span class="nav-dot" aria-hidden="true"></span>
                                الزيارات
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canSalesRequests)
                            <a href="{{ route('sales-requests.index') }}"
                               class="nav-sub {{ $isSalesRequests ? 'is-active' : '' }}"
                               @if ($isSalesRequests) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>
                                طلبات البيع
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true"
                                  title="{{ $salesRequestsRouteExists ? 'ليست لديك صلاحية عرض طلبات البيع' : 'غير متاح حاليًا' }}">
                                <span class="nav-dot" aria-hidden="true"></span>
                                طلبات البيع
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canQuotations)
                            <a href="{{ route('quotations.index') }}"
                               class="nav-sub {{ $isQuotations ? 'is-active' : '' }}"
                               @if ($isQuotations) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>
                                عروض الأسعار
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true"
                                  title="{{ $quotationsRouteExists ? 'ليست لديك صلاحية عرض عروض الأسعار' : 'غير متاح حاليًا' }}">
                                <span class="nav-dot" aria-hidden="true"></span>
                                عروض الأسعار
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canCustomerPos)
                            <a href="{{ route('customer-pos.index') }}"
                               class="nav-sub {{ $isCustomerPos ? 'is-active' : '' }}"
                               @if ($isCustomerPos) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>
                                أوامر شراء العملاء
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true"
                                  title="{{ $customerPosRouteExists ? 'ليست لديك صلاحية عرض أوامر الشراء' : 'غير متاح حاليًا' }}">
                                <span class="nav-dot" aria-hidden="true"></span>
                                أوامر شراء العملاء
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canSalesOrders)
                            <a href="{{ route('sales-orders.index') }}"
                               class="nav-sub {{ $isSalesOrders ? 'is-active' : '' }}"
                               @if ($isSalesOrders) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>
                                أوامر البيع
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true"
                                  title="{{ $salesOrdersRouteExists ? 'ليست لديك صلاحية عرض أوامر البيع' : 'غير متاح حاليًا' }}">
                                <span class="nav-dot" aria-hidden="true"></span>
                                أوامر البيع
                            </span>
                        @endif
                    </li>
                </ul>
            </details>

            {{-- المشتريات --}}
            <details class="nav-group" @if ($isProcurementNav) open @endif>
                <summary class="nav-group-summary {{ $isProcurementNav ? 'is-active' : '' }}">
                    <span class="nav-group-ico" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 7h18l-1.2 11.2A2 2 0 0 1 17.8 20H6.2a2 2 0 0 1-2-1.8L3 7Zm5-3h8l1 3H7l1-3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                    </span>
                    <span class="nav-group-label">المشتريات</span>
                    <span class="nav-chevron" aria-hidden="true"></span>
                </summary>
                <ul class="nav-tree">
                    <li>
                        @if ($canProcurement)
                            <a href="{{ route('procurement.tasks.index') }}"
                               class="nav-sub {{ $isProcurement ? 'is-active' : '' }}"
                               @if ($isProcurement) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>
                                مهام المشتريات
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true"
                                  title="{{ $procurementRouteExists ? 'ليست لديك صلاحية عرض المشتريات' : 'غير متاح حاليًا' }}">
                                <span class="nav-dot" aria-hidden="true"></span>
                                مهام المشتريات
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canSupplierPos)
                            <a href="{{ route('supplier-pos.index') }}"
                               class="nav-sub {{ $isSupplierPos ? 'is-active' : '' }}"
                               @if ($isSupplierPos) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>
                                أوامر شراء الموردين
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true"
                                  title="{{ $supplierPosRouteExists ? 'ليست لديك صلاحية عرض أوامر الموردين' : 'غير متاح حاليًا' }}">
                                <span class="nav-dot" aria-hidden="true"></span>
                                أوامر شراء الموردين
                            </span>
                        @endif
                    </li>
                    <li>
                        <span class="nav-sub is-disabled" aria-disabled="true" title="غير متاح حاليًا">
                            <span class="nav-dot" aria-hidden="true"></span>الأصناف بانتظار التوريد
                        </span>
                    </li>
                </ul>
            </details>

            {{-- المخزون --}}
            <details class="nav-group" @if ($isInventoryNav) open @endif>
                <summary class="nav-group-summary {{ $isInventoryNav ? 'is-active' : '' }}">
                    <span class="nav-group-ico" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 8.5 12 4l8 4.5v7L12 20l-8-4.5v-7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M12 12v8M4 8.5l8 3.5 8-3.5" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                    </span>
                    <span class="nav-group-label">المخزون والمستودع</span>
                    <span class="nav-chevron" aria-hidden="true"></span>
                </summary>
                <ul class="nav-tree">
                    <li>
                        @if ($canProducts)
                            <a href="{{ route('products.index') }}"
                               class="nav-sub {{ $isProducts ? 'is-active' : '' }}"
                               @if ($isProducts) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>
                                المنتجات
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true"
                                  title="{{ $productsRouteExists ? 'ليست لديك صلاحية عرض المنتجات' : 'غير متاح حاليًا' }}">
                                <span class="nav-dot" aria-hidden="true"></span>
                                المنتجات
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canStock)
                            <a href="{{ route('inventory.stock.index') }}"
                               class="nav-sub {{ $isStock ? 'is-active' : '' }}"
                               @if ($isStock) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>
                                أرصدة المخزون
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true"
                                  title="{{ $stockRouteExists ? 'ليست لديك صلاحية عرض الأرصدة' : 'غير متاح حاليًا' }}">
                                <span class="nav-dot" aria-hidden="true"></span>
                                أرصدة المخزون
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canAdjustments)
                            <a href="{{ route('inventory.adjustments.index') }}"
                               class="nav-sub {{ $isAdjustments ? 'is-active' : '' }}"
                               @if ($isAdjustments) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>
                                تسويات المخزون
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true"
                                  title="{{ $adjustmentsRouteExists ? 'ليست لديك صلاحية عرض التسويات' : 'غير متاح حاليًا' }}">
                                <span class="nav-dot" aria-hidden="true"></span>
                                تسويات المخزون
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canPreparation)
                            <a href="{{ route('warehouse.preparation.index') }}"
                               class="nav-sub {{ $isPreparation ? 'is-active' : '' }}"
                               @if ($isPreparation) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>
                                مهام تجهيز الطلبات
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true"
                                  title="{{ $preparationRouteExists ? 'ليست لديك صلاحية عرض التجهيز' : 'غير متاح حاليًا' }}">
                                <span class="nav-dot" aria-hidden="true"></span>
                                مهام تجهيز الطلبات
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canSupplierPos)
                            <a href="{{ route('supplier-pos.index', ['inbox' => 'inbound']) }}"
                               class="nav-sub {{ ($isSupplierInbound || ($isSupplierPos && auth()->user()->can('delivery.manage') && ! auth()->user()->can('procurement.manage'))) ? 'is-active' : '' }}"
                               @if ($isSupplierInbound) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>
                                طلبيات واردة
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true"
                                  title="{{ $supplierPosRouteExists ? 'ليست لديك صلاحية عرض الطلبيات الواردة' : 'غير متاح حاليًا' }}">
                                <span class="nav-dot" aria-hidden="true"></span>
                                طلبيات واردة
                            </span>
                        @endif
                    </li>
                    <li>
                        <span class="nav-sub is-disabled" aria-disabled="true" title="غير متاح حاليًا">
                            <span class="nav-dot" aria-hidden="true"></span>حركات المخزون
                        </span>
                    </li>
                </ul>
            </details>

            {{-- التجهيز والتسليم --}}
            <details class="nav-group" @if ($isFulfillmentNav) open @endif>
                <summary class="nav-group-summary {{ $isFulfillmentNav ? 'is-active' : '' }}">
                    <span class="nav-group-ico" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 7h11v10H3V7Zm11 3h4l3 3v4h-7v-7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="7" cy="18.5" r="1.5" stroke="currentColor" stroke-width="1.5"/><circle cx="17" cy="18.5" r="1.5" stroke="currentColor" stroke-width="1.5"/></svg>
                    </span>
                    <span class="nav-group-label">التجهيز والتسليم</span>
                    <span class="nav-chevron" aria-hidden="true"></span>
                </summary>
                <ul class="nav-tree">
                    <li>
                        @if ($canPreparation)
                            <a href="{{ route('warehouse.preparation.index') }}"
                               class="nav-sub {{ $isPreparation ? 'is-active' : '' }}"
                               @if ($isPreparation) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>
                                قائمة التجهيز
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true"
                                  title="{{ $preparationRouteExists ? 'ليست لديك صلاحية' : 'غير متاح حاليًا' }}">
                                <span class="nav-dot" aria-hidden="true"></span>
                                قائمة التجهيز
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canDeliveries)
                            <a href="{{ route('deliveries.index') }}"
                               class="nav-sub {{ $isDeliveries ? 'is-active' : '' }}"
                               @if ($isDeliveries) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>
                                أوامر التسليم
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true"
                                  title="{{ $deliveriesRouteExists ? 'ليست لديك صلاحية عرض التسليم' : 'غير متاح حاليًا' }}">
                                <span class="nav-dot" aria-hidden="true"></span>
                                أوامر التسليم
                            </span>
                        @endif
                    </li>
                    @foreach (['ملصقات', 'السائقون'] as $label)
                        <li>
                            <span class="nav-sub is-disabled" aria-disabled="true" title="غير متاح حاليًا — بلا GPS/سائقين خارجيين في v1">
                                <span class="nav-dot" aria-hidden="true"></span>{{ $label }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </details>

            {{-- المالية --}}
            <details class="nav-group" @if ($isFinanceNav) open @endif>
                <summary class="nav-group-summary {{ $isFinanceNav ? 'is-active' : '' }}">
                    <span class="nav-group-ico" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="3.5" y="5" width="17" height="14" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="M3.5 10h17M8 14h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    </span>
                    <span class="nav-group-label">المالية</span>
                    <span class="nav-chevron" aria-hidden="true"></span>
                </summary>
                <ul class="nav-tree">
                    <li>
                        @if ($canCreditNav)
                            <a href="{{ route('credit-requests.index') }}" class="nav-sub {{ $isCredit ? 'is-active' : '' }}"
                               @if ($isCredit) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>
                                الملف الائتماني للعملاء
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true" title="ليست لديك صلاحية عرض الائتمان">
                                <span class="nav-dot" aria-hidden="true"></span>
                                الملف الائتماني للعملاء
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canInvoices)
                            <a href="{{ route('invoices.index') }}" class="nav-sub {{ $isInvoices ? 'is-active' : '' }}"
                               @if ($isInvoices) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>الفواتير
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true" title="ليست لديك صلاحية عرض الفواتير">
                                <span class="nav-dot" aria-hidden="true"></span>الفواتير
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canCollections)
                            <a href="{{ route('collections.index') }}" class="nav-sub {{ $isCollections ? 'is-active' : '' }}"
                               @if ($isCollections) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>التحصيلات
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true" title="ليست لديك صلاحية التحصيلات">
                                <span class="nav-dot" aria-hidden="true"></span>التحصيلات
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canInvoiceNotes)
                            <a href="{{ route('invoice-notes.index') }}" class="nav-sub {{ $isInvoiceNotes ? 'is-active' : '' }}"
                               @if ($isInvoiceNotes) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>الإشعارات الدائنة والمدينة
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true" title="ليست لديك صلاحية">
                                <span class="nav-dot" aria-hidden="true"></span>الإشعارات الدائنة والمدينة
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canDeals)
                            <a href="{{ route('deals.index') }}" class="nav-sub {{ $isDeals ? 'is-active' : '' }}"
                               @if ($isDeals) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>الصفقات
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true" title="ليست لديك صلاحية">
                                <span class="nav-dot" aria-hidden="true"></span>الصفقات
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canReportsStatements)
                            <a href="{{ route('reports.statements.index') }}" class="nav-sub {{ $isReportsStatements ? 'is-active' : '' }}"
                               @if ($isReportsStatements) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>كشوف حساب العملاء
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true" title="ليست لديك صلاحية">
                                <span class="nav-dot" aria-hidden="true"></span>كشوف حساب العملاء
                            </span>
                        @endif
                    </li>
                </ul>
            </details>

            {{-- التواصل — Phase 8 deferred --}}
            <details class="nav-group">
                <summary class="nav-group-summary">
                    <span class="nav-group-ico" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 7.5 12 13l8-5.5V17a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M4 7.5 12 13l8-5.5" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                    </span>
                    <span class="nav-group-label">التواصل</span>
                    <span class="nav-chevron" aria-hidden="true"></span>
                </summary>
                <ul class="nav-tree">
                    @foreach (['البريد المرسل', 'قوالب الرسائل', 'سجل الإرسال وحالاته'] as $label)
                        <li>
                            <span class="nav-sub is-disabled" aria-disabled="true" title="مؤجّل — المرحلة 8">
                                <span class="nav-dot" aria-hidden="true"></span>{{ $label }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </details>

            {{-- التقارير --}}
            <details class="nav-group" @if ($isReports) open @endif>
                <summary class="nav-group-summary {{ $isReports ? 'is-active' : '' }}">
                    <span class="nav-group-ico" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 19V10M10 19V5M15 19v-7M20 19V8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    </span>
                    <span class="nav-group-label">التقارير</span>
                    <span class="nav-chevron" aria-hidden="true"></span>
                </summary>
                <ul class="nav-tree">
                    <li>
                        @if ($canReportsAr)
                            <a href="{{ route('reports.ar.index') }}" class="nav-sub {{ $isReportsAr ? 'is-active' : '' }}"
                               @if ($isReportsAr) aria-current="page" @endif
                               title="المتبقى وفق CR7 — بلا تصدير">
                                <span class="nav-dot" aria-hidden="true"></span>الذمم والتحصيل
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true" title="ليست لديك صلاحية">
                                <span class="nav-dot" aria-hidden="true"></span>الذمم والتحصيل
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canReportsStatements)
                            <a href="{{ route('reports.statements.index') }}" class="nav-sub {{ $isReportsStatements ? 'is-active' : '' }}"
                               @if ($isReportsStatements) aria-current="page" @endif
                               title="كشف حساب لكل منشأة — بلا تصدير">
                                <span class="nav-dot" aria-hidden="true"></span>كشوف حساب العملاء
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true" title="ليست لديك صلاحية">
                                <span class="nav-dot" aria-hidden="true"></span>كشوف حساب العملاء
                            </span>
                        @endif
                    </li>
                    @foreach (['تقارير المبيعات', 'تقارير المشتريات', 'تقارير المخزون والتسليم'] as $label)
                        <li>
                            <span class="nav-sub is-disabled" aria-disabled="true" title="لاحقاً — خارج Prompt 1">
                                <span class="nav-dot" aria-hidden="true"></span>{{ $label }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </details>

            {{-- الإدارة والإعدادات --}}
            <p class="sidebar-section">الإدارة والإعدادات</p>
            <details class="nav-group" @if ($isAdmin || $hasAdminAccess) open @endif>
                <summary class="nav-group-summary {{ $isAdmin ? 'is-active' : '' }}">
                    <span class="nav-group-ico" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.7"/><path d="M19.4 13a7.8 7.8 0 0 0 .05-2l2.05-1.6-2-3.46-2.45.8a7.9 7.9 0 0 0-1.73-1L15 3h-6l-.32 2.74a7.9 7.9 0 0 0-1.73 1l-2.45-.8-2 3.46L4.55 11a7.8 7.8 0 0 0 0 2l-2.05 1.6 2 3.46 2.45-.8a7.9 7.9 0 0 0 1.73 1L9 21h6l.32-2.74a7.9 7.9 0 0 0 1.73-1l2.45.8 2-3.46L19.4 13Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                    </span>
                    <span class="nav-group-label">الإدارة والإعدادات</span>
                    <span class="nav-chevron" aria-hidden="true"></span>
                </summary>
                <ul class="nav-tree">
                    <li>
                        @if ($canUsers)
                            <a href="{{ route('admin.users.index') }}" class="nav-sub {{ $isUsers ? 'is-active' : '' }}" @if ($isUsers) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>المستخدمون
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true" title="ليس لديك صلاحية">
                                <span class="nav-dot" aria-hidden="true"></span>المستخدمون
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canRoles)
                            <a href="{{ route('admin.roles.index') }}" class="nav-sub {{ $isRoles ? 'is-active' : '' }}" @if ($isRoles) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>الأدوار والصلاحيات
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true" title="ليس لديك صلاحية">
                                <span class="nav-dot" aria-hidden="true"></span>الأدوار والصلاحيات
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canApprovals)
                            <a href="{{ route('admin.approvals.edit') }}" class="nav-sub {{ $isApprovals ? 'is-active' : '' }}" @if ($isApprovals) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>جهات الاعتماد
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true" title="ليس لديك صلاحية">
                                <span class="nav-dot" aria-hidden="true"></span>جهات الاعتماد
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canSettings)
                            <a href="{{ route('admin.settings.company.edit') }}" class="nav-sub {{ $isCompany || $isSettingsHub ? 'is-active' : '' }}" @if ($isCompany) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>الشركة
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true" title="ليس لديك صلاحية">
                                <span class="nav-dot" aria-hidden="true"></span>الشركة
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canSettings)
                            <a href="{{ route('admin.settings.warehouse.edit') }}" class="nav-sub {{ $isWarehouse ? 'is-active' : '' }}" @if ($isWarehouse) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>المستودع
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true" title="ليس لديك صلاحية">
                                <span class="nav-dot" aria-hidden="true"></span>المستودع
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canAudit)
                            <a href="{{ route('admin.audit.index') }}" class="nav-sub {{ $isAudit ? 'is-active' : '' }}" @if ($isAudit) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>سجل التدقيق
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true" title="ليس لديك صلاحية">
                                <span class="nav-dot" aria-hidden="true"></span>سجل التدقيق
                            </span>
                        @endif
                    </li>
                    <li>
                        @if ($canSettings)
                            <a href="{{ route('admin.settings.mail.show') }}" class="nav-sub {{ $isMail ? 'is-active' : '' }}" @if ($isMail) aria-current="page" @endif>
                                <span class="nav-dot" aria-hidden="true"></span>إعداد البريد
                            </a>
                        @else
                            <span class="nav-sub is-disabled" aria-disabled="true" title="ليس لديك صلاحية">
                                <span class="nav-dot" aria-hidden="true"></span>إعداد البريد
                            </span>
                        @endif
                    </li>
                </ul>
            </details>
        @else
            <a href="{{ route('login') }}" class="nav-group-summary nav-group-link {{ request()->routeIs('login') ? 'is-active' : '' }}">
                <span class="nav-group-ico" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M10 12h9m0 0-3-3m3 3-3 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 5H7a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </span>
                <span class="nav-group-label">تسجيل الدخول</span>
            </a>
        @endauth
    </nav>

    @auth
        <div class="sidebar-footer">
            <a href="{{ route('account.profile') }}" class="sidebar-user-chip" title="الحساب">
                <span class="sidebar-user-avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                <div class="sidebar-user-meta">
                    <p class="sidebar-user">{{ auth()->user()->name }}</p>
                    <p class="sidebar-user-role">{{ auth()->user()->role?->name_ar ?? 'مستخدم داخلي' }}</p>
                </div>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-glass btn-sm sidebar-logout-btn">تسجيل الخروج</button>
            </form>
        </div>
    @endauth
</aside>
