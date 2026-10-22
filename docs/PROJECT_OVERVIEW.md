# Project Overview

**Document type:** Phase 0 — requirements and domain design  
**Status:** Phase 0 authorization matrix **accepted** (criterion 0.12); business-domain open questions remain gated  
**Source baseline:** `agents/project_vision/project_guide` + `docs/*` Phase 0 set  
**Repository:** Laravel skeleton only (see §7); documentation-only updates

---

## 1. Business goal

Build a single Laravel **modular monolith** that manages the commercial lifecycle for sales, procurement, inventory, warehouse preparation, delivery, invoicing, and collections for a **Saudi Arabia** market business.

The platform must be a consistent source of truth for business records and their relationships. Success means complete, traceable workflows with controlled state transitions, accurate quantities and balances, server-side authorization, and verifiable outcomes—not disconnected CRUD screens or decorative UI actions.

### Primary objectives

- Centralize customer, product, sales, inventory, delivery, and financial records.
- Enforce clear role responsibilities and **configurable** role/position-based approval authority.
- Track lifecycle and status of important business documents.
- Support partial fulfillment: multiple Sales Orders per Customer PO; **multiple deliveries per Sales Order**.
- Maintain accurate quantities, prices, margins/markups, invoice balances, and collections.
- Prevent unauthorized approvals and invalid state transitions.
- Keep an auditable history of important actions, inventory ledger events, and document changes.
- Integrate **email** first (WhatsApp deferred beyond initial release).
- Provide dashboards and reports based on authoritative records.
- Deliver incrementally, with a usable and testable result at each phase gate.

### Product constraints

- Arabic RTL UI support; responsive desktop and mobile layouts.
- Every operational UI action must map to a real backend workflow; no decorative buttons or fake data in production flows.
- Each implementation step requires automated tests for relevant business rules, authorization, validation, and edge cases, plus manual UI review.
- Target market: **Saudi Arabia**. E-invoicing integration is **not** in the initial release; this does **not** remove applicable legal obligations. Accountant review and applicable **ZATCA** compliance confirmation are **pre-launch requirements**.

---

## 2. Architecture: modular monolith

One Laravel application with logical business modules and explicit boundaries.

### v1 organization (confirmed)

- **One company**
- **One warehouse**
- Multi-company / multi-warehouse deferred unless a later decision changes scope

### Architectural rules (confirmed)

- Prefer Laravel conventions for the installed framework version.
- Controllers coordinate HTTP; complex business operations live in services/actions as justified.
- Use Form Requests, policies, resources, jobs, and events where they provide clear benefit.
- Keep business calculations centralized and deterministic.
- Use database transactions for operations that must succeed or fail together.
- Do not introduce repositories, interfaces, DTOs, or other abstractions without concrete justification.

---

## 3. Proposed modules

| # | Module | Responsibility |
|---|---|---|
| 1 | **Users / permissions** | Auth, users, roles, **configurable approval authority by role/position**, company settings, single warehouse config, audit, attachments, notifications, email integration config |
| 2 | **Customers / visits** | Profiles, contacts, locations, photos, credit limits, visits, history |
| 3 | **Products / inventory** | Catalog managed by Administrator; Procurement edits purchase cost only; single-warehouse balances; reserve/deduct/release; WH adjustments SM-approved |
| 4 | **Sales requests / quotations** | Requests, Sales Manager cost→price (margin/markup), VAT-inclusive approved quotations, email send |
| 5 | **Procurement** | Supplier quotes / costs when products unavailable |
| 6 | **Customer POs / sales orders** | PO linkage; Sales Rep create/submit SO drafts; SM approve (reserve); multi-SO fulfillment; **SM-only** cancel approved SO; Rep views **own** SOs; SM/Warehouse/Accounts view **all** SOs |
| 7 | **Warehouse / delivery** | Preparation, multi-delivery per SO, POD, confirm (deduct); Warehouse records inventory adjustments (SM approves); **no driver GPS** in initial release |
| 8 | **Invoices / collections / credit** | Invoice after POD; unpaid-invoice credit exposure; credit limit Rep→Accounts→SM; collections; Accounts **manual** deal close when all invoices paid; auto overdue email + Accounts resend; credit/debit notes |
| 9 | **Communications** | **Email** templates, send, tracking, retry (**WhatsApp out of initial release**) |
| 10 | **Reports** | Role-scoped dashboards reconciling to authoritative records |

**Lifecycle spine:**

Customer acquisition → Visit → Sales Request → Pricing / Quotation (VAT-inclusive) → Customer PO → Sales Order (Rep submit; SM approve/reserve) → Preparation → Delivery + POD (deduct) → Invoice → Collection → Accounts closes deal (all invoices paid).

**Branch:** Procurement / supplier quote when products unavailable or need cost.

---

## 4. Confirmed vs assumptions vs open

### 4.1 Confirmed (stakeholder Prompt 2 + prior guide)

