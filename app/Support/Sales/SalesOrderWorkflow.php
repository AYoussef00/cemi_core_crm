<?php

namespace App\Support\Sales;

use App\Models\CustomerPurchaseOrder;
use App\Models\CustomerPurchaseOrderLine;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Authorization\RoleSlug;
use App\Support\Credit\CreditExposure;
use App\Support\Inventory\InventoryService;
use App\Support\Notifications\InternalNotifier;
use App\Support\Procurement\SupplierPurchaseOrderWorkflow;
use App\Support\Warehouse\DeliveryStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

class SalesOrderWorkflow
{
    public function __construct(
        private AuditLogger $audit,
        private InternalNotifier $notifier,
        private InventoryService $inventory,
        private CreditExposure $credit,
        private SupplierPurchaseOrderWorkflow $supplierPos,
    ) {}

    /**
     * @param  array{
     *     customer_purchase_order_id: int,
     *     notes_ar?: string|null,
     *     lines: list<array{customer_purchase_order_line_id: int, quantity: string}>
     * }  $payload
     */
    public function createDraft(User $actor, array $payload): SalesOrder
    {
        $po = CustomerPurchaseOrder::query()
            ->with(['deal', 'lines', 'customer'])
            ->findOrFail($payload['customer_purchase_order_id']);

        if (! $po->isOpen()) {
            throw new DomainException('لا يمكن إنشاء أمر بيع على أمر شراء ملغى.');
        }

        if ($po->deal === null) {
            throw new DomainException('أمر الشراء بلا صفقة مرتبطة (ORG3).');
        }

        $this->assertLinesPayload($po, $payload['lines'], null);

        return DB::transaction(function () use ($actor, $payload, $po): SalesOrder {
            $order = SalesOrder::query()->create([
                'customer_id' => $po->customer_id,
                'customer_purchase_order_id' => $po->id,
                'deal_id' => $po->deal->id,
                'created_by_user_id' => $actor->id,
                'status' => SalesOrderStatus::Draft,
                'notes_ar' => $payload['notes_ar'] ?? null,
            ]);

            $this->syncLines($order, $po, $payload['lines']);

            $this->audit->record('sales_order.created', $order, [
                'customer_purchase_order_id' => $po->id,
                'deal_id' => $po->deal->id,
                'status' => $order->status->value,
                'line_count' => count($payload['lines']),
            ], $actor);

            return $order->fresh(['customer', 'customerPurchaseOrder', 'deal', 'lines.product']);
        });
    }

    /**
     * @param  array{
     *     notes_ar?: string|null,
     *     lines: list<array{customer_purchase_order_line_id: int, quantity: string}>
     * }  $payload
     */
    public function updateDraft(SalesOrder $order, User $actor, array $payload): SalesOrder
    {
        if (! $order->isDraft()) {
            throw new DomainException('لا يمكن تعديل أمر بيع ليس مسودة.');
        }

        $po = CustomerPurchaseOrder::query()
            ->with('lines')
            ->findOrFail($order->customer_purchase_order_id);

        $this->assertLinesPayload($po, $payload['lines'], $order);

        return DB::transaction(function () use ($order, $actor, $payload, $po): SalesOrder {
            $order->fill(['notes_ar' => $payload['notes_ar'] ?? null])->save();
            $order->lines()->delete();
            $this->syncLines($order, $po, $payload['lines']);

            $this->audit->record('sales_order.updated', $order, [
                'status' => $order->status->value,
            ], $actor);

            return $order->fresh(['customer', 'lines.product', 'deal']);
        });
    }

    public function submit(SalesOrder $order, User $actor): SalesOrder
    {
        if (! $order->isDraft()) {
            throw new DomainException('لا يمكن إرسال أمر بيع ليس مسودة.');
        }

        $blocking = $this->supplierPos->blockingSupplyPoForOrder($order);
        if ($blocking !== null) {
            throw new DomainException(
                'أمر البيع بانتظار توريد المشتريات وأول استلام في المستودع (أمر مورد #'.$blocking->id.'). '
                .'بعد أي استلام جزئي يمكن الإرسال والتوريد للكمية الواصلة.'
            );
        }

        $order->load('lines');
        if ($order->lines->isEmpty()) {
            throw new DomainException('يجب إضافة بنود قبل الإرسال.');
        }

        $result = DB::transaction(function () use ($order, $actor): SalesOrder {
            $order->fill([
                'status' => SalesOrderStatus::PendingApproval,
                'submitted_at' => now(),
                'submitted_by_user_id' => $actor->id,
            ])->save();

            $this->audit->record('sales_order.submitted', $order, [
                'status' => $order->status->value,
            ], $actor);

            return $order->fresh(['customer', 'lines.product', 'createdBy']);
        });

        DB::afterCommit(function () use ($result): void {
            $this->notifier->notifyRole(RoleSlug::SalesManager, [
                'title_ar' => 'أمر بيع بانتظار الاعتماد',
                'body_ar' => 'منشأة: '.($result->customer?->name_ar ?? '#').' — أمر #'.$result->id,
                'action_url' => route('sales-orders.show', $result, false),
                'category' => 'sales_order',
            ]);
        });

        return $result;
    }

