<?php

namespace App\Support\SalesRequests;

use App\Models\Customer;
use App\Models\ProcurementTask;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesRequest;
use App\Models\SalesRequestLine;
use App\Models\User;
use App\Support\Attachments\AttachmentService;
use App\Support\Audit\AuditLogger;
use App\Support\Authorization\RoleSlug;
use App\Support\Notifications\InternalNotifier;
use App\Support\Procurement\ProcurementTaskStatus;
use App\Support\Quotations\QuotationWorkflow;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

class SalesRequestWorkflow
{
    public function __construct(
        private AuditLogger $audit,
        private InternalNotifier $notifier,
        private QuotationWorkflow $quotations,
        private AttachmentService $attachments,
    ) {}

    /**
     * @param  array{
     *     customer_id: int,
     *     origin_visit_id?: int|null,
     *     notes_ar?: string|null,
     *     lines: list<array{product_id: int, quantity: string, availability: string}>
     * }  $payload
     */
    public function create(User $actor, array $payload): SalesRequest
    {
        $customer = Customer::query()->findOrFail($payload['customer_id']);
        $this->assertHasLines($payload['lines']);

        $request = DB::transaction(function () use ($actor, $payload, $customer): SalesRequest {
            $request = SalesRequest::query()->create([
                'customer_id' => $customer->id,
                'origin_visit_id' => $payload['origin_visit_id'] ?? null,
                'created_by_user_id' => $actor->id,
                'status' => SalesRequestStatus::Draft,
                'notes_ar' => $payload['notes_ar'] ?? null,
            ]);

            $this->syncLines($request, $payload['lines']);

            $this->audit->record('sales_request.created', $request, [
                'customer_id' => $customer->id,
                'customer_name_ar' => $customer->name_ar,
                'origin_visit_id' => $request->origin_visit_id,
                'status' => $request->status->value,
                'line_count' => count($payload['lines']),
            ], $actor);

            return $request->fresh(['customer', 'lines.product']);
        });

        return $request;
    }

    /**
     * @param  array{
     *     customer_id: int,
     *     notes_ar?: string|null,
     *     lines: list<array{product_id: int, quantity: string, availability: string}>
     * }  $payload
     */
    public function update(SalesRequest $request, User $actor, array $payload): SalesRequest
    {
        if (! $request->isDraft()) {
            throw new DomainException('لا يمكن تعديل طلب بيع ليس في حالة مسودة.');
        }

        $this->assertHasLines($payload['lines']);
        $customer = Customer::query()->findOrFail($payload['customer_id']);

        return DB::transaction(function () use ($request, $actor, $payload, $customer): SalesRequest {
            $request->fill([
                'customer_id' => $customer->id,
                'notes_ar' => $payload['notes_ar'] ?? null,
            ])->save();

            $request->lines()->delete();
            $this->syncLines($request, $payload['lines']);

            $this->audit->record('sales_request.updated', $request, [
                'customer_id' => $customer->id,
                'status' => $request->status->value,
                'line_count' => count($payload['lines']),
            ], $actor);

            return $request->fresh(['customer', 'lines.product']);
        });
    }

    public function submit(SalesRequest $request, User $actor): SalesRequest
    {
        if (! $request->isDraft()) {
            throw new DomainException('لا يمكن إرسال طلب بيع ليس في حالة مسودة.');
        }

        $request->load('lines.product');
        if ($request->lines->isEmpty()) {
            throw new DomainException('يجب إضافة بند واحد على الأقل قبل الإرسال.');
        }

        $result = DB::transaction(function () use ($request, $actor): SalesRequest {
            $needs = $request->lines->filter(fn (SalesRequestLine $line) => $line->needsProcurement());

            if ($needs->isNotEmpty()) {
                foreach ($needs as $line) {
                    ProcurementTask::query()->create([
                        'sales_request_id' => $request->id,
                        'sales_request_line_id' => $line->id,
                        'product_id' => $line->product_id,
                        'status' => ProcurementTaskStatus::Open,
                    ]);
                }

                $request->fill([
                    'status' => SalesRequestStatus::AwaitingProcurement,
                    'submitted_at' => now(),
                ])->save();

                $this->audit->record('sales_request.submitted', $request, [
                    'status' => $request->status->value,
                    'procurement_tasks' => $needs->count(),
                ], $actor);

                $this->audit->record('procurement_task.created', $request, [
                    'sales_request_id' => $request->id,
                    'task_count' => $needs->count(),
                ], $actor);
            } else {
                $request->fill([
                    'status' => SalesRequestStatus::ReadyForPricing,
                    'submitted_at' => now(),
                ])->save();

                $this->audit->record('sales_request.submitted', $request, [
                    'status' => $request->status->value,
                    'procurement_tasks' => 0,
                ], $actor);
            }

            return $request->fresh(['customer', 'lines.product', 'procurementTasks', 'createdBy']);
        });

        if ($result->status === SalesRequestStatus::AwaitingProcurement) {
            DB::afterCommit(function () use ($result): void {
                $this->notifier->notifyRole(RoleSlug::Procurement, [
                    'title_ar' => 'مهمة مشتريات من طلب بيع',
                    'body_ar' => 'منشأة: '.($result->customer?->name_ar ?? '#').' — بانتظار تسجيل تكلفة المورد.',
                    'action_url' => route('procurement.tasks.index', absolute: false),
                    'category' => 'procurement',
                ]);
            });
        }

        // Available-only path: open draft quotation for SM margin pricing immediately.
        if ($result->status === SalesRequestStatus::ReadyForPricing) {
            DB::afterCommit(function () use ($result, $actor): void {
                $this->openQuotationDraftForSalesManager($result, $actor);
            });
        }

        return $result;
    }

