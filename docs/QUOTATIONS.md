# Quotations — margin pricing & VAT-inclusive offers (Phase 4 Prompt 2)

**Status:** Implemented — plat-guide #25  
**Live SMTP (`CM1b`):** not claimed complete; `Mail::fake` / log mailer OK for tests.

---

## Pricing rules (resolved)

| ID | Rule |
|---|---|
| PM1 | `selling_price_vat_inclusive = supplier_cost + margin_amount` (absolute SAR) |
| PM4a | **No discounts** — no fields/UI |
| PM4b | Round half-up to **2** decimal places |
| TX8 | VAT rate fixed **15%** |
| TX2 | Customer-facing prices are **VAT-inclusive** |

Helpers: `App\Support\Money\Money`.

---

## Cost basis (snapshotted on quotation lines)

| Sales-request line | Cost source |
|---|---|
| `needs_procurement` | Line `supplier_cost` (from procurement task) |
| `available` | Product `purchase_cost` — blocked with Arabic error if missing |

Later product cost edits do **not** rewrite quotation line snapshots.

---

## Statuses

`draft` → `approved` → `sent`  
(Legacy `pending_price_approval` may still appear on old rows; new happy path skips it.)  
Also: `cancelled`, `superseded` (new draft version), `expired` (past `expires_at` on approve/send).

**Happy path (PM1 / PM2):**

1. Procurement records supplier cost (all lines) → sales request becomes `ready_for_pricing` and a quotation **draft is auto-created** for the Sales Manager (costs snapshotted; margins start at 0). SM is notified with a link to the quote.  
2. Available-only requests: on submit → same auto draft for SM.  
3. **Sales Manager only** sets `margin_amount` and **approves** from draft → `approved`. Rep (request owner) is notified that the quote is ready. **No** supplier PO yet.  
4. **Sales Rep** sends approved quote to customer email.  
5. **Convert to sales order:** on `approved`/`sent`, Rep/SM see **تحويل إلى أمر بيع** — must upload the customer’s purchase-order **PDF**, which creates the Customer PO (+ Deal) and a **sales order draft**. For procurement-sourced lines this also opens the **supplier PO** for Procurement (`PROC-BUY-ON-CPO`).

**Sales request:** auto quotation moves request `ready_for_pricing` → `quotation_linked`.

**PDF / print:** After SM approval (`approved` or `sent`), Rep/SM see **عرض عرض السعر PDF**. Route `quotations.print` streams an inline `application/pdf` (mPDF, Arabic RTL template). Not available on draft / pending / cancelled.

---

## Authz (matrix)

- View: Cust / All / Admin (Accounts All); **Warehouse N**
- Create draft / send / cancel: Rep + SM (`quotations.manage` / `send` / `cancel`)
- **Set margins + finalize pricing:** Sales Manager only (`quotations.approve_price` + `price_margin` workflow)
- Administrator: no sales-approval bypass

---

## Email send

- Only when `approved` and not expired
- Recipient: **account** contact email, else **accounting** contact
- `QuotationApprovedMail` + `quotation_send_logs` (`sent` \| `failed`)
- Failure does **not** mark quotation as sent
- Throttle: `6,1` on send route

---

## Supersede

Creates a new draft (`version+1`) copying snapshotted lines; prior quote → `superseded`. No silent edit of approved commercial prices.

---

## Out of scope

Discounts, multi-currency, per-product VAT, ZATCA. (Auto availability from stock is on sales requests — see `SR-AUTO-STOCK` in `docs/SALES_REQUESTS.md`.)
