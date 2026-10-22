<?php

namespace App\Support\Quotations;

use App\Mail\QuotationApprovedMail;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\QuotationSendLog;
use App\Models\SalesRequest;
use App\Models\SalesRequestLine;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Authorization\RoleSlug;
use App\Support\Customers\CustomerContactRole;
use App\Support\Money\Money;
use App\Support\Notifications\InternalNotifier;
use App\Support\SalesRequests\LineAvailability;
use App\Support\SalesRequests\SalesRequestStatus;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class QuotationWorkflow
{
    public function __construct(
        private AuditLogger $audit,
        private InternalNotifier $notifier,
    ) {}

    public function createFromSalesRequest(SalesRequest $request, User $actor, ?string $notesAr = null, ?string $expiresAt = null): Quotation
    {
        $request->load(['lines.product', 'customer']);

        if ($request->status !== SalesRequestStatus::ReadyForPricing) {
            throw new DomainException('لا يمكن إنشاء عرض سعر إلا من طلب بيع بحالة «جاهز للتسعير».');
        }

        if ($request->lines->isEmpty()) {
            throw new DomainException('طلب البيع بلا بنود.');
        }

        $quotation = DB::transaction(function () use ($request, $actor, $notesAr, $expiresAt): Quotation {
            $quotation = Quotation::query()->create([
                'sales_request_id' => $request->id,
                'customer_id' => $request->customer_id,
                'created_by_user_id' => $actor->id,
                'version' => 1,
                'status' => QuotationStatus::Draft,
                'notes_ar' => $notesAr,
                'vat_rate' => '0.1500',
                'expires_at' => $expiresAt ?: now()->addDays(14),
            ]);

            foreach ($request->lines->values() as $index => $line) {
                $cost = $this->resolveCostForLine($line);
                $margin = '0.00';
                $price = Money::sellingPriceVatInclusive($cost, $margin);

                QuotationLine::query()->create([
                    'quotation_id' => $quotation->id,
                    'sales_request_line_id' => $line->id,
                    'product_id' => $line->product_id,
                    'product_name_ar' => $line->product?->name_ar ?? ('#'.$line->product_id),
                    'quantity' => $line->quantity,
                    'supplier_cost' => $cost,
                    'margin_amount' => $margin,
                    'selling_price_vat_inclusive' => $price,
                    'sort_order' => $index,
                ]);
            }

            $request->fill(['status' => SalesRequestStatus::QuotationLinked])->save();

            $this->audit->record('quotation.created', $quotation, [
                'sales_request_id' => $request->id,
                'customer_id' => $request->customer_id,
                'status' => $quotation->status->value,
                'version' => $quotation->version,
                'line_count' => $request->lines->count(),
            ], $actor);

            $this->audit->record('sales_request.quotation_linked', $request, [
                'quotation_id' => $quotation->id,
                'status' => SalesRequestStatus::QuotationLinked->value,
            ], $actor);

            return $quotation->fresh(['lines', 'customer', 'salesRequest']);
        });

        DB::afterCommit(function () use ($quotation): void {
            $this->notifier->notifyRole(RoleSlug::SalesManager, [
                'title_ar' => 'عرض سعر بانتظار تسعير المدير',
                'body_ar' => 'منشأة: '.($quotation->customer?->name_ar ?? '#').' — ضع الهامش ثم اعتمد للمندوب — '.$quotation->document_number,
                'action_url' => route('quotations.show', $quotation, false),
                'category' => 'quotation',
            ]);
        });

        return $quotation;
    }

    /**
     * @param  list<array{id: int, margin_amount: string}>  $lineMargins
     */
    public function updateDraftMargins(Quotation $quotation, User $actor, array $lineMargins, ?string $notesAr = null, ?string $expiresAt = null): Quotation
    {
        if (! $quotation->isDraft()) {
            throw new DomainException('لا يمكن تعديل هوامش عرض سعر ليس مسودة.');
        }

        return DB::transaction(function () use ($quotation, $actor, $lineMargins, $notesAr, $expiresAt): Quotation {
            $quotation->fill([
                'notes_ar' => $notesAr,
                'expires_at' => $expiresAt ?: $quotation->expires_at,
            ])->save();

            $byId = collect($lineMargins)->keyBy('id');

            foreach ($quotation->lines as $line) {
                if (! $byId->has($line->id)) {
                    continue;
                }

                $margin = Money::round2($byId[$line->id]['margin_amount']);
                if ((float) $margin < 0) {
                    throw new DomainException('الهامش لا يمكن أن يكون سالبًا.');
                }

                $price = Money::sellingPriceVatInclusive($line->supplier_cost, $margin);
                $line->fill([
                    'margin_amount' => $margin,
                    'selling_price_vat_inclusive' => $price,
                ])->save();
            }

            $this->audit->record('quotation.updated', $quotation, [
                'status' => $quotation->status->value,
            ], $actor);

            return $quotation->fresh(['lines', 'customer']);
        });
    }

    public function submitPrice(Quotation $quotation, User $actor): Quotation
    {
        if (! $quotation->isDraft()) {
            throw new DomainException('لا يمكن إرسال اعتماد السعر إلا من مسودة.');
        }

        $quotation->load('lines');
        $this->assertLinesPriced($quotation);

        $result = DB::transaction(function () use ($quotation, $actor): Quotation {
            $quotation->fill([
                'status' => QuotationStatus::PendingPriceApproval,
                'submitted_by_user_id' => $actor->id,
                'submitted_at' => now(),
            ])->save();

            $this->audit->record('quotation.submitted', $quotation, [
                'status' => $quotation->status->value,
            ], $actor);

            return $quotation->fresh(['lines', 'customer', 'createdBy']);
        });

        DB::afterCommit(function () use ($result): void {
            $this->notifier->notifyRole(RoleSlug::SalesManager, [
                'title_ar' => 'عرض سعر بانتظار اعتماد الهامش',
                'body_ar' => 'منشأة: '.($result->customer?->name_ar ?? '#').' — '.$result->document_number,
                'action_url' => route('quotations.show', $result, false),
                'category' => 'quotation',
            ]);
        });

        return $result;
    }

    public function approve(Quotation $quotation, User $actor, ?string $notes = null): Quotation
    {
        $applied = false;

        $result = DB::transaction(function () use ($quotation, $actor, $notes, &$applied): Quotation {
            $quotation = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();

            if ($quotation->status === QuotationStatus::Approved) {
                return $quotation->fresh(['lines', 'customer', 'createdBy', 'salesRequest']);
            }

            $fromDraft = $quotation->status === QuotationStatus::Draft;
            $fromPending = $quotation->status === QuotationStatus::PendingPriceApproval;

            if (! $fromDraft && ! $fromPending) {
                throw new DomainException('لا يمكن اعتماد عرض السعر من حالته الحالية.');
            }

            if ($quotation->isExpiredByDate()) {
                $quotation->fill(['status' => QuotationStatus::Expired])->save();
                throw new DomainException('انتهت صلاحية عرض السعر ولا يمكن اعتماده.');
            }

            $quotation->load('lines');
            $this->assertLinesPriced($quotation);

            $quotation->fill([
                'status' => QuotationStatus::Approved,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
                'decision_notes' => $notes,
            ])->save();

            $this->audit->record('quotation.approved', $quotation, [
                'status' => $quotation->status->value,
                'priced_from' => $fromDraft ? 'draft' : 'pending_price_approval',
                'total_vat_inclusive' => $quotation->totalVatInclusive(),
            ], $actor);

            $applied = true;

            return $quotation->fresh(['lines', 'customer', 'createdBy', 'decidedBy', 'salesRequest']);
        });

        if ($applied) {
            DB::afterCommit(function () use ($result): void {
                $this->notifySalesRepAfterPricing(
                    $result,
                    'عرض السعر جاهز بعد اعتماد التسعير — يمكنك إرساله للعميل أو تحويله إلى أمر بيع بعد إرفاق أمر شراء العميل.',
                );
            });
        }

        return $result;
    }

    public function reject(Quotation $quotation, User $actor, ?string $notes = null): Quotation
    {
        $applied = false;

        $result = DB::transaction(function () use ($quotation, $actor, $notes, &$applied): Quotation {
            $quotation = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();

            if ($quotation->status !== QuotationStatus::PendingPriceApproval) {
                throw new DomainException('لا يمكن رفض عرض سعر ليس بانتظار اعتماد السعر.');
            }

            $quotation->fill([
                'status' => QuotationStatus::Draft,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
                'decision_notes' => $notes,
                'submitted_by_user_id' => null,
                'submitted_at' => null,
            ])->save();

            $this->audit->record('quotation.rejected', $quotation, [
                'status' => $quotation->status->value,
            ], $actor);

            $applied = true;

            return $quotation->fresh(['lines', 'customer', 'createdBy']);
        });

        if ($applied) {
            DB::afterCommit(function () use ($result): void {
                $this->notifyRequester($result, 'رُفض اعتماد السعر — أُعيد العرض إلى مسودة.');
            });
        }

        return $result;
    }

    public function cancel(Quotation $quotation, User $actor, ?string $notes = null): Quotation
    {
        if ($quotation->status->isTerminal() || $quotation->status === QuotationStatus::Sent) {
            // Sent quotes: cancel allowed to stop further commercial use; supersede preferred for revisions.
        }

        if (in_array($quotation->status, [QuotationStatus::Cancelled, QuotationStatus::Superseded], true)) {
            throw new DomainException('عرض السعر ملغى أو مستبدل مسبقًا.');
        }

        return DB::transaction(function () use ($quotation, $actor, $notes): Quotation {
            $quotation->fill([
                'status' => QuotationStatus::Cancelled,
                'decision_notes' => $notes,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
            ])->save();

            $this->audit->record('quotation.cancelled', $quotation, [
                'status' => $quotation->status->value,
            ], $actor);

            return $quotation->fresh(['lines', 'customer']);
        });
    }

    /**
     * Create a new draft version; mark current as superseded (history preserved).
     */
    public function supersede(Quotation $quotation, User $actor): Quotation
    {
        if (! in_array($quotation->status, [QuotationStatus::Approved, QuotationStatus::Sent, QuotationStatus::Expired], true)) {
            throw new DomainException('يُسمح بالاستبدال من عرض معتمد أو مُرسل أو منتهي الصلاحية فقط.');
        }

        $quotation->load(['lines', 'salesRequest']);

        return DB::transaction(function () use ($quotation, $actor): Quotation {
            $quotation->fill(['status' => QuotationStatus::Superseded])->save();

            $this->audit->record('quotation.superseded', $quotation, [
                'status' => $quotation->status->value,
            ], $actor);

            $new = Quotation::query()->create([
                'sales_request_id' => $quotation->sales_request_id,
                'customer_id' => $quotation->customer_id,
                'created_by_user_id' => $actor->id,
                'version' => $quotation->version + 1,
                'supersedes_quotation_id' => $quotation->id,
                'status' => QuotationStatus::Draft,
                'notes_ar' => $quotation->notes_ar,
                'vat_rate' => '0.1500',
                'expires_at' => now()->addDays(14),
            ]);

            foreach ($quotation->lines as $index => $line) {
                QuotationLine::query()->create([
                    'quotation_id' => $new->id,
                    'sales_request_line_id' => $line->sales_request_line_id,
                    'product_id' => $line->product_id,
                    'product_name_ar' => $line->product_name_ar,
                    'quantity' => $line->quantity,
                    'supplier_cost' => $line->supplier_cost,
                    'margin_amount' => $line->margin_amount,
                    'selling_price_vat_inclusive' => $line->selling_price_vat_inclusive,
                    'sort_order' => $index,
                ]);
            }

            $this->audit->record('quotation.created', $new, [
                'supersedes_quotation_id' => $quotation->id,
                'version' => $new->version,
                'status' => $new->status->value,
            ], $actor);

            return $new->fresh(['lines', 'customer', 'salesRequest']);
        });
    }

    public function send(Quotation $quotation, User $actor): Quotation
    {
        $quotation->load(['lines', 'customer.contacts']);

        if ($quotation->status === QuotationStatus::Sent) {
            throw new DomainException('تم إرسال عرض السعر مسبقًا. للاستبدال أنشئ نسخة جديدة.');
        }

        if (! $quotation->status->canSend()) {
            throw new DomainException('لا يمكن إرسال عرض سعر غير معتمد.');
        }

        if ($quotation->isExpiredByDate()) {
            DB::transaction(function () use ($quotation): void {
                $quotation->fill(['status' => QuotationStatus::Expired])->save();
            });
            throw new DomainException('انتهت صلاحية عرض السعر ولا يمكن إرساله.');
        }

        $recipient = $this->resolveRecipientEmail($quotation->customer);
        if ($recipient === null) {
            throw new DomainException('لا يوجد بريد لجهة الاتصال التجارية أو المحاسبة على سجل المنشأة.');
        }

        try {
            Mail::to($recipient)->send(new QuotationApprovedMail($quotation));

            $result = DB::transaction(function () use ($quotation, $actor, $recipient): Quotation {
                $quotation->fill([
                    'status' => QuotationStatus::Sent,
                    'sent_at' => now(),
                    'sent_to_email' => $recipient,
                ])->save();

                QuotationSendLog::query()->create([
                    'quotation_id' => $quotation->id,
                    'actor_user_id' => $actor->id,
                    'recipient_email' => $recipient,
                    'status' => QuotationSendLog::STATUS_SENT,
                    'created_at' => now(),
                ]);

                $this->audit->record('quotation.sent', $quotation, [
                    'status' => $quotation->status->value,
                    'recipient_email' => $recipient,
                ], $actor);

                return $quotation->fresh(['lines', 'customer', 'sendLogs']);
            });

            DB::afterCommit(function () use ($result, $recipient): void {
                $this->notifyRequester($result, 'أُرسل عرض السعر بالبريد إلى '.$recipient);
            });

            return $result;
        } catch (Throwable $e) {
            QuotationSendLog::query()->create([
                'quotation_id' => $quotation->id,
                'actor_user_id' => $actor->id,
                'recipient_email' => $recipient,
                'status' => QuotationSendLog::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'created_at' => now(),
            ]);

            $this->audit->record('quotation.send_failed', $quotation, [
                'recipient_email' => $recipient,
                'error' => mb_substr($e->getMessage(), 0, 200),
            ], $actor);

            $this->notifier->notifyUser($actor, [
                'title_ar' => 'فشل إرسال عرض السعر',
                'body_ar' => 'تعذر إرسال العرض '.$quotation->document_number.' إلى '.$recipient,
                'action_url' => route('quotations.show', $quotation, false),
                'category' => 'quotation',
            ]);

            throw new DomainException('فشل إرسال البريد. لم يُعلَّم العرض كـ«مُرسل». راجع سجل الإرسال.');
        }
    }

    public function resolveCostForLine(SalesRequestLine $line): string
    {
        $line->loadMissing('product');

        if ($line->availability === LineAvailability::NeedsProcurement) {
            if ($line->supplier_cost === null || (float) $line->supplier_cost < 0) {
                throw new DomainException(
                    'بند يحتاج توريدًا بلا تكلفة مورد مسجّلة: '.($line->product?->name_ar ?? '#'.$line->product_id)
                );
            }

            return Money::round2($line->supplier_cost);
        }

        $purchase = $line->product?->purchase_cost;
        if ($purchase === null || $purchase === '') {
            throw new DomainException(
                'لا توجد تكلفة شراء للمنتج المتوفر: '.($line->product?->name_ar ?? '#'.$line->product_id)
                .' — حدّث تكلفة الشراء قبل التسعير.'
            );
        }

        return Money::round2($purchase);
    }

    /**
     * Recipient: account contact email first, then accounting contact.
     */
    public function resolveRecipientEmail(?Customer $customer): ?string
    {
        if ($customer === null) {
            return null;
        }

        $customer->loadMissing('contacts');

        $account = $customer->contacts->first(
            fn (CustomerContact $c) => $c->role === CustomerContactRole::Account && filled($c->email)
        );
        if ($account !== null) {
            return (string) $account->email;
        }

        $accounting = $customer->contacts->first(
            fn (CustomerContact $c) => $c->role === CustomerContactRole::Accounting && filled($c->email)
        );

        return $accounting?->email ? (string) $accounting->email : null;
    }

    private function assertLinesPriced(Quotation $quotation): void
    {
        if ($quotation->lines->isEmpty()) {
            throw new DomainException('عرض السعر بلا بنود.');
        }

        foreach ($quotation->lines as $line) {
            if ((float) $line->supplier_cost < 0) {
                throw new DomainException('تكلفة غير صالحة في بند: '.$line->product_name_ar);
            }
            if ((float) $line->margin_amount < 0) {
                throw new DomainException('هامش سالب غير مسموح: '.$line->product_name_ar);
            }

            $expected = Money::sellingPriceVatInclusive($line->supplier_cost, $line->margin_amount);
            if ($expected !== Money::round2($line->selling_price_vat_inclusive)) {
                throw new DomainException('سعر البيع غير متسق مع التكلفة + الهامش: '.$line->product_name_ar);
            }
        }
    }

    private function notifyRequester(Quotation $quotation, string $body): void
    {
        $this->notifySalesRepAfterPricing($quotation, $body);
    }

    /**
     * After SM prices/approves, notify the owning Sales Rep (sales-request creator preferred).
     */
    private function notifySalesRepAfterPricing(Quotation $quotation, string $body): void
    {
        $quotation->loadMissing(['salesRequest.createdBy', 'createdBy', 'submittedBy']);

        $user = $quotation->salesRequest?->createdBy
            ?? $quotation->createdBy
            ?? $quotation->submittedBy;

        if ($user === null) {
            return;
        }

        $this->notifier->notifyUser($user, [
            'title_ar' => 'عرض السعر جاهز للمندوب',
            'body_ar' => $body.' — '.$quotation->document_number,
            'action_url' => route('quotations.show', $quotation, false),
            'category' => 'quotation',
        ]);
    }
}