    public function cancel(SalesRequest $request, User $actor): SalesRequest
    {
        if ($request->status->isTerminal()) {
            throw new DomainException('الطلب ملغى مسبقًا.');
        }

        if ($request->status === SalesRequestStatus::QuotationLinked) {
            throw new DomainException('لا يمكن إلغاء طلب بيع مربوط بعرض سعر — ألغِ أو استبدل العرض أولاً.');
        }

        return DB::transaction(function () use ($request, $actor): SalesRequest {
            $request->fill([
                'status' => SalesRequestStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->id,
            ])->save();

            ProcurementTask::query()
                ->where('sales_request_id', $request->id)
                ->where('status', ProcurementTaskStatus::Open)
                ->update([
                    'status' => ProcurementTaskStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancelled_by_user_id' => $actor->id,
                ]);

            $this->audit->record('sales_request.cancelled', $request, [
                'status' => $request->status->value,
            ], $actor);

            return $request->fresh(['customer', 'lines.product', 'procurementTasks']);
        });
    }

    /**
     * @param  array{
     *     supplier_cost: string,
     *     supplier_name_ar?: string|null,
     *     notes_ar?: string|null,
     *     supplier_quote_pdf: \Illuminate\Http\UploadedFile
     * }  $payload
     */
    public function recordSupplierCost(ProcurementTask $task, User $actor, array $payload): ProcurementTask
    {
        $applied = false;

        $result = DB::transaction(function () use ($task, $actor, $payload, &$applied): ProcurementTask {
            $task = ProcurementTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if ($task->status === ProcurementTaskStatus::CostRecorded) {
                return $task->fresh(['product', 'line', 'salesRequest.customer', 'salesRequest.createdBy', 'attachments']);
            }

            if ($task->status !== ProcurementTaskStatus::Open) {
                throw new DomainException('لا يمكن تسجيل تكلفة لمهمة مشتريات غير مفتوحة.');
            }

            if (! isset($payload['supplier_quote_pdf'])) {
                throw new DomainException('يجب إرفاق عرض سعر المورد بصيغة PDF.');
            }

            $cost = number_format((float) $payload['supplier_cost'], 2, '.', '');

            $task->fill([
                'status' => ProcurementTaskStatus::CostRecorded,
                'supplier_cost' => $cost,
                'supplier_name_ar' => $payload['supplier_name_ar'] ?? null,
                'notes_ar' => $payload['notes_ar'] ?? null,
                'recorded_by_user_id' => $actor->id,
                'cost_recorded_at' => now(),
            ])->save();

            SalesRequestLine::query()->whereKey($task->sales_request_line_id)->update([
                'supplier_cost' => $cost,
            ]);

            $this->attachments->store($task, $payload['supplier_quote_pdf'], $actor);

            $this->audit->record('procurement_task.cost_recorded', $task, [
                'sales_request_id' => $task->sales_request_id,
                'product_id' => $task->product_id,
                'supplier_cost' => $cost,
                'status' => $task->status->value,
                'supplier_quote_pdf' => true,
            ], $actor);

            $request = SalesRequest::query()->whereKey($task->sales_request_id)->lockForUpdate()->firstOrFail();
            $this->refreshRequestAfterProcurement($request, $actor);

            $applied = true;

            return $task->fresh(['product', 'line', 'salesRequest.customer', 'salesRequest.createdBy', 'recordedBy', 'attachments']);
        });

        if ($applied) {
            DB::afterCommit(function () use ($result, $actor): void {
                $request = $result->salesRequest?->fresh(['customer', 'createdBy', 'lines.product']);
                if ($request === null) {
                    return;
                }

                // When all procurement costs are in → auto-open quotation for SM (PM1).
                if ($request->status === SalesRequestStatus::ReadyForPricing) {
                    $quotation = $this->openQuotationDraftForSalesManager($request, $actor);
                    $this->notifyRepCostRecorded($request, $result, $quotation);
                }
            });
        }

        return $result;
    }