    public function approve(SalesOrder $order, User $actor, ?string $notes = null): SalesOrder
    {
        return $this->approveInternal($order, $actor, $notes, allowDraft: false);
    }

    /**
     * After warehouse receives any supplier shipment: activate the linked SO (draft/pending → approved)
     * with available stock reserved. No rep submit / SM decide required (PROC-PARTIAL-SHIP).
     */
    public function activateAfterSupplyReceipt(SalesOrder $order, User $actor): SalesOrder
    {
        if ($order->isApproved()) {
            $this->topUpReservationsFromStock($order, $actor);

            return $order->fresh(['customer', 'lines.product', 'deal', 'createdBy']);
        }

        return $this->approveInternal(
            $order,
            $actor,
            'تفعيل تلقائي بعد استلام شحنة المورد في المستودع',
            allowDraft: true,
        );
    }

    private function approveInternal(
        SalesOrder $order,
        User $actor,
        ?string $notes,
        bool $allowDraft,
    ): SalesOrder {
        $applied = false;
        /** @var list<array{line: SalesOrderLine, shortfall: float, supplier_name_ar: string|null, unit_cost: string|null}>|null $pendingShortfalls */
        $pendingShortfalls = null;
        $shortfallOrder = null;

        try {
            $result = DB::transaction(function () use ($order, $actor, $notes, $allowDraft, &$applied, &$pendingShortfalls, &$shortfallOrder): SalesOrder {
                $order = SalesOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                if ($order->status === SalesOrderStatus::Approved) {
                    return $order->fresh(['customer', 'lines.product', 'deal']);
                }

                $allowed = $allowDraft
                    ? in_array($order->status, [SalesOrderStatus::Draft, SalesOrderStatus::PendingApproval], true)
                    : $order->status === SalesOrderStatus::PendingApproval;

                if (! $allowed) {
                    throw new DomainException(
                        $allowDraft
                            ? 'لا يمكن تفعيل أمر البيع في حالته الحالية.'
                            : 'لا يمكن اعتماد أمر بيع ليس بانتظار الاعتماد.'
                    );
                }

                $order->load(['lines.product', 'customer', 'customerPurchaseOrder.lines']);
                $customer = $order->customer;
                if ($customer === null) {
                    throw new DomainException('أمر البيع بلا منشأة.');
                }

                if (! $this->credit->allowsNewOrder($customer)) {
                    throw new DomainException($this->credit->denyMessageAr($customer));
                }

                $po = $order->customerPurchaseOrder;
                if ($po === null || ! $po->isOpen()) {
                    throw new DomainException('أمر الشراء المرتبط غير مفتوح.');
                }

                foreach ($order->lines as $line) {
                    $poLine = $po->lines->firstWhere('id', $line->customer_purchase_order_line_id);
                    if ($poLine === null) {
                        throw new DomainException('بند أمر شراء غير موجود.');
                    }
                    $allocatedOthers = (float) SalesOrderLine::query()
                        ->where('customer_purchase_order_line_id', $poLine->id)
                        ->where('sales_order_id', '!=', $order->id)
                        ->whereHas('salesOrder', fn ($q) => $q->whereIn('status', [
                            SalesOrderStatus::Draft->value,
                            SalesOrderStatus::PendingApproval->value,
                            SalesOrderStatus::Approved->value,
                        ]))
                        ->sum('quantity');

                    if ($allocatedOthers + (float) $line->quantity > (float) $poLine->quantity + 1e-9) {
                        throw new DomainException(
                            'الكمية تتجاوز المتبقي غير المخصّص في أمر الشراء للمنتج: '
                            .($line->product?->name_ar ?? '#')
                        );
                    }
                }

                $warehouse = $this->inventory->requireWarehouse();
                $order->loadMissing([
                    'lines.product',
                    'lines.customerPurchaseOrderLine.quotationLine.salesRequestLine.procurementTask',
                ]);

                // Reserve available now; shortfall keeps/creates SPO. Partial receipt may approve with partial reserve.
                $shortfalls = [];
                $reservePlan = [];
                foreach ($order->lines as $line) {
                    $product = Product::query()->findOrFail($line->product_id);
                    $available = $this->inventory->availableQty($product, $warehouse);
                    $need = (float) $line->quantity;
                    $toReserve = min($need, max(0.0, $available));
                    if ($toReserve > 1e-9) {
                        $reservePlan[] = [
                            'line' => $line,
                            'product' => $product,
                            'quantity' => $toReserve,
                        ];
                    }
                    if ($need > $toReserve + 1e-9) {
                        $quoteLine = $line->customerPurchaseOrderLine?->quotationLine;
                        $task = $quoteLine?->salesRequestLine?->procurementTask;
                        $shortfalls[] = [
                            'line' => $line,
                            'shortfall' => $need - $toReserve,
                            'supplier_name_ar' => $task?->supplier_name_ar,
                            'unit_cost' => $quoteLine?->supplier_cost !== null
                                ? (string) $quoteLine->supplier_cost
                                : ($task?->supplier_cost !== null ? (string) $task->supplier_cost : null),
                        ];
                    }
                }

                if ($shortfalls !== []) {
                    $order->loadMissing('customerPurchaseOrder');
                    $quotationId = (int) ($order->customerPurchaseOrder?->quotation_id ?? 0);
                    $existingSpo = $quotationId > 0
                        ? $this->supplierPos->openSpoForQuotation($quotationId)
                        : null;

                    if ($existingSpo === null) {
                        $pendingShortfalls = $shortfalls;
                        $shortfallOrder = $order;
                        throw new DomainException('STOCK_SHORTFALL');
                    }

                    if ($reservePlan === []) {
                        throw new DomainException(
                            'المخزون غير كافٍ للاعتماد. أمر شراء مورد #'.$existingSpo->id
                            .' ما زال بانتظار استلام في المستودع — بعد أي شحنة أعد الاعتماد لتوريد الواصل.'
                        );
                    }
                    // Existing supply SPO covers the remainder; approve with what is on hand now.
                }

                foreach ($reservePlan as $plan) {
                    /** @var SalesOrderLine $line */
                    $line = $plan['line'];
                    $qty = (float) $plan['quantity'];
                    $this->inventory->reserve(
                        $warehouse,
                        $plan['product'],
                        $qty,
                        $actor,
                        $order,
                        'reserve:sales_order:'.$order->id.':line:'.$line->id.':upto:'.number_format($qty, 3, '.', ''),
                        ['sales_order_line_id' => $line->id],
                    );
                }

                if ($order->isDraft() && $order->submitted_at === null) {
                    $order->fill([
                        'submitted_at' => now(),
                        'submitted_by_user_id' => $actor->id,
                    ]);
                }

                $order->fill([
                    'status' => SalesOrderStatus::Approved,
                    'decided_at' => now(),
                    'decided_by_user_id' => $actor->id,
                    'decision_notes' => $notes,
                ])->save();

                $this->audit->record('sales_order.approved', $order, [
                    'status' => $order->status->value,
                    'line_count' => $order->lines->count(),
                    'auto_from_supply_receipt' => $allowDraft,
                ], $actor);

                $applied = true;

                return $order->fresh(['customer', 'lines.product', 'deal', 'createdBy']);
            });
        } catch (DomainException $e) {
            if ($e->getMessage() === 'STOCK_SHORTFALL' && $pendingShortfalls !== null && $shortfallOrder !== null) {
                $shortfallOrder->loadMissing('customerPurchaseOrder');
                $quotationId = (int) ($shortfallOrder->customerPurchaseOrder?->quotation_id ?? 0);
                $existingSpo = $quotationId > 0
                    ? $this->supplierPos->openSpoForQuotation($quotationId)
                    : null;

                if ($existingSpo !== null) {
                    throw new DomainException(
                        'المخزون غير كافٍ للاعتماد. أمر شراء مورد #'.$existingSpo->id
                        .' مفتوح للمشتريات/المستودع — بعد استلام التوريد أعد الاعتماد.'
                    );
                }

                $spo = $this->supplierPos->createFromSalesOrderShortfall($shortfallOrder, $actor, $pendingShortfalls);
                throw new DomainException(
                    'المخزون غير كافٍ للاعتماد (لا يُطلب شراء لما هو متوفر). '
                    .'تم إنشاء أمر شراء مورد #'.$spo->id.' للمشتريات — بعد الاستلام أعد الاعتماد.'
                );
            }

            throw $e;
        }

        if ($applied) {
            DB::afterCommit(function () use ($result, $allowDraft): void {
                $this->notifier->notifyRole(RoleSlug::Warehouse, [
                    'title_ar' => $allowDraft ? 'أمر بيع مفعّل — جاهز للتوريد' : 'أمر بيع معتمد — تجهيز',
                    'body_ar' => 'أمر بيع #'.$result->id.' للمنشأة '.($result->customer?->name_ar ?? '#'),
                    'action_url' => route('sales-orders.show', $result, false),
                    'category' => 'sales_order',
                ]);
                $this->notifyCreator(
                    $result,
                    $allowDraft
                        ? 'تم تفعيل أمر البيع تلقائيًا بعد استلام شحنة المورد وحجز الكمية الواصلة.'
                        : 'تم اعتماد أمر البيع وحجز المخزون.'
                );
            });
        }

        return $result;
    }

