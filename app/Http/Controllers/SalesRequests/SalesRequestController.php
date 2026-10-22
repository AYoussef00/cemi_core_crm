<?php

namespace App\Http\Controllers\SalesRequests;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesRequests\StoreSalesRequestRequest;
use App\Http\Requests\SalesRequests\UpdateSalesRequestRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesRequest;
use App\Models\User;
use App\Support\Inventory\InventoryService;
use App\Support\SalesRequests\LineAvailability;
use App\Support\SalesRequests\SalesRequestStatus;
use App\Support\SalesRequests\SalesRequestWorkflow;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesRequestController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalesRequest::class);

        /** @var User $user */
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $requests = SalesRequest::query()
            ->visibleTo($user)
            ->search($q !== '' ? $q : null)
            ->status($status !== '' ? $status : null)
            ->with(['customer', 'createdBy', 'lines'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('sales-requests.index', [
            'requests' => $requests,
            'q' => $q,
            'status' => $status,
            'statuses' => SalesRequestStatus::cases(),
            'canCreate' => $user->can('create', SalesRequest::class),
        ]);
    }

    public function create(Request $request, InventoryService $inventory): View
    {
        $this->authorize('create', SalesRequest::class);

        /** @var User $user */
        $user = $request->user();

        return view('sales-requests.create', [
            'customers' => Customer::query()->visibleTo($user)->orderBy('name_ar')->get(),
            'products' => Product::query()->with('unit')->orderBy('name_ar')->get(),
            'availabilities' => LineAvailability::cases(),
            'stockHints' => $this->stockHints($inventory),
            'preselectCustomerId' => $request->integer('customer_id') ?: null,
        ]);
    }

    public function store(StoreSalesRequestRequest $request, SalesRequestWorkflow $workflow): RedirectResponse
    {
        try {
            $salesRequest = $workflow->create($request->user(), $request->payload());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales-requests.show', $salesRequest)
            ->with('status_ar', 'تم إنشاء مسودة طلب البيع.');
    }

    public function show(Request $request, SalesRequest $salesRequest, InventoryService $inventory): View
    {
        $this->authorize('view', $salesRequest);

        $salesRequest->load([
            'customer',
            'createdBy',
            'cancelledBy',
            'lines.product.unit',
            'procurementTasks.product',
            'quotations' => fn ($q) => $q->latest('id'),
        ]);

        /** @var User $user */
        $user = $request->user();

        return view('sales-requests.show', [
            'salesRequest' => $salesRequest,
            'canUpdate' => $user->can('update', $salesRequest),
            'canSubmit' => $user->can('submit', $salesRequest),
            'canCancel' => $user->can('cancel', $salesRequest),
            'canCreateQuotation' => $user->can('createFromSalesRequest', [Quotation::class, $salesRequest]),
            'stockHints' => $this->stockHints($inventory),
            'canViewCosts' => $user->can('prices.view') || $user->can('procurement.manage') || $user->can('procurement.view'),
        ]);
    }

    public function edit(Request $request, SalesRequest $salesRequest, InventoryService $inventory): View
    {
        $this->authorize('update', $salesRequest);

        /** @var User $user */
        $user = $request->user();
        $salesRequest->load('lines');

        return view('sales-requests.edit', [
            'salesRequest' => $salesRequest,
            'customers' => Customer::query()->visibleTo($user)->orderBy('name_ar')->get(),
            'products' => Product::query()->with('unit')->orderBy('name_ar')->get(),
            'availabilities' => LineAvailability::cases(),
            'stockHints' => $this->stockHints($inventory),
        ]);
    }

    public function update(
        UpdateSalesRequestRequest $request,
        SalesRequest $salesRequest,
        SalesRequestWorkflow $workflow,
    ): RedirectResponse {
        try {
            $workflow->update($salesRequest, $request->user(), $request->payload());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales-requests.show', $salesRequest)
            ->with('status_ar', 'تم تحديث مسودة طلب البيع.');
    }

    public function submit(Request $request, SalesRequest $salesRequest, SalesRequestWorkflow $workflow): RedirectResponse
    {
        $this->authorize('submit', $salesRequest);

        try {
            $workflow->submit($salesRequest, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales-requests.show', $salesRequest)
            ->with('status_ar', 'تم إرسال طلب البيع.');
    }

    public function cancel(Request $request, SalesRequest $salesRequest, SalesRequestWorkflow $workflow): RedirectResponse
    {
        $this->authorize('cancel', $salesRequest);

        try {
            $workflow->cancel($salesRequest, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()
            ->route('sales-requests.show', $salesRequest)
            ->with('status_ar', 'تم إلغاء طلب البيع.');
    }

    /**
     * Available warehouse qty per product — drives SR-AUTO-STOCK on the form and in FormRequests.
     *
     * @return array<int, string>
     */
    private function stockHints(InventoryService $inventory): array
    {
        $hints = [];
        foreach (Product::query()->orderBy('id')->get() as $product) {
            $balance = $inventory->balanceFor($product);
            $hints[$product->id] = $balance?->available() ?? '0.000';
        }

        return $hints;
    }
}
