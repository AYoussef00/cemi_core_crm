<?php

namespace App\Support\Warehouse;

use App\Models\Delivery;
use App\Models\DeliveryLine;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Authorization\RoleSlug;
use App\Support\Finance\InvoiceWorkflow;
use App\Support\Inventory\InventoryService;
use App\Support\Notifications\InternalNotifier;
use App\Support\Sales\SalesOrderStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

class DeliveryWorkflow
{
    public function __construct(
        private AuditLogger $audit,
        private InternalNotifier $notifier,
        private InventoryService $inventory,
        private InvoiceWorkflow $invoices,
    ) {}

    /**
     * Mark approved SO as preparation-ready (simple warehouse checklist).
     */
    public function markPreparationReady(SalesOrder $order, User $actor, ?string $notes = null): SalesOrder
    {
        if ($order->status !== SalesOrderStatus::Approved) {
            throw new DomainException('التجهيز متاح لأوامر البيع المعتمدة فقط.');
        }

        if ($order->isFullyDelivered()) {
            throw new DomainException('أمر البيع مكتمل التسليم — لا حاجة لتجهيز إضافي.');
        }

        return DB::transaction(function () use ($order, $actor, $notes): SalesOrder {
            $order = SalesOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $order->fill([
                'preparation_status' => PreparationStatus::Ready,
                'preparation_notes_ar' => $notes ?? $order->preparation_notes_ar,
                'preparation_ready_at' => now(),
                'preparation_ready_by_user_id' => $actor->id,
            ])->save();

            $this->audit->record('sales_order.preparation_ready', $order, [
                'preparation_status' => $order->preparation_status->value,
            ], $actor);

            return $order->fresh(['customer', 'lines.product', 'preparationReadyBy']);
        });
    }

    /**
     * @param  array{
     *     sales_order_id: int,
     *     notes_ar?: string|null,
     *     lines: list<array{sales_order_line_id: int, quantity: string}>
     * }  $payload
     */
    public function createDraft(User $actor, array $payload): Delivery
    {
        $order = SalesOrder::query()
            ->with('lines')
            ->findOrFail($payload['sales_order_id']);

        if ($order->status !== SalesOrderStatus::Approved) {
            throw new DomainException('التسليم يُنشأ على أمر بيع معتمد فقط.');
        }

        if ($order->isFullyDelivered()) {
            throw new DomainException('أمر البيع مكتمل التسليم.');
        }

        $this->assertLinesPayload($order, $payload['lines'], null);

        return DB::transaction(function () use ($actor, $payload, $order): Delivery {
            $delivery = Delivery::query()->create([
                'sales_order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'created_by_user_id' => $actor->id,
                'status' => DeliveryStatus::Draft,
                'notes_ar' => $payload['notes_ar'] ?? null,
            ]);

            $this->syncLines($delivery, $order, $payload['lines']);

            $this->audit->record('delivery.created', $delivery, [
                'sales_order_id' => $order->id,
                'status' => $delivery->status->value,
                'line_count' => count($payload['lines']),
            ], $actor);

            return $delivery->fresh(['customer', 'salesOrder', 'lines.product']);
        });
    }

    /**
     * @param  array{
     *     notes_ar?: string|null,
     *     lines: list<array{sales_order_line_id: int, quantity: string}>
     * }  $payload
     */
    public function updateDraft(Delivery $delivery, User $actor, array $payload): Delivery
    {
        if ($delivery->status !== DeliveryStatus::Draft) {
            throw new DomainException('لا يمكن تعديل تسليم ليس مسودة.');
        }

        $order = SalesOrder::query()->with('lines')->findOrFail($delivery->sales_order_id);
        $this->assertLinesPayload($order, $payload['lines'], $delivery);

        return DB::transaction(function () use ($delivery, $actor, $payload, $order): Delivery {
            $delivery->fill(['notes_ar' => $payload['notes_ar'] ?? null])->save();
            $delivery->lines()->delete();
            $this->syncLines($delivery, $order, $payload['lines']);

            $this->audit->record('delivery.updated', $delivery, [
                'status' => $delivery->status->value,
            ], $actor);

            return $delivery->fresh(['customer', 'lines.product', 'salesOrder']);
        });
    }