    /**
     * After a supplier goods receipt, reserve newly available stock against an already-approved SO
     * so later partial deliveries can ship without waiting for the full SPO.
     */
    public function topUpReservationsFromStock(SalesOrder $order, User $actor): void
    {
        if (! $order->isApproved() || $order->isFullyDelivered()) {
            return;
        }

        DB::transaction(function () use ($order, $actor): void {
            $order = SalesOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $order->isApproved() || $order->isFullyDelivered()) {
                return;
            }

            $order->load('lines.product');
            $warehouse = $this->inventory->requireWarehouse();

            foreach ($order->lines as $line) {
                $confirmed = $order->confirmedDeliveredQuantityForLine($line);
                $targetReserved = max(0.0, (float) $line->quantity - $confirmed);
                $already = $this->inventory->remainingReservedForOrderLine($order, $line);
                $gap = $targetReserved - $already;
                if ($gap <= 1e-9) {
                    continue;
                }

                $product = Product::query()->findOrFail($line->product_id);
                $available = $this->inventory->availableQty($product, $warehouse);
                $toReserve = min($gap, max(0.0, $available));
                if ($toReserve <= 1e-9) {
                    continue;
                }

                $upto = $already + $toReserve;
                $this->inventory->reserve(
                    $warehouse,
                    $product,
                    $toReserve,
                    $actor,
                    $order,
                    'reserve:sales_order:'.$order->id.':line:'.$line->id.':upto:'.number_format($upto, 3, '.', ''),
                    ['sales_order_line_id' => $line->id],
                );
            }
        });
    }

    public function reject(SalesOrder $order, User $actor, ?string $notes = null): SalesOrder
    {
        $applied = false;

        $result = DB::transaction(function () use ($order, $actor, $notes, &$applied): SalesOrder {
            $order = SalesOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status === SalesOrderStatus::Rejected) {
                return $order->fresh(['customer', 'lines']);
            }

            if ($order->status !== SalesOrderStatus::PendingApproval) {
                throw new DomainException('لا يمكن رفض أمر بيع ليس بانتظار الاعتماد.');
            }

            $order->fill([
                'status' => SalesOrderStatus::Rejected,
                'decided_at' => now(),
                'decided_by_user_id' => $actor->id,
                'decision_notes' => $notes,
            ])->save();

            $this->audit->record('sales_order.rejected', $order, [
                'status' => $order->status->value,
            ], $actor);

            $applied = true;

            return $order->fresh(['customer', 'lines', 'createdBy']);
        });

        if ($applied) {
            DB::afterCommit(function () use ($result): void {
                $this->notifyCreator($result, 'رُفض أمر البيع — بلا حجز مخزون.');
            });
        }

        return $result;
    }

    public function cancelApproved(SalesOrder $order, User $actor, ?string $notes = null): SalesOrder
    {
        $applied = false;

        $result = DB::transaction(function () use ($order, $actor, $notes, &$applied): SalesOrder {
            $order = SalesOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status === SalesOrderStatus::Cancelled) {
                return $order->fresh(['customer', 'lines.product']);
            }

            if ($order->status !== SalesOrderStatus::Approved) {
                throw new DomainException('لا يمكن إلغاء إلا أمر بيع معتمد.');
            }

            $hasOpenDelivery = $order->deliveries()
                ->whereIn('status', [
                    DeliveryStatus::Draft->value,
                    DeliveryStatus::PendingPod->value,
                ])
                ->exists();

            if ($hasOpenDelivery) {
                throw new DomainException('ألغِ تسليمات المسودة/بانتظار الإثبات أولاً قبل إلغاء أمر البيع.');
            }

            $order->load('lines.product');
            $warehouse = $this->inventory->requireWarehouse();

            foreach ($order->lines as $line) {
                $product = Product::query()->findOrFail($line->product_id);
                // Release only undelivered remainder (INV5 after partial deduct).
                $toRelease = (float) $line->quantity - $order->confirmedDeliveredQuantityForLine($line);
                if ($toRelease <= 1e-9) {
                    continue;
                }

                $this->inventory->release(
                    $warehouse,
                    $product,
                    $actor,
                    $order,
                    'release:sales_order:'.$order->id.':line:'.$line->id,
                    $toRelease,
                    ['sales_order_line_id' => $line->id],
                );
            }

            $order->fill([
                'status' => SalesOrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->id,
                'decision_notes' => $notes,
            ])->save();

            $this->audit->record('sales_order.cancelled', $order, [
                'status' => $order->status->value,
            ], $actor);

            $applied = true;

            return $order->fresh(['customer', 'lines.product', 'createdBy']);
        });

        if ($applied) {
            DB::afterCommit(function () use ($result): void {
                $this->notifyCreator($result, 'أُلغي أمر البيع المعتمد وأُفرج عن الحجز المتبقي.');
            });
        }

        return $result;
    }

    /**
     * @param  list<array{customer_purchase_order_line_id: int, quantity: string}>  $lines
     */
    private function assertLinesPayload(CustomerPurchaseOrder $po, array $lines, ?SalesOrder $excludeOrder): void
    {
        if ($lines === []) {
            throw new DomainException('يجب إضافة بند واحد على الأقل.');
        }

        $seen = [];
        foreach ($lines as $lineInput) {
            $poLineId = (int) $lineInput['customer_purchase_order_line_id'];
            if (isset($seen[$poLineId])) {
                throw new DomainException('لا تكرر بند أمر الشراء في أمر البيع.');
            }
            $seen[$poLineId] = true;

            /** @var CustomerPurchaseOrderLine|null $poLine */
            $poLine = $po->lines->firstWhere('id', $poLineId);
            if ($poLine === null) {
                throw new DomainException('بند أمر شراء غير صالح.');
            }

            $qty = (float) $lineInput['quantity'];
            if ($qty <= 0) {
                throw new DomainException('كمية أمر البيع يجب أن تكون موجبة.');
            }

            $allocatedOthers = (float) SalesOrderLine::query()
                ->where('customer_purchase_order_line_id', $poLine->id)
                ->when($excludeOrder, fn ($q) => $q->where('sales_order_id', '!=', $excludeOrder->id))
                ->whereHas('salesOrder', fn ($q) => $q->whereIn('status', [
                    SalesOrderStatus::Draft->value,
                    SalesOrderStatus::PendingApproval->value,
                    SalesOrderStatus::Approved->value,
                ]))
                ->sum('quantity');

            if ($allocatedOthers + $qty > (float) $poLine->quantity + 1e-9) {
                throw new DomainException(
                    'الكمية تتجاوز المتبقي غير المخصّص في أمر الشراء (المنتج #'.$poLine->product_id.').'
                );
            }
        }
    }

    /**
     * @param  list<array{customer_purchase_order_line_id: int, quantity: string}>  $lines
     */
    private function syncLines(SalesOrder $order, CustomerPurchaseOrder $po, array $lines): void
    {
        foreach (array_values($lines) as $index => $lineInput) {
            $poLine = $po->lines->firstWhere('id', (int) $lineInput['customer_purchase_order_line_id']);
            SalesOrderLine::query()->create([
                'sales_order_id' => $order->id,
                'customer_purchase_order_line_id' => $poLine->id,
                'product_id' => $poLine->product_id,
                'quantity' => number_format((float) $lineInput['quantity'], 3, '.', ''),
                'sort_order' => $index,
            ]);
        }
    }

    private function notifyCreator(SalesOrder $order, string $body): void
    {
        $user = $order->createdBy;
        if ($user === null) {
            return;
        }

        $this->notifier->notifyUser($user, [
            'title_ar' => 'تحديث أمر البيع',
            'body_ar' => $body.' — أمر #'.$order->id,
            'action_url' => route('sales-orders.show', $order, false),
            'category' => 'sales_order',
        ]);
    }
}
