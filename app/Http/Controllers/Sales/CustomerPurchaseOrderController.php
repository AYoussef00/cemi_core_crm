<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreCustomerPurchaseOrderRequest;
use App\Http\Requests\Sales\UpdateCustomerPurchaseOrderRequest;
use App\Models\Customer;
use App\Models\CustomerPurchaseOrder;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Attachments\AttachmentService;
use App\Support\Quotations\QuotationStatus;
use App\Support\Sales\CustomerPurchaseOrderStatus;
use App\Support\Sales\CustomerPurchaseOrderWorkflow;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerPurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', CustomerPurchaseOrder::class);

        /** @var User $user */
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $orders = CustomerPurchaseOrder::query()
            ->visibleTo($user)
            ->search($q !== '' ? $q : null)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->with(['customer', 'deal', 'quotation', 'createdBy', 'attachments'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('customer-pos.index', [
            'orders' => $orders,
            'q' => $q,
            'status' => $status,
            'statuses' => CustomerPurchaseOrderStatus::cases(),
            'canCreate' => $user->can('create', CustomerPurchaseOrder::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', CustomerPurchaseOrder::class);

        /** @var User $user */
        $user = $request->user();
        $customerId = (int) $request->query('customer_id', 0);
        $quotationId = (int) $request->query('quotation_id', 0);

        $customers = Customer::query()
            ->visibleTo($user)
            ->orderBy('name_ar')
            ->get(['id', 'name_ar']);

        $quotations = Quotation::query()
            ->visibleTo($user)
            ->whereIn('status', [QuotationStatus::Approved, QuotationStatus::Sent])
            ->when($customerId > 0, fn ($q) => $q->where('customer_id', $customerId))
            ->with(['customer', 'lines.product'])
            ->latest('id')
            ->get();

        $selectedQuotation = $quotationId > 0
            ? $quotations->firstWhere('id', $quotationId)
            : null;

        return view('customer-pos.create', [
            'customers' => $customers,
            'quotations' => $quotations,
            'selectedCustomerId' => $customerId > 0 ? $customerId : null,
            'selectedQuotation' => $selectedQuotation,
        ]);
    }

    public function store(
        StoreCustomerPurchaseOrderRequest $request,
        CustomerPurchaseOrderWorkflow $workflow,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $payload = $request->payload();

        $customer = Customer::query()->findOrFail($payload['customer_id']);
        if (! $user->can('view', $customer)) {
            abort(403);
        }

        $quotation = Quotation::query()->findOrFail($payload['quotation_id']);
        if (! $user->can('view', $quotation)) {
            abort(403);
        }

        try {
            $po = $workflow->create($user, $payload);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['customer_po' => $e->getMessage()]);
        }

        return redirect()
            ->route('customer-pos.show', $po)
            ->with('status_ar', 'تم إنشاء أمر شراء العميل والصفقة المرتبطة (1:1).');
    }

    /**
     * Open the uploaded customer PO PDF in the browser (same file from convert/upload).
     */
    public function pdf(
        CustomerPurchaseOrder $customer_po,
        AttachmentService $attachments,
    ): StreamedResponse|RedirectResponse {
        $this->authorize('view', $customer_po);

        $attachment = $customer_po->attachments()
            ->where('extension', 'pdf')
            ->latest('id')
            ->first()
            ?? $customer_po->attachments()->latest('id')->first();

        if ($attachment === null) {
            return redirect()
                ->route('customer-pos.show', $customer_po)
                ->withErrors(['customer_po' => 'لا يوجد ملف PDF مرفق لأمر الشراء هذا.']);
        }

        $attachments->assertBelongsTo($attachment, $customer_po);

        return $attachments->stream($attachment);
    }

    public function show(Request $request, CustomerPurchaseOrder $customer_po): View
    {
        $this->authorize('view', $customer_po);

        $customer_po->load([
            'customer',
            'quotation.lines',
            'lines.product',
            'deal',
            'createdBy',
            'cancelledBy',
            'attachments',
            'salesOrders' => fn ($q) => $q->latest('id')->limit(20),
        ]);

        /** @var User $user */
        $user = $request->user();

        $lineAllocations = [];
        foreach ($customer_po->lines as $line) {
            $lineAllocations[$line->id] = [
                'allocated' => $customer_po->allocatedQuantityForLine($line),
                'unallocated' => $customer_po->unallocatedQuantityForLine($line),
                'delivered' => $customer_po->deliveredQuantityForLine($line),
                'undelivered' => $customer_po->undeliveredQuantityForLine($line),
                'invoiced' => $customer_po->invoicedQuantityForLine($line),
                'uninvoiced' => $customer_po->uninvoicedQuantityForLine($line),
            ];
        }

        return view('customer-pos.show', [
            'po' => $customer_po,
            'lineAllocations' => $lineAllocations,
            'pf1' => $customer_po->partialFulfillmentTotals(),
            'poPdf' => $customer_po->attachments
                ->first(fn ($a) => $a->extension === 'pdf')
                ?? $customer_po->attachments->first(),
            'canUpdate' => $user->can('update', $customer_po),
            'canCancel' => $user->can('cancel', $customer_po),
            'canCreateSo' => $user->can('create', \App\Models\SalesOrder::class) && $customer_po->isOpen(),
        ]);
    }

    public function edit(Request $request, CustomerPurchaseOrder $customer_po): View
    {
        $this->authorize('update', $customer_po);
        $customer_po->load(['customer', 'deal', 'lines.product']);

        return view('customer-pos.edit', [
            'po' => $customer_po,
        ]);
    }

    public function update(
        UpdateCustomerPurchaseOrderRequest $request,
        CustomerPurchaseOrder $customer_po,
        CustomerPurchaseOrderWorkflow $workflow,
    ): RedirectResponse {
        try {
            $workflow->updateOpen($customer_po, $request->user(), $request->payload());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['customer_po' => $e->getMessage()]);
        }

        return redirect()
            ->route('customer-pos.show', $customer_po)
            ->with('status_ar', 'تم تحديث أمر شراء العميل.');
    }

    public function cancel(
        Request $request,
        CustomerPurchaseOrder $customer_po,
        CustomerPurchaseOrderWorkflow $workflow,
    ): RedirectResponse {
        $this->authorize('cancel', $customer_po);

        try {
            $workflow->cancel($customer_po, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['customer_po' => $e->getMessage()]);
        }

        return redirect()
            ->route('customer-pos.show', $customer_po)
            ->with('status_ar', 'تم إلغاء أمر شراء العميل.');
    }
}
