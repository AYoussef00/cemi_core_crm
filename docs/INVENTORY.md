# Inventory — balances, adjustments, ledger (Phase 3)

**Status:** Phase 3 Prompt 3 — balances + adjustments + reserve/deduct/release service  
**Warehouse:** One singleton (`Warehouse` settings). No multi-warehouse filters in v1.

---

## Quantities

| Item | Convention |
|---|---|
| Storage | `DECIMAL(15, 3)` — supports fractional bulk units without inventing lot tracking |
| Available | **Computed:** `on_hand − reserved` (never stored as source of truth) |
| `INV3` | Available must never be negative. Operations that would violate this are **rejected** (not clamped). |

**Invariant:** `on_hand ≥ reserved ≥ 0` and therefore `available ≥ 0`.

---

## Stock balance

One row per `(warehouse_id, product_id)` for the singleton warehouse.

| Column | Meaning |
|---|---|
| `on_hand` | Physical quantity |
| `reserved` | Quantity reserved for approved SOs |

Missing rows are created as `0 / 0` inside the mutating transaction before `lockForUpdate`.

---

## Inventory adjustment (INV6)

| Step | Actor | Effect |
|---|---|---|
| Record | Warehouse (`inventory_adjustment.record`) | Creates `pending` request; **no** balance change |
| Approve | Sales Manager (`canApproveWorkflow('inventory_adjustment')`) | Applies signed `quantity_delta` to `on_hand` + ledger; terminal |
| Reject | Sales Manager | Terminal; balances unchanged |

- Warehouse **cannot** approve/reject their own adjustment.
- Administrator has **no** sales approval bypass for this workflow.
- Decreasing approval rejected when resulting `on_hand < reserved` (covers `on_hand < 0` when reserved is 0).

Statuses: `pending` → `approved` | `rejected` (terminal). Approval is **idempotent**.

---

## Reserve / deduct / release (`InventoryService`)

Service API for later Sales Order / delivery wiring. **No SO/Delivery UI in Phase 3.**

| Method | Balance effect | Ledger `event_type` | Typical caller (later) |
|---|---|---|---|
| `reserve($warehouse, $product, $qty, $actor, $reference, $idempotencyKey)` | `reserved += qty` | `reserve` | SO approve |
| `deduct(..., $qty, ..., $reference, $idempotencyKey, $reservationOwner?)` | `on_hand -= qty`, `reserved -= qty` | `deduct` | Delivery confirm |
| `release(..., $reference, $idempotencyKey, $qty?)` | `reserved -= qty` (on-hand unchanged) | `release` | SO cancel |

**INV3 / INV5 rules enforced server-side:**

- Reserve must not exceed available (`on_hand − reserved`).
- Deduct must not exceed global `reserved`, and when `$reservationOwner` is passed must not exceed remaining reserved **for that owner**.
- Release with `$qty = null` releases remaining for `$reference`; must not exceed that remaining or global reserved.
- Cancel after partial delivery: remaining = reserved_for_ref − deducted_against_ref.

**Remaining for a reservation owner (SO morph):**

```
remaining = sum(reserve for ref)
          − abs(sum(release for ref))
          − abs(sum(deduct where properties.reservation_owner_* = ref))
```

Helper: `remainingReservedFor($reference)`.

---

## Idempotency

| Mechanism | Detail |
|---|---|
| Column | `inventory_ledger_entries.idempotency_key` (nullable `UNIQUE`) |
| Callers | Must pass a stable key, e.g. `reserve:sales_order:{id}`, `deduct:delivery:{id}`, `release:sales_order:{id}` |
| Behavior | Same key returns the existing ledger row; balance is **not** applied twice |
| Concurrency | Transaction + `lockForUpdate` on balance; unique key catches races (`UniqueConstraintViolationException` → reload existing) |
| Adjustments | Continue without idempotency keys (status lock + terminal states) |

Morph `$reference` is any Eloquent model (SO/Delivery when they exist). Tests use `Tests\Support\Inventory\FakeInventoryDocument` (test-only table).

---

## Ledger

Append-only `inventory_ledger_entries` for applied effects:

| Event | Prompt | Wired to documents |
|---|---|---|
| `adjustment` | 2 | Adjustment approve |
| `reserve` | 3 | Service only (SO later) |
| `deduct` | 3 | Service only (delivery later) |
| `release` | 3 | Service only (SO cancel later) |

Balance update + ledger insert are **atomic**. Adjustment notifications fire **after commit**.

---

## Out of scope

Lot/batch (`INV4`), soft allocation (`INV7`), unit conversions, SKU, Sales Order / delivery UI / nav («حركات المخزون», «مهام تجهيز الطلبات»).
