# Domain Glossary

**Document type:** Phase 0 — terminology and relationships  
**Status:** Updated with stakeholder decisions (Prompt 2)  
**Rule:** Do not treat names as final schema identifiers until migrations are designed.

English terms for entities/code; Arabic for UI copy as applicable.

---

## 1. Core terms

### Customer

A commercial **organization / business establishment** that buys (or may buy) products. Holds contacts, locations, photographs, **credit limit**, and document history. **Individuals are out of v1 scope** (`ORG6` resolved).

**UI:** `customers.name_ar` is labeled **اسم المنشأة** (establishment name). No customer-type discriminator or individual-specific fields in v1.

**Relationships:** Contacts, Locations, Visits, Sales Requests, Quotations, Customer Purchase Orders, Sales Orders, Invoices, Collections, Deal(s).

**Credit:** `credit_exposure` = sum of **unpaid issued invoice balances** only. Approved undelivered Sales Orders are excluded. If exposure **≥** credit limit, **new orders are blocked** until payments reduce exposure **below** the limit.

---

### Product

Sellable catalog item. v1 master fields: Arabic **name**, one primary **unit of measure**, optional **purchase cost**, and technical **specifications** (flexible label/value/optional-unit rows). Documented density uses the Arabic label **الكثافة**. No SKU/numbering, stock balance, selling price, or category fields in Phase 3 Prompt 1.

**Ownership:** Administrator manages the product master; Procurement may edit **purchase cost only**.

**Relationships (later phases):** inventory balances/movements, sales lines, procurement quotes.

---

### Unit of Measure (UoM)

Admin-managed unit catalog (`name_ar`, optional `symbol_ar`, active flag). Each product has **one** primary unit. **Conversions between units are out of scope** until policy is defined.

---

### Visit (Customer Visit)

Recorded interaction with a customer (scheduled or completed), with outcome and notes. Does not create commercial obligation by itself.

**Assignee:** always the customer’s assigned Sales Representative (no alternate override).

**GPS for visits:** **excluded from v1** (no visit check-in / tracking). **Delivery driver GPS** remains out of initial release.

---

### Sales Request

Internal request of products/quantities before or alongside pricing. May include unavailable lines that route to procurement.

Prefer the full name **Sales Request** (avoid bare “request”).

---

### Quotation

Priced offer to the customer. **Sales Manager** sets selling price by adding **margin/markup to cost** and **approves** price/margin. The **approved** customer quotation shows prices **including VAT**.

**Discount and rounding:** undecided — do not invent formulas (`PM4a`, `PM4b`).

Sent via **email** in the initial release (WhatsApp deferred).

---

### Supplier quote

Supplier price/availability used when products are unavailable or need external cost. Feeds cost input for Sales Manager pricing.

---

### Customer purchase order (Customer PO)

Customer’s order (often uploaded), typically linked to a quotation. Source of **ordered** quantity. May be fulfilled by **multiple Sales Orders**.

Not the same as a supplier purchase order.

---

### Sales order (SO)

Internal fulfillment order. **On approval:** inventory is **reserved**. May have **multiple deliveries**. **On cancellation:** release only remaining reservation **exactly once** (ledger/audit; no double release).

**Relationships:** **must** link to a Customer PO in v1 (`PF7` — SO without PO not allowed); has Deliveries; invoice eligibility comes from confirmed deliveries.

---

### Delivery / fulfillment batch

A delivery against an SO. Quantity must **not exceed** the SO’s **undelivered** quantity. **On confirmation with proof of delivery:** stock is **deducted** (on-hand and reserved).

Distinct from inventory lot/batch unless lot tracking is later approved (`INV4`).

---

### Delivery note

Operational delivery document (quantities, customer, location, driver). Accompanied by **proof of delivery (POD)** before confirmation.

**Driver GPS:** not required for initial release.

---

### Proof of delivery (POD)

Evidence that delivery occurred (e.g., signed document / approved evidence attachment). **Required** before delivery confirmation and before invoicing.

---

### Invoice

Financial charge for **confirmed delivered** quantities only, issued **only after POD**. Quantities and amounts must be **traceable** to confirmed deliveries.

