# Roles and Permissions

**Document type:** Phase 0 — authorization design (final stakeholder authorization decisions)  
**Status:** Permission matrix **approved** for v1 defaults below  
**v1 organization:** one company, one warehouse, **no branch filters**, **one role per user**, **no external-driver accounts**

---

## 1. Implementation requirements (non-negotiable)

| Requirement | Meaning |
|---|---|
| **Least privilege** | Grant only permissions required for the role’s job. Default deny for sensitive actions. |
| **Server-side authorization** | Enforce every view/create/edit/submit/approve-reject/send/cancel/report path in the backend. UI hiding is not authorization. |
| **No silent privilege invention** | Do not grant capabilities beyond this matrix without a new stakeholder decision. |
| **Configurable approval authority** | Participants remain configurable by role/position; **v1 sequences below are confirmed defaults**. |
| **Audit** | Elevated and financial/inventory actions remain auditable; Administrator must not silently bypass audit. |
| **Single role** | Each user has **exactly one** role in v1 (no multi-role assignment). |

---

## 2. Role permissions vs record-level scope

| Layer | Meaning | Example |
|---|---|---|
| **Role permission** | Can the role perform the action? | Only Sales Manager cancels approved Sales Orders |
| **Record-level scope** | Which records? | Sales Rep views only assigned customers’ documents |

### Confirmed scope vocabulary

| Marker | Meaning |
|---|---|
| **Own** | Records owned/created by that user (e.g. own-scope reports) |
| **Assigned** | Customers assigned to the Sales Representative |
| **Cust** | Records for the user’s assigned customers |
| **All** | All company records of that type (v1 single company; no branch filter) |
| **Work** | Records related to the user’s operational work (e.g. Warehouse/Accounts task-linked visits or POs) |
| **Referred** | Sales Orders referred to Procurement |
| **Admin** | Administrative access (manage/support), **not** sales-transaction approval |

---

## 3. v1 roles (one role per user)

| Role | Confirmed responsibility summary |
|---|---|
| **Sales Representative** | Assigned customers only; Cust-scoped quotations/invoices/visits/sales orders; create/submit SO drafts; request credit-limit changes; schedule/complete visits for assigned customers; cancel **own** scheduled visits; own-scope reports |
| **Sales Manager** | Price/margin approve; approve SOs; **only** cancel approved SOs; view **All** SOs/quotations/invoices/visits/POs; schedule/manage visits (assignee always = customer’s Sales Rep); may cancel **any** scheduled visit; approve inventory adjustments; final credit-limit approve; approve collection reversals and credit/debit notes; all reports |
| **Procurement** | Edit **purchase cost only** on products; view **Referred** Sales Orders; supplier quotes |
| **Warehouse** | Preparation/delivery/POD/confirm; record inventory adjustments; view **All** SOs; **Work**-scoped visits/POs; **cannot** view prices or invoices |
| **Accounts** | Issue invoices; collections; create credit/debit notes; request collection reversals; review credit limits; view **All** SOs/quotations/invoices; **Work**-scoped visits/POs; manual deal close; resend overdue emails; all reports |
| **Executive** | **Read-only / report** access (all commercial visibility as specified); does **not** approve sales transactions |
| **Administrator** | Manage users, system settings, **product master records**; administrative SO access; does **not** approve sales transactions |

**External drivers:** no system accounts in v1.

### Confirmed approval / workflow defaults (retained + finalized)

| Workflow | Confirmed rule |
|---|---|
| Price / margin | Sales Manager sets and approves |
| Sales Order approve | Sales Manager |
| Cancel approved Sales Order | **Sales Manager only** |
| Inventory adjustment | Warehouse records → Sales Manager approves |
| Credit limit | Sales Rep requests → Accounts reviews → Sales Manager final |
| Collection reversal | Accounts **requests** → Sales Manager **approves** |
| Credit / debit notes | Accounts **creates** → Sales Manager **approves** |
| Deal close | Accounts manually closes when all linked invoices fully paid |
| Overdue email | System auto-sends; Accounts may resend |
| Product master | **Administrator** manages product records; **Procurement** may edit **purchase cost only** |

### Confirmed denials

- Warehouse: no prices, no invoices, no financial edits, no SO cancel, no adjustment self-approve.
- Administrator / Executive: **no** approval of sales transactions (pricing, SO approve/cancel, credit final, notes, collection reversal).
- Sales Rep: no view outside Assigned/Cust/Own scopes; no cancel approved SO; no self-approve credit/pricing.
- No multi-role users; no external-driver logins.

---

## 4. Action vocabulary & legend

