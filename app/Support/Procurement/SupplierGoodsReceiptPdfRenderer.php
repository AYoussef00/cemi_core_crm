<?php

namespace App\Support\Procurement;

use App\Models\Company;
use App\Models\SupplierGoodsReceipt;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\Response;

final class SupplierGoodsReceiptPdfRenderer
{
    public function stream(SupplierGoodsReceipt $receipt, ?Company $company = null): Response
    {
        $receipt->loadMissing([
            'lines.product.unit',
            'purchaseOrder.salesOrder.customer',
            'receivedBy',
        ]);

        $html = view('supplier-goods-receipts.pdf', [
            'receipt' => $receipt,
            'spo' => $receipt->purchaseOrder,
            'company' => $company ?? Company::current(),
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 12,
            'default_font' => 'dejavusans',
            'tempDir' => storage_path('app/mpdf-temp'),
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->SetTitle('أمر استلام شحنة #'.$receipt->id);
        $mpdf->WriteHTML($html);

        $filename = 'supplier-goods-receipt-'.$receipt->id.'.pdf';
        $binary = $mpdf->Output($filename, Destination::STRING_RETURN);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
