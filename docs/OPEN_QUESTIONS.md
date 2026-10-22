# Open Questions

**Document type:** Phase 0 — unresolved business decisions  
**Status:** Authorization matrix finalized; **business-domain** questions remain open and gated  
**Rule:** Do not invent answers in implementation. Do **not** mark domain questions resolved merely because the permission matrix is approved.  
**Phase gates:** See `docs/PHASE_ACCEPTANCE.md`.

Priority key:

| Priority | Meaning |
|---|---|
| **P0 — Blocking** | Blocks safe database design or core workflow implementation |
| **P1 — High** | Blocks a major module or creates high rework risk if guessed |
| **P2 — Medium** | Needed before the related phase acceptance |
| **P3 — Later** | Can wait until nearer the consuming phase |

---

## Resolved decisions (including final authorization)

| ID | Decision |
|---|---|
| ORG1 | v1 has **one company**. |
| ORG2 | **No branch filters** in v1. |
| INV2 | v1 has **one warehouse**. |
| INV1 | Reserve on SO approval; deduct on delivery confirmation. |
| INV3 | **Negative stock is not allowed.** Available = on-hand − reserved. The system **must reject** SO approval (reserve), delivery confirmation (deduct), and inventory adjustment approval when the resulting available/on-hand quantity would be insufficient (would go negative or oversell available). Confirmed spine unchanged: reserve on SO approve; deduct on delivery confirm; release remaining reservation once on SO cancel. Decided 2026-10-16. |
| INV5 / PF6 | SO cancel releases remaining reservation once; ledger/audit; no double release/deduction. |
| INV6 | Warehouse records inventory adjustments; Sales Manager approves. |
| PF2 | One SO may have multiple deliveries. |
| PF7 | **Sales Order without Customer PO is not allowed in v1.** Every SO must link to a Customer PO. Decided 2026-10-16. |
| PF3 | **One invoice per confirmed delivery** (not consolidated across deliveries). Decided 2026-10-16. **Auto-issued on delivery confirm** (2026-10-18). |
| CR1 / CR2 | Credit exposure = unpaid invoices only; block new orders at/above limit until below. |
| CR3-seq | Credit limit: Rep requests → Accounts reviews → SM final. |
| CR-CREDIT-WF | Phase 2 Prompt 2 implemented request/review/final with terminal reject; return-for-revision left open (`CR-CREDIT-RETURN`). Payment term stored as free text pending `CR5`. Exposure/order gate deferred to invoice/SO prompts — see `docs/CREDIT.md`. |
| PM2 | Sales Manager sets/approves price via margin/markup on cost. |
| PM1 | **Cost basis = supplier / purchase cost** (Procurement records supplier prices). Flow: procurement prices → Sales Manager. **Arithmetic (v1):** `selling_price = supplier_cost + margin_amount` (SAR). Example: cost 200 → SM margin 100 → selling price 300. Margin is an absolute amount added on cost (not a required %-of-cost formula in v1). Customer quotation line price remains **VAT-inclusive** per TX2. Decided 2026-10-16. |
| PM4a | **No discounts in v1** — do not implement discount fields, rules, or UI. Decided 2026-10-16. |
| PM4b | Money amounts round to **2 decimal places (nearest halala)** — standard half-up to 0.01 SAR. Decided 2026-10-16. |
| TX8 | VAT rate **fixed 15%** for v1 (KSA). No per-product rate or exemption matrix in v1. Decided 2026-10-16. |
| TX2 / TX3 | Quotation prices VAT-inclusive; invoice only after POD for delivered qty. |
| Deal close | Accounts manually closes after all linked invoices fully paid. |
| CR7 | **Unpaid invoice balance** = issued amount − **confirmed** allocated collections − **approved credit notes** + **approved debit notes**. Draft/unconfirmed collections do **not** reduce unpaid. Approved credit notes reduce what the customer owes (netting). Decided 2026-10-16. |
| TX5b | Invoice is **fully paid** when its unpaid balance (per `CR7`) is **0**. Deal may be closed by Accounts only when **every linked invoice** on the Deal is fully paid. Decided 2026-10-16. |
| ORG3 | **Deal is a stored entity** (not merely derived). **v1:** one Deal ↔ one Customer PO; SOs under that PO belong to the same Deal. Stores open/closed status, closer, closed_at for Accounts manual close when all linked invoices are fully paid. Decided 2026-10-16. |
| Notes | Accounts **creates** credit/debit notes; Sales Manager **approves**. |
| Collection reversal | Accounts **requests**; Sales Manager **approves**. |
| CM1 / GPS1 | Email first; WhatsApp and driver GPS out of initial release. |
| CM-DEFER | **Phase 8 deferred** (2026-10-16): do not productize live email activation or WhatsApp now. Quote/overdue/mail-test may stay on `Mail::fake` / log. Reopen `CM1b` / `CM2`–`CM5` / `CM6` when Phase 8 resumes. |
| RP2 | App timezone **Asia/Riyadh**. Reporting calendar = Gregorian day/month/year (no separate fiscal year in v1). Decided 2026-10-16. |
| RP1 | Home/report KPIs from **authoritative scoped records only** (zero OK; no fake numbers). v1 KPI set: pending sales requests; quotations awaiting approval; SOs awaiting delivery/preparation; warehouse prep queue; overdue invoices / AR unpaid (CR7); open deals. Decided 2026-10-16. |
| RP3 / RP4 | **No** scheduled report jobs and **no** file export in v1 — in-app screens only. Decided 2026-10-16. |
| PF1 | Partial-fulfillment labels (separate figures, never collapsed): **غير مخصّص** (ordered − allocated on active SOs), **غير مسلّم** (ordered − delivered), **غير مفوتر** (delivered − invoiced). Decided 2026-10-16. |
| GPS2 | **Visit GPS / check-in tracking is excluded from v1** (same release posture as driver GPS). |
| GPS4 | External drivers **do not** have system accounts in v1. |
| VISIT-STATUS | Visit lifecycle: `scheduled` → `completed` **or** `cancelled`. **Completed** and **cancelled** are terminal. |
| VISIT-ASSIGNEE | Visit assignee is **always** the Sales Representative **assigned to the customer** (server-set). Sales Manager **may not** assign a different representative. Scheduling blocked if the customer has no assigned Rep. |
| VISIT-ASSIGNEE-SCOPE | **Superseded / closed:** alternate-assignee override removed; Cust view scope and visit assignee remain aligned (same Rep). |
| ORG6 | V1 customers are **organizations / business establishments only**. Individual customers are **out of scope**. UI treats `customers.name_ar` as **اسم المنشأة**. No customer-type discriminator or individual-specific fields in v1. |
| VISIT-CANCEL | Sales Representative may cancel **their own** scheduled visits. Sales Manager may cancel **any** scheduled visit. (Optional free-text cancel note remains implementation detail; no enumerated cancel-reason catalog required.) |
| VISIT-NOTIFY | In-app (internal DB) notification to the **assigned representative** when **another** authorized user **creates**, **reschedules**, or **cancels** that visit. **No** recurring reminders. Reschedule notify = **scheduled time** change only (minute precision). |
| R2 | Each user has **one role only** in v1 (no multi-role). |
| PERM-PROD | Administrator manages product master; Procurement may edit **purchase cost only**. |
| Auth scopes | See `docs/ROLES_AND_PERMISSIONS.md` (Rep Assigned/Cust/Own; SM/Accounts All quotes & invoices; WH no prices/invoices; SM/WH/Accounts All SOs; Procurement Referred SOs; Executive read-only/report All; Admin users/settings/products, no sales approvals; reports Own vs All; SM-only cancel approved SO; Rep create/submit SO drafts). |
| TX1 / TX4 | KSA market; e-invoicing API not in initial release; obligations not waived; ZATCA accountant review pre-launch. |
| 0.12 | Role/permission matrix stakeholder decisions applied — criterion **satisfied**. |
| PROC-QUOTE-PDF | Recording supplier cost on a procurement task **requires** attaching the supplier’s quote as **PDF** (mandatory). Decided 2026-10-18. |
| PROC-BUY-ON-SO | On Sales Manager **SO approve attempt**: if stock covers every line → reserve + approve and **do not** create a supplier buy. If any line is short → create **supplier purchase order(s)** for the shortfall (or point to an open quote-sourced SPO), notify Procurement, and **block approve** until stock is received (preserves `INV3`). Decided 2026-10-18. |
| PROC-BUY-ON-QUOTE | **Superseded 2026-10-18** by `PROC-BUY-ON-CPO`. Quotation approve no longer creates a supplier PO. |
| PROC-BUY-ON-CPO | When Sales Rep **converts** an approved/sent quotation to a sales order **and attaches the customer PO PDF**, create a **supplier purchase order** for procurement-sourced lines and notify Procurement. SM approve only notifies the Rep that the quote is ready. Idempotent per quotation. Decided 2026-10-18. |
| PROC-PARTIAL-SHIP | After **any** warehouse goods receipt (partial or full), the linked sales order is **auto-activated** (approved) — no rep submit / SM decide. Reserves on-hand qty only; remaining stays on the open SPO. Further receipts **top up** reservation so partial customer deliveries can proceed. Decided 2026-10-18. |
| PROC-SPO-AR | Supplier purchase order document is generated/downloadable in **Arabic**. Decided 2026-10-18. |
| PROC-RECV | Warehouse **goods receipt** against a supplier PO **increases on-hand immediately** (no SM adjustment approval for this receipt path). Decided 2026-10-18. |
| PROC-SPO-ATTACH | When Procurement **issues** a supplier PO, the Arabic PO PDF is **attached/stored** on the PO in-system (optional extra signed PDF upload). Decided 2026-10-18. |
| PROC-RECV-SHIP | Stock may arrive in **multiple shipments**. Each arrival creates a distinct **goods receipt document** (أمر استلام) linked to the original supplier PO; quantities accumulate until the PO is fully received. Decided 2026-10-18. |
| PROC-RECV-NOTE | Each warehouse goods receipt **requires** attaching the **signed supplier delivery note** as PDF (محضر/إذن التسليم الموقع). Decided 2026-10-18. |

