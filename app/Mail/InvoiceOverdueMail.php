<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceOverdueMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تذكير فاتورة متأخرة #'.$this->invoice->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p dir="rtl">تذكير: الفاتورة رقم <strong>'.$this->invoice->id
                .'</strong> للمنشأة <strong>'.e($this->invoice->customer?->name_ar ?? '')
                .'</strong> متأخرة. المتبقي: <strong>'.$this->invoice->unpaidBalance()
                .'</strong> ر.س.</p><p dir="rtl">ملاحظة: الإرسال الحي يعتمد على إعداد البريد (CM1b).</p>',
        );
    }
}
