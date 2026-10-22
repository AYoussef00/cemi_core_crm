<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\DecideSalesOrderRequest;
use App\Http\Requests\Sales\StoreSalesOrderRequest;
use App\Http\Requests\Sales\UpdateSalesOrderDraftRequest;
use App\Models\CustomerPurchaseOrder;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\Credit\CreditExposure;
use App\Support\Finance\InvoiceStatus;
use App\Support\Money\Money;
use App\Support\Procurement\SupplierPurchaseOrderWorkflow;
use App\Support\Sales\CustomerPurchaseOrderStatus;
use App\Support\Sales\SalesOrderStatus;
use App\Support\Sales\SalesOrderWorkflow;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesOrderController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalesOrder::class);

        /** @var User $user */
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $orders = SalesOrder::query()
            ->visibleTo($user)
            ->search($q !== '' ? $q : null)
            ->status($status !== '' ? $status : null)
            ->with(['customer', 'customerPurchaseOrder', 'deal', 'createdBy'])
            ->withCount([
                'deliveries as pending_pod_deliveries_count' => fn ($q) => $q->where('status', 'pending_pod'),
                'deliveries as draft_deliveries_count' => fn ($q) => $q->where('status', 'draft'),
            ])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('sales-orders.index', [
            'orders' => $orders,
            'q' => $q,
            'status' => $status,
            'statuses' => SalesOrderStatus::cases(),
            'canCreate' => $user->can('create', SalesOrder::class),
            'canViewPrices' => $user->can('prices.view'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SalesOrder::class);

        /** @var User $user */
        $user = $request->user();
        $poId = (int) $request->query('customer_purchase_order_id', 0);

        $purchaseOrders = CustomerPurchaseOrder::query()
            ->visibleTo($user)
            ->where('status', CustomerPurchaseOrderStatus::Open)
            ->with(['customer', 'deal', 'lines.product'])
            ->latest('id')
            ->get();

        $selectedPo = $poId > 0
            ? $purchaseOrders->firstWhere('id', $poId)
            : null;

        $unallocated = [];
        if ($selectedPo !== null) {
            foreach ($selectedPo->lines as $line) {
                $unallocated[$line->id] = $selectedPo->unallocatedQuantityForLine($line);
            }
        }

        return view('sales-orders.create', [
            'purchaseOrders' => $purchaseOrders,
            'selectedPo' => $selectedPo,
            'unallocated' => $unallocated,
        ]);
    }

    public function store(
        StoreSalesOrderRequest $request,
        SalesOrderWorkflow $workflow,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $payload = $request->payload();

        $po = CustomerPurchaseOrder::query()->findOrFail($payload['customer_purchase_order_id']);
        if (! $user->can('view', $po)) {
            abort(403);
        }

        try {
            $order = $workflow->createDraft($user, $payload);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['sales_order' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales-orders.show', $order)
            ->with('status_ar', 'تم إنشاء مسودة أمر البيع.');
    }

    public function show(Request $request, SalesOrder $sales_order): View
    {
        $this->authorize('view', $sales_order);

        $sales_order->load([
            'customer.assignedSalesRep',
            'customerPurchaseOrder.quotation',
            'customerPurchaseOrder.attachments',
            'deal',
            'lines.product',
            'lines.customerPurchaseOrderLine.quotationLine.salesRequestLine.procurementTask',
            'createdBy',
            'submittedBy',
            'decidedBy',
            'cancelledBy',
        ]);

        /** @var User $user */
        $user = $request->user();
        $canViewPrices = $user->can('prices.view');
        $canViewCredit = $canViewPrices || $user->can('invoices.view');

        $creditLimit = null;
        $creditExposure = null;
        $overdueInvoicesCount = 0;

        if ($canViewCredit && $sales_order->customer) {
            $credit = app(CreditExposure::class);
            $creditExposure = $credit->forCustomer($sales_order->customer);
            $creditLimit = $sales_order->customer->approved_credit_limit !== null
                ? Money::round2($sales_order->customer->approved_credit_limit)
                : null;

            $issued = Invoice::query()
                ->where('customer_id', $sales_order->customer_id)
                ->where('status', InvoiceStatus::Issued->value)
                ->get();

            foreach ($issued as $invoice) {
                if ((float) $invoice->unpaidBalance() > 0.005 && $invoice->isOverdue()) {
                    $overdueInvoicesCount++;
                }
            }
        }

        $po = $sales_order->customerPurchaseOrder;
        $quotation = $po?->quotation;
        $poPdf = $po?->attachments
            ->first(fn ($a) => $a->extension === 'pdf')
            ?? $po?->attachments->first();

        $blockingSupplyPo = app(SupplierPurchaseOrderWorkflow::class)
            ->blockingSupplyPoForOrder($sales_order);
        $awaitingSupplierSupply = $blockingSupplyPo !== null;

        return view('sales-orders.show', [
            'order' => $sales_order,
            'quotation' => $quotation,
            'poPdf' => $poPdf,
            'canPrintQuotation' => $quotation !== null && $user->can('print', $quotation),
            'canViewPoPdf' => $po !== null && $poPdf !== null && $user->can('view', $po),
            'canUpdate' => $user->can('update', $sales_order) && ! $awaitingSupplierSupply,
            'canSubmit' => $user->can('submit', $sales_order) && ! $awaitingSupplierSupply,
            'canApprove' => $user->can('approve', $sales_order) && ! $awaitingSupplierSupply,
            'canReject' => $user->can('reject', $sales_order) && ! $awaitingSupplierSupply,
            'canCancelApproved' => $user->can('cancelApproved', $sales_order),
            'awaitingSupplierSupply' => $awaitingSupplierSupply,
            'blockingSupplyPo' => $blockingSupplyPo,
            'canViewPrices' => $canViewPrices,
            'canViewCredit' => $canViewCredit,
            'creditLimit' => $creditLimit,
            'creditExposure' => $creditExposure,
            'overdueInvoicesCount' => $overdueInvoicesCount,
        ]);
    }

    public function edit(Request $request, SalesOrder $sales_order): View
    {
        $this->authorize('update', $sales_order);

        $sales_order->load(['lines', 'customerPurchaseOrder.lines.product', 'deal', 'customer']);
        $po = $sales_order->customerPurchaseOrder;
        $unallocated = [];
        if ($po !== null) {
            foreach ($po->lines as $line) {
                // Include this draft's current allocation in remaining display for edit UX.
                $allocatedOthers = $po->allocatedQuantityForLine($line);
                foreach ($sales_order->lines as $soLine) {
                    if ((int) $soLine->customer_purchase_order_line_id === (int) $line->id) {
                        $allocatedOthers -= (float) $soLine->quantity;
                    }
                }
                $unallocated[$line->id] = max(0.0, (float) $line->quantity - $allocatedOthers);
            }
        }

        return view('sales-orders.edit', [
            'order' => $sales_order,
            'unallocated' => $unallocated,
        ]);
    }

    public function update(
        UpdateSalesOrderDraftRequest $request,
        SalesOrder $sales_order,
        SalesOrderWorkflow $workflow,
    ): RedirectResponse {
        try {
            $workflow->updateDraft($sales_order, $request->user(), $request->payload());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['sales_order' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales-orders.show', $sales_order)
            ->with('status_ar', 'تم تحديث مسودة أمر البيع.');
    }

    public function submit(
        Request $request,
        SalesOrder $sales_order,
        SalesOrderWorkflow $workflow,
    ): RedirectResponse {
        $this->authorize('submit', $sales_order);

        try {
            $workflow->submit($sales_order, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['sales_order' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales-orders.show', $sales_order)
            ->with('status_ar', 'تم إرسال أمر البيع للاعتماد.');
    }

    public function decide(
        DecideSalesOrderRequest $request,
        SalesOrder $sales_order,
        SalesOrderWorkflow $workflow,
    ): RedirectResponse {
        $notes = $request->validated('decision_notes');

        try {
            if ($request->validated('decision') === 'approve') {
                $workflow->approve($sales_order, $request->user(), $notes);
                $message = 'تم اعتماد أمر البيع وحجز المخزون.';
            } else {
                $workflow->reject($sales_order, $request->user(), $notes);
                $message = 'تم رفض أمر البيع.';
            }
        } catch (DomainException $e) {
            return back()->withErrors(['sales_order' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales-orders.show', $sales_order)
            ->with('status_ar', $message);
    }

    public function cancelApproved(
        Request $request,
        SalesOrder $sales_order,
        SalesOrderWorkflow $workflow,
    ): RedirectResponse {
        $this->authorize('cancelApproved', $sales_order);

        try {
            $workflow->cancelApproved(
                $sales_order,
                $request->user(),
                $request->input('decision_notes'),
            );
        } catch (DomainException $e) {
            return back()->withErrors(['sales_order' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales-orders.show', $sales_order)
            ->with('status_ar', 'تم إلغاء أمر البيع المعتمد والإفراج عن الحجز.');
    }
}
