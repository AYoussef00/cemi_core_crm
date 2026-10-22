<?php

namespace App\Support\Finance;

use App\Models\Invoice;
use App\Models\InvoiceNote;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Authorization\RoleSlug;
use App\Support\Money\Money;
use App\Support\Notifications\InternalNotifier;
use DomainException;
use Illuminate\Support\Facades\DB;

class InvoiceNoteWorkflow
{
    public function __construct(
        private AuditLogger $audit,
        private InternalNotifier $notifier,
    ) {}

    /**
     * @param  array{
     *     invoice_id: int,
     *     type: string,
     *     amount: string,
     *     reason_ar?: string|null
     * }  $payload
     */
    public function createDraft(User $actor, array $payload): InvoiceNote
    {
        $invoice = Invoice::query()->findOrFail($payload['invoice_id']);
        $type = InvoiceNoteType::from($payload['type']);
        $amount = (float) $payload['amount'];

        if ($amount <= 0) {
            throw new DomainException('مبلغ الإشعار يجب أن يكون موجبًا.');
        }

        if ($type === InvoiceNoteType::Credit && $amount > (float) $invoice->unpaidBalance() + 1e-9) {
            throw new DomainException('الإشعار الدائن لا يتجاوز المتبقي غير المسدد للفاتورة.');
        }

        return DB::transaction(function () use ($actor, $payload, $invoice, $type, $amount): InvoiceNote {
            $note = InvoiceNote::query()->create([
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'created_by_user_id' => $actor->id,
                'type' => $type,
                'status' => InvoiceNoteStatus::Draft,
                'amount' => Money::round2($amount),
                'reason_ar' => $payload['reason_ar'] ?? null,
            ]);

            $this->audit->record('invoice_note.created', $note, [
                'type' => $type->value,
                'amount' => (string) $note->amount,
            ], $actor);

            return $note->fresh(['invoice', 'customer']);
        });
    }

    public function submit(InvoiceNote $note, User $actor): InvoiceNote
    {
        if ($note->status !== InvoiceNoteStatus::Draft) {
            throw new DomainException('الإرسال متاح للمسودة فقط.');
        }

        $result = DB::transaction(function () use ($note, $actor): InvoiceNote {
            $note->fill([
                'status' => InvoiceNoteStatus::PendingApproval,
                'submitted_at' => now(),
            ])->save();

            $this->audit->record('invoice_note.submitted', $note, [
                'status' => $note->status->value,
            ], $actor);

            return $note->fresh(['invoice', 'createdBy']);
        });

        DB::afterCommit(function () use ($result): void {
            $this->notifier->notifyRole(RoleSlug::SalesManager, [
                'title_ar' => 'إشعار دائن/مدين بانتظار الاعتماد',
                'body_ar' => $result->type->labelAr().' #'.$result->id.' — فاتورة '.doc_no('INV', $result->invoice_id),
                'action_url' => route('invoice-notes.show', $result, false),
                'category' => 'invoice_note',
            ]);
        });

        return $result;
    }

    public function decide(InvoiceNote $note, User $actor, string $decision, ?string $notes = null): InvoiceNote
    {
        $applied = false;

        $result = DB::transaction(function () use ($note, $actor, $decision, $notes, &$applied): InvoiceNote {
            $note = InvoiceNote::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();

            if ($note->status !== InvoiceNoteStatus::PendingApproval) {
                throw new DomainException('الإشعار ليس بانتظار الاعتماد.');
            }

            if ((int) $note->created_by_user_id === (int) $actor->id) {
                throw new DomainException('لا يمكن اعتماد إشعارك الذي أنشأته.');
            }

            if ($decision === 'approve') {
                if ($note->type === InvoiceNoteType::Credit) {
                    $invoice = Invoice::query()->findOrFail($note->invoice_id);
                    // Unpaid does not yet include this note; ensure credit ≤ current unpaid.
                    if ((float) $note->amount > (float) $invoice->unpaidBalance() + 1e-9) {
                        throw new DomainException('الإشعار الدائن يتجاوز المتبقي غير المسدد عند الاعتماد.');
                    }
                }

                $note->fill([
                    'status' => InvoiceNoteStatus::Approved,
                    'decided_at' => now(),
                    'decided_by_user_id' => $actor->id,
                    'decision_notes' => $notes,
                ])->save();

                $this->audit->record('invoice_note.approved', $note, [
                    'type' => $note->type->value,
                    'amount' => (string) $note->amount,
                ], $actor);
            } else {
                $note->fill([
                    'status' => InvoiceNoteStatus::Rejected,
                    'decided_at' => now(),
                    'decided_by_user_id' => $actor->id,
                    'decision_notes' => $notes,
                ])->save();

                $this->audit->record('invoice_note.rejected', $note, [
                    'type' => $note->type->value,
                ], $actor);
            }

            $applied = true;

            return $note->fresh(['invoice', 'createdBy']);
        });

        if ($applied) {
            DB::afterCommit(function () use ($result): void {
                $user = $result->createdBy;
                if ($user === null) {
                    return;
                }
                $this->notifier->notifyUser($user, [
                    'title_ar' => 'قرار إشعار دائن/مدين',
                    'body_ar' => $result->type->labelAr().' #'.$result->id.' — '.$result->status->labelAr(),
                    'action_url' => route('invoice-notes.show', $result, false),
                    'category' => 'invoice_note',
                ]);
            });
        }

        return $result;
    }
}