Full matrix: `docs/ROLES_AND_PERMISSIONS.md`.

---

## Active open questions (business domain)

### 1. Pricing / discounts / rounding / cost basis

| ID | Question | Priority | Blocks |
|---|---|---|---|
| PM3 | Sales Rep self-approve within threshold? (default deny unless configured) | **P1** | Phase 4 |
| PM5 | Supplier cost change after quotation send | **P1** | Phase 4 |
| PM6 | Multi-currency vs SAR only | **P1** | Schema |

**Resolved for Phase 4 P0 pricing gates:** `PM1`, `PM4a`, `PM4b`, `TX8` — see Resolved decisions.

### 2. Credit / collections edge cases

| ID | Question | Priority | Blocks |
|---|---|---|---|
| CR2b | Credit gate at SO only, or also quote/PO? | **P1** | Phase 5/7 |
| CR4 | Concurrent orders near credit limit | **P1** | Phase 5/7 |
| CR5 | Payment terms / due-date calendar rules | **P1** | Phase 7 |
| CR6 | Credit customer-level only vs deal/PO overrides | **P1** | Phase 7 |
| CR-CREDIT-RETURN | After Accounts/SM reject: return-for-revision vs terminal only? Resubmit same request? | **P1** | Phase 2/7 credit UX |
| TX5a | Write-offs, refunds, overpayments beyond notes | **P1** | Phase 7 |
| CM6 | Overdue schedule and stop conditions (auto-send confirmed) | **P1** (deferred with Phase 8) | Phase 8 — current overdue path + Mail::fake accepted for Phase 7 |

