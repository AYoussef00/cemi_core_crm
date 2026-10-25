# Workflows

**Document type:** Phase 0 — workflow design  
**Status:** Updated with stakeholder decisions (Prompt 2 + visit policies 2026-10-16)  
**Rule:** Unresolved policy remains marked **OPEN QUESTION**. Status names below are **PROPOSED** working labels, not frozen enums, unless a decision explicitly fixes behavior.

**Source of truth for this update:** stakeholder decisions applied on top of prior Phase 0 docs under `docs/`.

---

## 1. Confirmed domain constraints (must hold in every workflow)

| ID | Constraint |
|---|---|
| C1 | **v1 org:** one company, one warehouse. |
| C2 | **Reserve** stock when a Sales Order is **approved**. |
| C17 | Every Sales Order **must** link to a Customer PO (`PF7`). SO without PO is not allowed in v1. |
| C18 | **Deal** is a **stored** entity; **v1:** one Deal ↔ one Customer PO (`ORG3`). Accounts closes Deal manually when all linked invoices are paid (Phase 7 UI). |
| C3 | **Deduct** stock when a delivery is **confirmed** (with proof of delivery). |
| C4 | One Sales Order may have **multiple deliveries**. |
| C5 | A delivery quantity must **not exceed** the Sales Order’s **undelivered** quantity. |
| C6 | On Sales Order **cancellation**, release only that SO’s **remaining reservation**, **exactly once**. Ledger/audit every reserve, release, and deduct; prevent double release and double deduction. |
| C7 | **Credit exposure** = sum of **unpaid invoice balances** only. Approved but undelivered Sales Orders are **not** included. |
| C8 | If unpaid invoice balance **reaches** the customer credit limit, **prevent new orders** until payments reduce the unpaid balance **below** the limit. |
| C9 | Sales Manager sets selling price by adding **margin/markup to cost** and **approves** price/margin. Approved customer quotation shows prices **including VAT**. |
| C10 | Invoice is issued **only after** proof of delivery is recorded, for the **delivered** quantity. Invoice quantities and amounts must be **traceable** to confirmed delivered quantities. |
| C11 | A deal closes only after all its invoices are fully paid (`TX5b`: unpaid per `CR7` = 0 on every linked invoice). **Accounts** closes the deal **manually** after that confirmation. A deal cannot close while any linked invoice has an unpaid balance. |
| C19 | **PF3:** one **invoice per confirmed delivery** (not consolidated). |
| C20 | Only **confirmed** collections reduce invoice unpaid balances (`CR7`). |
| C12 | **Issued invoices must not be silently edited or deleted.** Corrections use credit/debit notes linked to the original invoice (see §7). |
| C13 | Initial communications: **email only**. WhatsApp is **out of initial release**. |
| C14 | Driver **GPS tracking is not required** for the initial release. **Visit GPS / check-in tracking is also excluded from v1.** |
| C15 | Target market: **Saudi Arabia**. E-invoicing integration is **not** in the initial release; this does **not** remove any applicable legal obligations. Accountant review and applicable **ZATCA** compliance confirmation are **pre-launch requirements**. |
| C16 | **Visits (confirmed):** status `scheduled` → `completed` **or** `cancelled` (both terminal). Assignee is **always** the customer’s assigned Sales Representative (server-set; no alternate override). Scheduling is **blocked** if the customer has no assigned Sales Representative. Sales Representative may cancel **their own** scheduled visits; Sales Manager may cancel **any** scheduled visit. In-app notify the assigned representative when **another** authorized user creates, changes the scheduled time, or cancels that visit — **no** recurring reminders. |

**Still open (do not invent):** discount formulas, rounding rules, product master-data ownership, and other items in `docs/OPEN_QUESTIONS.md`.

---

## 2. Cross-cutting rules

- Each document has an identifiable owner, current state, and relevant history.
- Important transitions have explicit preconditions and permission checks.
- A document must not advance merely by manually editing a status field.
- Required supporting records must exist before a transition is allowed.
- Financial and inventory effects must match these confirmed rules.
- Repeated requests must not create duplicate financial or inventory effects.
- Failed asynchronous operations remain visible and recoverable.
- Completed records remain auditable.
- Every operational UI action must map to a real backend workflow.
- Approval authority is **configurable by role/position** (see `docs/ROLES_AND_PERMISSIONS.md`). Confirmed defaults: Sales Manager approves pricing and Sales Orders; only Sales Manager cancels approved Sales Orders; credit-limit sequence = Sales Representative requests → Accounts reviews → Sales Manager final approval; Accounts creates credit/debit notes and Sales Manager approves them; Accounts requests collection reversals and Sales Manager approves; Accounts manually closes deals when all linked invoices are fully paid; overdue emails auto-sent with Accounts resend; Warehouse cannot view prices or invoices; Administrator manages product master (Procurement edits purchase cost only); one role per user; no external-driver accounts; no branch filters in v1.

