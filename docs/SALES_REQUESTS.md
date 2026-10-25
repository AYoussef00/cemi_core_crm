# Sales requests & procurement path (Phase 4)

**Status:** Prompt 1 + Prompt 2 linkage (`quotation_linked`)  
**Auto routing from stock (`SR-AUTO-STOCK`):** **decided** — line availability is computed from warehouse available qty vs line quantity. Rep does not choose.

---

## Sales request statuses

| Status | Meaning |
|---|---|
| `draft` | Editable; not visible as Referred to Procurement |
| `awaiting_procurement` | Submitted with ≥1 `needs_procurement` line; open procurement tasks exist |
| `ready_for_pricing` | All lines available on submit, **or** every open procurement task has recorded cost |
| `quotation_linked` | A quotation was created from this request (Prompt 2) |
| `cancelled` | Terminal; open procurement tasks cancelled with the request |

**Submit action:** moves `draft` → `awaiting_procurement` **or** `ready_for_pricing`.  
**Quotation create:** moves `ready_for_pricing` → `quotation_linked`. Cancel of linked requests is blocked — cancel/supersede the quotation first.

## Origin visit (optional handoff)

A visit **does not** create a sales request. Completing a visit records outcome / `customer_needs` only.

Optional `sales_requests.origin_visit_id` (nullable, completed visit, same customer) records that the draft was started from that visit. Direct create from the customer profile or the sales-request list remains valid with a null origin.

UI handoff (no new routes):

- Completed visit show: CTA to `sales-requests.create?customer_id=&visit_id=`
- Customer profile: CTA to `sales-requests.create?customer_id=`
- Create form from a visit locks the customer, prefills notes from `customer_needs` (truncated to 2000), and does not invent product lines.

See `docs/QUOTATIONS.md` for pricing / send.

---

## Line availability

| Value | Effect |
|---|---|
| `available` | qty ≤ warehouse available; no procurement task; quotation cost = product `purchase_cost` |
| `needs_procurement` | qty > warehouse available (or no balance); on submit: one **procurement task per line**; quotation cost = recorded `supplier_cost` |

**Resolution (`SR-AUTO-STOCK`):** `LineAvailabilityResolver` on store/update ignores any client-sent availability and sets the value from `InventoryService` available qty. The create/edit form shows a read-only badge updated as product/qty change.

---

## Procurement tasks

**Model:** one task per `needs_procurement` line (`unique` on `sales_request_line_id`).

| Status | Meaning |
|---|---|
| `open` | Awaiting supplier cost |
| `cost_recorded` | `supplier_cost` DECIMAL(15,2) SAR recorded; **mandatory supplier-quote PDF** attached; copied to line for pricing |
| `cancelled` | Terminal |

When **no open** tasks remain and **at least one** cost is recorded → request → `ready_for_pricing`.

**Supplier quote PDF (`PROC-QUOTE-PDF`):** required on cost record (PDF only). Stored as a private attachment on the procurement task.

Authz: `procurement.view` / `.manage` / `.cancel` per matrix. Warehouse has **no** sales-request or procurement view (no prices).

---

## Scopes (sales_requests.view)

| Role | Scope |
|---|---|
| Sales Rep | Cust (customer’s assigned rep) |
| Sales Manager | All |
| Procurement | Referred = has procurement tasks |
| Accounts | Work = empty until later links |
| Warehouse | N |
| Executive / Admin | All / Admin (read) |
