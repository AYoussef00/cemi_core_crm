<?php

namespace App\Providers;

use App\Models\Collection;
use App\Models\CreditLimitRequest;
use App\Models\Customer;
use App\Models\CustomerPurchaseOrder;
use App\Models\CustomerVisit;
use App\Models\Deal;
use App\Models\Delivery;
use App\Models\InventoryAdjustment;
use App\Models\Invoice;
use App\Models\InvoiceNote;
use App\Models\ProcurementTask;
use App\Models\Product;
use App\Models\ProductStockBalance;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesRequest;
use App\Models\SupplierPurchaseOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Policies\CollectionPolicy;
use App\Policies\CreditLimitRequestPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\CustomerPurchaseOrderPolicy;
use App\Policies\CustomerVisitPolicy;
use App\Policies\DealPolicy;
use App\Policies\DeliveryPolicy;
use App\Policies\InventoryAdjustmentPolicy;
use App\Policies\InvoiceNotePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\ProcurementTaskPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProductStockBalancePolicy;
use App\Policies\QuotationPolicy;
use App\Policies\RolePolicy;
use App\Policies\SalesOrderPolicy;
use App\Policies\SalesRequestPolicy;
use App\Policies\SupplierPurchaseOrderPolicy;
use App\Policies\UnitOfMeasurePolicy;
use App\Policies\UserPolicy;
use App\Support\Authorization\GateRegistrar;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Password::defaults(function () {
            $rule = Password::min(8)->mixedCase()->numbers();

            return $this->app->isProduction()
                ? $rule->uncompromised()
                : $rule;
        });

        Paginator::defaultView('vendor.pagination.cemi');
        Paginator::defaultSimpleView('vendor.pagination.cemi');

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(CreditLimitRequest::class, CreditLimitRequestPolicy::class);
        Gate::policy(CustomerVisit::class, CustomerVisitPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(UnitOfMeasure::class, UnitOfMeasurePolicy::class);
        Gate::policy(ProductStockBalance::class, ProductStockBalancePolicy::class);
        Gate::policy(InventoryAdjustment::class, InventoryAdjustmentPolicy::class);
        Gate::policy(SalesRequest::class, SalesRequestPolicy::class);
        Gate::policy(ProcurementTask::class, ProcurementTaskPolicy::class);
        Gate::policy(SupplierPurchaseOrder::class, SupplierPurchaseOrderPolicy::class);
        Gate::policy(Quotation::class, QuotationPolicy::class);
        Gate::policy(CustomerPurchaseOrder::class, CustomerPurchaseOrderPolicy::class);
        Gate::policy(SalesOrder::class, SalesOrderPolicy::class);
        Gate::policy(Delivery::class, DeliveryPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Collection::class, CollectionPolicy::class);
        Gate::policy(InvoiceNote::class, InvoiceNotePolicy::class);
        Gate::policy(Deal::class, DealPolicy::class);

        Relation::morphMap(config('attachments.attachables', []));

        app(GateRegistrar::class)->register();
    }
}
