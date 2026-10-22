<?php

namespace App\Support\Credit;

use App\Models\CreditLimitRequest;
use App\Models\Customer;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Authorization\RoleSlug;
use App\Support\Notifications\InternalNotifier;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Confirmed sequence: Rep request → Accounts review → Sales Manager final.
 * Requested values never become approved until final SM approval.
 */
class CreditLimitWorkflow
{
    public function __construct(
        private AuditLogger $audit,
        private InternalNotifier $notifier,
    ) {}

    /**
     * @param  array{requested_credit_limit: string|float, requested_payment_term?: string|null, request_notes?: string|null}  $data
     */
    public function submitRequest(Customer $customer, User $actor, array $data): CreditLimitRequest
    {
        if ($customer->creditLimitRequests()->whereIn('status', [
            CreditLimitRequestStatus::PendingReview->value,
            CreditLimitRequestStatus::PendingFinal->value,
        ])->exists()) {
            throw new DomainException('يوجد طلب حد ائتمان مفتوح لهذا العميل بالفعل.');
        }

        return DB::transaction(function () use ($customer, $actor, $data): CreditLimitRequest {
            $request = CreditLimitRequest::query()->create([
                'customer_id' => $customer->id,
                'requested_by_user_id' => $actor->id,
                'requested_credit_limit' => $data['requested_credit_limit'],
                'requested_payment_term' => $data['requested_payment_term'] ?? null,
                'status' => CreditLimitRequestStatus::PendingReview,
                'request_notes' => $data['request_notes'] ?? null,
            ]);

            $this->audit->record('credit_limit.requested', $request, [
                'customer_id' => $customer->id,
                'requested_credit_limit' => (string) $request->requested_credit_limit,
                'requested_payment_term' => $request->requested_payment_term,
                'status' => $request->status->value,
            ]);

            $this->notifier->notifyRole(RoleSlug::Accounts, [
                'title_ar' => 'طلب مراجعة حد ائتمان',
                'body_ar' => 'عميل: '.$customer->name_ar.' — الحد المطلوب: '.$request->requested_credit_limit,
                'action_url' => route('credit-requests.show', $request, false),
                'category' => 'credit_limit',
            ]);

            return $request;
        });
    }

    public function forwardFromAccounts(CreditLimitRequest $request, User $actor, ?string $notes = null): CreditLimitRequest
    {
        $this->assertStatus($request, CreditLimitRequestStatus::PendingReview);

        return DB::transaction(function () use ($request, $actor, $notes): CreditLimitRequest {
            $request->fill([
                'status' => CreditLimitRequestStatus::PendingFinal,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ])->save();

            $this->audit->record('credit_limit.review_forwarded', $request, [
                'customer_id' => $request->customer_id,
                'status' => $request->status->value,
                'requested_credit_limit' => (string) $request->requested_credit_limit,
            ]);

            $this->notifier->notifyRole(RoleSlug::SalesManager, [
                'title_ar' => 'طلب اعتماد نهائي لحد ائتمان',
                'body_ar' => 'عميل: '.$request->customer->name_ar.' — بانتظار اعتمادك النهائي.',
                'action_url' => route('credit-requests.show', $request, false),
                'category' => 'credit_limit',
            ]);

            return $request->fresh(['customer', 'requestedBy', 'reviewedBy']);
        });
    }

    public function rejectAtReview(CreditLimitRequest $request, User $actor, ?string $notes = null): CreditLimitRequest
    {
        $this->assertStatus($request, CreditLimitRequestStatus::PendingReview);

        return DB::transaction(function () use ($request, $actor, $notes): CreditLimitRequest {
            $request->fill([
                'status' => CreditLimitRequestStatus::Rejected,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
                'decision_notes' => $notes,
            ])->save();

            $this->audit->record('credit_limit.review_rejected', $request, [
                'customer_id' => $request->customer_id,
                'status' => $request->status->value,
            ]);

            $this->notifyRequester($request, 'رُفض طلب حد الائتمان في مرحلة المراجعة.');

            return $request->fresh(['customer', 'requestedBy', 'reviewedBy']);
        });
    }

    public function approveFinal(CreditLimitRequest $request, User $actor, ?string $notes = null): CreditLimitRequest
    {
        $this->assertStatus($request, CreditLimitRequestStatus::PendingFinal);

        return DB::transaction(function () use ($request, $actor, $notes): CreditLimitRequest {
            $customer = Customer::query()->whereKey($request->customer_id)->lockForUpdate()->firstOrFail();

            $before = $customer->only(['approved_credit_limit', 'approved_payment_term']);

            $customer->forceFill([
                'approved_credit_limit' => $request->requested_credit_limit,
                'approved_payment_term' => $request->requested_payment_term,
            ])->save();

            $request->fill([
                'status' => CreditLimitRequestStatus::Approved,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
                'decision_notes' => $notes,
            ])->save();

            $changes = $this->audit->changes(
                $before,
                $customer->only(['approved_credit_limit', 'approved_payment_term']),
                ['approved_credit_limit', 'approved_payment_term'],
            );

            $this->audit->record('credit_limit.approved', $request, [
                'customer_id' => $customer->id,
                'status' => $request->status->value,
                'approved_credit_limit' => (string) $customer->approved_credit_limit,
                'approved_payment_term' => $customer->approved_payment_term,
                ...$changes,
            ]);

            $this->notifyRequester($request, 'اعتُمد حد الائتمان وشروط الدفع المطلوبة.');

            return $request->fresh(['customer', 'requestedBy', 'reviewedBy', 'decidedBy']);
        });
    }

    public function rejectFinal(CreditLimitRequest $request, User $actor, ?string $notes = null): CreditLimitRequest
    {
        $this->assertStatus($request, CreditLimitRequestStatus::PendingFinal);

        return DB::transaction(function () use ($request, $actor, $notes): CreditLimitRequest {
            $request->fill([
                'status' => CreditLimitRequestStatus::Rejected,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
                'decision_notes' => $notes,
            ])->save();

            // Explicit: customer approved_* columns are NOT updated on rejection.
            $this->audit->record('credit_limit.final_rejected', $request, [
                'customer_id' => $request->customer_id,
                'status' => $request->status->value,
                'requested_credit_limit' => (string) $request->requested_credit_limit,
                'customer_approved_credit_limit_unchanged' => true,
            ]);

            $this->notifyRequester($request, 'رُفض طلب حد الائتمان في الاعتماد النهائي. القيم المعتمدة السابقة لم تتغير.');

            return $request->fresh(['customer', 'requestedBy', 'reviewedBy', 'decidedBy']);
        });
    }

    private function assertStatus(CreditLimitRequest $request, CreditLimitRequestStatus $expected): void
    {
        if ($request->status !== $expected) {
            throw new DomainException('انتقال حالة طلب حد الائتمان غير مسموح من الحالة الحالية.');
        }
    }

    private function notifyRequester(CreditLimitRequest $request, string $body): void
    {
        $requester = $request->requestedBy;
        if ($requester === null) {
            return;
        }

        $this->notifier->notifyUser($requester, [
            'title_ar' => 'تحديث طلب حد الائتمان',
            'body_ar' => $body.' ('.$request->customer->name_ar.')',
            'action_url' => route('credit-requests.show', $request, false),
            'category' => 'credit_limit',
        ]);
    }
}
