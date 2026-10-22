# Invoices, collections, notes, and deal close

**Phase:** 7 Prompt 1 (plat-guide #32–#33)  
**Status:** Implemented — Phase 7 overall **Not Accepted** (Designer polish + stakeholder Accepted pending).  
**Rules:** `PF3`, `CR7`, `TX5b`, `TX2`/`TX3`/`TX8`/`PM4b`; Warehouse has **no** invoices/prices.

---

## Invoice issue (PF3)

**One invoice per confirmed delivery** (`delivery_id` unique) — **auto-issued** when Warehouse confirms the delivery (POD required). Manual Accounts issue remains for backfill / edge cases.

| Rule | Behavior |
|---|---|
| Trigger | `DeliveryWorkflow::confirm` → `InvoiceWorkflow::issueFromDelivery` (idempotent) |
| Lines | Snapshot from delivery lines × quotation `selling_price_vat_inclusive` (via SO → CPO → quotation line) |
| Totals | VAT-inclusive; 2 dp half-up; **no discounts** |
| Due date | `issued_at + 30 days` (v1 default; calendar terms `CR5` still open) |
| Immutability | Issued amounts are not edited in place — use credit/debit notes |
| Duplicate | Manual second issue of same delivery → form error; auto path is idempotent |

Statuses: `issued` only in v1 (no draft invoice).

---

## Unpaid balance (CR7)

```
unpaid = issued
       − sum(confirmed collection allocations)
       − sum(approved credit notes)
       + sum(approved debit notes)
```

- **Draft** collections do **not** reduce unpaid.
- Fully paid when unpaid ≈ 0 (`TX5b`).

---

## Collections

```
draft → confirmed
confirmed → (reversal pending) → reversed | (reject keeps confirmed)
```

| Step | Who | Effect on unpaid |
|---|---|---|
| Create draft + allocate | Accounts (`collections.record`) | None |
| Confirm | Accounts | Reduces unpaid |
| Request reversal | Accounts (`collection_reversal.request`) | None until approved |
| Approve/reject reversal | Sales Manager | Approve restores unpaid (allocation no longer confirmed) |

---

## Credit / debit notes

```
draft → pending_approval → approved | rejected
```

| Action | Who |
|---|---|
| Create / submit | Accounts (`credit_notes.create`) |
| Approve / reject | Sales Manager (`credit_notes.approve`); not the creator |

Approved **credit** reduces unpaid; approved **debit** increases unpaid. Credit amount cannot exceed current unpaid at create/approve.

**Stock return on credit note (`INV8`):** not implemented — deferred.

---

## Credit exposure (live)

`App\Support\Credit\CreditExposure::forCustomer()` = sum of positive unpaid balances on **issued** invoices (CR1/CR7).

- Wired on Sales Order approve (unchanged gate): null limit → allow; else exposure must be **&lt;** `approved_credit_limit`.
- Replaces Phase 5 stub zeros when invoices exist.

---

## Deal close (TX5b)

Accounts (`deals.close`) may close an **open** Deal only when:

1. At least one linked invoice exists, and  
2. **Every** linked invoice is fully paid (unpaid = 0).

Reject with form error if any unpaid remains. Audit: `deal.closed`.

---

## Overdue reminders

| Path | Mechanism |
|---|---|
| Scheduled / CLI | `php artisan invoices:send-overdue-reminders` → `OverdueInvoiceReminder::sendDue()` |
| Manual resend | Accounts (`overdue.resend`) on invoice show |

Recipient = customer Account contact email (same resolver as quotations). Tests use `Mail::fake`.

**Limitation (CM1b):** Live SMTP / production provider not claimed — Phase 8 productizes email. Do not treat Mail::fake coverage as ZATCA or production mail compliance.

---

## Authorization (matrix §5.8)

| Action | Who |
|---|---|
| View invoices | Rep **Cust**; SM/Accounts/Exec **All**; Admin; Warehouse **denied** |
| Issue invoice | Accounts |
| Collections record/confirm | Accounts |
| Notes create | Accounts; approve SM |
| Collection reversal | Accounts request; SM approve |
| Deal close | Accounts |
| Overdue resend | Accounts |
| Prices | Hidden unless `prices.view` |

---

## Routes

- `invoices.*` — index/create/store/show/resend-overdue  
- `collections.*` — index/create/store/show/confirm/request-reversal/decide-reversal  
- `invoice-notes.*` — index/create/store/show/submit/decide  
- `deals.*` — index/show/close  
- Artisan: `invoices:send-overdue-reminders`

---

## Designer handoff (mandatory)

Polish Arabic RTL screens for invoices, collections, invoice-notes, and deals (list/create/show): unpaid/balance honesty (draft vs confirmed), note approve panels, deal-close gated UI, Warehouse has **no** finance nav. Preserve PF3/CR7/TX5b, routes, authz. Do **not** invent ZATCA QR, write-offs (`TX5a`), or stock-return on notes.

---

## Out of scope

ZATCA e-invoicing API; write-offs; INV8 stock return on credit note; Phase 8 full email productization; claiming Phase 7 **Accepted** or legal compliance.
