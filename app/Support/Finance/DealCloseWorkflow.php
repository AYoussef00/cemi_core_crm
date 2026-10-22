<?php

namespace App\Support\Finance;

use App\Models\Deal;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Sales\DealStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

class DealCloseWorkflow
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * TX5b: Accounts closes deal only when every linked invoice unpaid (CR7) is 0.
     */
    public function close(Deal $deal, User $actor): Deal
    {
        if ($deal->status === DealStatus::Closed) {
            throw new DomainException('الصفقة مغلقة مسبقًا.');
        }

        $invoices = Invoice::query()->where('deal_id', $deal->id)->get();

        if ($invoices->isEmpty()) {
            throw new DomainException('لا يمكن إغلاق صفقة بلا فواتير صادرة.');
        }

        foreach ($invoices as $invoice) {
            if (! $invoice->isFullyPaid()) {
                throw new DomainException(
                    'لا يمكن إغلاق الصفقة: الفاتورة '.$invoice->document_number
                    .' ما زال عليها متبقي '.$invoice->unpaidBalance().' ر.س (TX5b/CR7).'
                );
            }
        }

        return DB::transaction(function () use ($deal, $actor): Deal {
            $deal = Deal::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();

            if ($deal->status === DealStatus::Closed) {
                return $deal->fresh();
            }

            $deal->fill([
                'status' => DealStatus::Closed,
                'closed_at' => now(),
                'closed_by_user_id' => $actor->id,
            ])->save();

            $this->audit->record('deal.closed', $deal, [
                'status' => $deal->status->value,
            ], $actor);

            return $deal->fresh(['customer', 'closedBy']);
        });
    }
}
