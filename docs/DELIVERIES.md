# Warehouse preparation and deliveries

**Phase:** 6 Prompt 1 (plat-guide #30)  
**Status:** Implemented — Phase 6 overall **Accepted** (stakeholder 2026-10-16).  
**Rules:** `INV1` deduct on confirm with POD; `PF2` multi-delivery per SO; no driver GPS (`GPS4`/`GPS1` out of v1).

---

## Preparation (simple)

Approved sales orders that are not fully delivered appear in **مهام تجهيز الطلبات** (`warehouse.preparation.index`).

| Field on SO | Values |
|---|---|
| `preparation_status` | `pending` \| `ready` |
| `preparation_notes_ar` | Optional |
| `preparation_ready_at` / `preparation_ready_by_user_id` | Set when marked ready |

Not a full WMS — checklist only. No invented dispatch/GPS states.

---

## Delivery lifecycle

```
draft → pending_pod (optional) → confirmed
draft | pending_pod → cancelled
```

Skipped from WORKFLOWS `dispatched` / `failed` — no driver GPS in v1.

| Step | Effect |
|---|---|
| Create draft | Against **approved** SO; line qty ≤ undelivered (confirmed + open drafts/pending_pod count as allocated) |
| Upload POD | Private attachment morph alias `delivery` (PDF/images) |
| Confirm | Requires ≥1 POD attachment; `InventoryService.deduct` per line with `reservationOwner` = SO; idempotency `deduct:delivery:{id}:line:{lineId}` |
| Cancel (before confirm) | No deduct; audit `delivery.cancelled` |

---

## Caps (INV1 / C5)

1. Delivery line qty ≤ SO line undelivered (SO qty − confirmed − other open deliveries).
2. On confirm: delivery total ≤ `remainingReservedFor(SO)`.
3. Concurrent-safe: SO + delivery row locks on confirm; re-check caps.

---

## SO fulfillment progress

| `fulfillment_status` | Meaning |
|---|---|
| `pending` | No confirmed deliveries |
| `partially_delivered` | Some confirmed qty |
| `fully_delivered` | All SO line qtys confirmed |

Updated on each successful confirm. Cancel approved SO after partial delivery releases **only remaining** reservation (line qty − confirmed).

---

## POD

- Model: attachments on `Delivery` via `HasAttachments` + `AttachmentService`.
- Confirm **blocked** without at least one attachment.
- Private disk; download authorized via `delivery.view`.

---

## Authorization (§5.7)

| Action | Who |
|---|---|
| View preparation/deliveries | Warehouse, SM (oversight), Executive (read), Admin |
| Manage (create/POD/confirm/prep ready) | Warehouse + SM |
| Cancel before confirm | Warehouse + SM |
| Prices on warehouse screens | **No** (`prices.view` absent for Warehouse) |
| Sales Rep | **N** (matrix) — no delivery UI |
| Accounts | Report only (`reports.delivery`) — **no** `delivery.view` in this prompt |

---

## Notifications / audit

| Event | Notify | Audit |
|---|---|---|
| Confirm | SM role + SO creator (Rep) | `delivery.confirmed` |
| Create / cancel / prep ready | — | `delivery.created` / `delivery.cancelled` / `sales_order.preparation_ready` |
| POD upload/remove | — | `attachment.uploaded` / `attachment.removed` |

---

## Routes

- `warehouse.preparation.index` / `warehouse.preparation.ready`
- `deliveries.*` — index/create/store/show/edit/update/pending-pod/confirm/cancel/pod.*

---

## Designer handoff (mandatory)

Polish preparation queue + delivery list/create/show/edit (Arabic RTL): status badges, POD upload panel, confirm/cancel actions, Warehouse no-price copy, empty states. Preserve routes, authz, multi-delivery caps, POD-before-confirm, deduct-once. Do **not** invent driver GPS or labels printing. Invoicing is Accounts-owned (`docs/INVOICES.md`) — Warehouse screens stay without invoice/price CTAs.

---

## Out of scope (warehouse)

Lot tracking (`INV4`); external drivers; invoice issue/prices on warehouse UI.
