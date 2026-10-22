<?php

namespace App\Support\Customers;

use App\Models\CreditLimitRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Credit\CreditExposure;
use App\Support\Finance\InvoiceStatus;
use App\Support\Money\Money;
use Illuminate\Support\Collection;

/**
 * Aggregates commercial records for the customer profile hub.
 */
final class CustomerCommercialSnapshot
{
    private const LIST_LIMIT = 20;

    public function __construct(private CreditExposure $credit) {}

    /**
     * @return array{
     *     exposure: string,
     *     credit_limit: string|null,
     *     unpaid_invoices_count: int,
     *     overdue_invoices_count: int,
     *     counts: array<string, int>,
     *     sales_requests: Collection,
     *     quotations: Collection,
     *     purchase_orders: Collection,
     *     deals: Collection,
     *     sales_orders: Collection,
     *     deliveries: Collection,
     *     invoices: Collection,
     *     outstanding_invoices: Collection,
     *     collections: Collection,
     *     permissions: array<string, bool>
     * }
     */
    public function for(Customer $customer, User $viewer): array
    {
        $customer->loadCount([
            'salesRequests',
            'quotations',
            'customerPurchaseOrders',
            'deals',
            'salesOrders',
            'deliveries',
            'invoices',
            'collections',
        ]);

        $issued = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('status', InvoiceStatus::Issued->value)
            ->orderByDesc('id')
            ->get();

        $outstanding = collect();
        $unpaidCount = 0;
        $overdueCount = 0;

        foreach ($issued as $invoice) {
            $balance = (float) $invoice->unpaidBalance();
            if ($balance <= 0.005) {
                continue;
            }

            $invoice->setAttribute('profile_unpaid_balance', Money::round2($balance));
            $outstanding->push($invoice);
            $unpaidCount++;

            if ($invoice->isOverdue()) {
                $overdueCount++;
            }
        }

        $permissions = [
            'sales_requests' => $viewer->can('sales_requests.view'),
            'quotations' => $viewer->can('quotations.view'),
            'customer_pos' => $viewer->can('customer_pos.view'),
            'sales_orders' => $viewer->can('sales_orders.view'),
            'delivery' => $viewer->can('delivery.view'),
            'invoices' => $viewer->can('invoices.view'),
            'collections' => $viewer->can('collections.record') || $viewer->can('invoices.view'),
            'deals' => $viewer->can('invoices.view')
                || $viewer->can('sales_orders.view')
                || $viewer->can('deals.close'),
            'credit' => $viewer->can('viewProfile', [CreditLimitRequest::class, $customer]),
            'prices' => $viewer->can('prices.view'),
        ];

        return [
            'exposure' => $this->credit->forCustomer($customer),
            'credit_limit' => $customer->approved_credit_limit !== null
                ? Money::round2($customer->approved_credit_limit)
                : null,
            'unpaid_invoices_count' => $unpaidCount,
            'overdue_invoices_count' => $overdueCount,
            'counts' => [
                'sales_requests' => (int) $customer->sales_requests_count,
                'quotations' => (int) $customer->quotations_count,
                'purchase_orders' => (int) $customer->customer_purchase_orders_count,
                'deals' => (int) $customer->deals_count,
                'sales_orders' => (int) $customer->sales_orders_count,
                'deliveries' => (int) $customer->deliveries_count,
                'invoices' => (int) $customer->invoices_count,
                'collections' => (int) $customer->collections_count,
            ],
            'sales_requests' => $permissions['sales_requests']
                ? $customer->salesRequests()->limit(self::LIST_LIMIT)->get()
                : collect(),
            'quotations' => $permissions['quotations']
                ? $customer->quotations()->with(['createdBy', 'lines'])->limit(self::LIST_LIMIT)->get()
                : collect(),
            'purchase_orders' => $permissions['customer_pos']
                ? $customer->customerPurchaseOrders()->with(['quotation', 'deal'])->limit(self::LIST_LIMIT)->get()
                : collect(),
            'deals' => $permissions['deals']
                ? $customer->deals()->with('customerPurchaseOrder')->limit(self::LIST_LIMIT)->get()
                : collect(),
            'sales_orders' => $permissions['sales_orders']
                ? $customer->salesOrders()->with('customerPurchaseOrder')->limit(self::LIST_LIMIT)->get()
                : collect(),
            'deliveries' => $permissions['delivery']
                ? $customer->deliveries()->with('salesOrder')->limit(self::LIST_LIMIT)->get()
                : collect(),
            'invoices' => $permissions['invoices']
                ? $customer->invoices()->limit(self::LIST_LIMIT)->get()
                : collect(),
            'outstanding_invoices' => $permissions['invoices']
                ? $outstanding->take(self::LIST_LIMIT)->values()
                : collect(),
            'collections' => $permissions['collections']
                ? $customer->collections()->limit(self::LIST_LIMIT)->get()
                : collect(),
            'permissions' => $permissions,
        ];
    }
}
