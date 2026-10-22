<?php

namespace App\Support\Finance;

use App\Models\Collection;
use App\Models\CollectionAllocation;
use App\Models\CollectionReversal;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Authorization\RoleSlug;
use App\Support\Money\Money;
use App\Support\Notifications\InternalNotifier;
use DomainException;
use Illuminate\Support\Facades\DB;

class CollectionWorkflow
{
    public function __construct(
        private AuditLogger $audit,
        private InternalNotifier $notifier,
    ) {}

    /**
     * @param  array{
     *     customer_id: int,
     *     notes_ar?: string|null,
     *     allocations: list<array{invoice_id: int, amount: string}>
     * }  $payload
     */
    public function createDraft(User $actor, array $payload): Collection
    {
        $customer = Customer::query()->findOrFail($payload['customer_id']);
        $this->assertAllocations($customer, $payload['allocations'], null);

        $total = array_sum(array_map(fn ($a) => (float) $a['amount'], $payload['allocations']));

        return DB::transaction(function () use ($actor, $payload, $customer, $total): Collection {
            $collection = Collection::query()->create([
                'customer_id' => $customer->id,
                'created_by_user_id' => $actor->id,
                'status' => CollectionStatus::Draft,
                'amount_total' => Money::round2($total),
                'notes_ar' => $payload['notes_ar'] ?? null,
            ]);

            foreach ($payload['allocations'] as $row) {
                CollectionAllocation::query()->create([
                    'collection_id' => $collection->id,
                    'invoice_id' => (int) $row['invoice_id'],
                    'amount' => Money::round2($row['amount']),
                ]);
            }

            $this->audit->record('collection.created', $collection, [
                'status' => $collection->status->value,
                'amount_total' => (string) $collection->amount_total,
            ], $actor);

            return $collection->fresh(['customer', 'allocations.invoice']);
        });
    }

    public function confirm(Collection $collection, User $actor): Collection
    {
        $applied = false;

        $result = DB::transaction(function () use ($collection, $actor, &$applied): Collection {
            $collection = Collection::query()->whereKey($collection->id)->lockForUpdate()->firstOrFail();

            if ($collection->status === CollectionStatus::Confirmed) {
                return $collection->fresh(['allocations.invoice', 'customer']);
            }

            if ($collection->status !== CollectionStatus::Draft) {
                throw new DomainException('لا يمكن تأكيد تحصيل ليس مسودة.');
            }

            $collection->load('allocations.invoice');
            $this->assertAllocations(
                $collection->customer,
                $collection->allocations->map(fn ($a) => [
                    'invoice_id' => $a->invoice_id,
                    'amount' => (string) $a->amount,
                ])->all(),
                $collection,
            );

            $collection->fill([
                'status' => CollectionStatus::Confirmed,
                'confirmed_at' => now(),
                'confirmed_by_user_id' => $actor->id,
            ])->save();

            $this->audit->record('collection.confirmed', $collection, [
                'status' => $collection->status->value,
                'amount_total' => (string) $collection->amount_total,
            ], $actor);

            $applied = true;

            return $collection->fresh(['allocations.invoice', 'customer']);
        });

        return $result;
    }

    public function requestReversal(Collection $collection, User $actor, ?string $reason = null): CollectionReversal
    {
        if ($collection->status !== CollectionStatus::Confirmed) {
            throw new DomainException('عكس التحصيل متاح للتحصيل المؤكد فقط.');
        }

        if (CollectionReversal::query()
            ->where('collection_id', $collection->id)
            ->where('status', CollectionReversalStatus::Pending->value)
            ->exists()) {
            throw new DomainException('يوجد طلب عكس بانتظار الاعتماد.');
        }

        if ($collection->status === CollectionStatus::Reversed
            || CollectionReversal::query()
                ->where('collection_id', $collection->id)
                ->where('status', CollectionReversalStatus::Approved->value)
                ->exists()) {
            throw new DomainException('التحصيل معكوس مسبقًا.');
        }

        return DB::transaction(function () use ($collection, $actor, $reason): CollectionReversal {
            $reversal = CollectionReversal::query()->create([
                'collection_id' => $collection->id,
                'requested_by_user_id' => $actor->id,
                'status' => CollectionReversalStatus::Pending,
                'reason_ar' => $reason,
            ]);

            $this->audit->record('collection_reversal.requested', $reversal, [
                'collection_id' => $collection->id,
            ], $actor);

            DB::afterCommit(function () use ($reversal): void {
                $this->notifier->notifyRole(RoleSlug::SalesManager, [
                    'title_ar' => 'طلب عكس تحصيل',
                    'body_ar' => 'تحصيل #'.$reversal->collection_id.' بانتظار الاعتماد',
                    'action_url' => route('collections.show', $reversal->collection_id, false),
                    'category' => 'collection',
                ]);
            });

            return $reversal->fresh(['collection']);
        });
    }