### 2.1 Quantity vocabulary (confirmed tracking needs)

| Concept | Meaning | Notes |
|---|---|---|
| Ordered qty | Quantity on the Customer Purchase Order line | Source of commercial ordered amount |
| Allocated qty | Quantity on non-cancelled Sales Orders linked to the PO line | Used for PO unallocated remaining |
| Reserved qty | Quantity reserved in inventory for an approved SO line | Created on SO approve; reduced on delivery deduct and on cancel release |
| Delivered qty | Quantity on confirmed deliveries (with POD) for an SO line | Cumulative; delivery cannot exceed undelivered |
| Undelivered qty (SO) | SO qty − confirmed delivered qty | Caps each new delivery |
| Invoiced qty | Quantity on issued invoices (and net of credit/debit notes per policy) | Must trace to confirmed deliveries |
| Unpaid invoice balance (`CR7`) | Issued amount − **confirmed** allocated collections − approved credit notes + approved debit notes | Sole input to credit exposure; draft collections do not reduce unpaid |

**PO “remaining” reporting (`PF1`):** track as **separate** figures — never collapse:
- **غير مخصّص** (unallocated) = ordered − allocated on active SOs
- **غير مسلّم** (undelivered) = ordered − delivered across linked SOs
- **غير مفوتر** (uninvoiced) = delivered − invoiced
UI labels in Arabic as above.

### 2.2 Inventory ledger effects (confirmed)

| Event | On-hand | Reserved | Available (= on-hand − reserved; **never negative** — `INV3`) | Audit |
|---|---|---|---|---|
| SO **approved** | unchanged | **+** SO remaining to reserve | **−** | Ledger: `reserve` linked to SO; idempotent per SO approval |
| Delivery **confirmed** | **−** delivered qty | **−** delivered qty | unchanged (both sides move) | Ledger: `deduct` linked to delivery; must not deduct more than remaining reservation for that SO |
| SO **cancelled** | unchanged | **−** remaining reservation only | **+** | Ledger: `release` linked to SO cancel; **exactly once**; remaining reservation = reserved − already deducted for that SO |
| Credit note with stock return (proposed) | **+** if goods returned and accepted | per policy | per policy | Ledger + Accounts approval; details **OPEN** for return quantity matching |
| Inventory **adjustment** (non-SO) | per adjustment | per adjustment | per adjustment | **Warehouse** records; **Sales Manager** approves; audited; no self-approve by Warehouse |

**Idempotency rules (confirmed intent):**

- Approving the same SO must not create a second full reservation.
- Confirming the same delivery must not deduct twice.
- Cancelling the same SO must not release twice.
- Cancel after partial delivery releases only **undelivered reserved** quantity.

**No negative stock (`INV3` — confirmed 2026-10-16):** Available = on-hand − reserved. The system **must reject** (not silently clamp) when available/on-hand would be insufficient for:

- Sales Order **approval** (reserve) — require available ≥ qty being reserved.
- Delivery **confirmation** (deduct) — must not leave on-hand negative; delivery qty still capped by undelivered SO qty and remaining reservation.
- Inventory **adjustment** approval — decreasing adjustments must leave `on_hand ≥ reserved` (hence available ≥ 0); never silently clamp.

**Future SO/delivery wiring (not this prompt):** reserve must not exceed available; delivery deduction must not exceed that SO’s remaining reservation; cancel releases remaining reservation **exactly once**.

Confirmed spine unchanged: **reserve** on SO approve; **deduct** on delivery confirm; **release** remaining reservation once on SO cancel.

### 2.3 Credit gate (confirmed)

```
credit_exposure = sum(unpaid balances of customer's issued invoices)
if credit_exposure >= customer.credit_limit:
    prevent new orders
```

- “New orders” means creating/approving new Sales Orders (and any earlier commercial step that would commit a new order — **OPEN** whether quotation send is also blocked; SO path is confirmed blocked).
- Undelivered approved SOs do **not** add to exposure.
- Payments that reduce unpaid balances below the limit re-enable new orders.

### 2.4 Pricing / VAT (confirmed)

