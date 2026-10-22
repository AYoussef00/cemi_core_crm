<?php

namespace App\Support\Quotations;

use App\Models\Company;
use App\Models\Quotation;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class QuotationPdfRenderer
{
    public function stream(Quotation $quotation, ?Company $company = null): \Symfony\Component\HttpFoundation\Response
    {
        $quotation->loadMissing(['lines.product.unit', 'customer']);

        $html = view('quotations.pdf', [
            'quotation' => $quotation,
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
        $mpdf->SetTitle('عرض سعر #'.$quotation->id);
        $mpdf->WriteHTML($html);

        $filename = 'quotation-'.$quotation->id.'.pdf';
        $binary = $mpdf->Output($filename, Destination::STRING_RETURN);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
