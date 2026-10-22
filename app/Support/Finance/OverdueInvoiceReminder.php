<?php

namespace App\Support\Finance;

use App\Mail\InvoiceOverdueMail;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Quotations\QuotationWorkflow;
use Illuminate\Support\Facades\Mail;

/**
 * Overdue reminders — Mail::fake covered in tests; live SMTP gated by CM1b.
 */
class OverdueInvoiceReminder
{
    public function __construct(
        private AuditLogger $audit,
        private QuotationWorkflow $recipients,
    ) {}

    /**
     * @return int Number of reminders attempted
     */
    public function sendDue(?User $actor = null): int
    {
        $count = 0;
        $invoices = Invoice::query()
            ->with('customer.contacts')
            ->where('status', InvoiceStatus::Issued->value)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->get()
            ->filter(fn (Invoice $i) => $i->isOverdue());

        foreach ($invoices as $invoice) {
            if ($this->sendOne($invoice, $actor)) {
                $count++;
            }
        }

        return $count;
    }

    public function sendOne(Invoice $invoice, ?User $actor = null): bool
    {
        if (! $invoice->isOverdue()) {
            return false;
        }

        $email = $this->recipients->resolveRecipientEmail($invoice->customer);
        if ($email === null || $email === '') {
            return false;
        }

        Mail::to($email)->send(new InvoiceOverdueMail($invoice->loadMissing('customer')));

        $invoice->fill(['last_overdue_reminded_at' => now()])->save();

        $this->audit->record('invoice.overdue_reminded', $invoice, [
            'recipient_email' => $email,
            'unpaid' => $invoice->unpaidBalance(),
        ], $actor);

        return true;
    }
}
