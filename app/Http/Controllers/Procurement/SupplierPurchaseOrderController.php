<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\MarkSupplierPurchaseOrderOrderedRequest;
use App\Http\Requests\Procurement\ReceiveSupplierPurchaseOrderRequest;
use App\Models\Attachment;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use App\Support\Attachments\AttachmentService;
use App\Support\Procurement\SupplierPurchaseOrderPdfRenderer;
use App\Support\Procurement\SupplierPurchaseOrderStatus;
use App\Support\Procurement\SupplierPurchaseOrderWorkflow;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierPurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SupplierPurchaseOrder::class);

        /** @var User $user */
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $warehouseFacing = $user->can('delivery.manage') && ! $user->can('procurement.manage');
        $inbox = (string) $request->query(
            'inbox',
            ($warehouseFacing && $status === '') ? 'inbound' : '',
        );

        $orders = SupplierPurchaseOrder::query()
            ->visibleTo($user)
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like, $q) {
                    $inner->where('supplier_name_ar', 'like', $like)
                        ->orWhere('id', is_numeric($q) ? (int) $q : 0)
                        ->orWhereHas('salesOrder.customer', fn ($c) => $c->where('name_ar', 'like', $like))
                        ->orWhereHas('lines.product', fn ($p) => $p->where('name_ar', 'like', $like));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($inbox === 'inbound' && $status === '', function ($query) {
                $query->whereIn('status', [
                    SupplierPurchaseOrderStatus::Ordered->value,
                    SupplierPurchaseOrderStatus::PartiallyReceived->value,
                ]);
            })
            ->with(['salesOrder.customer', 'lines.product', 'createdBy'])
            ->withCount('goodsReceipts')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('supplier-pos.index', [
            'orders' => $orders,
            'q' => $q,
            'status' => $status,
            'inbox' => $inbox,
            'warehouseFacing' => $warehouseFacing,
            'statuses' => SupplierPurchaseOrderStatus::cases(),
            'canViewCosts' => $user->can('prices.view')
                || $user->can('procurement.manage')
                || $user->can('procurement.view'),
        ]);
    }

    public function show(Request $request, SupplierPurchaseOrder $supplier_po): View
    {
        $this->authorize('view', $supplier_po);

        $supplier_po->load([
            'lines.product.unit',
            'salesOrder.customer',
            'quotation.customer',
            'createdBy',
            'receivedBy',
            'attachments',
            'goodsReceipts.receivedBy',
            'goodsReceipts.lines',
        ]);

        /** @var User $user */
        $user = $request->user();

        return view('supplier-pos.show', [
            'spo' => $supplier_po,
            'canMarkOrdered' => $user->can('markOrdered', $supplier_po),
            'canReceive' => $user->can('receive', $supplier_po),
            'canPrint' => $user->can('print', $supplier_po),
            'warehouseFacing' => $user->can('delivery.manage') && ! $user->can('procurement.manage'),
            'canViewCosts' => $user->can('prices.view')
                || $user->can('procurement.manage')
                || $user->can('procurement.view'),
        ]);
    }

    public function pdf(
        Request $request,
        SupplierPurchaseOrder $supplier_po,
        SupplierPurchaseOrderPdfRenderer $pdf,
    ): Response {
        $this->authorize('print', $supplier_po);

        return $pdf->stream($supplier_po);
    }

    public function attachment(
        Request $request,
        SupplierPurchaseOrder $supplier_po,
        Attachment $attachment,
        AttachmentService $attachments,
    ): StreamedResponse {
        $this->authorize('view', $supplier_po);
        $attachments->assertBelongsTo($attachment, $supplier_po);

        return $attachments->stream($attachment);
    }

    public function markOrdered(
        MarkSupplierPurchaseOrderOrderedRequest $request,
        SupplierPurchaseOrder $supplier_po,
        SupplierPurchaseOrderWorkflow $workflow,
    ): RedirectResponse {
        try {
            $workflow->markOrdered($supplier_po, $request->user(), $request->payload());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['supplier_po' => $e->getMessage()]);
        }

        return redirect()
            ->route('supplier-pos.show', $supplier_po)
            ->with('status_ar', 'تم إصدار أمر الشراء للمورد وإرفاقه في النظام وإشعار المستودع.');
    }

    public function receive(
        ReceiveSupplierPurchaseOrderRequest $request,
        SupplierPurchaseOrder $supplier_po,
        SupplierPurchaseOrderWorkflow $workflow,
    ): RedirectResponse {
        try {
            $receipt = $workflow->receiveShipment($supplier_po, $request->user(), $request->payload());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['supplier_po' => $e->getMessage()]);
        }

        return redirect()
            ->route('supplier-goods-receipts.show', $receipt)
            ->with('status_ar', 'تم إنشاء أمر استلام الشحنة وزيادة المخزون.');
    }
}
