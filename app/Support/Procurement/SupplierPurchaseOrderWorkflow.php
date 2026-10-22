<?php

namespace App\Support\Procurement;

use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SupplierGoodsReceipt;
use App\Models\SupplierGoodsReceiptLine;
use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderLine;
use App\Models\User;
use App\Support\Attachments\AttachmentService;
use App\Support\Audit\AuditLogger;
use App\Support\Authorization\RoleSlug;
use App\Support\Inventory\InventoryService;
use App\Support\Notifications\InternalNotifier;
use App\Support\Sales\SalesOrderWorkflow;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SupplierPurchaseOrderWorkflow
{
    public function __construct(
        private AuditLogger $audit,
        private InternalNotifier $notifier,
        private InventoryService $inventory,
        private AttachmentService $attachments,
        private SupplierPurchaseOrderPdfRenderer $poPdf,
    ) {}

    /**
     * PROC-BUY-ON-CPO: after Rep converts approved quote → customer PO PDF + sales order draft,
     * open SPO for procurement-sourced lines. Idempotent per quotation.
     */
    public function createFromCustomerPoConversion(
        Quotation $quotation,
        SalesOrder $order,
        User $actor,
    ): ?SupplierPurchaseOrder {
        $existing = $this->openSpoForQuotation((int) $quotation->id);
        if ($existing !== null) {
            if ($existing->sales_order_id === null) {
                $existing->fill(['sales_order_id' => $order->id])->save();
            }

            return $existing->fresh(['lines.product', 'quotation.customer', 'salesOrder']);
        }

        $order->loadMissing([
            'lines.customerPurchaseOrderLine.quotationLine.salesRequestLine.procurementTask',
        ]);

        $buyLines = $order->lines->filter(function (SalesOrderLine $soLine): bool {
            $srLine = $soLine->customerPurchaseOrderLine?->quotationLine?->salesRequestLine;
            if ($srLine === null) {
                return false;
            }

            return $srLine->needsProcurement() || $srLine->procurementTask !== null;
        })->values();

        if ($buyLines->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($quotation, $order, $actor, $buyLines): SupplierPurchaseOrder {
            $supplierNames = $buyLines
                ->map(fn (SalesOrderLine $soLine) => $soLine->customerPurchaseOrderLine
                    ?->quotationLine
                    ?->salesRequestLine
                    ?->procurementTask
                    ?->supplier_name_ar)
                ->filter()
                ->unique()
                ->values();

            $spo = SupplierPurchaseOrder::query()->create([
                'quotation_id' => $quotation->id,
                'sales_order_id' => $order->id,
                'status' => SupplierPurchaseOrderStatus::Open,
                'supplier_name_ar' => $supplierNames->count() === 1 ? $supplierNames->first() : null,
                'created_by_user_id' => $actor->id,
            ]);

            foreach ($buyLines as $index => $soLine) {
                $qLine = $soLine->customerPurchaseOrderLine?->quotationLine;
                $task = $qLine?->salesRequestLine?->procurementTask;
                SupplierPurchaseOrderLine::query()->create([
                    'supplier_purchase_order_id' => $spo->id,
                    'product_id' => $soLine->product_id,
                    'sales_order_line_id' => $soLine->id,
                    'quantity' => number_format((float) $soLine->quantity, 3, '.', ''),
                    'received_quantity' => '0.000',
                    'unit_cost' => $qLine?->supplier_cost !== null
                        ? (string) $qLine->supplier_cost
                        : ($task?->supplier_cost !== null ? (string) $task->supplier_cost : null),
                    'sort_order' => $index,
                ]);
            }

            $this->audit->record('supplier_po.created', $spo, [
                'quotation_id' => $quotation->id,
                'sales_order_id' => $order->id,
                'source' => 'customer_po_converted',
                'line_count' => $buyLines->count(),
                'status' => $spo->status->value,
            ], $actor);

            $spo = $spo->fresh(['lines.product', 'quotation.customer', 'salesOrder']);

            DB::afterCommit(function () use ($spo, $quotation, $order): void {
                $this->notifier->notifyRole(RoleSlug::Procurement, [
                    'title_ar' => 'أمر شراء مورد — بعد تحويل العرض',
                    'body_ar' => 'عُرض #'.$quotation->id.' حُوِّل لأمر بيع #'.$order->id.' مع أمر شراء العميل. اشترِ من المورد — أمر #'.$spo->id,
                    'action_url' => route('supplier-pos.show', $spo, false),
                    'category' => 'supplier_po',
                ]);
            });

            return $spo;
        });
    }

    public function openSpoForQuotation(int $quotationId): ?SupplierPurchaseOrder
    {
        return SupplierPurchaseOrder::query()
            ->where('quotation_id', $quotationId)
            ->whereIn('status', [
                SupplierPurchaseOrderStatus::Open->value,
                SupplierPurchaseOrderStatus::Ordered->value,
                SupplierPurchaseOrderStatus::PartiallyReceived->value,
            ])
            ->latest('id')
            ->first();
    }

    /**
     * Supplier PO that blocks SO submit/approve until the first warehouse receipt.
     * Partial receipt unlocks the sales order so received qty can ship immediately.
     */
    public function blockingSupplyPoForOrder(SalesOrder $order): ?SupplierPurchaseOrder
    {
        $awaitingFirstReceipt = [
            SupplierPurchaseOrderStatus::Open->value,
            SupplierPurchaseOrderStatus::Ordered->value,
        ];

        $byOrder = SupplierPurchaseOrder::query()
            ->where('sales_order_id', $order->id)
            ->whereIn('status', $awaitingFirstReceipt)
            ->latest('id')
            ->first();

        if ($byOrder !== null) {
            return $byOrder;
        }

        $order->loadMissing('customerPurchaseOrder');
        $quotationId = (int) ($order->customerPurchaseOrder?->quotation_id ?? 0);
        if ($quotationId <= 0) {
            return null;
        }

        return SupplierPurchaseOrder::query()
            ->where('quotation_id', $quotationId)
            ->whereIn('status', $awaitingFirstReceipt)
            ->latest('id')
            ->first();
    }

    /**
     * Create SPO for shortfall lines when SO approve lacks stock (PROC-BUY-ON-SO).
     *
     * @param  list<array{line: SalesOrderLine, shortfall: float, supplier_name_ar: string|null, unit_cost: string|null}>  $shortfalls
     */
    public function createFromSalesOrderShortfall(SalesOrder $order, User $actor, array $shortfalls): SupplierPurchaseOrder
    {
        if ($shortfalls === []) {
            throw new DomainException('لا بنود ناقصة لإنشاء أمر شراء مورد.');
        }

        return DB::transaction(function () use ($order, $actor, $shortfalls): SupplierPurchaseOrder {
            $supplierNames = collect($shortfalls)
                ->pluck('supplier_name_ar')
                ->filter()
                ->unique()
                ->values();

            $order->loadMissing('customerPurchaseOrder');
            $quotationId = $order->customerPurchaseOrder?->quotation_id;

            $spo = SupplierPurchaseOrder::query()->create([
                'sales_order_id' => $order->id,
                'quotation_id' => $quotationId,
                'status' => SupplierPurchaseOrderStatus::Open,
                'supplier_name_ar' => $supplierNames->count() === 1 ? $supplierNames->first() : null,
                'created_by_user_id' => $actor->id,
            ]);

            foreach (array_values($shortfalls) as $index => $row) {
                /** @var SalesOrderLine $line */
                $line = $row['line'];
                SupplierPurchaseOrderLine::query()->create([
                    'supplier_purchase_order_id' => $spo->id,
                    'product_id' => $line->product_id,
                    'sales_order_line_id' => $line->id,
                    'quantity' => number_format((float) $row['shortfall'], 3, '.', ''),
                    'received_quantity' => '0.000',
                    'unit_cost' => $row['unit_cost'],
                    'sort_order' => $index,
                ]);
            }

            $this->audit->record('supplier_po.created', $spo, [
                'sales_order_id' => $order->id,
                'line_count' => count($shortfalls),
                'status' => $spo->status->value,
            ], $actor);

            $spo = $spo->fresh(['lines.product', 'salesOrder.customer']);

            DB::afterCommit(function () use ($spo, $order): void {
                $this->notifier->notifyRole(RoleSlug::Procurement, [
                    'title_ar' => 'أمر شراء مورد — نقص مخزون',
                    'body_ar' => 'أمر بيع #'.$order->id.' يحتاج شراء كميات ناقصة. أمر المورد #'.$spo->id,
                    'action_url' => route('supplier-pos.show', $spo, false),
                    'category' => 'supplier_po',
                ]);
            });

            return $spo;
        });
    }

    /**
     * Issue PO to supplier: status ordered, attach Arabic PO PDF (+ optional uploaded copy), notify Warehouse.
     *
     * @param  array{
     *     expected_at: string,
     *     notes_ar?: string|null,
     *     supplier_name_ar?: string|null,
     *     po_document?: UploadedFile|null
     * }  $payload
     */
    public function markOrdered(SupplierPurchaseOrder $spo, User $actor, array $payload): SupplierPurchaseOrder
    {
        $applied = false;

        $result = DB::transaction(function () use ($spo, $actor, $payload, &$applied): SupplierPurchaseOrder {
            $spo = SupplierPurchaseOrder::query()->whereKey($spo->id)->lockForUpdate()->firstOrFail();

            if (! in_array($spo->status, [
                SupplierPurchaseOrderStatus::Open,
                SupplierPurchaseOrderStatus::Ordered,
            ], true)) {
                throw new DomainException('لا يمكن تسجيل إرسال المورد في الحالة الحالية.');
            }

            $spo->fill([
                'status' => SupplierPurchaseOrderStatus::Ordered,
                'expected_at' => $payload['expected_at'],
                'notes_ar' => $payload['notes_ar'] ?? $spo->notes_ar,
                'supplier_name_ar' => $payload['supplier_name_ar'] ?? $spo->supplier_name_ar,
                'ordered_at' => $spo->ordered_at ?? now(),
            ])->save();

            $this->audit->record('supplier_po.ordered', $spo, [
                'expected_at' => optional($spo->expected_at)?->toIso8601String(),
                'status' => $spo->status->value,
            ], $actor);

            $applied = true;

            return $spo->fresh(['lines.product', 'salesOrder.customer', 'attachments']);
        });

        // Attach issued PO document outside the lock tx (file I/O) but before notifications.
        $this->attachIssuedPoDocument($result, $actor, $payload['po_document'] ?? null);

        if ($applied) {
            DB::afterCommit(function () use ($result): void {
                $when = $result->expected_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—';
                $supplier = $result->supplier_name_ar ?: 'مورد';
                $totalQty = number_format($result->totalOrderedQuantity(), 3, '.', '');
                $lines = $result->lines->map(
                    fn ($l) => ($l->product?->name_ar ?? '#').' × '.number_format((float) $l->quantity, 3, '.', '')
                )->implode('، ');
                $this->notifier->notifyRole(RoleSlug::Warehouse, [
                    'title_ar' => 'طلبية واردة للمستودع',
                    'body_ar' => $supplier.' — وصول متوقع '.$when.' — إجمالي '.$totalQty.' — أمر #'.$result->id.' — '.$lines,
                    'action_url' => route('supplier-pos.show', $result, false),
                    'category' => 'supplier_po',
                ]);
            });
        }

        return $result->fresh(['lines.product', 'salesOrder.customer', 'attachments']);
    }

    /**
     * Record one warehouse shipment as a goods-receipt document against the PO (partial OK).
     * PROC-RECV-NOTE: each receipt requires the signed supplier delivery note PDF.
     *
     * @param  array{
     *     lines: list<array{line_id: int, quantity: string}>,
     *     shipment_reference_ar?: string|null,
     *     notes_ar?: string|null,
     *     delivery_note_pdf: UploadedFile
     * }  $payload
     */
    public function receiveShipment(SupplierPurchaseOrder $spo, User $actor, array $payload): SupplierGoodsReceipt
    {
        $linesPayload = $payload['lines'] ?? [];
        if ($linesPayload === []) {
            throw new DomainException('حدد كميات الاستلام لهذه الشحنة.');
        }

        $deliveryNote = $payload['delivery_note_pdf'] ?? null;
        if (! $deliveryNote instanceof UploadedFile) {
            throw new DomainException('أرفق محضر/إذن التسليم الموقع مع المورد (PDF).');
        }

        $applied = false;
        $receiptId = null;

        $result = DB::transaction(function () use ($spo, $actor, $payload, $linesPayload, &$applied, &$receiptId): SupplierGoodsReceipt {
            $spo = SupplierPurchaseOrder::query()->whereKey($spo->id)->lockForUpdate()->firstOrFail();

            if (! $spo->canReceive()) {
                throw new DomainException('لا يمكن استلام هذا الأمر في حالته الحالية.');
            }

            $spo->load(['lines.product']);
            $warehouse = $this->inventory->requireWarehouse();

            $receipt = SupplierGoodsReceipt::query()->create([
                'supplier_purchase_order_id' => $spo->id,
                'received_by_user_id' => $actor->id,
                'received_at' => now(),
                'shipment_reference_ar' => $payload['shipment_reference_ar'] ?? null,
                'notes_ar' => $payload['notes_ar'] ?? null,
            ]);

            $any = false;
            $sort = 0;

            foreach ($linesPayload as $row) {
                $line = $spo->lines->firstWhere('id', (int) $row['line_id']);
                if ($line === null) {
                    throw new DomainException('بند أمر شراء غير موجود.');
                }

                $qty = (float) $row['quantity'];
                if ($qty <= 0) {
                    continue;
                }

                $remaining = $line->remainingQuantity();
                if ($qty > $remaining + 1e-9) {
                    throw new DomainException(
                        'كمية الاستلام تتجاوز المتبقي للمنتج: '.($line->product?->name_ar ?? '#')
                    );
                }

                $receiptLine = SupplierGoodsReceiptLine::query()->create([
                    'supplier_goods_receipt_id' => $receipt->id,
                    'supplier_purchase_order_line_id' => $line->id,
                    'product_id' => $line->product_id,
                    'quantity' => number_format($qty, 3, '.', ''),
                    'sort_order' => $sort++,
                ]);

                $product = Product::query()->findOrFail($line->product_id);
                // Include qty in the key so reused auto-increment IDs after a data wipe
                // cannot silently no-op against a stale ledger row from a prior cycle.
                $this->inventory->receive(
                    $warehouse,
                    $product,
                    $qty,
                    $actor,
                    $receipt,
                    'receipt:goods_receipt:'.$receipt->id
                        .':line:'.$receiptLine->id
                        .':qty:'.number_format($qty, 3, '.', ''),
                    [
                        'supplier_po_id' => $spo->id,
                        'supplier_po_line_id' => $line->id,
                        'supplier_goods_receipt_line_id' => $receiptLine->id,
                    ],
                );

                $line->fill([
                    'received_quantity' => number_format((float) $line->received_quantity + $qty, 3, '.', ''),
                ])->save();

                $any = true;
            }

            if (! $any) {
                throw new DomainException('لم تُسجَّل أي كمية استلام في هذه الشحنة.');
            }

            $spo->load('lines');
            $allReceived = $spo->lines->every(fn ($l) => $l->remainingQuantity() <= 1e-9);
            $anyReceived = $spo->lines->contains(fn ($l) => (float) $l->received_quantity > 0);

            $spo->fill([
                'status' => $allReceived
                    ? SupplierPurchaseOrderStatus::Received
                    : ($anyReceived ? SupplierPurchaseOrderStatus::PartiallyReceived : $spo->status),
                'received_by_user_id' => $actor->id,
                'received_at' => $allReceived ? now() : $spo->received_at,
            ])->save();

            $this->audit->record('supplier_goods_receipt.created', $receipt, [
                'supplier_po_id' => $spo->id,
                'spo_status' => $spo->status->value,
                'line_count' => $sort,
            ], $actor);

            $applied = true;
            $receiptId = $receipt->id;

            return $receipt->fresh([
                'lines.product',
                'purchaseOrder.salesOrder.customer',
                'receivedBy',
                'attachments',
            ]);
        });

        // Attach signed delivery note outside the lock (file I/O).
        $this->attachments->store($result, $deliveryNote, $actor);

        if ($applied && $receiptId !== null) {
            DB::afterCommit(function () use ($result, $actor): void {
                $spo = $result->purchaseOrder?->fresh(['salesOrder']);
                $this->notifier->notifyRole(RoleSlug::Procurement, [
                    'title_ar' => 'أمر استلام شحنة #'.$result->id,
                    'body_ar' => 'استلام جزئي/كامل على أمر مورد #'.$spo?->id.' — حالة الأمر: '.($spo?->status->labelAr() ?? '—'),
                    'action_url' => route('supplier-goods-receipts.show', $result, false),
                    'category' => 'supplier_po',
                ]);
                if ($spo?->sales_order_id) {
                    $order = $spo->salesOrder;
                    if ($order !== null) {
                        try {
                            app(SalesOrderWorkflow::class)->activateAfterSupplyReceipt($order, $actor);
                        } catch (DomainException $e) {
                            $this->notifier->notifyRole(RoleSlug::SalesManager, [
                                'title_ar' => 'استلام شحنة — تعذّر تفعيل أمر البيع',
                                'body_ar' => 'أمر استلام #'.$result->id.' — '.$e->getMessage(),
                                'action_url' => route('sales-orders.show', $spo->sales_order_id, false),
                                'category' => 'supplier_po',
                            ]);
                        }
                    }

                    $this->notifier->notifyRole(RoleSlug::SalesManager, [
                        'title_ar' => 'مخزون وارد — أمر البيع مفعّل للتوريد',
                        'body_ar' => 'أمر استلام #'.$result->id.' على أمر مورد #'.$spo->id
                            .' (أمر بيع #'.$spo->sales_order_id.') — الكمية الواصلة محجوزة ويمكن توريدها للعميل فورًا.',
                        'action_url' => route('sales-orders.show', $spo->sales_order_id, false),
                        'category' => 'supplier_po',
                    ]);
                }
            });
        }

        return $result->fresh([
            'lines.product',
            'purchaseOrder.salesOrder.customer',
            'receivedBy',
            'attachments',
        ]);
    }

    private function attachIssuedPoDocument(
        SupplierPurchaseOrder $spo,
        User $actor,
        ?UploadedFile $uploaded,
    ): void {
        $spo->loadMissing('attachments');

        $hasSystemPo = $spo->attachments->contains(
            fn ($a) => str_starts_with((string) $a->original_name, 'supplier-po-'.$spo->id)
        );

        if (! $hasSystemPo) {
            $binary = $this->poPdf->binary($spo);
            $this->attachments->storeContents(
                $spo,
                $binary,
                'supplier-po-'.$spo->id.'.pdf',
                'pdf',
                'application/pdf',
                $actor,
            );
        }

        if ($uploaded !== null) {
            $this->attachments->store($spo, $uploaded, $actor);
        }
    }
}