### 3. Inventory / product master (post Prompt 1)

| ID | Question | Priority | Blocks |
|---|---|---|---|
| INV4 | Lot/batch tracking in v1? | **P1** | Phase 3/6 |
| INV7 | Soft allocation against future incoming stock? | **P1** | Phase 3–5 |
| INV8 | Stock return timing on credit note | **P1** | Phase 7 returns |
| PROD-COST-VIEW | Who may **view** purchase cost besides Administrator and Procurement? (Current: only `products.manage` + `products.edit_purchase_cost`; Sales Manager sees catalog without cost.) | **P1** | Phase 3/4 pricing UX |
| PROD-SPEC-CATALOG | Beyond density: fixed technical-property catalog vs keep flexible label/value rows only? | **P2** | Product master UX |
| PROD-UOM-CONV | Unit conversion factors / multi-unit per product? (Explicitly **not** implemented; policy undefined.) | **P1** | Inventory / sales qty |
| PROD-SKU | Product code / SKU / numbering required? | **P1** | Catalog / docs (`NUM*`) |
| PROD-INACTIVE | Soft-delete / inactive product lifecycle vs hard retention of catalog rows? | **P2** | Catalog maintenance |

### 4. Partial fulfillment / documents

| ID | Question | Priority | Blocks |
|---|---|---|---|
| PF1 | ~~KPI labels~~ → **Resolved** (see Resolved decisions) | — | — |
| PF4 | PO amendments after partial fulfillment | **P1** | Phase 5 |
| PF5 | PO qty may exceed quotation? | **P1** | Phase 5 |

### 5. Numbering

| ID | Question | Priority | Blocks |
|---|---|---|---|
| NUM1 | Formats | **Decided 2026-10-18** | QA / PO / INV / DN + 9-digit seq from **100** (`QA-000000100`). Derived from internal id (`id + 99`). No year reset. |
| NUM2–NUM4 | Year reset, gaps policy, manual override | **P2** | Doc UX / compliance |