**Issued invoices must not be silently edited or deleted.** Corrections use **credit notes** / **debit notes** linked to the original invoice, with **Accounts approval** and auditable effects.

**Unpaid balance (`CR7`):** issued amount − **confirmed** allocated collections − approved credit notes + approved debit notes. Draft collections do not reduce unpaid.

**Fully paid (`TX5b`):** unpaid balance = 0. Unpaid balances of issued invoices form **credit exposure**.

Target market **Saudi Arabia**. E-invoicing integration is **not** in the initial release; legal obligations are not waived. Accountant review and applicable **ZATCA** confirmation are **pre-launch** requirements.

---

### Credit note / debit note (proposed correction instruments)

| Instrument | Proposed purpose |
|---|---|
| Credit note | Reduce customer liability and/or reverse delivered/returned quantities against an issued invoice |
| Debit note | Increase liability if under-billed, linked to the original invoice |

Preserve the original issued invoice. Inventory effects (if any) must be ledger-audited. Exact return timing and ZATCA presentation: pre-launch / open detail.

---

### Payment / collection

Recorded receipt allocated to invoice(s), reducing unpaid balances and credit exposure. Distinct from sending a reminder email.

---

### Deal

Commercial thread linking related documents through financial settlement.

**Persistence (`ORG3` — confirmed):** **stored entity**. **v1:** one Deal ↔ one Customer PO; Sales Orders under that PO belong to the same Deal.

**Closure (confirmed):** a deal **closes only after all its invoices are fully paid**. It **cannot** close while any linked invoice has an unpaid balance. **Accounts** closes **manually** (status, actor, timestamp stored on the Deal).

---

## 2. Inventory terms (confirmed behavior)

| Term | Meaning |
|---|---|
| On-hand | Physical quantity in the **single** v1 warehouse |
| Reserved | Quantity held for **approved** Sales Orders not yet deducted |
| Available | On-hand − reserved (**confirmed**). Must never go negative (`INV3`). |
| Reserve event | SO approved → increase reserved (idempotent per approval) |
| Deduct event | Delivery confirmed → decrease on-hand and reserved for delivered qty |
| Release event | SO cancelled → decrease reserved by **remaining** reservation only, **once** |

All reserve / deduct / release events must appear in an **inventory ledger / audit** trail.

---

## 3. Pricing terms

| Term | Status |
|---|---|
| Cost | Supplier / purchase cost from Procurement (`PM1` resolved) |
| Margin | Absolute SAR amount added on cost: `selling_price = cost + margin` (`PM1`) |
| VAT-inclusive price | Shown on **approved** customer quotation (`TX2`); VAT rate fixed **15%** (`TX8`) |
| Discount | **None in v1** (`PM4a`) |
| Rounding | **2 decimal places / nearest halala**, half-up (`PM4b`) |

---

## 4. Relationship map

```
Company (single)
  └── Warehouse (single)
        └── Inventory balances + ledger

Customer
  ├── Credit limit
  ├── Visit
  ├── Sales Request ──► Supplier Quote (if needed)
  │         └── Quotation (VAT-inclusive, SM-approved)
  ├── Customer Purchase Order
  │         └── Sales Order(s) ──reserve──► Inventory
  │                └── Delivery(ies) + POD ──deduct──► Inventory
  │                         └── Invoice(s) ──► Credit exposure
  │                                └── Credit/Debit Note(s) (corrections)
  └── Collection(s) ──► allocate to Invoice(s) ──► Deal closed (all paid)
```

Communications (initial release): **Email** outgoing messages linked to documents. WhatsApp deferred.

---

## 5. Ambiguities remaining

| Term | Issue |
|---|---|
| Remaining (PO) | Report unallocated vs undelivered vs uninvoiced separately |
| New orders | Which documents the credit gate blocks besides SO (`CR2b`) |

---

## 6. Naming conventions

- Code/database: English `snake_case` / StudlyCase models.
- Always say **Customer Purchase Order** for inbound customer orders.
- Always say **Sales Request** instead of bare “request”.
- Distinguish **fulfillment delivery** from **inventory lot**.
