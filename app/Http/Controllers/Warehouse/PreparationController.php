<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\Sales\SalesOrderStatus;
use App\Support\Warehouse\DeliveryWorkflow;
use App\Support\Warehouse\FulfillmentStatus;
use App\Support\Warehouse\PreparationStatus;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PreparationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', \App\Models\Delivery::class);

        /** @var User $user */
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $prep = (string) $request->query('preparation', '');

        $orders = SalesOrder::query()
            ->visibleTo($user)
            ->where('status', SalesOrderStatus::Approved)
            ->where('fulfillment_status', '!=', FulfillmentStatus::FullyDelivered->value)
            ->when($prep !== '', fn ($query) => $query->where('preparation_status', $prep))
            ->search($q !== '' ? $q : null)
            ->with(['customer', 'lines.product', 'preparationReadyBy'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('warehouse.preparation.index', [
            'orders' => $orders,
            'q' => $q,
            'preparation' => $prep,
            'preparationStatuses' => PreparationStatus::cases(),
            'canManage' => $user->can('delivery.manage'),
            'canViewPrices' => $user->can('prices.view'),
        ]);
    }

    public function markReady(
        Request $request,
        SalesOrder $sales_order,
        DeliveryWorkflow $workflow,
    ): RedirectResponse {
        $this->authorize('managePreparation', [\App\Models\Delivery::class, $sales_order]);

        try {
            $workflow->markPreparationReady(
                $sales_order,
                $request->user(),
                $request->input('preparation_notes_ar'),
            );
        } catch (DomainException $e) {
            return back()->withErrors(['preparation' => $e->getMessage()]);
        }

        return redirect()
            ->route('warehouse.preparation.index')
            ->with('status_ar', 'تم تعليم أمر البيع كجاهز للتسليم.');
    }
}
