<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreDeliveryPodRequest;
use App\Http\Requests\Warehouse\StoreDeliveryRequest;
use App\Http\Requests\Warehouse\UpdateDeliveryDraftRequest;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\Attachments\AttachmentService;
use App\Support\Sales\SalesOrderStatus;
use App\Support\Warehouse\DeliveryStatus;
use App\Support\Warehouse\DeliveryWorkflow;
use App\Support\Warehouse\FulfillmentStatus;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Delivery::class);

        /** @var User $user */
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $deliveries = Delivery::query()
            ->visibleTo($user)
            ->search($q !== '' ? $q : null)
            ->status($status !== '' ? $status : null)
            ->with(['customer', 'salesOrder', 'createdBy', 'lines'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('deliveries.index', [
            'deliveries' => $deliveries,
            'q' => $q,
            'status' => $status,
            'statuses' => DeliveryStatus::cases(),
            'canCreate' => $user->can('create', Delivery::class),
            'canViewPrices' => $user->can('prices.view'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Delivery::class);

        /** @var User $user */
        $user = $request->user();
        $soId = (int) $request->query('sales_order_id', 0);

        $orders = SalesOrder::query()
            ->visibleTo($user)
            ->where('status', SalesOrderStatus::Approved)
            ->where('fulfillment_status', '!=', FulfillmentStatus::FullyDelivered->value)
            ->with(['customer', 'lines.product'])
            ->latest('id')
            ->get();

        $selected = $soId > 0 ? $orders->firstWhere('id', $soId) : null;
        if ($selected !== null) {
            $this->authorize('createForSalesOrder', [Delivery::class, $selected]);
        }

        $undelivered = [];
        if ($selected !== null) {
            foreach ($selected->lines as $line) {
                $undelivered[$line->id] = $selected->undeliveredQuantityForLine($line);
            }
        }

        return view('deliveries.create', [
            'orders' => $orders,
            'selected' => $selected,
            'undelivered' => $undelivered,
            'canViewPrices' => $user->can('prices.view'),
        ]);
    }

    public function store(
        StoreDeliveryRequest $request,
        DeliveryWorkflow $workflow,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $payload = $request->payload();
        $order = SalesOrder::query()->findOrFail($payload['sales_order_id']);
        $this->authorize('createForSalesOrder', [Delivery::class, $order]);

        try {
            $delivery = $workflow->createDraft($user, $payload);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['delivery' => $e->getMessage()]);
        }

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('status_ar', 'تم إنشاء مسودة التسليم — ارفع إثبات التسليم ثم أكّد.');
    }

    public function show(Request $request, Delivery $delivery): View
    {
        $this->authorize('view', $delivery);

        $delivery->load([
            'customer',
            'salesOrder.lines.product',
            'lines.product',
            'createdBy',
            'confirmedBy',
            'cancelledBy',
            'attachments.uploader',
        ]);

        /** @var User $user */
        $user = $request->user();

        return view('deliveries.show', [
            'delivery' => $delivery,
            'canUpdate' => $user->can('update', $delivery),
            'canPendingPod' => $user->can('markPendingPod', $delivery),
            'canConfirm' => $user->can('confirm', $delivery),
            'canCancel' => $user->can('cancel', $delivery),
            'canUploadPod' => $user->can('uploadPod', $delivery),
            'canViewPrices' => $user->can('prices.view'),
            'podLimits' => [
                'extensions' => config('attachments.allowed_extensions', []),
                'max_kilobytes' => (int) config('attachments.max_kilobytes', 5120),
            ],
        ]);
    }

    public function edit(Request $request, Delivery $delivery): View
    {
        $this->authorize('update', $delivery);

        $delivery->load(['lines', 'salesOrder.lines.product', 'customer']);
        $order = $delivery->salesOrder;
        $undelivered = [];
        if ($order !== null) {
            foreach ($order->lines as $line) {
                $undelivered[$line->id] = $order->undeliveredQuantityForLine($line, $delivery);
            }
        }

        return view('deliveries.edit', [
            'delivery' => $delivery,
            'undelivered' => $undelivered,
            'canViewPrices' => $request->user()->can('prices.view'),
        ]);
    }

    public function update(
        UpdateDeliveryDraftRequest $request,
        Delivery $delivery,
        DeliveryWorkflow $workflow,
    ): RedirectResponse {
        try {
            $workflow->updateDraft($delivery, $request->user(), $request->payload());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['delivery' => $e->getMessage()]);
        }

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('status_ar', 'تم تحديث مسودة التسليم.');
    }

    public function markPendingPod(
        Request $request,
        Delivery $delivery,
        DeliveryWorkflow $workflow,
    ): RedirectResponse {
        $this->authorize('markPendingPod', $delivery);

        try {
            $workflow->markPendingPod($delivery, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['delivery' => $e->getMessage()]);
        }

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('status_ar', 'التسليم بانتظار إثبات التسليم.');
    }

    public function confirm(
        Request $request,
        Delivery $delivery,
        DeliveryWorkflow $workflow,
    ): RedirectResponse {
        $this->authorize('confirm', $delivery);

        try {
            $workflow->confirm($delivery, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['delivery' => $e->getMessage()]);
        }

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('status_ar', 'تم تأكيد التسليم وخصم المخزون وإصدار الفاتورة تلقائيًا.');
    }

    public function cancel(
        Request $request,
        Delivery $delivery,
        DeliveryWorkflow $workflow,
    ): RedirectResponse {
        $this->authorize('cancel', $delivery);

        try {
            $workflow->cancel($delivery, $request->user(), $request->input('cancel_notes_ar'));
        } catch (DomainException $e) {
            return back()->withErrors(['delivery' => $e->getMessage()]);
        }

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('status_ar', 'تم إلغاء التسليم — بلا خصم مخزون.');
    }

    public function storePod(
        StoreDeliveryPodRequest $request,
        Delivery $delivery,
        AttachmentService $attachments,
    ): RedirectResponse {
        try {
            $attachments->store($delivery, $request->file('file'), $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('status_ar', 'تم رفع إثبات التسليم.');
    }

    public function downloadPod(
        Request $request,
        Delivery $delivery,
        Attachment $attachment,
        AttachmentService $attachments,
    ): StreamedResponse {
        $this->authorize('view', $delivery);
        $attachments->assertBelongsTo($attachment, $delivery);

        return $attachments->download($attachment);
    }

    public function destroyPod(
        Request $request,
        Delivery $delivery,
        Attachment $attachment,
        AttachmentService $attachments,
    ): RedirectResponse {
        $this->authorize('uploadPod', $delivery);
        $attachments->assertBelongsTo($attachment, $delivery);
        $attachments->delete($attachment, $request->user());

        return redirect()
            ->route('deliveries.show', $delivery)
            ->with('status_ar', 'تم حذف مرفق إثبات التسليم.');
    }

    public function print(Request $request, Delivery $delivery): View
    {
        $this->authorize('view', $delivery);

        $delivery->load(['customer', 'lines.product', 'salesOrder']);

        return view('deliveries.print', [
            'delivery' => $delivery,
            'company' => Company::current(),
            'canViewPrices' => $request->user()->can('prices.view'),
        ]);
    }
}