| Action | Meaning |
|---|---|
| View / Create / Edit / Submit / Approve-Reject / Send / Cancel / Report | As previously defined |

| Cell | Meaning |
|---|---|
| **Y** | Confirmed allow |
| **N** | Confirmed deny |
| **Own** / **Assigned** / **Cust** / **All** / **Work** / **Referred** / **Admin** | Confirmed scoped allow |
| **Sys** | System-automated |
| **Cost** | Edit purchase-cost fields only |

---

## 5. Permission matrix

### 5.1 Platform

| Action | Sales Rep | Sales Mgr | Procurement | Warehouse | Accounts | Executive | Administrator |
|---|---|---|---|---|---|---|---|
| View users | N | N | N | N | N | N | Y |
| Create/edit users; assign **one** role | N | N | N | N | N | N | Y |
| Manage roles / permission config | N | N | N | N | N | N | Y |
| Configure approval authority | N | N | N | N | N | N | Y |
| View audit logs | N | Y | N | N | Y | Y | Y |
| Manage email / secrets / company / warehouse settings | N | N | N | N | N | N | Y |

### 5.2 Customers and visits

| Action | Sales Rep | Sales Mgr | Procurement | Warehouse | Accounts | Executive | Administrator |
|---|---|---|---|---|---|---|---|
| View customers | **Assigned** | **All** | N | **Work** | **Work** | **All** (read) | **Admin** |
| Create/edit customers | Y | Y | N | N | N | N | N |
| View visits | **Cust** | **All** | N | **Work** | **Work** | **All** (read) | **Admin** |
| Create/edit/submit visits | Y | Y | N | N | N | N | N |
| Cancel scheduled visits | **Own** (visits assigned to that Rep) | **All** | N | N | N | N | N |
| Report: customer activity | **Own**/Cust | **All** | N | N | **All** | **All** | N |

**Visit assignment (confirmed):** assignee is **always** the Sales Representative **assigned to the customer** (server-enforced). No alternate-assignee override. If the customer has no assigned Rep, visit scheduling is blocked until assignment.

**Visit notifications (confirmed):** in-app notify the assigned representative when **another** authorized user creates, changes the scheduled time, or cancels that visit; **no** recurring reminders. Visit GPS excluded from v1 — see `docs/WORKFLOWS.md` §2.5 / `docs/OPEN_QUESTIONS.md` resolved `VISIT-*` / `GPS2`.

### 5.3 Products and inventory

| Action | Sales Rep | Sales Mgr | Procurement | Warehouse | Accounts | Executive | Administrator |
|---|---|---|---|---|---|---|---|
| View products (non-price catalog as applicable) | Y | Y | Y | Y | Y | Y | Y |
| Create/edit product master records | N | N | N | N | N | N | **Y** |
| Edit purchase cost only | N | N | **Cost** | N | N | N | Y |
| View stock balances | Y | Y | Y | Y | Y | Y | Y |
| Record inventory adjustment | N | N | N | **Y** | N | N | N |
| Approve/reject inventory adjustment | N | **Y** | N | N | N | N | N |
| View selling prices | Y | Y | N | **N** | Y | Y | Y |
| Report: inventory | N | Y | Y | Y | Y | Y | Y |

### 5.4 Sales requests and quotations

| Action | Sales Rep | Sales Mgr | Procurement | Warehouse | Accounts | Executive | Administrator |
|---|---|---|---|---|---|---|---|
| View sales requests | Cust | All | Referred/related | N | Work | All (read) | Admin |
| Create/edit/submit/cancel sales requests | Y | Y | N | N | N | N | N |
| View quotations (incl. prices) | **Cust** | **All** | N | **N** | **All** | **All** (read) | **Admin** |
| Create/edit quotation drafts | Y | Y | N | N | N | N | N |
| Submit for price approval | Y | Y | N | N | N | N | N |
| Approve/reject price & margin | N | **Y** | N | N | N | N | N |
| Send quotation (email) | Y | Y | N | N | N | N | N |
| Cancel / supersede quotation | Y | Y | N | N | N | N | N |
| Report: quotation pipeline | Own/Cust | All | N | N | All | All | N |

### 5.5 Procurement

| Action | Sales Rep | Sales Mgr | Procurement | Warehouse | Accounts | Executive | Administrator |
|---|---|---|---|---|---|---|---|
| View procurement tasks | related | All | Y | N | N | All (read) | Admin |
| Create/edit/submit supplier quotes | N | N | Y | N | N | N | N |
| Cancel procurement task | N | Y | Y | N | N | N | N |

