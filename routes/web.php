<?php

use App\Http\Controllers\Account\NotificationController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\ApprovalAuthorityController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CompanySettingsController;
use App\Http\Controllers\Admin\MailSettingsController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\SettingsAttachmentController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WarehouseSettingsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Credit\CreditLimitRequestController;
use App\Http\Controllers\Credit\CustomerCreditController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Customers\CustomerLocationController;
use App\Http\Controllers\Customers\CustomerPhotoController;
use App\Http\Controllers\Design\DashboardPreviewController;
use App\Http\Controllers\Foundation\HomeController;
use App\Http\Controllers\Inventory\InventoryAdjustmentController;
use App\Http\Controllers\Inventory\StockBalanceController;
use App\Http\Controllers\Procurement\ProcurementTaskController;
use App\Http\Controllers\Procurement\SupplierGoodsReceiptController;
use App\Http\Controllers\Procurement\SupplierPurchaseOrderController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Products\UnitOfMeasureController;
use App\Http\Controllers\Quotations\QuotationController;
use App\Http\Controllers\Sales\CustomerPurchaseOrderController;
use App\Http\Controllers\Sales\SalesOrderController;
use App\Http\Controllers\SalesRequests\SalesRequestController;
use App\Http\Controllers\Visits\VisitController;
use App\Http\Controllers\Finance\CollectionController;
use App\Http\Controllers\Finance\DealController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\InvoiceNoteController;
use App\Http\Controllers\Reports\ArReportController;
use App\Http\Controllers\Reports\CustomerStatementController;
use App\Http\Controllers\Warehouse\DeliveryController;
use App\Http\Controllers\Warehouse\PreparationController;
use Illuminate\Support\Facades\Route;

// Root = login (guests). Authenticated visitors go to the operational home dashboard.
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('home');
    }

    return app(AuthenticatedSessionController::class)->create();
})->name('login');

