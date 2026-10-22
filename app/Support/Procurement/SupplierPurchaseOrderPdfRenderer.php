<?php

namespace App\Support\Procurement;

use App\Models\Company;
use App\Models\SupplierPurchaseOrder;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\Response;

final class SupplierPurchaseOrderPdfRenderer
{
    public function stream(SupplierPurchaseOrder $spo, ?Company $company = null): Response
    {
        $binary = $this->binary($spo, $company);
        $filename = 'supplier-po-'.$spo->id.'.pdf';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function binary(SupplierPurchaseOrder $spo, ?Company $company = null): string
    {
        $spo->loadMissing(['lines.product.unit', 'salesOrder.customer', 'quotation']);

        $html = view('supplier-pos.pdf', [
            'spo' => $spo,
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
        $mpdf->SetTitle('أمر شراء مورد #'.$spo->id);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('supplier-po-'.$spo->id.'.pdf', Destination::STRING_RETURN);
    }
}