- **Cost basis (`PM1`):** supplier / purchase cost recorded by Procurement.
- **Flow:** Procurement supplier prices → Sales Manager.
- **Arithmetic (`PM1`):** `selling_price = supplier_cost + margin_amount` (SAR absolute margin). Example: cost 200 + margin 100 → selling price 300.
- Sales Manager approves the selling price / margin; Rep does not invent pricing rules beyond approved authority (see matrix / `PM3` if thresholds later).
- Approved customer quotation presents prices **including VAT** (`TX2`).
- **VAT (`TX8`):** fixed **15%** in v1 (no per-product rate / exemption matrix).
- **Discounts (`PM4a`):** **none** in v1 — no discount fields or rules.
- **Rounding (`PM4b`):** amounts to **2 decimal places** (nearest halala), half-up.

### 2.5 Customer visits (confirmed)

| Topic | Rule |
|---|---|
| States | `scheduled` → `completed` **or** `cancelled`. **Completed** and **cancelled** are terminal. |
| Assignment | Visit `assigned_user_id` is **always** the customer’s `assigned_sales_rep_id` (set on the server; client assignee input ignored). |
| Unassigned customer | Scheduling / rescheduling is **blocked** until the customer has an assigned Sales Representative. |
| Manager override | **Not allowed** — Sales Manager may schedule/manage visits but cannot pick a different representative. |
| Cancel — Sales Rep | May cancel **their own** scheduled visits. |
| Cancel — Sales Manager | May cancel **any** scheduled visit. |
| Notifications | In-app (internal) notification to the **assigned representative** when **another** authorized user **creates**, **changes the scheduled time**, or **cancels** that visit. No notify on self-action or when time is unchanged. **No** recurring reminders. |
| GPS | Visit GPS / check-in tracking **excluded from v1**. |
| Sales request | Completing a visit does **not** create a sales request or commercial obligation. Optional UI handoff (completed visit + customer profile) may start a draft with nullable `origin_visit_id`. Direct create without a visit remains valid. |

Visit records remain linked to the customer and appear in customer activity/history. View/manage scopes remain per `docs/ROLES_AND_PERMISSIONS.md` (Rep **Cust**, SM **All**).

---

## 3. Workflow A — Fully available product order

Happy path when products are available and supplier quoting is not required before quotation.

### Actors

| Actor | Actions |
|---|---|
| Sales Representative | Customer, visit, sales request; draft commercial inputs; send approved quotation via **email** when allowed; link Customer PO |
| Sales Manager | Set/approve selling price and margin/markup; approve Sales Orders; only cancel approved SOs; final-approve credit limits; approve inventory adjustments |
| Warehouse Staff | Prepare goods; create deliveries; record proof of delivery; confirm delivery (triggers stock deduct); record inventory adjustments |
| Accounts | Issue invoices after POD; record collections; create credit/debit notes (SM approves); request collection reversals (SM approves); review credit-limit requests; manually close deals when all invoices paid; resend overdue emails; cannot be blocked from invoice view (Warehouse cannot view invoices/prices) |
| Procurement | Not primary on this path |
| Executive / Administrator | Oversight / configuration where permitted |

### Records

Customer → (optional) Visit → Sales Request → Quotation (VAT-inclusive approved prices) → Customer Purchase Order → Sales Order → Preparation → Delivery (+ POD) → Invoice → Collection(s) → Deal closed (when all invoices paid)

### Proposed transitions

```
Sales Request:   draft → submitted → ready_for_pricing → quotation_linked → closed|cancelled
Quotation:       draft → pending_price_approval → approved → sent → accepted|expired|superseded|cancelled
Customer PO:     received → linked → open → partially_fulfilled → fulfilled|cancelled
Sales Order:     draft → pending_approval → approved → in_fulfillment → partially_delivered → delivered|cancelled
Delivery:        draft → dispatched → pending_pod → confirmed|failed|cancelled
Invoice:         draft → issued → partially_paid → paid
                 (corrections: credit_note / debit_note linked; original issued invoice preserved)
Collection:      recorded → allocated
Deal:            open → closed  (only if all linked invoices paid)
```

### Preconditions and transitions (core)

| Transition | Preconditions | Effects |
|---|---|---|
| Quotation → approved | Sales Manager (or configured approver) approved price/margin; VAT-inclusive prices present | Quotation becomes sendable |
| Quotation → sent | Approved; email channel available; recipient rules satisfied | Outgoing email message recorded |
| SO draft → submitted | Sales Representative (or Sales Manager) creates/submits draft | Awaiting Sales Manager approval |
| SO → approved | Approver is Sales Manager; credit gate allows new orders; available stock ≥ SO qty; SO qty within PO unallocated remaining | **Reserve** stock; notify warehouse |
| SO → cancelled (approved) | **Sales Manager only** | Release remaining reservation once |
| Delivery → confirmed | POD recorded; delivery qty ≤ SO undelivered qty; delivery qty ≤ remaining reservation | **Deduct** on-hand and reserved; enable invoicing for that qty |
| Invoice → issued | POD-confirmed delivery qty not yet invoiced; amounts traceable to delivery lines | Increases unpaid balance / credit exposure |
| Deal → closed | **Accounts** confirms every linked invoice is fully paid, then **manually** closes | Terminal commercial closure; system must reject close if any linked invoice unpaid |