    public function markPendingPod(Delivery $delivery, User $actor): Delivery
    {
        if ($delivery->status !== DeliveryStatus::Draft) {
            throw new DomainException('تعليمقل حالة إثبات التسليم متاح للمسودة فقط.');
        }

        $delivery->load('lines');
        if ($delivery->lines->isEmpty()) {
            throw new DomainException('أضف بنودًا قبل طلب إثبات التسليم.');
        }

        return DB::transaction(function () use ($delivery, $actor): Delivery {
            $delivery->fill([
                'status' => DeliveryStatus::PendingPod,
                'pending_pod_at' => now(),
            ])->save();

            $this->audit->record('delivery.pending_pod', $delivery, [
                'status' => $delivery->status->value,
            ], $actor);

            return $delivery->fresh(['lines.product', 'salesOrder']);
        });
    }

    public function confirm(Delivery $delivery, User $actor): Delivery
    {
        $applied = false;

        $result = DB::transaction(function () use ($delivery, $actor, &$applied): Delivery {
            $delivery = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if ($delivery->status === DeliveryStatus::Confirmed) {
                return $delivery->fresh(['lines.product', 'salesOrder', 'attachments']);
            }

            if (! in_array($delivery->status, [DeliveryStatus::Draft, DeliveryStatus::PendingPod], true)) {
                throw new DomainException('لا يمكن تأكيد تسليم ملغى أو غير مفتوح.');
            }

            if (! $delivery->attachments()->exists()) {
                throw new DomainException('إثبات التسليم (مرفق) مطلوب قبل التأكيد.');
            }

            $order = SalesOrder::query()
                ->whereKey($delivery->sales_order_id)
                ->lockForUpdate()
                ->with('lines')
                ->firstOrFail();

            if ($order->status !== SalesOrderStatus::Approved) {
                throw new DomainException('أمر البيع لم يعد معتمدًا.');
            }

            $delivery->load('lines.product');
            if ($delivery->lines->isEmpty()) {
                throw new DomainException('التسليم بلا بنود.');
            }

            // Re-validate caps under locks.
            $lineInputs = $delivery->lines->map(fn (DeliveryLine $l) => [
                'sales_order_line_id' => $l->sales_order_line_id,
                'quantity' => (string) $l->quantity,
            ])->all();
            $this->assertLinesPayload($order, $lineInputs, $delivery);

            $remainingReserved = $this->inventory->remainingReservedFor($order);
            $deliveryTotal = (float) $delivery->lines->sum('quantity');
            if ($deliveryTotal > $remainingReserved + 1e-9) {
                throw new DomainException(
                    'كمية التسليم تتجاوز الحجز المتبقي لأمر البيع.'
                );
            }

            $warehouse = $this->inventory->requireWarehouse();

            foreach ($delivery->lines as $line) {
                $product = Product::query()->findOrFail($line->product_id);
                $this->inventory->deduct(
                    $warehouse,
                    $product,
                    $line->quantity,
                    $actor,
                    $delivery,
                    'deduct:delivery:'.$delivery->id.':line:'.$line->id,
                    $order,
                    ['delivery_line_id' => $line->id, 'sales_order_line_id' => $line->sales_order_line_id],
                );
            }

            $delivery->fill([
                'status' => DeliveryStatus::Confirmed,
                'confirmed_at' => now(),
                'confirmed_by_user_id' => $actor->id,
            ])->save();

            $order->refreshFulfillmentStatus();

            // Auto-issue invoice for this confirmed delivery (PF3) — no separate Accounts step.
            $invoice = $this->invoices->issueFromDelivery(
                $delivery->fresh(['lines.product', 'lines.salesOrderLine.customerPurchaseOrderLine.quotationLine', 'salesOrder.deal', 'customer']),
                $actor,
                'إصدار تلقائي عند تأكيد التسليم',
                idempotent: true,
            );

            $this->audit->record('delivery.confirmed', $delivery, [
                'status' => $delivery->status->value,
                'sales_order_id' => $order->id,
                'fulfillment_status' => $order->fresh()->fulfillment_status->value,
                'line_count' => $delivery->lines->count(),
                'invoice_id' => $invoice->id,
            ], $actor);

            $applied = true;

            return $delivery->fresh(['customer', 'lines.product', 'salesOrder.createdBy', 'confirmedBy', 'attachments', 'invoice']);
        });

        if ($applied) {
            DB::afterCommit(function () use ($result): void {
                $order = $result->salesOrder;
                $body = 'تم تأكيد إذن التسليم '.$result->document_number.' لأمر البيع #'.($order?->id ?? '—')
                    .' — المنشأة '.($result->customer?->name_ar ?? '#');

                $this->notifier->notifyRole(RoleSlug::SalesManager, [
                    'title_ar' => 'تأكيد تسليم',
                    'body_ar' => $body,
                    'action_url' => route('deliveries.show', $result, false),
                    'category' => 'delivery',
                ]);

                if ($order?->createdBy !== null) {
                    $this->notifier->notifyUser($order->createdBy, [
                        'title_ar' => 'تأكيد تسليم لأمرك',
                        'body_ar' => $body,
                        'action_url' => route('deliveries.show', $result, false),
                        'category' => 'delivery',
                    ]);
                }

                $invoice = $result->invoice ?? Invoice::query()->where('delivery_id', $result->id)->first();
                if ($invoice !== null) {
                    $this->notifier->notifyRole(RoleSlug::Accounts, [
                        'title_ar' => 'فاتورة صادرة تلقائيًا',
                        'body_ar' => 'فاتورة '.$invoice->document_number.' بعد تأكيد التسليم '.$result->document_number,
                        'action_url' => route('invoices.show', $invoice, false),
                        'category' => 'invoice',
                    ]);
                }
            });
        }

        return $result;
    }