### 6. Approvals (non-auth)

| ID | Question | Priority | Blocks |
|---|---|---|---|
| AP2 | Extra executive thresholds on large SOs? | **P1** | Phase 5 |
| AP3–AP5 | Delegation, rejection reasons, revision-only edits | **P1–P2** | Lifecycle UX |

### 7. Tax / ZATCA presentation

| ID | Question | Priority | Blocks |
|---|---|---|---|
| TX6 | KSA invoice/note PDF/numbering expectations without e-invoicing API | **P1** | Phase 7 + pre-launch |
| TX7 | Proforma invoices in scope? | **P2** | Phase 7 |
| TX9 | Accountant ZATCA checklist confirmation | **Deferred for now** (stakeholder 2026-10-17 — not required for current pilot; no compliance claim) | Regulated production later if needed |

### 8. Communications

| ID | Question | Priority | Blocks |
|---|---|---|---|
| CM1b | Email provider/API | **P1** (deferred) | Phase 8 — postponed per `CM-DEFER` |
| CM2–CM5 | Auto vs manual sends, recipients, dedupe, threading | **P1–P2** (deferred) | Phase 8 — postponed per `CM-DEFER` |

### 9. Maps (ops)

| ID | Question | Priority | Blocks |
|---|---|---|---|
| MAP1 | Production tile provider vs OSM fair-use / self-hosted tiles | **P2** | Ops / maps |

Resolved visit policies (`VISIT-STATUS`, `VISIT-ASSIGNEE`, `VISIT-ASSIGNEE-SCOPE`, `VISIT-CANCEL`, `VISIT-NOTIFY`, `GPS2`) and **ORG6** (org-only customers) are in Resolved decisions above.

### 10. Reporting / org

| ID | Question | Priority | Blocks |
|---|---|---|---|
| RP1 | ~~KPI formulas~~ → **Resolved** (see Resolved decisions) | — | — |
| RP2 | ~~Timezone / fiscal~~ → **Resolved** (Asia/Riyadh) | — | — |
| RP3–RP4 | ~~Scheduled / export~~ → **Resolved** (none in v1) | — | — |
| ORG4 | Arabic-only vs bilingual | **P2** | UI |
| ORG5 | Attachment retention/sizes | **P2** | Storage |
| ORG7 | Which legal identifiers belong on the company profile (e.g. commercial registration, VAT registration number)? Identity/contact fields only are stored in Phase 1 Prompt 4 — no tax math. | **P1** | Invoicing / ZATCA presentation |
| ORG8 | Company/warehouse address: free-text only (current) vs structured city/district/postal fields? | **P2** | Settings UX / documents |
| ORG9 | Warehouse operational code/identifier beyond Arabic display name? | **P2** | Inventory / documents |
| ORG10 | Bilingual company/warehouse names (Arabic + English) vs Arabic-only — related to ORG4 | **P2** | Settings / documents |

---

## Still blocking schema / domain implementation (summary)

1. **NUM***, **INV4** as needed by consuming phases; **CM1b**/WhatsApp deferred (`CM-DEFER`) until Phase 8 resumes  

**Resolved for Phase 9 reporting gates:** `RP1` / `RP2` / `RP3`–`RP4` / `PF1` — see Resolved decisions.  

**Resolved for Phase 7 financial predicates:** `PF3` (invoice per delivery); `CR7` (unpaid nets confirmed collections + approved notes); `TX5b` (fully paid / deal close when all linked invoices unpaid=0) — see Resolved decisions.

**Resolved for Phase 5:** `PF7` (SO requires Customer PO); `ORG3` (Deal stored, 1:1 Customer PO) — see Resolved decisions.  
**Resolved for Phase 3 inventory quantity gates:** `INV3` (no negative stock) — see Resolved decisions.  
**Resolved for Phase 4 P0 pricing gates:** `PM1` (cost + absolute margin), `PM4a` (no discounts), `PM4b` (2 dp / halala), `TX8` (fixed 15% VAT) — see Resolved decisions.

**Not blocking Phase 1 foundation** (auth, single-role users, RBAC from approved matrix, audit, attachments, company/warehouse settings, RTL shell): see `docs/PHASE_ACCEPTANCE.md` §7.1.

**Authorization:** no remaining open permission-matrix questions — see `docs/ROLES_AND_PERMISSIONS.md`.

---

## How to resolve further items

Record decision text, date, decision maker, and update `WORKFLOWS` / `DOMAIN_GLOSSARY` / this file. Move to **Resolved decisions**; do not delete history.