    public function decideReversal(
        CollectionReversal $reversal,
        User $actor,
        string $decision,
        ?string $notes = null,
    ): CollectionReversal {
        $applied = false;

        $result = DB::transaction(function () use ($reversal, $actor, $decision, $notes, &$applied): CollectionReversal {
            $reversal = CollectionReversal::query()->whereKey($reversal->id)->lockForUpdate()->firstOrFail();

            if ($reversal->status !== CollectionReversalStatus::Pending) {
                throw new DomainException('طلب العكس ليس بانتظار الاعتماد.');
            }

            if ($decision === 'approve') {
                $collection = Collection::query()->whereKey($reversal->collection_id)->lockForUpdate()->firstOrFail();
                if ($collection->status !== CollectionStatus::Confirmed) {
                    throw new DomainException('التحصيل لم يعد مؤكدًا.');
                }

                $collection->fill([
                    'status' => CollectionStatus::Reversed,
                    'reversed_at' => now(),
                    'reversed_by_user_id' => $actor->id,
                ])->save();

                $reversal->fill([
                    'status' => CollectionReversalStatus::Approved,
                    'decided_at' => now(),
                    'decided_by_user_id' => $actor->id,
                    'decision_notes' => $notes,
                ])->save();

                $this->audit->record('collection_reversal.approved', $reversal, [
                    'collection_id' => $collection->id,
                ], $actor);
                $this->audit->record('collection.reversed', $collection, [
                    'status' => $collection->status->value,
                ], $actor);
            } else {
                $reversal->fill([
                    'status' => CollectionReversalStatus::Rejected,
                    'decided_at' => now(),
                    'decided_by_user_id' => $actor->id,
                    'decision_notes' => $notes,
                ])->save();

                $this->audit->record('collection_reversal.rejected', $reversal, [
                    'collection_id' => $reversal->collection_id,
                ], $actor);
            }

            $applied = true;

            return $reversal->fresh(['collection', 'requestedBy']);
        });

        if ($applied) {
            DB::afterCommit(function () use ($result): void {
                $user = $result->requestedBy;
                if ($user === null) {
                    return;
                }
                $this->notifier->notifyUser($user, [
                    'title_ar' => 'قرار عكس التحصيل',
                    'body_ar' => 'طلب العكس #'.$result->id.' — '.$result->status->labelAr(),
                    'action_url' => route('collections.show', $result->collection_id, false),
                    'category' => 'collection',
                ]);
            });
        }

        return $result;
    }

    /**
     * @param  list<array{invoice_id: int, amount: string}>  $allocations
     */
    private function assertAllocations(Customer $customer, array $allocations, ?Collection $exclude): void
    {
        if ($allocations === []) {
            throw new DomainException('يجب تخصيص مبلغ لفاتورة واحدة على الأقل.');
        }

        $seen = [];
        foreach ($allocations as $row) {
            $invoiceId = (int) $row['invoice_id'];
            if (isset($seen[$invoiceId])) {
                throw new DomainException('لا تكرر تخصيص نفس الفاتورة.');
            }
            $seen[$invoiceId] = true;

            $amount = (float) $row['amount'];
            if ($amount <= 0) {
                throw new DomainException('مبلغ التخصيص يجب أن يكون موجبًا.');
            }

            $invoice = Invoice::query()->find($invoiceId);
            if ($invoice === null || (int) $invoice->customer_id !== (int) $customer->id) {
                throw new DomainException('فاتورة غير صالحة لهذه المنشأة.');
            }

            $unpaid = (float) $invoice->unpaidBalance();
            // When confirming a draft that already "counts" nothing (draft), unpaid is full.
            // When updating, draft allocations don't reduce unpaid — so unpaid is available.
            if ($amount > $unpaid + 1e-9) {
                throw new DomainException(
                    'مبلغ التخصيص يتجاوز المتبقي غير المسدد للفاتورة '.$invoice->document_number
                    .' (المتبقي '.Money::round2($unpaid).').'
                );
            }
        }
    }
}
