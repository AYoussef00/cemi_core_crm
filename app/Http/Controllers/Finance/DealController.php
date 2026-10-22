<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\User;
use App\Support\Finance\DealCloseWorkflow;
use App\Support\Sales\DealStatus;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DealController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(
            $request->user()->can('deals.close') || $request->user()->can('invoices.view'),
            403
        );

        /** @var User $user */
        $user = $request->user();

        $deals = Deal::query()
            ->with(['customer', 'invoices', 'closedBy'])
            ->when(
                $request->query('status') === 'open',
                fn ($q) => $q->where('status', DealStatus::Open->value)
            )
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('deals.index', [
            'deals' => $deals,
            'canClose' => $user->can('deals.close'),
        ]);
    }

    public function show(Request $request, Deal $deal): View
    {
        $this->authorize('view', $deal);

        $deal->load([
            'customer',
            'customerPurchaseOrder.lines',
            'invoices',
            'salesOrders',
            'closedBy',
        ]);

        /** @var User $user */
        $user = $request->user();

        $pf1 = $deal->customerPurchaseOrder?->partialFulfillmentTotals() ?? [
            'ordered' => 0.0,
            'unallocated' => 0.0,
            'undelivered' => 0.0,
            'uninvoiced' => 0.0,
        ];

        return view('deals.show', [
            'deal' => $deal,
            'canClose' => $user->can('close', $deal),
            'allPaid' => $deal->allInvoicesFullyPaid(),
            'pf1' => $pf1,
        ]);
    }

    public function close(
        Request $request,
        Deal $deal,
        DealCloseWorkflow $workflow,
    ): RedirectResponse {
        $this->authorize('close', $deal);

        try {
            $workflow->close($deal, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['deal' => $e->getMessage()]);
        }

        return redirect()
            ->route('deals.show', $deal)
            ->with('status_ar', 'تم إغلاق الصفقة — جميع الفواتير المسددة (TX5b).');
    }
}