- Modular monolith; v1 one company, one warehouse.
- Reserve on SO approve; deduct on delivery confirm; cancel SO releases remaining reservation exactly once with ledger/audit; prevent double release/deduction.
- One SO → many deliveries; delivery qty ≤ undelivered SO qty.
- Credit exposure = unpaid invoice balances only; block new orders when exposure ≥ credit limit until below limit.
- Sales Manager sets/approves price via margin/markup on cost; quotation prices include VAT.
- Invoice only after POD, for delivered quantity; amounts traceable to confirmed deliveries.
- Deal closes only when all linked invoices are fully paid.
- Issued invoices are not silently edited/deleted; corrections via credit/debit notes with Accounts approval.
- Approval authority configurable by role/position; Sales Manager approves pricing and SOs; **only Sales Manager cancels approved SOs**.
- Sales Rep: assigned customers only; quotations/invoices for those customers; create/submit SO drafts; own SO view only.
- Sales Manager / Warehouse / Accounts: view **all** Sales Orders.
- Inventory adjustments: Warehouse records; Sales Manager approves.
- Credit limit: Rep requests → Accounts reviews → Sales Manager final approval.
- Accounts manually closes deals when all linked invoices are fully paid; system auto-sends overdue emails; Accounts may resend.
- Product master: **Administrator** manages; Procurement edits **purchase cost only**.
- One role per user; no external-driver accounts; no branch filters in v1.
- Executive read-only/report; Administrator manages users/settings/products but does **not** approve sales transactions.
- Credit/debit notes: Accounts creates, SM approves; collection reversal: Accounts requests, SM approves.
- Warehouse cannot view prices or invoices.
- Email in; WhatsApp, driver GPS, and **visit GPS / check-in** out of initial release.
- **Customers (v1):** organizations / establishments only; `name_ar` = establishment name (اسم المنشأة); individuals out of scope (`ORG6`).
- **Visits:** `scheduled` → `completed` or `cancelled` (terminal); assignee **always** = customer’s Sales Rep (no override; blocked if unassigned); Rep cancels own scheduled visits; SM cancels any scheduled visit; in-app notify assignee when another authorized user creates/changes time/cancels — no recurring reminders.
- Arabic RTL + responsive; real backend workflows only; tests required per implementation step.

### 4.2 Assumptions (still not fully locked)

| ID | Assumption |
|---|---|
| A2 | Executive and System Administrator remain separate role categories. |
| A3 | Procurement remains a first-class module boundary. |
| A4 | English entity/field names; Arabic UI copy. |
| A5 | Document numbering uses configurable sequences (format open). |
| A6 | External driver login optional/deferred with GPS. |
| A7 | ~~Superseded by `INV3`~~ Available = on-hand − reserved; negative stock **not** allowed — see Resolved `INV3`. |

### 4.3 Highest remaining open questions

See `docs/OPEN_QUESTIONS.md`. Keep visible (especially schema-related):

- ~~Pricing P0~~ resolved (`PM1`/`PM4a`/`PM4b`/`TX8`); remaining pricing edge cases `PM3`/`PM5`/`PM6` are P1.
- Invoice grouping: per delivery vs consolidated.
- Whether Sales Orders may exist without a Customer Purchase Order.
- Document numbering, lot tracking, email provider.
- Permission matrix is **approved** — remaining opens are **business-domain** only (`docs/OPEN_QUESTIONS.md`).

---

## 5. Development phases

| Phase | Focus | Status |
|---|---|---|
| 0 | Requirements and domain design | **Accepted** for 0.1–0.12 (auth matrix); domain P0 questions still gate later phases |
| 1 | Platform foundation | Ready for Review (Prompts 1–8) — see `PHASE_ACCEPTANCE` §9a; stakeholder **Accepted** still required |
| 2 | Customers and visits | **Accepted** (stakeholder) — see `PHASE_ACCEPTANCE` §9b |
| 3 | Products and inventory | **Accepted** (stakeholder, 2026-10-16); SO/delivery UI wiring later (Phase 5); `INV4` only if lots in scope |
| 4 | Sales requests, pricing, quotations | **Accepted** (stakeholder, 2026-10-16); `CM1b` live SMTP still open |
| 5 | Purchase orders and sales orders | **Accepted** (stakeholder, 2026-10-16); live credit exposure Phase 7 |
| 6 | Warehouse preparation and delivery | **Accepted** (stakeholder, 2026-10-16) |
| 7 | Invoicing, collections, credit | **Accepted** (stakeholder, 2026-10-16); live SMTP deferred to Phase 8 (`CM1b`) |
| 8 | Email / WhatsApp communications | **Deferred** (stakeholder, 2026-10-16) — live activation later; `Mail::fake`/log only for now |
| 9 | Dashboards and reporting | **Accepted** (stakeholder, 2026-10-17); no scheduled exports in v1 |
| 10 | End-to-end validation and pilot (+ ZATCA pre-launch review) | Ready — Phase 9 Accepted; Phase 8 email deferred from pilot gate |

Full gates and evidence: `docs/PHASE_ACCEPTANCE.md`.

---

## 6. Related documents

| File | Purpose |
|---|---|
| `docs/plat-guide.md` | Plain Arabic numbered plan by phase; completed steps struck through and updated after every finished task |
| `docs/WORKFLOWS.md` | Workflows, transitions, inventory/credit effects |
| `docs/ROLES_AND_PERMISSIONS.md` | v1 roles, configurable approvals, permission matrix |
| `docs/DOMAIN_GLOSSARY.md` | Terms and relationships |
| `docs/OPEN_QUESTIONS.md` | Resolved vs remaining questions |
| `docs/PHASE_ACCEPTANCE.md` | Phase 0–10 acceptance criteria and Phase 1 safe-start list |
| `docs/ARCHITECTURE.md` | Modular-monolith directory convention and shared UI placement |
| `agents/project_vision/project_guide` | Broader engineering guidelines |

---

## 7. Repository findings (unchanged inspection summary)

| Item | Finding |
|---|---|
| PHP | 8.4.1 (`^8.3`) |
| Laravel | **13.36.0** |
| App packages | `laravel/framework`, `laravel/tinker` |
| Auth packages | None |
| Domain features | None implemented |
| Tests | Stock Example feature/unit tests only |

**Prompt 3 did not modify application code, packages, migrations, or database schema. No tests were run for this documentation step.**