### 5.6 Customer purchase orders and sales orders

| Action | Sales Rep | Sales Mgr | Procurement | Warehouse | Accounts | Executive | Administrator |
|---|---|---|---|---|---|---|---|
| View customer POs | **Cust** | **All** | N | **Work** | **Work** | **All** (read) | **Admin** |
| Create/link/edit/submit customer POs | Y | Y | N | N | N | N | N |
| Cancel customer PO | N | Y | N | N | N | N | N |
| View sales orders | **Cust** | **All** | **Referred** | **All** | **All** | **All** (read) | **Admin** |
| Create/edit SO drafts | **Y** | Y | N | N | N | N | N |
| Submit SO for approval | **Y** | Y | N | N | N | N | N |
| Approve/reject SO | N | **Y** | N | N | N | N | N |
| Cancel approved SO | N | **Y** | N | N | N | N | N |
| Report: PO/SO | Own | All | Referred | All | All | All | N |

System credit gate: block new SO approval when unpaid invoice exposure ≥ credit limit.

### 5.7 Warehouse and delivery

| Action | Sales Rep | Sales Mgr | Procurement | Warehouse | Accounts | Executive | Administrator |
|---|---|---|---|---|---|---|---|
| View/update preparation & delivery; POD; confirm (deduct) | N | Y (oversight) | N | **Y** | N | All (read) | Admin |
| Cancel delivery before confirm | N | Y | N | Y | N | N | N |
| View prices on warehouse screens | N | Y | N | **N** | Y | Y | Y |
| Report: delivery progress | N | Y | N | Y | Y | Y | N |

### 5.8 Invoices, collections, credit

| Action | Sales Rep | Sales Mgr | Procurement | Warehouse | Accounts | Executive | Administrator |
|---|---|---|---|---|---|---|---|
| View invoices | **Cust** | **All** | N | **N** | **All** | **All** (read) | **Admin** |
| Issue invoice after POD | N | N | N | N | **Y** | N | N |
| Edit issued invoice | N | N | N | N | N | N | N |
| Create credit/debit note | N | N | N | N | **Y** | N | N |
| Approve/reject credit/debit note | N | **Y** | N | N | N | N | N |
| Request collection reversal | N | N | N | N | **Y** | N | N |
| Approve/reject collection reversal | N | **Y** | N | N | N | N | N |
| Record/allocate collections | N | N | N | N | **Y** | N | N |
| Request credit-limit change | **Y** | N | N | N | N | N | N |
| Review credit-limit request | N | N | N | N | **Y** | N | N |
| Final approve/reject credit-limit | N | **Y** | N | N | N | N | N |
| Close deal (manual; all invoices paid) | N | N | N | N | **Y** | N | N |
| Auto overdue reminder | — | — | — | — | **Sys** | — | — |
| Resend overdue reminder | N | N | N | N | **Y** | N | N |
| Report: AR / statements | Own/Cust | All | N | N | All | All | N |

### 5.9 Communications

| Action | Sales Rep | Sales Mgr | Procurement | Warehouse | Accounts | Executive | Administrator |
|---|---|---|---|---|---|---|---|
| Send approved email templates | Y | Y | Y | N | Y | N | N |
| Manage templates / provider | N | N | N | N | N | N | Y |
| Resend overdue | N | N | N | N | Y | N | N |
| WhatsApp | N | N | N | N | N | N | N |
| External driver login | N | N | N | N | N | N | N |

### 5.10 Reports (no branch filters in v1)

| Action | Sales Rep | Sales Mgr | Procurement | Warehouse | Accounts | Executive | Administrator |
|---|---|---|---|---|---|---|---|
| Operational / commercial reports | **Own** | **All** | Referred/related | Work/All ops | **All** | **All** | N |
| Financial statements | N | N | N | N | **All** | **All** | N |
| Export within allowed scope | Y | Y | Y | Y | Y | Y | Y |

---

## 6. Unresolved items

**Authorization matrix:** no open permission questions remain for the decisions in this document.

**Still open elsewhere (business domain — not permission gaps):** see `docs/OPEN_QUESTIONS.md` (invoice grouping, numbering, lots, email provider, credit-note netting, etc.). `INV3`, `PF7`, and `ORG3` (Deal stored 1:1 with Customer PO) are **resolved**. Those remaining must **not** be treated as resolved because this matrix is approved.

---

## 7. Phase 0 criterion 0.12

| Criterion | Status |
|---|---|
| 0.12 Stakeholder approval of role/permission matrix | **Satisfied** — matrix reflects final stakeholder authorization decisions in this update |