    public function cancelProcurementTask(ProcurementTask $task, User $actor): ProcurementTask
    {
        return DB::transaction(function () use ($task, $actor): ProcurementTask {
            $task = ProcurementTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if ($task->status === ProcurementTaskStatus::Cancelled) {
                return $task->fresh(['product', 'line', 'salesRequest']);
            }

            if ($task->status !== ProcurementTaskStatus::Open) {
                throw new DomainException('لا يمكن إلغاء مهمة مشتريات ليست مفتوحة.');
            }

            $task->fill([
                'status' => ProcurementTaskStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->id,
            ])->save();

            $this->audit->record('procurement_task.cancelled', $task, [
                'sales_request_id' => $task->sales_request_id,
                'status' => $task->status->value,
            ], $actor);

            return $task->fresh(['product', 'line', 'salesRequest']);
        });
    }

    private function refreshRequestAfterProcurement(SalesRequest $request, User $actor): void
    {
        if ($request->status === SalesRequestStatus::Cancelled) {
            return;
        }

        $openCount = ProcurementTask::query()
            ->where('sales_request_id', $request->id)
            ->where('status', ProcurementTaskStatus::Open)
            ->count();

        if ($openCount > 0) {
            return;
        }

        $hasCost = ProcurementTask::query()
            ->where('sales_request_id', $request->id)
            ->where('status', ProcurementTaskStatus::CostRecorded)
            ->exists();

        if (! $hasCost) {
            return;
        }

        if ($request->status === SalesRequestStatus::ReadyForPricing) {
            return;
        }

        $request->fill([
            'status' => SalesRequestStatus::ReadyForPricing,
        ])->save();

        $this->audit->record('sales_request.ready_for_pricing', $request, [
            'status' => $request->status->value,
        ], $actor);
    }

    /**
     * Create a draft quotation for Sales Manager margin pricing (PM1/PM2).
     * Idempotent: if a quotation already exists for the request, return it.
     */
    private function openQuotationDraftForSalesManager(SalesRequest $request, User $actor): ?Quotation
    {
        $existing = Quotation::query()
            ->where('sales_request_id', $request->id)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $request = $request->fresh(['lines.product', 'customer']);
        if ($request === null || $request->status !== SalesRequestStatus::ReadyForPricing) {
            return null;
        }

        try {
            return $this->quotations->createFromSalesRequest($request, $actor);
        } catch (DomainException|Throwable $e) {
            report($e);

            $this->notifier->notifyRole(RoleSlug::SalesManager, [
                'title_ar' => 'تعذّر فتح عرض سعر تلقائيًا',
                'body_ar' => 'طلب بيع #'.$request->id.' جاهز للتسعير لكن فشل إنشاء المسودة: '.$e->getMessage(),
                'action_url' => route('sales-requests.show', $request, false),
                'category' => 'sales_request',
            ]);

            return null;
        }
    }

    private function notifyRepCostRecorded(SalesRequest $request, ProcurementTask $task, ?Quotation $quotation): void
    {
        if ($request->createdBy === null) {
            return;
        }

        $body = 'تم تسجيل تكلفة المورد لطلب البيع #'.$request->id
            .' — منتج: '.($task->product?->name_ar ?? '#')
            .' — بانتظار تسعير مدير المبيعات.';

        $url = $quotation !== null
            ? route('quotations.show', $quotation, false)
            : route('sales-requests.show', $request, false);

        $this->notifier->notifyUser($request->createdBy, [
            'title_ar' => 'تكلفة مورد مسجّلة — بانتظار المدير',
            'body_ar' => $body,
            'action_url' => $url,
            'category' => 'sales_request',
        ]);
    }

    /**
     * @param  list<array{product_id: int, quantity: string, availability: string}>  $lines
     */
    private function syncLines(SalesRequest $request, array $lines): void
    {
        foreach (array_values($lines) as $index => $line) {
            Product::query()->findOrFail($line['product_id']);

            SalesRequestLine::query()->create([
                'sales_request_id' => $request->id,
                'product_id' => (int) $line['product_id'],
                'quantity' => number_format((float) $line['quantity'], 3, '.', ''),
                'availability' => $line['availability'],
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  list<array{product_id: int, quantity: string, availability: string}>  $lines
     */
    private function assertHasLines(array $lines): void
    {
        if ($lines === []) {
            throw new DomainException('يجب إضافة بند واحد على الأقل.');
        }
    }
}