### Validations

- Permission and configurable approval authority enforced server-side.
- Delivery qty ≤ undelivered SO qty.
- Invoice qty/amount traceable to confirmed delivery; no invoice without POD.
- No silent edit/delete of issued invoices.
- Credit gate before new SO approval when exposure ≥ limit.
- Reservation/deduction/release idempotent.

### Rejection / cancellation

| Path | Behavior |
|---|---|
| Quotation rejected / expired | Cannot send as approved; revise or cancel; no inventory effect |
| SO rejected before approval | No reservation |
| SO cancelled after approval | Release remaining reservation **once**; keep history; delivered/invoiced portions remain |
| Delivery failed/cancelled before confirm | No deduction; no invoice eligibility for that attempt |
| Invoice draft abandoned | No financial effect until issued |

### Exceptions

| Case | Handling |
|---|---|
| Insufficient available stock at SO approval | Reject approval |
| Credit exposure ≥ limit | Block new orders |
| Concurrent delivery over undelivered qty | Reject excess |
| Email send failure | Message failed/retryable; do not fake “delivered” |

---

## 4. Workflow B — Unavailable product requiring supplier quote

### Actors

Sales Representative, Procurement, Sales Manager (pricing after cost), then Warehouse/Accounts as in A.

### Records

Sales Request (unavailable line) → Procurement task → Supplier Quote / cost record → Sales Manager price approval → Quotation (VAT-inclusive) → (then Workflow A from PO onward, after stock is receivable/available as applicable)

### Preconditions / transitions

| Step | Preconditions | Notes |
|---|---|---|
| Route to procurement | Line unavailable or needs supplier cost | Auto vs manual criteria **OPEN** — v1 uses **explicit** line `needs_procurement` (see `docs/SALES_REQUESTS.md`) |
| Cost received | Supplier quote attached + structured cost | Feeds Sales Manager markup (Prompt 1 records `supplier_cost`; margin/quote in Prompt 2) |
| Price approval | Sales Manager sets margin/markup on cost | Same as C9 |
| SO approval later | Stock available (after receipt if purchased) + credit gate | Reserve on approve |

### Cancellations / exceptions

- Supplier quote delayed: request stays awaiting procurement.
- Multiple quotes: which cost is authoritative — **OPEN**.
- Customer accepts quotation but stock never arrives — cancellation/amendment — **OPEN**.

---

## 5. Workflow C — Customer PO fulfilled via multiple Sales Orders and/or multiple deliveries

### Confirmed shape

- One Customer PO may be fulfilled by multiple Sales Orders.
- One Sales Order may have multiple deliveries.
- Later deliveries must not rewrite earlier delivery history.

### Actors

Sales Rep / Sales Manager (SO batches), Warehouse (each delivery), Accounts (invoices per confirmed delivery qty).

### Transitions (PO / SO)

```
PO: open → partially_fulfilled → fulfilled
SO: approved → partially_delivered → delivered
```

### Validations

- New SO allocation ≤ PO unallocated remaining (concurrency-safe).
- Each delivery ≤ that SO’s undelivered qty.
- Each SO approval reserves only that SO’s qty once.
- Cancelled SO releases only its remaining reservation and restores PO unallocated accordingly.

### Exceptions

- Concurrent allocation of last units: one succeeds, one fails.
- Cancel SO after partial delivery: release undelivered reservation only; delivered/invoiced history preserved.

---

## 6. Workflow D — Partial delivery and invoice for delivered quantity only

### Confirmed

- Confirm delivery with POD for quantity D < SO qty.
- Deduct D from on-hand and reserved.
- Issue invoice only for confirmed delivered quantity (traceable to that delivery).
- Remaining undelivered qty stays open for later deliveries.
- Undelivered quantity is not invoiced.

### Actors

Warehouse (POD + confirm), Accounts (issue invoice).

### Transitions

```
SO: in_fulfillment → partially_delivered → delivered
Delivery: confirmed
Invoice: issued (qty = confirmed delivery qty not previously invoiced)
```

### Validations

- No invoice without POD-confirmed delivery.
- Invoice qty ≤ eligible uninvoiced delivered qty.
- Issued invoice immutable except via credit/debit note process (§7).

