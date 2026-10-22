<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\DecideInventoryAdjustmentRequest;
use App\Http\Requests\Inventory\StoreInventoryAdjustmentRequest;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Inventory\InventoryAdjustmentStatus;
use App\Support\Inventory\InventoryService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryAdjustmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', InventoryAdjustment::class);

        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $adjustments = InventoryAdjustment::query()
            ->search($q !== '' ? $q : null)
            ->status($status !== '' ? $status : null)
            ->with(['product.unit', 'recordedBy', 'decidedBy'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('inventory.adjustments.index', [
            'adjustments' => $adjustments,
            'q' => $q,
            'status' => $status,
            'statuses' => InventoryAdjustmentStatus::cases(),
            'canCreate' => $request->user()?->can('create', InventoryAdjustment::class) ?? false,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', InventoryAdjustment::class);

        return view('inventory.adjustments.create', [
            'warehouse' => Warehouse::current(),
            'products' => Product::query()->with('unit')->orderBy('name_ar')->get(),
        ]);
    }

    public function store(StoreInventoryAdjustmentRequest $request, InventoryService $inventory): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $adjustment = $inventory->recordAdjustment($user, $request->payload());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['product_id' => $e->getMessage()]);
        }

        return redirect()
            ->route('inventory.adjustments.show', $adjustment)
            ->with('status_ar', 'تم تسجيل تسوية المخزون وبانتظار اعتماد مدير المبيعات.');
    }

    public function show(Request $request, InventoryAdjustment $adjustment): View
    {
        $this->authorize('view', $adjustment);

        $adjustment->load(['product.unit', 'warehouse', 'recordedBy', 'decidedBy']);

        /** @var User $user */
        $user = $request->user();

        return view('inventory.adjustments.show', [
            'adjustment' => $adjustment,
            'canApprove' => $user->can('approve', $adjustment),
            'canReject' => $user->can('reject', $adjustment),
            'balance' => app(InventoryService::class)->balanceFor($adjustment->product),
        ]);
    }

    public function decide(
        DecideInventoryAdjustmentRequest $request,
        InventoryAdjustment $adjustment,
        InventoryService $inventory,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $notes = $request->validated('decision_notes');

        try {
            if ($request->validated('decision') === 'approve') {
                $inventory->approveAdjustment($adjustment, $user, $notes);
                $message = 'تم اعتماد التسوية وتحديث رصيد المخزون.';
            } else {
                $inventory->rejectAdjustment($adjustment, $user, $notes);
                $message = 'تم رفض التسوية دون تغيير الأرصدة.';
            }
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['decision' => $e->getMessage()]);
        }

        return redirect()
            ->route('inventory.adjustments.show', $adjustment)
            ->with('status_ar', $message);
    }
}
