<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\SupplierGoodsReceipt;
use App\Support\Attachments\AttachmentService;
use App\Support\Procurement\SupplierGoodsReceiptPdfRenderer;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierGoodsReceiptController extends Controller
{
    public function show(Request $request, SupplierGoodsReceipt $goods_receipt): View
    {
        $goods_receipt->load([
            'purchaseOrder.salesOrder.customer',
            'purchaseOrder.lines.product',
            'lines.product.unit',
            'receivedBy',
            'attachments',
        ]);

        $this->authorize('view', $goods_receipt->purchaseOrder);

        return view('supplier-goods-receipts.show', [
            'receipt' => $goods_receipt,
            'spo' => $goods_receipt->purchaseOrder,
            'canPrint' => $request->user()?->can('print', $goods_receipt->purchaseOrder) ?? false,
        ]);
    }

    public function attachment(
        Request $request,
        SupplierGoodsReceipt $goods_receipt,
        Attachment $attachment,
        AttachmentService $attachments,
    ): StreamedResponse {
        $goods_receipt->loadMissing('purchaseOrder');
        $this->authorize('view', $goods_receipt->purchaseOrder);
        $attachments->assertBelongsTo($attachment, $goods_receipt);

        return $attachments->stream($attachment);
    }

    public function pdf(
        Request $request,
        SupplierGoodsReceipt $goods_receipt,
        SupplierGoodsReceiptPdfRenderer $pdf,
    ): Response {
        $goods_receipt->loadMissing('purchaseOrder');
        $this->authorize('print', $goods_receipt->purchaseOrder);

        return $pdf->stream($goods_receipt);
    }
}
