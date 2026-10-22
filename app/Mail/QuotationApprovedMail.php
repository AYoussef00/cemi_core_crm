<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotationApprovedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Quotation $quotation)
    {
        $this->quotation->loadMissing(['lines', 'customer']);
    }

    public function envelope(): Envelope
    {
        $company = Company::current()?->name_ar ?? config('app.name');

        return new Envelope(
            subject: 'عرض سعر معتمد — '.$company.' #'.$this->quotation->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.quotation-approved',
            with: [
                'quotation' => $this->quotation,
                'company' => Company::current(),
            ],
        );
    }
}
