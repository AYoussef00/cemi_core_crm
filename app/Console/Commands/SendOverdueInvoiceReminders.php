<?php

namespace App\Console\Commands;

use App\Support\Finance\OverdueInvoiceReminder;
use Illuminate\Console\Command;

class SendOverdueInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-overdue-reminders';

    protected $description = 'Send overdue invoice reminder emails (CM1b: live SMTP not claimed)';

    public function handle(OverdueInvoiceReminder $reminder): int
    {
        $count = $reminder->sendDue();
        $this->info("Attempted overdue reminders: {$count}");

        return self::SUCCESS;
    }
}