Route::middleware('guest')->group(function (): void {
    Route::redirect('/login', '/');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/home', HomeController::class)->name('home');

    Route::get('/account', [ProfileController::class, 'show'])->name('account.profile');
    Route::put('/account/password', [ProfileController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('account.password');

    Route::get('/account/notifications', [NotificationController::class, 'index'])
        ->name('account.notifications.index');
    Route::get('/account/notifications/feed', [NotificationController::class, 'feed'])
        ->middleware('throttle:60,1')
        ->name('account.notifications.feed');
    Route::post('/account/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('account.notifications.read-all');
    Route::post('/account/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->name('account.notifications.read');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('permission:customers.manage')->group(function (): void {
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    });

    Route::middleware('permission:customers.view')->group(function (): void {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])
            ->whereNumber('customer')
            ->name('customers.show');
    });

    Route::middleware('permission:customers.manage')->group(function (): void {
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])
            ->whereNumber('customer')
            ->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])
            ->whereNumber('customer')
            ->name('customers.update');
    });

    Route::get('/customers/{customer}/credit', [CustomerCreditController::class, 'show'])
        ->whereNumber('customer')
        ->name('customers.credit.show');
    Route::post('/customers/{customer}/credit-requests', [CustomerCreditController::class, 'store'])
        ->whereNumber('customer')
        ->middleware('throttle:20,1')
        ->name('customers.credit-requests.store');

    Route::get('/customers/{customer}/location', [CustomerLocationController::class, 'edit'])
        ->whereNumber('customer')
        ->name('customers.location.edit');
    Route::put('/customers/{customer}/location', [CustomerLocationController::class, 'update'])
        ->whereNumber('customer')
        ->name('customers.location.update');

    Route::get('/customers/{customer}/photos', [CustomerPhotoController::class, 'index'])
        ->whereNumber('customer')
        ->name('customers.photos.index');
    Route::post('/customers/{customer}/photos', [CustomerPhotoController::class, 'store'])
        ->whereNumber('customer')
        ->middleware('throttle:20,1')
        ->name('customers.photos.store');
    Route::get('/customers/{customer}/photos/{attachment}', [CustomerPhotoController::class, 'download'])
        ->whereNumber('customer')
        ->name('customers.photos.download');
    Route::delete('/customers/{customer}/photos/{attachment}', [CustomerPhotoController::class, 'destroy'])
        ->whereNumber('customer')
        ->name('customers.photos.destroy');

    Route::get('/credit-requests', [CreditLimitRequestController::class, 'index'])
        ->name('credit-requests.index');
    Route::get('/credit-requests/{credit_request}', [CreditLimitRequestController::class, 'show'])
        ->whereNumber('credit_request')
        ->name('credit-requests.show');
    Route::post('/credit-requests/{credit_request}/review', [CreditLimitRequestController::class, 'review'])
        ->whereNumber('credit_request')
        ->middleware('throttle:20,1')
        ->name('credit-requests.review');
    Route::post('/credit-requests/{credit_request}/decide', [CreditLimitRequestController::class, 'decide'])
        ->whereNumber('credit_request')
        ->middleware('throttle:20,1')
        ->name('credit-requests.decide');

    Route::get('/visits', [VisitController::class, 'index'])
        ->middleware('permission:visits.view')
        ->name('visits.index');
    Route::get('/visits/create', [VisitController::class, 'create'])
        ->middleware('permission:visits.manage')
        ->name('visits.create');
    Route::post('/visits', [VisitController::class, 'store'])
        ->middleware(['permission:visits.manage', 'throttle:30,1'])
        ->name('visits.store');
    Route::get('/visits/{visit}', [VisitController::class, 'show'])
        ->middleware('permission:visits.view')
        ->whereNumber('visit')
        ->name('visits.show');
    Route::get('/visits/{visit}/edit', [VisitController::class, 'edit'])
        ->middleware('permission:visits.manage')
        ->whereNumber('visit')
        ->name('visits.edit');
    Route::put('/visits/{visit}', [VisitController::class, 'update'])
        ->middleware(['permission:visits.manage', 'throttle:30,1'])
        ->whereNumber('visit')
        ->name('visits.update');
    Route::post('/visits/{visit}/complete', [VisitController::class, 'complete'])
        ->middleware(['permission:visits.manage', 'throttle:30,1'])
        ->whereNumber('visit')
        ->name('visits.complete');
    Route::post('/visits/{visit}/cancel', [VisitController::class, 'cancel'])
        ->middleware(['permission:visits.manage', 'throttle:30,1'])
        ->whereNumber('visit')
        ->name('visits.cancel');

    Route::get('/sales-requests', [SalesRequestController::class, 'index'])
        ->middleware('permission:sales_requests.view')
        ->name('sales-requests.index');
    Route::get('/sales-requests/create', [SalesRequestController::class, 'create'])
        ->middleware('permission:sales_requests.manage')
        ->name('sales-requests.create');
    Route::post('/sales-requests', [SalesRequestController::class, 'store'])
        ->middleware(['permission:sales_requests.manage', 'throttle:30,1'])
        ->name('sales-requests.store');
    Route::get('/sales-requests/{sales_request}', [SalesRequestController::class, 'show'])
        ->middleware('permission:sales_requests.view')
        ->whereNumber('sales_request')
        ->name('sales-requests.show');
    Route::get('/sales-requests/{sales_request}/edit', [SalesRequestController::class, 'edit'])
        ->middleware('permission:sales_requests.manage')
        ->whereNumber('sales_request')
        ->name('sales-requests.edit');
    Route::put('/sales-requests/{sales_request}', [SalesRequestController::class, 'update'])
        ->middleware(['permission:sales_requests.manage', 'throttle:30,1'])
        ->whereNumber('sales_request')
        ->name('sales-requests.update');
    Route::post('/sales-requests/{sales_request}/submit', [SalesRequestController::class, 'submit'])
        ->middleware(['permission:sales_requests.manage', 'throttle:30,1'])
        ->whereNumber('sales_request')
        ->name('sales-requests.submit');
    Route::post('/sales-requests/{sales_request}/cancel', [SalesRequestController::class, 'cancel'])
        ->middleware(['permission:sales_requests.manage', 'throttle:30,1'])
        ->whereNumber('sales_request')
        ->name('sales-requests.cancel');

    Route::get('/procurement/tasks', [ProcurementTaskController::class, 'index'])
        ->middleware('permission:procurement.view')
        ->name('procurement.tasks.index');
    Route::get('/procurement/tasks/{task}', [ProcurementTaskController::class, 'show'])
        ->middleware('permission:procurement.view')
        ->whereNumber('task')
        ->name('procurement.tasks.show');
    Route::post('/procurement/tasks/{task}/cost', [ProcurementTaskController::class, 'recordCost'])
        ->middleware(['permission:procurement.manage', 'throttle:30,1'])
        ->whereNumber('task')
        ->name('procurement.tasks.cost');
    Route::get('/procurement/tasks/{task}/quote-pdf/{attachment:uuid}', [ProcurementTaskController::class, 'quotePdf'])
        ->middleware('permission:procurement.view')
        ->whereNumber('task')
        ->name('procurement.tasks.quote-pdf');
    Route::post('/procurement/tasks/{task}/cancel', [ProcurementTaskController::class, 'cancel'])
        ->middleware(['permission:procurement.cancel', 'throttle:30,1'])
        ->whereNumber('task')
        ->name('procurement.tasks.cancel');

    Route::get('/supplier-pos', [SupplierPurchaseOrderController::class, 'index'])
        ->middleware('auth')
        ->name('supplier-pos.index');
    Route::get('/supplier-pos/{supplier_po}', [SupplierPurchaseOrderController::class, 'show'])
        ->middleware('auth')
        ->whereNumber('supplier_po')
        ->name('supplier-pos.show');
    Route::get('/supplier-pos/{supplier_po}/pdf', [SupplierPurchaseOrderController::class, 'pdf'])
        ->middleware('auth')
        ->whereNumber('supplier_po')
        ->name('supplier-pos.pdf');
    Route::get('/supplier-pos/{supplier_po}/attachments/{attachment:uuid}', [SupplierPurchaseOrderController::class, 'attachment'])
        ->middleware('auth')
        ->whereNumber('supplier_po')
        ->name('supplier-pos.attachment');
    Route::post('/supplier-pos/{supplier_po}/mark-ordered', [SupplierPurchaseOrderController::class, 'markOrdered'])
        ->middleware(['permission:procurement.manage', 'throttle:30,1'])
        ->whereNumber('supplier_po')
        ->name('supplier-pos.mark-ordered');
    Route::post('/supplier-pos/{supplier_po}/receive', [SupplierPurchaseOrderController::class, 'receive'])
        ->middleware(['permission:delivery.manage', 'throttle:30,1'])
        ->whereNumber('supplier_po')
        ->name('supplier-pos.receive');

    Route::get('/supplier-goods-receipts/{goods_receipt}', [SupplierGoodsReceiptController::class, 'show'])
        ->middleware('auth')
        ->whereNumber('goods_receipt')
        ->name('supplier-goods-receipts.show');
    Route::get('/supplier-goods-receipts/{goods_receipt}/attachments/{attachment:uuid}', [SupplierGoodsReceiptController::class, 'attachment'])
        ->middleware('auth')
        ->whereNumber('goods_receipt')
        ->name('supplier-goods-receipts.attachment');
    Route::get('/supplier-goods-receipts/{goods_receipt}/pdf', [SupplierGoodsReceiptController::class, 'pdf'])
        ->middleware('auth')
        ->whereNumber('goods_receipt')
        ->name('supplier-goods-receipts.pdf');

    Route::get('/quotations', [QuotationController::class, 'index'])
        ->middleware('permission:quotations.view')
        ->name('quotations.index');
    Route::post('/sales-requests/{sales_request}/quotations', [QuotationController::class, 'storeFromSalesRequest'])
        ->middleware(['permission:quotations.manage', 'throttle:30,1'])
        ->whereNumber('sales_request')
        ->name('sales-requests.quotations.store');
    Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])
        ->middleware('permission:quotations.view')
        ->whereNumber('quotation')
        ->name('quotations.show');
    Route::get('/quotations/{quotation}/edit', [QuotationController::class, 'edit'])
        ->middleware('permission:quotations.manage')
        ->whereNumber('quotation')
        ->name('quotations.edit');
    Route::put('/quotations/{quotation}', [QuotationController::class, 'update'])
        ->middleware(['permission:quotations.manage', 'throttle:30,1'])
        ->whereNumber('quotation')
        ->name('quotations.update');
    Route::post('/quotations/{quotation}/submit-price', [QuotationController::class, 'submitPrice'])
        ->middleware(['permission:quotations.submit_price', 'throttle:30,1'])
        ->whereNumber('quotation')
        ->name('quotations.submit-price');
    Route::post('/quotations/{quotation}/decide', [QuotationController::class, 'decide'])
        ->middleware('throttle:30,1')
        ->whereNumber('quotation')
        ->name('quotations.decide');
    Route::post('/quotations/{quotation}/send', [QuotationController::class, 'send'])
        ->middleware(['permission:quotations.send', 'throttle:6,1'])
        ->whereNumber('quotation')
        ->name('quotations.send');
    Route::post('/quotations/{quotation}/cancel', [QuotationController::class, 'cancel'])
        ->middleware(['permission:quotations.cancel', 'throttle:30,1'])
        ->whereNumber('quotation')
        ->name('quotations.cancel');
    Route::post('/quotations/{quotation}/supersede', [QuotationController::class, 'supersede'])
        ->middleware(['permission:quotations.cancel', 'throttle:30,1'])
        ->whereNumber('quotation')
        ->name('quotations.supersede');
    Route::get('/quotations/{quotation}/print', [QuotationController::class, 'print'])
        ->middleware('permission:quotations.view')
        ->whereNumber('quotation')
        ->name('quotations.print');
    Route::get('/quotations/{quotation}/convert-to-sales-order', [QuotationController::class, 'convertForm'])
        ->middleware(['permission:customer_pos.manage', 'permission:sales_orders.create_draft'])
        ->whereNumber('quotation')
        ->name('quotations.convert');
    Route::post('/quotations/{quotation}/convert-to-sales-order', [QuotationController::class, 'convertStore'])
        ->middleware(['permission:customer_pos.manage', 'permission:sales_orders.create_draft', 'throttle:20,1'])
        ->whereNumber('quotation')
        ->name('quotations.convert.store');

    Route::get('/customer-pos', [CustomerPurchaseOrderController::class, 'index'])
        ->middleware('permission:customer_pos.view')
        ->name('customer-pos.index');
    Route::get('/customer-pos/create', [CustomerPurchaseOrderController::class, 'create'])
        ->middleware('permission:customer_pos.manage')
        ->name('customer-pos.create');
    Route::post('/customer-pos', [CustomerPurchaseOrderController::class, 'store'])
        ->middleware(['permission:customer_pos.manage', 'throttle:30,1'])
        ->name('customer-pos.store');
    Route::get('/customer-pos/{customer_po}/pdf', [CustomerPurchaseOrderController::class, 'pdf'])
        ->middleware('permission:customer_pos.view')
        ->whereNumber('customer_po')
        ->name('customer-pos.pdf');
    Route::get('/customer-pos/{customer_po}', [CustomerPurchaseOrderController::class, 'show'])
        ->middleware('permission:customer_pos.view')
        ->whereNumber('customer_po')
        ->name('customer-pos.show');
    Route::get('/customer-pos/{customer_po}/edit', [CustomerPurchaseOrderController::class, 'edit'])
        ->middleware('permission:customer_pos.manage')
        ->whereNumber('customer_po')
        ->name('customer-pos.edit');
    Route::put('/customer-pos/{customer_po}', [CustomerPurchaseOrderController::class, 'update'])
        ->middleware(['permission:customer_pos.manage', 'throttle:30,1'])
        ->whereNumber('customer_po')
        ->name('customer-pos.update');
    Route::post('/customer-pos/{customer_po}/cancel', [CustomerPurchaseOrderController::class, 'cancel'])
        ->middleware(['permission:customer_pos.cancel', 'throttle:30,1'])
        ->whereNumber('customer_po')
        ->name('customer-pos.cancel');

    Route::get('/sales-orders', [SalesOrderController::class, 'index'])
        ->middleware('permission:sales_orders.view')
        ->name('sales-orders.index');
    Route::get('/sales-orders/create', [SalesOrderController::class, 'create'])
        ->middleware('permission:sales_orders.create_draft')
        ->name('sales-orders.create');
    Route::post('/sales-orders', [SalesOrderController::class, 'store'])
        ->middleware(['permission:sales_orders.create_draft', 'throttle:30,1'])
        ->name('sales-orders.store');
    Route::get('/sales-orders/{sales_order}', [SalesOrderController::class, 'show'])
        ->middleware('permission:sales_orders.view')
        ->whereNumber('sales_order')
        ->name('sales-orders.show');
    Route::get('/sales-orders/{sales_order}/edit', [SalesOrderController::class, 'edit'])
        ->middleware('permission:sales_orders.create_draft')
        ->whereNumber('sales_order')
        ->name('sales-orders.edit');
    Route::put('/sales-orders/{sales_order}', [SalesOrderController::class, 'update'])
        ->middleware(['permission:sales_orders.create_draft', 'throttle:30,1'])
        ->whereNumber('sales_order')
        ->name('sales-orders.update');
    Route::post('/sales-orders/{sales_order}/submit', [SalesOrderController::class, 'submit'])
        ->middleware(['permission:sales_orders.submit', 'throttle:30,1'])
        ->whereNumber('sales_order')
        ->name('sales-orders.submit');
    Route::post('/sales-orders/{sales_order}/decide', [SalesOrderController::class, 'decide'])
        ->middleware('throttle:30,1')
        ->whereNumber('sales_order')
        ->name('sales-orders.decide');
    Route::post('/sales-orders/{sales_order}/cancel-approved', [SalesOrderController::class, 'cancelApproved'])
        ->middleware(['permission:sales_orders.cancel_approved', 'throttle:30,1'])
        ->whereNumber('sales_order')
        ->name('sales-orders.cancel-approved');

    Route::get('/warehouse/preparation', [PreparationController::class, 'index'])
        ->middleware('permission:delivery.view')
        ->name('warehouse.preparation.index');
    Route::post('/warehouse/preparation/{sales_order}/ready', [PreparationController::class, 'markReady'])
        ->middleware(['permission:delivery.manage', 'throttle:30,1'])
        ->whereNumber('sales_order')
        ->name('warehouse.preparation.ready');

    Route::get('/deliveries', [DeliveryController::class, 'index'])
        ->middleware('permission:delivery.view')
        ->name('deliveries.index');
    Route::get('/deliveries/create', [DeliveryController::class, 'create'])
        ->middleware('permission:delivery.manage')
        ->name('deliveries.create');
    Route::post('/deliveries', [DeliveryController::class, 'store'])
        ->middleware(['permission:delivery.manage', 'throttle:30,1'])
        ->name('deliveries.store');
    Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show'])
        ->middleware('permission:delivery.view')
        ->whereNumber('delivery')
        ->name('deliveries.show');
    Route::get('/deliveries/{delivery}/print', [DeliveryController::class, 'print'])
        ->middleware('permission:delivery.view')
        ->whereNumber('delivery')
        ->name('deliveries.print');
    Route::get('/deliveries/{delivery}/edit', [DeliveryController::class, 'edit'])
        ->middleware('permission:delivery.manage')
        ->whereNumber('delivery')
        ->name('deliveries.edit');
    Route::put('/deliveries/{delivery}', [DeliveryController::class, 'update'])
        ->middleware(['permission:delivery.manage', 'throttle:30,1'])
        ->whereNumber('delivery')
        ->name('deliveries.update');
    Route::post('/deliveries/{delivery}/pending-pod', [DeliveryController::class, 'markPendingPod'])
        ->middleware(['permission:delivery.manage', 'throttle:30,1'])
        ->whereNumber('delivery')
        ->name('deliveries.pending-pod');
    Route::post('/deliveries/{delivery}/confirm', [DeliveryController::class, 'confirm'])
        ->middleware(['permission:delivery.manage', 'throttle:30,1'])
        ->whereNumber('delivery')
        ->name('deliveries.confirm');
    Route::post('/deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel'])
        ->middleware(['permission:delivery.cancel', 'throttle:30,1'])
        ->whereNumber('delivery')
        ->name('deliveries.cancel');
    Route::post('/deliveries/{delivery}/pod', [DeliveryController::class, 'storePod'])
        ->middleware(['permission:delivery.manage', 'throttle:20,1'])
        ->whereNumber('delivery')
        ->name('deliveries.pod.store');
    Route::get('/deliveries/{delivery}/pod/{attachment}', [DeliveryController::class, 'downloadPod'])
        ->middleware('permission:delivery.view')
        ->whereNumber(['delivery', 'attachment'])
        ->name('deliveries.pod.download');
    Route::delete('/deliveries/{delivery}/pod/{attachment}', [DeliveryController::class, 'destroyPod'])
        ->middleware(['permission:delivery.manage', 'throttle:30,1'])
        ->whereNumber(['delivery', 'attachment'])
        ->name('deliveries.pod.destroy');

    Route::get('/invoices', [InvoiceController::class, 'index'])
        ->middleware('permission:invoices.view')
        ->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])
        ->middleware('permission:invoices.issue')
        ->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])
        ->middleware(['permission:invoices.issue', 'throttle:30,1'])
        ->name('invoices.store');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])
        ->middleware('permission:invoices.view')
        ->whereNumber('invoice')
        ->name('invoices.show');
    Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])
        ->middleware('permission:invoices.view')
        ->whereNumber('invoice')
        ->name('invoices.print');
    Route::post('/invoices/{invoice}/resend-overdue', [InvoiceController::class, 'resendOverdue'])
        ->middleware(['permission:overdue.resend', 'throttle:6,1'])
        ->whereNumber('invoice')
        ->name('invoices.resend-overdue');

    Route::get('/collections', [CollectionController::class, 'index'])
        ->middleware('permission:collections.record')
        ->name('collections.index');
    Route::get('/collections/create', [CollectionController::class, 'create'])
        ->middleware('permission:collections.record')
        ->name('collections.create');
    Route::post('/collections', [CollectionController::class, 'store'])
        ->middleware(['permission:collections.record', 'throttle:30,1'])
        ->name('collections.store');
    Route::get('/collections/{collection}', [CollectionController::class, 'show'])
        ->middleware('permission:collections.record')
        ->whereNumber('collection')
        ->name('collections.show');
    Route::post('/collections/{collection}/confirm', [CollectionController::class, 'confirm'])
        ->middleware(['permission:collections.record', 'throttle:30,1'])
        ->whereNumber('collection')
        ->name('collections.confirm');
    Route::post('/collections/{collection}/request-reversal', [CollectionController::class, 'requestReversal'])
        ->middleware(['permission:collection_reversal.request', 'throttle:30,1'])
        ->whereNumber('collection')
        ->name('collections.request-reversal');
    Route::post('/collections/{collection}/decide-reversal', [CollectionController::class, 'decideReversal'])
        ->middleware(['permission:collection_reversal.approve', 'throttle:30,1'])
        ->whereNumber('collection')
        ->name('collections.decide-reversal');

    Route::get('/invoice-notes', [InvoiceNoteController::class, 'index'])
        ->name('invoice-notes.index');
    Route::get('/invoice-notes/create', [InvoiceNoteController::class, 'create'])
        ->middleware('permission:credit_notes.create')
        ->name('invoice-notes.create');
    Route::post('/invoice-notes', [InvoiceNoteController::class, 'store'])
        ->middleware(['permission:credit_notes.create', 'throttle:30,1'])
        ->name('invoice-notes.store');
    Route::get('/invoice-notes/{invoice_note}', [InvoiceNoteController::class, 'show'])
        ->whereNumber('invoice_note')
        ->name('invoice-notes.show');
    Route::post('/invoice-notes/{invoice_note}/submit', [InvoiceNoteController::class, 'submit'])
        ->middleware(['permission:credit_notes.create', 'throttle:30,1'])
        ->whereNumber('invoice_note')
        ->name('invoice-notes.submit');
    Route::post('/invoice-notes/{invoice_note}/decide', [InvoiceNoteController::class, 'decide'])
        ->middleware('throttle:30,1')
        ->whereNumber('invoice_note')
        ->name('invoice-notes.decide');

    Route::get('/deals', [DealController::class, 'index'])
        ->name('deals.index');
    Route::get('/deals/{deal}', [DealController::class, 'show'])
        ->whereNumber('deal')
        ->name('deals.show');
    Route::post('/deals/{deal}/close', [DealController::class, 'close'])
        ->middleware(['permission:deals.close', 'throttle:30,1'])
        ->whereNumber('deal')
        ->name('deals.close');

    Route::get('/reports/ar', [ArReportController::class, 'index'])
        ->name('reports.ar.index');
    Route::get('/reports/statements', [CustomerStatementController::class, 'index'])
        ->name('reports.statements.index');
    Route::get('/reports/statements/{customer}', [CustomerStatementController::class, 'show'])
        ->whereNumber('customer')
        ->name('reports.statements.show');

    Route::middleware('permission:products.manage')->group(function (): void {
        Route::get('/products/units', [UnitOfMeasureController::class, 'index'])->name('products.units.index');
        Route::get('/products/units/create', [UnitOfMeasureController::class, 'create'])->name('products.units.create');
        Route::post('/products/units', [UnitOfMeasureController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('products.units.store');
        Route::get('/products/units/{unit}/edit', [UnitOfMeasureController::class, 'edit'])
            ->whereNumber('unit')
            ->name('products.units.edit');
        Route::put('/products/units/{unit}', [UnitOfMeasureController::class, 'update'])
            ->middleware('throttle:30,1')
            ->whereNumber('unit')
            ->name('products.units.update');

        Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('products.store');
    });

    Route::middleware('permission:products.view')->group(function (): void {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product}', [ProductController::class, 'show'])
            ->whereNumber('product')
            ->name('products.show');
    });

    Route::middleware('permission:products.manage')->group(function (): void {
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])
            ->whereNumber('product')
            ->name('products.edit');
        Route::put('/products/{product}', [ProductController::class, 'update'])
            ->middleware('throttle:30,1')
            ->whereNumber('product')
            ->name('products.update');
    });

    Route::put('/products/{product}/purchase-cost', [ProductController::class, 'updatePurchaseCost'])
        ->middleware(['permission:products.edit_purchase_cost', 'throttle:30,1'])
        ->whereNumber('product')
        ->name('products.purchase-cost.update');

    Route::middleware('permission:stock.view')->group(function (): void {
        Route::get('/inventory/stock', [StockBalanceController::class, 'index'])->name('inventory.stock.index');
    });

    Route::get('/inventory/adjustments', [InventoryAdjustmentController::class, 'index'])
        ->name('inventory.adjustments.index');
    Route::get('/inventory/adjustments/create', [InventoryAdjustmentController::class, 'create'])
        ->middleware('permission:inventory_adjustment.record')
        ->name('inventory.adjustments.create');
    Route::post('/inventory/adjustments', [InventoryAdjustmentController::class, 'store'])
        ->middleware(['permission:inventory_adjustment.record', 'throttle:30,1'])
        ->name('inventory.adjustments.store');
    Route::get('/inventory/adjustments/{adjustment}', [InventoryAdjustmentController::class, 'show'])
        ->whereNumber('adjustment')
        ->name('inventory.adjustments.show');
    Route::post('/inventory/adjustments/{adjustment}/decide', [InventoryAdjustmentController::class, 'decide'])
        ->middleware('throttle:30,1')
        ->whereNumber('adjustment')
        ->name('inventory.adjustments.decide');

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::middleware('permission:users.view')->group(function (): void {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
        });

        Route::middleware('permission:users.manage')->group(function (): void {
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
            Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
            Route::post('/users/{user}/password-reset', [UserController::class, 'sendPasswordReset'])
                ->middleware('throttle:6,1')
                ->name('users.password-reset');
        });

        Route::middleware('permission:roles.manage')->group(function (): void {
            Route::get('/roles', [RolePermissionController::class, 'index'])->name('roles.index');
            Route::put('/roles/{role}', [RolePermissionController::class, 'update'])->name('roles.update');
        });

        Route::middleware('permission:approval_authority.configure')->group(function (): void {
            Route::get('/approvals', [ApprovalAuthorityController::class, 'edit'])->name('approvals.edit');
            Route::put('/approvals', [ApprovalAuthorityController::class, 'update'])->name('approvals.update');
        });

        Route::middleware('permission:settings.manage')->group(function (): void {
            Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::get('/settings/company', [CompanySettingsController::class, 'edit'])->name('settings.company.edit');
            Route::put('/settings/company', [CompanySettingsController::class, 'update'])->name('settings.company.update');
            Route::get('/settings/warehouse', [WarehouseSettingsController::class, 'edit'])->name('settings.warehouse.edit');
            Route::put('/settings/warehouse', [WarehouseSettingsController::class, 'update'])->name('settings.warehouse.update');

            Route::post('/settings/company/attachments', [SettingsAttachmentController::class, 'storeCompany'])
                ->middleware('throttle:20,1')
                ->name('settings.company.attachments.store');
            Route::get('/settings/company/attachments/{attachment}/download', [SettingsAttachmentController::class, 'downloadCompany'])
                ->name('settings.company.attachments.download');
            Route::delete('/settings/company/attachments/{attachment}', [SettingsAttachmentController::class, 'destroyCompany'])
                ->name('settings.company.attachments.destroy');

            Route::post('/settings/warehouse/attachments', [SettingsAttachmentController::class, 'storeWarehouse'])
                ->middleware('throttle:20,1')
                ->name('settings.warehouse.attachments.store');
            Route::get('/settings/warehouse/attachments/{attachment}/download', [SettingsAttachmentController::class, 'downloadWarehouse'])
                ->name('settings.warehouse.attachments.download');
            Route::delete('/settings/warehouse/attachments/{attachment}', [SettingsAttachmentController::class, 'destroyWarehouse'])
                ->name('settings.warehouse.attachments.destroy');

            Route::get('/settings/mail', [MailSettingsController::class, 'show'])->name('settings.mail.show');
            Route::post('/settings/mail/test', [MailSettingsController::class, 'sendTest'])
                ->middleware('throttle:3,1')
                ->name('settings.mail.test');
        });


        Route::middleware('permission:audit.view')->group(function (): void {
            Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
        });
    });
});

/*
| Design dashboard preview — registered only for local (and testing).
| Controller also 404s outside local/testing. Static fixtures only; no business data.
*/
if (app()->environment(['local', 'testing'])) {
    Route::middleware('auth')->group(function (): void {
        Route::get('/design/dashboard-preview', DashboardPreviewController::class)
            ->name('design.dashboard-preview');
    });
}