### Exceptions

- Disputed POD after confirm but before invoice — **OPEN** correction path (likely reverse delivery only if policy allows; do not invent).
- After invoice: use credit/debit note; do not edit issued invoice.

---

## 7. Workflow E — Credit, collections, overdue email reminders, deal close

### Actors

| Actor | Actions |
|---|---|
| Sales Representative | **Requests** credit-limit change; cannot silently change credit; views invoices for assigned customers only |
| Accounts | **Reviews** credit-limit requests; issues invoices; collections; **creates** credit/debit notes; **requests** collection reversals; **manually closes** deals; **resends** overdue emails |
| Sales Manager | **Final approval** of credit-limit changes; **approves** credit/debit notes and collection reversals |
| System | Sends overdue **email** reminders automatically (WhatsApp out of initial release) |

### Credit limit approval (confirmed sequence)

```
Sales Representative requests
    → Accounts reviews (approve/reject to proceed or return)
    → Sales Manager final approval
    → Credit limit updated (audited)
```

- Authority remains configurable by role/position for future changes; **v1 default sequence above is confirmed**.
- Sales Representative cannot self-approve credit.

### Credit enforcement (confirmed)

1. Maintain customer credit limit (after final Sales Manager approval).
2. `credit_exposure = unpaid issued invoice balances only`.
3. If `credit_exposure >= credit_limit` → block new orders until payments reduce exposure below limit.

### Collections and close

| Transition | Preconditions | Effects |
|---|---|---|
| Collection allocated | Invoice issued; allocation ≤ unpaid | Reduces unpaid balance / exposure |
| Deal → closed | **Accounts** confirms every linked invoice is fully paid, then **manually** closes | Terminal closure |
| Deal close attempted with unpaid invoice | Reject (any actor) | C11 |
| Non-Accounts attempt to close deal | Deny | Confirmed: Accounts only |

### Overdue reminders

- Channel: **email** for initial release.
- **System sends automatically** per schedule (schedule/stop details may still be refined — see `OPEN_QUESTIONS` `CM6`).
- **Accounts may resend** a reminder manually.
- Reminder send/resend does not change financial state by itself.

---

## 8. Returns and issued-invoice errors (proposed correction workflow)

**Confirmed principles:**

- Preserve the original **issued** invoice (no silent edit/delete).
- Use a **credit note** and/or **debit note** linked to the original invoice.
- **Accounts creates** the note; **Sales Manager approves**.
- **Collection reversal:** Accounts requests; Sales Manager approves.
- Inventory and financial effects must be auditable.
- Accountant review and applicable **ZATCA** compliance confirmation are **pre-launch requirements**.
- Saudi Arabia is the target market; e-invoicing integration is **not** in the initial release, without claiming exemption from legal obligations.

### Proposed flow (not a substitute for accountant/ZATCA sign-off)

```
Issued Invoice (immutable monetary snapshot)
    → Accounts creates Credit Note and/or Debit Note (linked to original)
    → Sales Manager approves note
    → Inventory ledger adjustment if goods returned/re-shipped (audited)
    → Recalculate unpaid balances / credit exposure
    → Accounts may close deal only when all linked invoices fully paid
```

### Open within this area

- Exact matching of credit-note lines to deliveries/returns.
- Whether stock must be physically received before credit note approval.
- Write-offs, refunds, overpayments beyond credit/debit notes.
- Final ZATCA field/XML/PDF requirements for notes (pre-launch).

---

## 9. Communications and GPS (release scope)

| Topic | Initial release |
|---|---|
| Email | In scope (first integration) |
| WhatsApp | **Out of scope** for initial release |
| Driver GPS | **Not required** / out of initial release |
| Visit GPS / check-in | **Excluded from v1** |
| Visit in-app notify | Create / reschedule / cancel → assigned rep when actor ≠ assignee; **no** recurring reminders |

---

## 10. Phase 0 workflow coverage checklist

| Scenario | Section |
|---|---|
| Fully available order | §3 Workflow A |
| Unavailable / supplier quote | §4 Workflow B |
| Multi SO / multi delivery fulfillment | §5 Workflow C |
| Partial delivery + invoice delivered qty | §6 Workflow D |
| Credit, partial collection, email overdue, deal close | §7 Workflow E |
| Invoice error / returns via credit-debit notes | §8 |
| Customer visits (status, assignment, cancel, notify) | §2.5 / C16 |

---

## 11. Status label disclaimer

Proposed status names may be renamed during implementation, but the **behavioral constraints in §1** are stakeholder-confirmed and must not be weakened without a new decision record in `docs/OPEN_QUESTIONS.md` / change log.