    public function cancel(Delivery $delivery, User $actor, ?string $notes = null): Delivery
    {
        $applied = false;

        $result = DB::transaction(function () use ($delivery, $actor, $notes, &$applied): Delivery {
            $delivery = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if ($delivery->status === DeliveryStatus::Cancelled) {
                return $delivery->fresh(['lines']);
            }

            if ($delivery->status === DeliveryStatus::Confirmed) {
                throw new DomainException('لا يمكن إلغاء تسليم مؤكد.');
            }

            if (! $delivery->status->isOpen()) {
                throw new DomainException('لا يمكن إلغاء هذا التسليم.');
            }

            $delivery->fill([
                'status' => DeliveryStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->id,
                'cancel_notes_ar' => $notes,
            ])->save();

            $this->audit->record('delivery.cancelled', $delivery, [
                'status' => $delivery->status->value,
            ], $actor);

            $applied = true;

            return $delivery->fresh(['lines.product', 'salesOrder']);
        });

        return $result;
    }

    /**
     * @param  list<array{sales_order_line_id: int, quantity: string}>  $lines
     */
    private function assertLinesPayload(SalesOrder $order, array $lines, ?Delivery $exclude): void
    {
        if ($lines === []) {
            throw new DomainException('يجب إضافة بند تسليم واحد على الأقل.');
        }

        $seen = [];
        foreach ($lines as $lineInput) {
            $soLineId = (int) $lineInput['sales_order_line_id'];
            if (isset($seen[$soLineId])) {
                throw new DomainException('لا تكرر بند أمر البيع في التسليم.');
            }
            $seen[$soLineId] = true;

            /** @var SalesOrderLine|null $soLine */
            $soLine = $order->lines->firstWhere('id', $soLineId);
            if ($soLine === null || (int) $soLine->sales_order_id !== (int) $order->id) {
                throw new DomainException('بند أمر بيع غير صالح.');
            }

            $qty = (float) $lineInput['quantity'];
            if ($qty <= 0) {
                throw new DomainException('كمية التسليم يجب أن تكون موجبة.');
            }

            $available = $order->undeliveredQuantityForLine($soLine, $exclude);
            if ($qty > $available + 1e-9) {
                throw new DomainException(
                    'كمية التسليم تتجاوز المتبقي غير المسلَّم للبند: '
                    .($soLine->product?->name_ar ?? '#'.$soLine->product_id)
                );
            }
        }
    }

    /**
     * @param  list<array{sales_order_line_id: int, quantity: string}>  $lines
     */
    private function syncLines(Delivery $delivery, SalesOrder $order, array $lines): void
    {
        foreach (array_values($lines) as $index => $lineInput) {
            $soLine = $order->lines->firstWhere('id', (int) $lineInput['sales_order_line_id']);
            DeliveryLine::query()->create([
                'delivery_id' => $delivery->id,
                'sales_order_line_id' => $soLine->id,
                'product_id' => $soLine->product_id,
                'quantity' => number_format((float) $lineInput['quantity'], 3, '.', ''),
                'sort_order' => $index,
            ]);
        }
    }
}
