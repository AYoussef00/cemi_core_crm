# Customer purchase orders, deals, and sales orders

**Phase:** 5 Prompt 1 (plat-guide #27)  
**Status:** Implemented — Phase 5 **Accepted**. Delivery/POD/deduct in Phase 6 (`docs/DELIVERIES.md`). Deal close Phase 7.  
**Resolved rules:** `PF7` (SO requires Customer PO), `ORG3` (Deal stored, 1:1 with Customer PO).

---

## Entities

| Entity | Role |
|---|---|
| **Customer purchase order (CPO)** | Customer commitment linked to an **approved or sent** quotation for the same customer |
| **Deal** | Stored commercial container; **exactly one** Deal per CPO (`customer_purchase_order_id` unique); status `open` until Phase 7 close |
| **Sales order (SO)** | Fulfillment draft under a CPO (and thus its Deal); **cannot** exist without `customer_purchase_order_id` |

---

## Convert from quotation (Rep path)

From an **approved/sent** quotation: **تحويل إلى أمر بيع** → upload customer PO PDF (required) → creates CPO + Deal + SO draft (full quote quantities). Attachment morph: `customer_purchase_order`.

**Procurement path (`PROC-BUY-ON-CPO` / `PROC-PARTIAL-SHIP`):** If the quote had procurement-sourced lines, a **supplier PO opens immediately** for Procurement. The SO stays draft (no rep submit / SM decide) until the **first warehouse receipt**. That receipt **auto-activates** the SO (approved) and reserves **available qty**; remaining stays on the open SPO. Further receipts top up reservation so partial customer deliveries can proceed.

## Customer PO rules (v1 honest)

1. Must belong to a Customer; must link to a Quotation in status `approved` or `sent` for that customer.
2. Line quantities must be &gt; 0 and **≤ quotation line quantity**.  
   - **Note:** `PF5` (whether PO qty may exceed quote) remains open — this prompt does **not** invent “may exceed”; it enforces ≤ quote.
3. Statuses: `open` | `cancelled` (no fake fulfillment states).
4. On create: create Deal 1:1 with status `open`. Audit `customer_po.created` + `deal.created`.
5. Cancel (SM only): blocked if any draft / pending_approval / approved SO exists.
6. Edit (open only): reference + notes; lines immutable after create in v1.

---

## Deal

| Field | Notes |
|---|---|
| `customer_id` | Same as PO |
| `customer_purchase_order_id` | Unique |
| `status` | `open` \| `closed` |
| `closed_by` / `closed_at` | Null until Phase 7 |

No `deals.close` UI in this prompt. Deal id shown read-only on PO/SO screens.

---

## Sales order lifecycle

```
draft → pending_approval → approved | rejected
approved → cancelled   (SM only; InventoryService.release remaining once)
```

| Step | Who | Effect |
|---|---|---|
| Create/edit draft | Rep (Own) / SM | Requires open CPO; line qty ≤ PO unallocated (draft+pending+approved count as allocated) |
| Submit | Rep / SM | Notify Sales Manager |
| Approve | SM | Credit gate stub + `InventoryService.reserve` per line (idempotency keys) + notify Warehouse + creator |
| Reject | SM | No reservation; notify creator |
| Cancel approved | SM only | `InventoryService.release` per line qty once; notify creator |

**Fulfillment (Phase 6):** `preparation_status`, `fulfillment_status`; multi-delivery + POD + deduct — see `docs/DELIVERIES.md`. Cancel approved releases **remaining** reservation only after partial delivery.

**Supplier buy on approve (`PROC-BUY-ON-SO`):** If available stock covers every line → reserve + approve (no supplier PO). If any line is short → create Arabic **supplier purchase order(s)** for the shortfall, notify Procurement, and **block approve** until warehouse receipt increases on-hand (`PROC-RECV`, preserves `INV3`). See `docs/SUPPLIER_PURCHASE_ORDERS.md`.

**Still out of scope here:** invoices / deal close (Phase 7).

---

## Inventory keys

| Event | Idempotency key pattern |
|---|---|
| Reserve on approve | `reserve:sales_order:{soId}:line:{lineId}` |
| Release on cancel | `release:sales_order:{soId}:line:{lineId}` |

Double-approve is status-idempotent and reservation-idempotent (same keys).

---

## Credit gate (live — Phase 7)

`App\Support\Credit\CreditExposure`:

- `forCustomer()` = sum of unpaid issued invoice balances (`CR7`).
- `allowsNewOrder()`: if `approved_credit_limit` is null → allow; else allow only when exposure **&lt;** limit.
- Wired on SO approve (CR1/CR2). See `docs/INVOICES.md`.

---

## Authorization (matrix §5.6)

| Action | Scope / role |
|---|---|
| View CPO | Rep **Cust**; SM/Exec **All**; WH/Accounts **Work** (empty until delivery links); Admin **Admin** |
| Manage CPO | Rep + SM |
| Cancel CPO | SM |
| View SO | Rep **Own**; SM/WH/Accounts/Exec **All**; Procurement **Referred** (empty until referral); Admin **Admin** |
| Draft + submit SO | Rep + SM |
| Approve/reject / cancel approved | SM only |
| Prices on SO screens | Hidden unless `prices.view` (Warehouse denied) |

---

## Routes

- `customer-pos.*` — index/create/store/show/edit/update/cancel  
- `sales-orders.*` — index/create/store/show/edit/update/submit/decide/cancel-approved  

---

## Designer handoff (mandatory)

After ship of Prompt 1: Designer polish for Customer PO + Sales Order Arabic RTL screens (list/create/show/edit, decide/cancel panels, Deal read-only context, Warehouse no-price copy). Preserve routes, authz, PF7/ORG3, reserve-on-approve, and empty states (no fake data). Delivery/POD UI is Phase 6 — do not invent.

---

## Related docs

- `WORKFLOWS.md` C2/C6/C7/C8/C17/C18 + SO transitions  
- `INVENTORY.md` + `InventoryService`  
- `CREDIT.md` (exposure policy; stub now wired)  
- `QUOTATIONS.md`, `ROLES_AND_PERMISSIONS.md` §5.6  
