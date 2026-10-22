<?php

namespace App\Support\Inventory;

use App\Models\InventoryAdjustment;
use App\Models\InventoryLedgerEntry;
use App\Models\Product;
use App\Models\ProductStockBalance;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Audit\AuditLogger;
use App\Support\Authorization\RoleSlug;
use App\Support\Notifications\InternalNotifier;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single-warehouse stock mutations + INV3 enforcement.
 * Reserve/deduct/release helpers are ready for SO/delivery wiring later — not exposed via UI here.
 */
class InventoryService
{
    public function __construct(
        private AuditLogger $audit,
        private InternalNotifier $notifier,
    ) {}

    public function requireWarehouse(): Warehouse
    {
        $warehouse = Warehouse::current();
        if ($warehouse === null) {
            throw new DomainException('لم يُعدّ المستودع بعد. أكمل إعداد المستودع من الإعدادات أولاً.');
        }

        return $warehouse;
    }

    /**
     * Ensure balance row exists, then lock it. Must run inside an open transaction.
     */
    public function lockBalance(Warehouse $warehouse, Product $product): ProductStockBalance
    {
        ProductStockBalance::query()->firstOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
            ],
            [
                'on_hand' => '0.000',
                'reserved' => '0.000',
            ]
        );

        $balance = ProductStockBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->first();

        if ($balance === null) {
            throw new RuntimeException('تعذر قفل رصيد المخزون.');
        }

        return $balance;
    }

    public function balanceFor(Product $product, ?Warehouse $warehouse = null): ?ProductStockBalance
    {
        $warehouse ??= Warehouse::current();
        if ($warehouse === null) {
            return null;
        }

        return ProductStockBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->first();
    }

    /**
     * @param  array{product_id: int, quantity_delta: string, reason_ar: string}  $payload
     */
    public function recordAdjustment(User $actor, array $payload): InventoryAdjustment
    {
        $warehouse = $this->requireWarehouse();
        $product = Product::query()->findOrFail($payload['product_id']);

        $adjustment = DB::transaction(function () use ($actor, $payload, $warehouse, $product): InventoryAdjustment {
            // Touch balance existence early so concurrent approves have a row to lock.
            $this->lockBalance($warehouse, $product);

            $adjustment = InventoryAdjustment::query()->create([
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'quantity_delta' => $payload['quantity_delta'],
                'reason_ar' => $payload['reason_ar'],
                'status' => InventoryAdjustmentStatus::Pending,
                'recorded_by_user_id' => $actor->id,
            ]);

            $this->audit->record('inventory_adjustment.recorded', $adjustment, [
                'product_id' => $product->id,
                'product_name_ar' => $product->name_ar,
                'warehouse_id' => $warehouse->id,
                'quantity_delta' => (string) $adjustment->quantity_delta,
                'status' => $adjustment->status->value,
            ]);

            return $adjustment;
        });

        DB::afterCommit(function () use ($adjustment, $product): void {
            $this->notifier->notifyRole(RoleSlug::SalesManager, [
                'title_ar' => 'تسوية مخزون بانتظار الاعتماد',
                'body_ar' => 'منتج: '.$product->name_ar.' — الكمية: '.$adjustment->quantity_delta,
                'action_url' => route('inventory.adjustments.show', $adjustment, false),
                'category' => 'inventory_adjustment',
            ]);
        });

        return $adjustment->fresh(['product', 'warehouse', 'recordedBy']);
    }

    public function approveAdjustment(InventoryAdjustment $adjustment, User $actor, ?string $notes = null): InventoryAdjustment
    {
        $applied = false;

        $result = DB::transaction(function () use ($adjustment, $actor, $notes, &$applied): InventoryAdjustment {
            $adjustment = InventoryAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

            if ($adjustment->status === InventoryAdjustmentStatus::Approved) {
                return $adjustment->fresh(['product', 'warehouse', 'recordedBy', 'decidedBy']);
            }

            if ($adjustment->status !== InventoryAdjustmentStatus::Pending) {
                throw new DomainException('لا يمكن اعتماد تسوية ليست بانتظار الاعتماد.');
            }

            $product = Product::query()->findOrFail($adjustment->product_id);
            $warehouse = Warehouse::query()->findOrFail($adjustment->warehouse_id);
            $balance = $this->lockBalance($warehouse, $product);

            $delta = (float) $adjustment->quantity_delta;
            $onHandBefore = (float) $balance->on_hand;
            $reservedBefore = (float) $balance->reserved;
            $onHandAfter = $onHandBefore + $delta;

            $this->assertOnHandAllowsReserved($onHandAfter, $reservedBefore);

            $balance->fill([
                'on_hand' => $this->formatQty($onHandAfter),
            ])->save();

            InventoryLedgerEntry::query()->create([
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'event_type' => InventoryLedgerEventType::Adjustment,
                'quantity_delta' => $this->formatQty($delta),
                'on_hand_before' => $this->formatQty($onHandBefore),
                'on_hand_after' => $this->formatQty($onHandAfter),
                'reserved_before' => $this->formatQty($reservedBefore),
                'reserved_after' => $this->formatQty($reservedBefore),
                'reference_type' => $adjustment->getMorphClass(),
                'reference_id' => $adjustment->id,
                'actor_user_id' => $actor->id,
                'properties' => [
                    'adjustment_id' => $adjustment->id,
                    'reason_ar' => $adjustment->reason_ar,
                ],
                'created_at' => now(),
            ]);

            $adjustment->fill([
                'status' => InventoryAdjustmentStatus::Approved,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
                'decision_notes' => $notes,
            ])->save();

            $this->audit->record('inventory_adjustment.approved', $adjustment, [
                'product_id' => $product->id,
                'quantity_delta' => (string) $adjustment->quantity_delta,
                'on_hand_before' => $this->formatQty($onHandBefore),
                'on_hand_after' => $this->formatQty($onHandAfter),
                'reserved' => $this->formatQty($reservedBefore),
                'available_after' => $this->formatQty($onHandAfter - $reservedBefore),
                'status' => $adjustment->status->value,
            ]);

            $applied = true;

            return $adjustment->fresh(['product', 'warehouse', 'recordedBy', 'decidedBy']);
        });

        if ($applied) {
            DB::afterCommit(function () use ($result): void {
                $this->notifyRequester($result, 'تم اعتماد تسوية المخزون.');
            });
        }

        return $result;
    }

    public function rejectAdjustment(InventoryAdjustment $adjustment, User $actor, ?string $notes = null): InventoryAdjustment
    {
        $applied = false;

        $result = DB::transaction(function () use ($adjustment, $actor, $notes, &$applied): InventoryAdjustment {
            $adjustment = InventoryAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

            if ($adjustment->status === InventoryAdjustmentStatus::Rejected) {
                return $adjustment->fresh(['product', 'warehouse', 'recordedBy', 'decidedBy']);
            }

            if ($adjustment->status !== InventoryAdjustmentStatus::Pending) {
                throw new DomainException('لا يمكن رفض تسوية ليست بانتظار الاعتماد.');
            }

            $adjustment->fill([
                'status' => InventoryAdjustmentStatus::Rejected,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
                'decision_notes' => $notes,
            ])->save();

            $this->audit->record('inventory_adjustment.rejected', $adjustment, [
                'product_id' => $adjustment->product_id,
                'quantity_delta' => (string) $adjustment->quantity_delta,
                'status' => $adjustment->status->value,
            ]);

            $applied = true;

            return $adjustment->fresh(['product', 'warehouse', 'recordedBy', 'decidedBy']);
        });

        if ($applied) {
            DB::afterCommit(function () use ($result): void {
                $this->notifyRequester($result, 'رُفضت تسوية المخزون.');
            });
        }

        return $result;
    }

    /**
     * Available qty = on_hand − reserved (INV3).
     */
    public function availableQty(Product $product, ?Warehouse $warehouse = null): float
    {
        $warehouse ??= $this->requireWarehouse();
        $balance = $this->balanceFor($product, $warehouse);

        if ($balance === null) {
            return 0.0;
        }

        return max(0.0, (float) $balance->on_hand - (float) $balance->reserved);
    }

    /**
     * Goods receipt: increase on_hand immediately (PROC-RECV). Idempotent on $idempotencyKey.
     *
     * @param  array<string, mixed>|null  $properties
     */
    public function receive(
        Warehouse $warehouse,
        Product $product,
        float|string $qty,
        User $actor,
        Model $reference,
        string $idempotencyKey,
        ?array $properties = null,
    ): InventoryLedgerEntry {
        $qty = (float) $qty;
        $this->assertPositiveQty($qty, 'كمية الاستلام يجب أن تكون موجبة.');

        $existing = $this->findByIdempotencyKey(trim($idempotencyKey));
        if ($existing !== null) {
            $this->assertIdempotentQuantityMatches($existing, $qty);

            return $existing;
        }

        return $this->applyIdempotentMutation(
            $idempotencyKey,
            function () use ($warehouse, $product, $qty, $actor, $reference, $idempotencyKey, $properties): InventoryLedgerEntry {
                $balance = $this->lockBalance($warehouse, $product);

                $onHandBefore = (float) $balance->on_hand;
                $reservedBefore = (float) $balance->reserved;
                $onHandAfter = $onHandBefore + $qty;

                $this->assertOnHandAllowsReserved($onHandAfter, $reservedBefore);

                $balance->fill(['on_hand' => $this->formatQty($onHandAfter)])->save();

                return $this->appendLedger(
                    warehouse: $warehouse,
                    product: $product,
                    eventType: InventoryLedgerEventType::Receipt,
                    quantityDelta: $this->formatQty($qty),
                    onHandBefore: $onHandBefore,
                    onHandAfter: $onHandAfter,
                    reservedBefore: $reservedBefore,
                    reservedAfter: $reservedBefore,
                    reference: $reference,
                    actor: $actor,
                    idempotencyKey: $idempotencyKey,
                    properties: $properties,
                );
            }
        );
    }

    /**
     * Reserve qty against available (INV1 / INV3). Idempotent on $idempotencyKey.
     * Later: call from Sales Order approve with reference = SO.
     *
     * @param  array<string, mixed>|null  $properties
     */
    public function reserve(
        Warehouse $warehouse,
        Product $product,
        float|string $qty,
        User $actor,
        Model $reference,
        string $idempotencyKey,
        ?array $properties = null,
    ): InventoryLedgerEntry {
        $qty = (float) $qty;
        $this->assertPositiveQty($qty, 'كمية الحجز يجب أن تكون موجبة.');

        return $this->applyIdempotentMutation(
            $idempotencyKey,
            function () use ($warehouse, $product, $qty, $actor, $reference, $idempotencyKey, $properties): InventoryLedgerEntry {
                $balance = $this->lockBalance($warehouse, $product);
                $this->assertCanReserve($balance, $qty);

                $onHandBefore = (float) $balance->on_hand;
                $reservedBefore = (float) $balance->reserved;
                $reservedAfter = $reservedBefore + $qty;

                $this->assertOnHandAllowsReserved($onHandBefore, $reservedAfter);

                $balance->fill(['reserved' => $this->formatQty($reservedAfter)])->save();

                return $this->appendLedger(
                    warehouse: $warehouse,
                    product: $product,
                    eventType: InventoryLedgerEventType::Reserve,
                    quantityDelta: $this->formatQty($qty),
                    onHandBefore: $onHandBefore,
                    onHandAfter: $onHandBefore,
                    reservedBefore: $reservedBefore,
                    reservedAfter: $reservedAfter,
                    reference: $reference,
                    actor: $actor,
                    idempotencyKey: $idempotencyKey,
                    properties: $properties,
                );
            }
        );
    }

    /**
     * Deduct qty from on_hand and reserved (INV1 / INV3). Idempotent on $idempotencyKey.
     * Later: call from delivery confirm with reference = delivery and $reservationOwner = SO.
     *
     * @param  array<string, mixed>|null  $properties
     */
    public function deduct(
        Warehouse $warehouse,
        Product $product,
        float|string $qty,
        User $actor,
        Model $reference,
        string $idempotencyKey,
        ?Model $reservationOwner = null,
        ?array $properties = null,
    ): InventoryLedgerEntry {
        $qty = (float) $qty;
        $this->assertPositiveQty($qty, 'كمية الخصم يجب أن تكون موجبة.');

        return $this->applyIdempotentMutation(
            $idempotencyKey,
            function () use ($warehouse, $product, $qty, $actor, $reference, $idempotencyKey, $reservationOwner, $properties): InventoryLedgerEntry {
                $balance = $this->lockBalance($warehouse, $product);
                $this->assertCanDeduct($balance, $qty);

                if ($reservationOwner !== null) {
                    $remaining = $this->remainingReservedFor($reservationOwner);
                    if ($qty > $remaining + 1e-9) {
                        throw new DomainException(
                            'لا يمكن الخصم بأكثر من الكمية المحجوزة المتبقية لهذا المرجع.'
                        );
                    }
                }

                $onHandBefore = (float) $balance->on_hand;
                $reservedBefore = (float) $balance->reserved;
                $onHandAfter = $onHandBefore - $qty;
                $reservedAfter = $reservedBefore - $qty;

                $this->assertOnHandAllowsReserved($onHandAfter, $reservedAfter);

                $balance->fill([
                    'on_hand' => $this->formatQty($onHandAfter),
                    'reserved' => $this->formatQty($reservedAfter),
                ])->save();

                $props = $properties ?? [];
                if ($reservationOwner !== null) {
                    $props['reservation_owner_type'] = $reservationOwner->getMorphClass();
                    $props['reservation_owner_id'] = $reservationOwner->getKey();
                }

                return $this->appendLedger(
                    warehouse: $warehouse,
                    product: $product,
                    eventType: InventoryLedgerEventType::Deduct,
                    quantityDelta: $this->formatQty(-$qty),
                    onHandBefore: $onHandBefore,
                    onHandAfter: $onHandAfter,
                    reservedBefore: $reservedBefore,
                    reservedAfter: $reservedAfter,
                    reference: $reference,
                    actor: $actor,
                    idempotencyKey: $idempotencyKey,
                    properties: $props,
                );
            }
        );
    }

    /**
     * Release reserved qty (INV5). If $qty is null, releases remaining for $reference.
     * Idempotent on $idempotencyKey (exactly-once cancel). Later: SO cancel.
     *
     * @param  array<string, mixed>|null  $properties
     */
    public function release(
        Warehouse $warehouse,
        Product $product,
        User $actor,
        Model $reference,
        string $idempotencyKey,
        float|string|null $qty = null,
        ?array $properties = null,
    ): InventoryLedgerEntry {
        return $this->applyIdempotentMutation(
            $idempotencyKey,
            function () use ($warehouse, $product, $actor, $reference, $idempotencyKey, $qty, $properties): InventoryLedgerEntry {
                $balance = $this->lockBalance($warehouse, $product);

                $remainingForRef = $this->remainingReservedFor($reference);
                $releaseQty = $qty === null ? $remainingForRef : (float) $qty;

                if ($releaseQty < -1e-9) {
                    throw new DomainException('كمية الإفراج لا يمكن أن تكون سالبة.');
                }

                if ($releaseQty > $remainingForRef + 1e-9) {
                    throw new DomainException(
                        'لا يمكن الإفراج بأكثر من الكمية المحجوزة المتبقية لهذا المرجع.'
                    );
                }

                if ($releaseQty > (float) $balance->reserved + 1e-9) {
                    throw new DomainException('لا يمكن الإفراج بأكثر من المحجوز الإجمالي.');
                }

                $onHandBefore = (float) $balance->on_hand;
                $reservedBefore = (float) $balance->reserved;
                $reservedAfter = $reservedBefore - $releaseQty;

                $this->assertOnHandAllowsReserved($onHandBefore, $reservedAfter);

                if ($releaseQty > 1e-9) {
                    $balance->fill(['reserved' => $this->formatQty($reservedAfter)])->save();
                }

                return $this->appendLedger(
                    warehouse: $warehouse,
                    product: $product,
                    eventType: InventoryLedgerEventType::Release,
                    quantityDelta: $this->formatQty(-$releaseQty),
                    onHandBefore: $onHandBefore,
                    onHandAfter: $onHandBefore,
                    reservedBefore: $reservedBefore,
                    reservedAfter: $reservedAfter,
                    reference: $reference,
                    actor: $actor,
                    idempotencyKey: $idempotencyKey,
                    properties: $properties,
                );
            }
        );
    }

    /**
     * Remaining reserved qty for a reservation-owner reference (SO morph).
     * = sum(reserve) − abs(sum(release)) − abs(sum(deduct against owner)).
     */
    public function remainingReservedFor(Model $reference): float
    {
        $type = $reference->getMorphClass();
        $id = $reference->getKey();

        $reserved = (float) InventoryLedgerEntry::query()
            ->where('event_type', InventoryLedgerEventType::Reserve)
            ->where('reference_type', $type)
            ->where('reference_id', $id)
            ->sum('quantity_delta');

        $released = abs((float) InventoryLedgerEntry::query()
            ->where('event_type', InventoryLedgerEventType::Release)
            ->where('reference_type', $type)
            ->where('reference_id', $id)
            ->sum('quantity_delta'));

        $deducted = abs((float) InventoryLedgerEntry::query()
            ->where('event_type', InventoryLedgerEventType::Deduct)
            ->where('properties->reservation_owner_type', $type)
            ->where('properties->reservation_owner_id', $id)
            ->sum('quantity_delta'));

        return max(0.0, $reserved - $released - $deducted);
    }

    /**
     * Remaining reserved qty attributed to one sales-order line (via ledger properties).
     */
    public function remainingReservedForOrderLine(SalesOrder $order, SalesOrderLine $line): float
    {
        $type = $order->getMorphClass();
        $id = $order->getKey();
        $lineId = $line->id;

        $reserved = (float) InventoryLedgerEntry::query()
            ->where('event_type', InventoryLedgerEventType::Reserve)
            ->where('reference_type', $type)
            ->where('reference_id', $id)
            ->where('properties->sales_order_line_id', $lineId)
            ->sum('quantity_delta');

        $deducted = abs((float) InventoryLedgerEntry::query()
            ->where('event_type', InventoryLedgerEventType::Deduct)
            ->where('properties->reservation_owner_type', $type)
            ->where('properties->reservation_owner_id', $id)
            ->where('properties->sales_order_line_id', $lineId)
            ->sum('quantity_delta'));

        return max(0.0, $reserved - $deducted);
    }

    /**
     * INV3: on_hand must remain ≥ reserved (available ≥ 0).
     */
    public function assertOnHandAllowsReserved(float $onHand, float $reserved): void
    {
        if ($reserved < 0) {
            throw new DomainException('لا يمكن أن يكون المحجوز سالبًا.');
        }

        if ($onHand < $reserved) {
            throw new DomainException(
                'لا يمكن تنفيذ العملية: الرصيد المتاح غير كافٍ (المتاح = الرصيد − المحجوز، ولا يُسمح بأن يصبح سالبًا).'
            );
        }
    }

    /**
     * @throws DomainException
     */
    public function assertCanReserve(ProductStockBalance $balance, float $qty): void
    {
        $this->assertPositiveQty($qty, 'كمية الحجز يجب أن تكون موجبة.');

        $available = (float) $balance->on_hand - (float) $balance->reserved;
        if ($qty > $available + 1e-9) {
            throw new DomainException('لا يمكن الحجز: الكمية المطلوبة تتجاوز المتاح.');
        }
    }

    /**
     * @throws DomainException
     */
    public function assertCanDeduct(ProductStockBalance $balance, float $qty): void
    {
        $this->assertPositiveQty($qty, 'كمية الخصم يجب أن تكون موجبة.');

        if ($qty > (float) $balance->reserved + 1e-9) {
            throw new DomainException('لا يمكن الخصم بأكثر من الكمية المحجوزة المتبقية.');
        }

        $onHandAfter = (float) $balance->on_hand - $qty;
        $reservedAfter = (float) $balance->reserved - $qty;
        $this->assertOnHandAllowsReserved($onHandAfter, $reservedAfter);
    }

    public function formatQty(float|string $qty): string
    {
        return number_format((float) $qty, 3, '.', '');
    }

    public function findByIdempotencyKey(string $key): ?InventoryLedgerEntry
    {
        return InventoryLedgerEntry::query()->where('idempotency_key', $key)->first();
    }

    /**
     * @param  callable(): InventoryLedgerEntry  $mutator
     */
    private function applyIdempotentMutation(string $idempotencyKey, callable $mutator): InventoryLedgerEntry
    {
        $key = trim($idempotencyKey);
        if ($key === '') {
            throw new DomainException('مفتاح عدم التكرار مطلوب لعمليات الحجز/الخصم/الإفراج.');
        }

        $existing = $this->findByIdempotencyKey($key);
        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($key, $mutator): InventoryLedgerEntry {
                $existing = $this->findByIdempotencyKey($key);
                if ($existing !== null) {
                    return $existing;
                }

                return $mutator();
            });
        } catch (UniqueConstraintViolationException) {
            $again = $this->findByIdempotencyKey($key);
            if ($again !== null) {
                return $again;
            }

            throw new RuntimeException('تعذر تطبيق حركة المخزون بشكل آمن من التكرار.');
        }
    }

    /**
     * Ensure an idempotent hit matches the intended quantity (guards against stale keys).
     */
    public function assertIdempotentQuantityMatches(InventoryLedgerEntry $entry, float $expectedQty): void
    {
        if (abs(abs((float) $entry->quantity_delta) - abs($expectedQty)) > 1e-9) {
            throw new DomainException(
                'تعارض في سجل المخزون لنفس مفتاح العملية (كمية مختلفة). راجع حركات المخزون أو أعد الاستلام.'
            );
        }
    }

    /**
     * @param  array<string, mixed>|null  $properties
     */
    private function appendLedger(
        Warehouse $warehouse,
        Product $product,
        InventoryLedgerEventType $eventType,
        string $quantityDelta,
        float $onHandBefore,
        float $onHandAfter,
        float $reservedBefore,
        float $reservedAfter,
        Model $reference,
        User $actor,
        string $idempotencyKey,
        ?array $properties,
    ): InventoryLedgerEntry {
        return InventoryLedgerEntry::query()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'event_type' => $eventType,
            'quantity_delta' => $quantityDelta,
            'on_hand_before' => $this->formatQty($onHandBefore),
            'on_hand_after' => $this->formatQty($onHandAfter),
            'reserved_before' => $this->formatQty($reservedBefore),
            'reserved_after' => $this->formatQty($reservedAfter),
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
            'idempotency_key' => $idempotencyKey,
            'actor_user_id' => $actor->id,
            'properties' => $properties,
            'created_at' => now(),
        ]);
    }

    private function assertPositiveQty(float $qty, string $message): void
    {
        if ($qty <= 0) {
            throw new DomainException($message);
        }
    }

    private function notifyRequester(InventoryAdjustment $adjustment, string $body): void
    {
        $requester = $adjustment->recordedBy;
        if ($requester === null) {
            return;
        }

        $this->notifier->notifyUser($requester, [
            'title_ar' => 'نتيجة تسوية المخزون',
            'body_ar' => $body.' منتج: '.($adjustment->product?->name_ar ?? '#'.$adjustment->product_id),
            'action_url' => route('inventory.adjustments.show', $adjustment, false),
            'category' => 'inventory_adjustment',
        ]);
    }
}
