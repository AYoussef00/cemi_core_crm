# Phase Acceptance Criteria

**Document type:** Phase 0 — acceptance gates (Prompt 3)  
**Status:** Active  
**Related:** `docs/PROJECT_OVERVIEW.md`, `docs/WORKFLOWS.md`, `docs/ROLES_AND_PERMISSIONS.md`, `docs/OPEN_QUESTIONS.md`, `agents/project_vision/project_guide`

---

## 1. Purpose

This document defines:

1. When **Phase 0 (documentation)** may be marked complete.  
2. Implementation acceptance gates for **Phases 1–10**.  
3. Required evidence (tests, UI review, authorization, integrations).  
4. How defects and open questions interact with phase completion.  
5. Which **Phase 1** work may start while domain-schema questions remain open.

---

## 2. Phase status model

| Status | Meaning |
|---|---|
| **Not Started** | No substantive work begun |
| **In Progress** | Implementation or documentation underway |
| **Blocked** | A dependency, decision, or critical defect stops progress on *this* phase’s critical path |
| **Implemented, Not Verified** | Code/docs exist; acceptance evidence incomplete |
| **Ready for Review** | Required work and checks done; awaiting reviewer/stakeholder |
| **Accepted** | Acceptance criteria verified and approved |

A phase is **Accepted** only when its criteria and required evidence are satisfied—not merely because code compiles or screens exist.

---

## 3. Defects and open questions

### 3.1 Defect tracking (implementation phases)

| Severity | Handling |
|---|---|
| **Blocking** | Prevents phase **Accepted**; must be fixed or explicitly waived in writing by stakeholder |
| **Major** | Should be fixed before phase Accepted unless waived with risk note |
| **Minor / polish** | May carry forward if documented in known limitations |

Track defects with: ID, phase, module, steps to reproduce, expected vs actual, severity, owner, status. Do not delete history when closing.

### 3.2 Open questions

- Unresolved items live in `docs/OPEN_QUESTIONS.md`.  
- An open question **blocks only the work that depends on it**.  
- Independent modules may proceed when they do not encode unapproved assumptions.  
- Guessing a P0 business rule to “unblock” schema is not allowed.

---

## 4. Required evidence types (all implementation phases)

Unless a phase is documentation-only:

| Evidence | Requirement |
|---|---|
| **Automated tests** | Cover relevant business rules, validation, authorization (allow and deny), and edge cases for the phase scope; results recorded (pass/fail summary) |
| **Authorization checks** | Server-side deny tests for roles that must not perform the action; least privilege verified for the phase’s resources |
| **Manual UI/UX review** | Where UI is delivered: Arabic RTL where applicable, responsive desktop/mobile, no decorative/fake actions; every control maps to a real backend workflow |
| **External integration verification** | Only when the phase includes a provider (e.g., email): config, send/failure/retry behavior verified in a non-production or controlled environment |
| **Audit / ledger** | Where inventory or financial side effects exist: events are traceable and idempotent rules tested |

**This Phase 0 documentation step does not claim any tests were run.**

---

## 5. Phase 0 — Documentation acceptance

### 5.1 Deliverables present

| Deliverable | Location |
|---|---|
| Project overview | `docs/PROJECT_OVERVIEW.md` |
| Workflows & transitions | `docs/WORKFLOWS.md` |
| Roles & permissions | `docs/ROLES_AND_PERMISSIONS.md` |
| Domain glossary | `docs/DOMAIN_GLOSSARY.md` |
| Open questions | `docs/OPEN_QUESTIONS.md` |
| Phase acceptance (this file) | `docs/PHASE_ACCEPTANCE.md` |

### 5.2 Phase 0 acceptance criteria

| # | Criterion | Notes |
|---|---|---|
| 0.1 | Ordinary available-product sales workflow documented | `WORKFLOWS` §A |
| 0.2 | Unavailable / supplier-quote workflow documented | `WORKFLOWS` §B |
| 0.3 | Multi-SO / multi-delivery and partial delivery + invoice documented | `WORKFLOWS` §C–D |
| 0.4 | Credit, collections, deal close, correction notes documented | `WORKFLOWS` §E–§8 |
| 0.5 | Important terms defined; ambiguities listed | `DOMAIN_GLOSSARY` |
| 0.6 | Confirmed vs open decisions explicit | `OPEN_QUESTIONS`, `PROJECT_OVERVIEW` |
| 0.7 | v1 roles listed; confirmed approval duties documented | `ROLES_AND_PERMISSIONS` |
| 0.8 | Permission matrix with view/create/edit/submit/approve-reject/send/cancel/report; unconfirmed cells marked proposed | `ROLES_AND_PERMISSIONS` |
| 0.9 | Role permission vs record-level scope distinguished; open scope called out | `ROLES_AND_PERMISSIONS` |
| 0.10 | Least privilege + server-side authorization required for implementation | `ROLES_AND_PERMISSIONS` |
| 0.11 | Phase gates 1–10 and Phase 1 “can start / must wait” documented | This file |
| 0.12 | **Stakeholder approval** of role/permission matrix | **Satisfied** — final authorization decisions applied in `ROLES_AND_PERMISSIONS.md` |

### 5.3 Phase 0 status

| Item | Status |
|---|---|
| Documentation set completeness (0.1–0.11) | **Met** |
| Stakeholder matrix approval (0.12) | **Met** |
| Overall Phase 0 (documentation / authorization) | **Accepted** for criteria 0.1–0.12 |
| Business-domain open questions (pricing math, VAT, deal entity, etc.) | **Remain open** — do not treat as resolved; continue to gate domain modules (§7) |

Unresolved P0 **schema/domain** questions do not reopen criterion 0.12, but they **block** the affected implementation phases until answered.

---

## 6. Phases 1–10 — Implementation gates

Aligned with the project plan; adjusted for confirmed v1 scope (single company/warehouse; email first; WhatsApp and driver GPS out of initial release).

### Phase 1 — Platform foundation

**Scope:** Authentication, users, roles/permissions scaffolding, configurable approval-authority settings, company + single warehouse settings, audit logging, attachment foundation, internal notifications, email integration **configuration foundation** (not full business sends).

**Acceptance criteria:**

- Users can authenticate; sessions secure for the chosen stack.  
- Role-based access tested; unauthorized operations denied server-side.  
- Approval-authority configuration can assign pricing / SO / credit participants by role/position; v1 defaults include confirmed credit sequence (Rep request → Accounts review → SM final).  
- Important config/user changes auditable.  
- Attachments stored privately with access control.  
- Secrets/config handled safely (not in source control).  
- Arabic RTL / responsive shell for auth and admin UI if UI is shipped.

**Evidence:** Feature tests for authz allow/deny; audit assertions; manual UI review of login/admin; no domain sales/inventory migrations required for **Accepted** if marked foundation-only.

**May start while domain schema questions remain open?** **Yes** — see §7.1.

---

### Phase 2 — Customers and visits

**Scope:** Customer profiles, contacts, locations, photos, credit limit fields, visits, outcomes, notes, history.

**Acceptance criteria:** Customers/visits CRUD per permissions; attachment access correct; search/history work; Sales Representative can view only **assigned customers**; credit-limit **request** by Sales Rep (review/final approve tested in Phase 7 if split). Visits follow approved policies: `scheduled` → `completed`\|`cancelled`; default assignee = customer’s Sales Rep (SM may override); Rep cancels **own** scheduled visits; SM cancels any scheduled visit; in-app notify assignee on create/reschedule/cancel by another user; **no** visit GPS in v1.

**Evidence:** Automated permission + validation tests; manual RTL/responsive UI review; authz deny tests for unassigned customers; visit workflow tests (Prompt 4).

**Status:** Phase 2 **Accepted** (stakeholder, 2026-10-16). `ORG6` resolved (org/establishment-only). Visit assignee is always the customer’s Sales Rep (see §9b).

---

### Phase 3 — Products and inventory

**Scope:** Product catalog, UoM, specs, single-warehouse balances, movements, availability; reserve/deduct/release ledger hooks as needed for later SO/delivery (or shared inventory service).

**Acceptance criteria:** Valid product data; movements update balances; **no negative stock** (`INV3`: reject reserve / deduct / decreasing adjustment when quantity insufficient); movement history traceable; concurrency tests where relevant.

**Evidence:** Unit/feature tests for ledger idempotency patterns; authz tests; UI review for catalog/stock screens.

**Must wait for:** ~~`INV3`~~ **resolved** — negative stock **not** allowed (reject reserve / deduct / decreasing adjustment when quantity insufficient). Lot tracking (`INV4`) only if in scope. Numbering (`NUM*`) if product codes require it. Product master: Administrator manages; Procurement purchase-cost only (confirmed). Inventory adjustment: Warehouse records + Sales Manager approve (confirmed).

**Status:** Phase 3 **Accepted** (stakeholder, 2026-10-16). Engineering Prompts 1–3 + UI polish delivered. SO/delivery wiring of InventoryService deferred to Phase 5.

---

### Phase 4 — Sales requests, pricing, quotations

**Scope:** Sales requests; procurement routing; Sales Manager cost→price (margin/markup); VAT-inclusive approved quotations; email send of approved quotes; revision/expiry.

**Acceptance criteria:** Available vs unavailable paths; price/margin approval enforced; unapproved quotes cannot send; PDF/content correct; expiry/revision verified.

**Evidence:** Pricing approval allow/deny tests; send-guard tests; UI review; email provider test or mocked provider with failure path.

**Must wait for:** ~~`PM1` / `PM4a` / `PM4b` / `TX8`~~ **resolved** 2026-10-16 — cost+absolute margin; no discounts; 2 dp rounding; VAT fixed 15%. Email provider (`CM1b`) still required for **real** send verification (mocked provider OK for earlier steps).

**Status:** Phase 4 **Accepted** (stakeholder, 2026-10-16) — Prompts 1–2 + Designer polish. Limitation recorded: live SMTP (`CM1b`) not claimed; mocked/log send accepted for this phase.

---

### Phase 5 — Customer POs and sales orders

**Scope:** Customer PO intake/link; Sales Rep create/submit SO drafts; Sales Manager approve (reserve); multi-SO per PO; Sales Manager–only cancel of approved SO (release once); credit gate on new orders; warehouse notification.

**Acceptance criteria:** Quantities respect PO limits; concurrency-safe allocation; reserve on approve; cancel releases remaining reservation once; credit exposure gate from unpaid invoices; multi-SO fulfillment works; Sales Rep create/submit SO drafts allowed; Sales Rep views only own SOs; Sales Manager / Warehouse / Accounts view all SOs; non–Sales Manager roles denied cancel of approved SO.

**Evidence:** Concurrent allocation tests; reserve/release idempotency tests; credit gate tests; authz tests (Rep create/submit, own-scope view, SM/WH/Accounts all-view, SM-only cancel); UI review.

**Must wait for:** ~~`PF7`~~ **resolved** — SO requires Customer PO. ~~`ORG3`~~ **resolved** — Deal stored, 1:1 with Customer PO (close action still Phase 7). `PF1` if reporting schema-critical; `CR2b`/`CR7` for credit-gate precision as applicable.

**Status:** Phase 5 **Accepted** (stakeholder, 2026-10-16). Limitation closed in Phase 7: `CreditExposure` now sums unpaid issued invoices (`CR7`).

---

### Phase 6 — Warehouse preparation and delivery

**Scope:** Preparation tasks/evidence; multiple deliveries per SO; POD; confirm delivery (deduct); **no** driver GPS requirement.

**Acceptance criteria:** Tasks from approved SOs; delivery qty ≤ undelivered SO qty; POD required before confirm; deduct once; quantities reconcile; no price edits from warehouse.

**Evidence:** Delivery cap tests; deduct idempotency; authz deny for price/finance; UI review; driver GPS explicitly out of scope for acceptance.

**Must wait for:** Stable SO/inventory from Phases 3–5. External driver access (`GPS4`) only if added later.

**Status:** Phase 6 **Accepted** (stakeholder, 2026-10-16). No driver GPS (by design).

---

### Phase 7 — Invoicing, collections, and credit

**Scope:** Invoice after POD for delivered qty; payment terms; credit-limit workflow (Rep request → Accounts review → SM final); collections; partial pay; automatic overdue email + Accounts resend; Accounts manual deal close when all invoices paid; credit/debit notes (no silent invoice edit).

**Acceptance criteria:** No invoice without POD; qty/amounts traceable to deliveries; no duplicate invoice of same qty; collections update balances; exposure = unpaid invoices; Sales Rep views invoices for assigned customers only; credit sequence enforced; deal close only by Accounts and only when all linked invoices fully paid; issued invoices immutable except via notes; overdue auto-send + Accounts resend.

**Evidence:** Financial reconciliation tests; credit gate + credit-limit sequence tests; note immutability tests; authz tests; overdue job + resend tests; UI review; ZATCA checklist recorded as **pre-launch** (not claiming e-invoicing API done).

**Must wait for:** ~~`CR7`/`TX5b`/`PF3`~~ **resolved** 2026-10-16 — per-delivery invoice; unpaid nets confirmed collections + approved notes; deal close when all linked invoices unpaid=0. ~~`TX8`/discounts/rounding~~ resolved earlier. Credit/debit notes: Accounts creates, SM approves; collection reversal: Accounts requests, SM approves (confirmed). Accountant/ZATCA pre-launch is a **launch** gate—Phase 7 must not claim legal compliance complete.

**Status:** Phase 7 **Accepted** (stakeholder, 2026-10-16) — Prompt 1 + Designer polish. Limitation: live SMTP (`CM1b`) not claimed; overdue uses `Mail::fake` in tests.

---

### Phase 8 — Email / WhatsApp communications

**Scope:** Provider config, templates, document emails, recipient rules, tracking, failure/retry; WhatsApp productization when in scope.

**Acceptance criteria:** Recipients per rules; content/attachments correct; provider states reflected; failures visible/retryable; duplicate-send controlled; messages linked to business records.

**Evidence:** Integration test or sandbox send; failure simulation; authz on send; UI review of message history.

**Must wait for:** `CM1b` provider choice; `CM2`/`CM3`/`CM6` for automation rules. Do not accept Phase 8 based on WhatsApp alone.

**Status:** **Deferred** (stakeholder, 2026-10-16) — do **not** start Phase 8 now. Live email activation and WhatsApp postponed until after core domain work. Existing quote/overdue paths may keep `Mail::fake` / log mailer; do not claim production inbox delivery. Revisit after Phase 9 (or when stakeholder reopens).

---

### Phase 9 — Dashboards and reporting

**Scope:** Role-specific dashboards; approvals; warehouse tasks; AR/collections; customer statements. **No** scheduled reports or file exports in v1 (`RP3`/`RP4`).

**Acceptance criteria:** Values reconcile to authoritative records; report authz enforced; partial fulfillment/collection represented correctly (PF1 labels); filters/dates use Asia/Riyadh (`RP2`); no fake KPIs.

**Evidence:** Reconciliation tests or verified fixtures; authz tests; UI review; timezone confirmed.

**Must wait for:** ~~`RP1`/`RP2`/`RP3`–`RP4`/`PF1`~~ **resolved** 2026-10-16.

**Status:** Phase 9 **Accepted** (stakeholder, 2026-10-17) — Prompt 1 + Designer polish. Limitations: no scheduled reports/exports (`RP3`/`RP4`); Phase 8 live email/WhatsApp still deferred.

### Phase 9 — Prompt 1 (home dashboard + AR/statements + PF1) — **Accepted**

| | |
|---|---|
| Scope delivered | `Asia/Riyadh`; extended `HomeDashboardBuilder` (sales requests, prep queue, AR CR7, open deals); `/reports/ar` + customer statements; PF1 on Customer PO + Deal show; sidebar enables report/statement links |
| Designer polish | `ds-page-bar` on dashboard/AR/statements; inventory metrics + unpaid emphasis; PF1 card with three separate labels; sidebar report titles aligned; empty surfaces; **no** export/schedule CTAs; Phase 8 email nav stays deferred |
| Authz | Warehouse denied AR/statements; Rep Own/Cust scoped; Accounts/SM/Exec All where permitted |
| Out of scope | Scheduled reports, file export, Phase 8 email/WhatsApp, sales/procurement/inventory report packs |
| Automated tests | `php artisan test --filter='Phase9ReportsTest|HomePageTest'` — **11 passed** (56 assertions) after polish |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4) after CSS |
| Docs | `docs/REPORTS.md`; plat-guide #36–#37 struck |
| Phase 9 overall | **Accepted** (stakeholder, 2026-10-17) |

**Recommended next:** Phase 10 end-to-end validation / pilot (#38–#39). Phase 8 remains deferred. Phase 1 formal Accepted still open.

---

### Phase 10 — End-to-end validation and pilot

**Scope:** Controlled full lifecycle pilot.

**Required scenarios (adjusted for v1 decisions):**

1. New customer, visit, fully available sales request → quote → PO → SO → delivery → invoice → collect → deal close.  
2. Unavailable product / supplier quote path.  
3. Quotation expires without acceptance.  
4. One Customer PO, multiple SOs and/or multiple deliveries.  
5. Partial delivery; invoice delivered qty only.  
6. Credit limit reached blocks new orders; payment re-enables.  
7. Partial collection then full settlement; Accounts manually closes deal only when all invoices paid.  
8. Delivery without inventing GPS (driver process as implemented without GPS).  
9. Email: **deferred with Phase 8** — verify `Mail::fake` / log paths still honest; do **not** require live SMTP recovery for Phase 10 Accepted.  
10. Unauthorized access attempts across roles.  
11. Concurrent allocation / double-submit reserve-deduct-release / duplicate payment safeguards.  
12. Reporting reconciliation (home KPIs + AR/statements vs CR7 / PF1).  
13. Credit/debit note path preserves original invoice.  
14. Pre-launch: accountant review + applicable ZATCA confirmation recorded (e-invoicing API still not required for initial release) — plat-guide #39.

**Acceptance criteria:** Critical workflows pass; permissions pass; blocking defects resolved; calculations reconcile; ops/recovery docs exist; limitations and risks reviewed; stakeholders approve pilot.

**Evidence:** Test suite green for critical paths; pilot checklist signed; known limitations list; ZATCA/accountant pre-launch sign-off attached or explicitly deferred with risk acceptance.

**Status:** Phase 10 Prompt 1 (#38) **Implemented**. **#39 ZATCA/accountant deferred for now** (stakeholder 2026-10-17 — not a current pilot blocker; no compliance claim). Phase 8 remains deferred. Phase 10 **Accepted** only when stakeholder signs pilot checklist §4 if they choose formal acceptance.

---

### Phase 10 Prompt 1 verification (2026-10-17)

| Item | Result |
|---|---|
| Scope delivered | E2E Feature suite for scenarios 1–8, 10–13; scenario 9 Mail::fake honesty; authz deny matrix; concurrency idempotency + duplicate invoice/collection guards |
| Tests | `tests/Feature/Pilot/Phase10LifecycleScenariosTest.php`, `Phase10AuthzConcurrencyTest.php`, trait `BuildsCommercialLifecycle`; full suite **233 passed** (1616 assertions) |
| Docs | `docs/PILOT_CHECKLIST.md`, `docs/KNOWN_LIMITATIONS.md`; plat-guide #38 struck |
| #39 | **Deferred for now** — not required for current pilot; no ZATCA API / no compliance claim |
| Out of scope | Live SMTP/WhatsApp; ZATCA for now; new features; UI redesign |
| Phase 10 overall | **Not Accepted** until stakeholder pilot sign-off (§4); #39 is not blocking |

**Recommended next:** Stakeholder optional pilot sign-off in `docs/PILOT_CHECKLIST.md` §4. ZATCA/#39 only if/when regulated production needs it.

---

## 7. What may start now vs must wait

### 7.1 Phase 1 foundation tasks that **can safely begin**

These must **not** freeze unapproved pricing, inventory, or finance business formulas into irreversible schemas:

| Task | Allowed now |
|---|---|
| Auth scaffolding (e.g., Breeze/Fortify/equivalent) | Yes |
| User CRUD + role assignment tables | Yes |
| Permission / policy structure + configurable approval-authority settings UI/API | Yes |
| Company profile + single warehouse settings records | Yes |
| Audit log infrastructure | Yes |
| Private attachment storage foundation | Yes |
| Internal notification channel foundation | Yes |
| Email provider **config placeholders** (without claiming production templates complete) | Yes |
| Arabic RTL layout shell / design tokens | Yes |
| CI running PHPUnit on foundation tests | Yes |

### 7.2 Modules that **must wait** for specific decisions

| Module / phase | Blocking open questions (IDs) |
|---|---|
| Inventory quantity constraints (Phase 3+) | ~~`INV3`~~ resolved (no negative stock); `INV4` if lots required |
| Pricing & quotations (Phase 4) | ~~`PM1`/`PM4a`/`PM4b`/`TX8`~~ resolved; `CM1b` for live email send; `PM3`/`PM5`/`PM6` P1 as needed |
| Sales orders / PO linkage (Phase 5) | ~~`PF7`/`ORG3`~~ resolved; `CR2b`/`CR7` for gate precision as needed |
| Invoicing & collections (Phase 7) | ~~`PF3`/`CR7`/`TX5b`~~ resolved |
| Deal persistence | ~~`ORG3`~~ resolved (stored, 1:1 Customer PO) |
| Document numbering UX | `NUM1`–`NUM4` |
| Production email sends | `CM1b` (+ recipient/automation rules) |
| Lot tracking features | `INV4` |
| Report KPI formulas | `RP1` (after pricing/VAT/notes answers); report scopes are auth-confirmed |

Unresolved questions block **only** the dependent work above; they do not freeze all Phase 1 foundation work.

---

## 8. How a phase is marked complete

1. Scope for the phase agreed (and updated in docs if changed).  
2. Implementation matches confirmed workflows/permissions; no invented P0 rules.  
3. Required automated tests added and **results recorded** (pass summary).  
4. Authorization allow/deny evidence captured.  
5. Manual UI/UX review completed where UI shipped (RTL/responsive/real actions).  
6. Integration checks done if the phase includes external systems.  
7. Blocking defects closed or formally waived.  
8. Open questions affecting the phase either resolved in `OPEN_QUESTIONS.md` or explicitly listed as deferred with no silent assumptions in code.  
9. Reviewer marks status **Accepted** in the project tracker / docs status table.

---

## 9. Phase 1 progress (verified evidence only)

### Phase 1 — Prompt 1 (shared RTL shell) — **Implemented, Not Verified as full Phase 1**

| Item | Result |
|---|---|
| Scope delivered | Modular-monolith convention doc; Arabic RTL shared layout; honest foundation home; no auth/business modules; no fake metrics; no dead nav |
| Convention | `docs/ARCHITECTURE.md` |
| Automated tests | `php artisan test` — **7 passed** (includes `tests/Feature/Foundation/HomePageTest.php` + ExampleTest) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** after clean `npm install` (Vite 8.3.4) — 2026-10-16 |
| Manual UI — desktop | Inspected `http://127.0.0.1:8000/` — `lang=ar` `dir=rtl`; brand + home nav only; foundation empty state; no fake KPIs |
| Manual UI — mobile | Emulated 390×844 — layout stacks cleanly; RTL retained; same single working home link |
| Phase 1 overall | **Not Accepted** — Prompt 1 only; auth/users/roles/audit/attachments still outstanding |

Known limitation during build: first `npm install` left a broken `@rolldown` native binding; resolved by removing `node_modules` + `package-lock.json` and reinstalling. Optional Vite warning about `fontaine` for font fallbacks (non-blocking).

### Phase 1 — Prompt 2 (authentication & session lifecycle) — **Implemented, Not Verified as full Phase 1**

| Item | Result |
|---|---|
| Scope delivered | Session login/logout; Arabic RTL login, forgot/reset password, account profile + password change; no public registration; no roles CRUD / no `is_admin` bypass |
| Mail docs | `docs/AUTH_MAIL.md`; forgot-password UI warns when mailer is `log`/`array` |
| Automated tests | `php artisan test` — **22 passed** (94 assertions), including `tests/Feature/Auth/*` and `tests/Feature/Account/ProfileTest.php` — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4); optional `fontaine` warning only |
| Manual UI — desktop | Login (success + wrong-password alert); account profile after login; logout → login; forgot-password with mail-config warning; `lang=ar` `dir=rtl`; shared shell nav |
| Manual UI — mobile | Emulated 390×844 — login and account stack cleanly; RTL retained |
| Phase 1 overall | **Not Accepted** — Prompt 2 only; roles/permissions scaffolding, user admin, audit, attachments, company/warehouse settings still outstanding |

**Security behavior (Prompt 2):** CSRF on forms; `auth`/`guest` middleware; session regenerate on login and invalidate + CSRF token regenerate on logout; login RateLimiter (5) + route throttle `10,1`; password reset/change throttled `6,1`; passwords via `Password::defaults()` + User `hashed` cast; guests redirected to login; authenticated users redirected away from guest auth routes to `/account`.

**Mail limitation:** Local uses `MAIL_MAILER=log`; PHPUnit uses `array`. Production requires SMTP (or other real mailer) via `.env` only — see `docs/AUTH_MAIL.md`. Set `SESSION_SECURE_COOKIE=true` on HTTPS.

**Local demo user (manual only, not seeded in repo):** `auth.demo@cemi.local` / `Password1`.

### Phase 1 — Prompt 3 (roles, permissions, approval authority, user admin) — **Implemented, Not Verified as full Phase 1**

| Item | Result |
|---|---|
| Scope delivered | One role per user; Gates/policies/middleware authz from approved matrix; no `Gate::before` admin bypass; Arabic RTL admin UI for users / role permissions / approval authorities; password-reset trigger via existing broker; inactive-user login + session block; last-active-Administrator protection; idempotent role/permission/approval seed; no public registration; no domain business tables |
| Catalog | `config/authorization.php` + `RolesAndPermissionsSeeder` (no demo users/credentials in seeders) |
| Automated tests | `php artisan test` — **46 passed** (249 assertions), including `tests/Feature/Authorization/*` (matrix allow/deny + scopes, user admin, inactive users) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4); optional `fontaine` warning only |
| Manual UI — desktop | Admin users list/create; roles permission editor; approval authorities (SM defaults + Accounts credit-limit review); profile shows role; Sales Manager session has no admin nav links; guests redirected from `/admin/*`; `/register` 404; `lang=ar` `dir=rtl` |
| Manual UI — mobile | Emulated 390×844 on approvals — nav wraps; table scrolls horizontally; RTL retained |
| Phase 1 overall | **Not Accepted** — Prompt 3 only; audit logging, attachments, company/warehouse settings, email config foundation still outstanding |

**Authorization design (Prompt 3):** Permissions + optional record scopes on `permission_role`; Gates registered per permission slug and `approve.{workflow}`; `RecordScopeResolver` enforces Own/Assigned/Cust/Work/Referred/All/Admin for future domain policies; approval requires configured role **and** matching permission (Administrator has no sales-approval grants by default).

**Known limitations:** Laravel Boost install was blocked (proxy/DNS to Packagist `repo.packagist.org`); proceeded with Laravel conventions. Domain models (customers, SO, invoices, …) not implemented — only authz foundation + scopes. Local non-seeded users may exist for manual review only.

### Phase 1 — Prompt 4 (company + single warehouse settings) — **Implemented, Not Verified as full Phase 1**

| Item | Result |
|---|---|
| Scope delivered | Arabic RTL settings hub + company/warehouse forms; first-time empty state (no seeded fake data); create/update via singleton upsert; `settings.manage` server-side authz; no VAT/tax rates, branches, or inventory ops |
| Single-record enforcement | Unique `singleton_key` DB column + model `creating` guard (reject if any row exists) + `saveSingleton()` transaction with `lockForUpdate` |
| Fields (conservative) | Company: `name_ar` (required), `address_ar`, `contact_phone`, `contact_email`. Warehouse: `name_ar` (required), `address_ar`, `contact_phone`. Legal IDs / structured address / bilingual names deferred — see `OPEN_QUESTIONS` ORG7–ORG10 |
| Automated tests | `php artisan test` — **58 passed** (323 assertions), including `tests/Feature/Admin/CompanyWarehouseSettingsTest.php` (12 tests) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4); optional `fontaine` warning only |
| Manual UI — desktop | Settings hub empty then completed; company first-time form; successful save/update; Sales Manager `GET /admin/settings` → **403**; `lang=ar` `dir=rtl` |
| Manual UI — mobile | Emulated 390×844 — status cards stack to one column; RTL retained; edit actions reachable |
| Phase 1 overall | **Not Accepted** — Prompt 4 only; audit logging, attachments, email config foundation still outstanding |

**Audit events for the next audit step (not implemented yet):** `company.created`, `company.updated`, `warehouse.created`, `warehouse.updated` (actor, timestamp, changed attributes; no secrets).

### Phase 1 — Prompt 5 (append-only audit trail) — **Implemented, Not Verified as full Phase 1**

| Item | Result |
|---|---|
| Scope delivered | Central `AuditLogger` + `AttributeRedactor` + append-only `audit_logs` table/model; Arabic RTL read-only audit UI with filters (action, actor, date range) + pagination; reusable for future modules |
| Events covered | `company.created/updated`, `warehouse.created/updated`, `user.created/updated/deactivated/activated`, `user.password_reset_link_sent`, `role.permissions_updated`, `approval_authority.updated`, `auth.login`, `auth.logout`, `account.password_changed`, `auth.password_reset` |
| Append-only | Model `updating`/`deleting` throw `RuntimeException`; no update/delete routes; mutations + audit writes wrapped in `DB::transaction` where applicable |
| Redaction | Denylist (+ substring heuristics) for passwords/tokens/secrets/sessions/file contents; `password_changed` boolean allow-listed; password values never stored |
| Authorization | `audit.view` per approved matrix (Sales Manager, Accounts, Executive, Administrator) — **not** Admin-only; route middleware `permission:audit.view` + controller gate |
| Automated tests | `php artisan test` — **67 passed** (394 assertions), including `tests/Feature/Admin/AuditTrailTest.php` (9 tests) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4); optional `fontaine` warning only |
| Manual UI — desktop | `/admin/audit` Arabic RTL; populated login/logout rows; filter `action=auth.login` narrows rows; empty state via future-dated filter shows «لا توجد أحداث بعد» / helper copy |
| Manual UI — mobile | Emulated 390×844 — filters stack; table scrolls horizontally; RTL retained; hamburger nav present |
| Manual UI — denied | Guest `GET /admin/audit` → **302** login; Sales Rep / Warehouse → **403** (feature test); admin nav shows «سجل التدقيق» |
| Phase 1 overall | **Not Accepted** — Prompt 5 only; attachments foundation, email config foundation, and remaining Phase 1 items still outstanding |

**Access-control note (stakeholder):** `audit.view` is mapped in `docs/ROLES_AND_PERMISSIONS.md` / `config/authorization.php` to SM + Accounts + Executive + Administrator. Implementation follows that matrix (not Administrator-only).

### Phase 1 — Prompt 6 (private attachment foundation) — **Implemented, Not Verified as full Phase 1**

| Item | Result |
|---|---|
| Scope delivered | Reusable `Attachment` + `AttachmentService` + `HasAttachments`; company/warehouse settings UI for upload/list/download/delete; morph aliases ready for future domain records |
| Storage | Private disk `attachments` (`storage/app/private/attachments_disk`); `serve` false; UUID filenames; no public `/storage` URLs |
| Limits (assumption documented in `docs/ATTACHMENTS.md`) | Extensions: pdf, jpg, jpeg, png, webp, gif; max **5120 KB (5 MiB)**; MIME must match extension map |
| Authorization | Upload/list/download/delete require `settings.manage`; cross-record UUID access → 404; guests → login redirect |
| Audit | `attachment.uploaded` / `attachment.removed` without file contents or storage paths |
| Automated tests | `php artisan test` — **76 passed** (458 assertions), including `tests/Feature/Admin/AttachmentFoundationTest.php` (9 tests) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4) |
| Manual UI — desktop | Company settings shows مرفقات empty then listed PDF with تنزيل/حذف; RTL; allowlist copy visible |
| Manual UI — mobile | Emulated 390×844 — upload controls + table usable; RTL retained |
| Manual UI — denied | Guest download/settings → **302**; Sales Manager/Rep forbidden covered by feature tests |
| Packages | No new Composer packages; Laravel Boost install still blocked (network to Packagist/GitHub) |
| Phase 1 overall | **Not Accepted** — Prompt 6 only; internal notifications + email config foundation still outstanding |

### Phase 1 — Prompt 7 (internal notification foundation) — **Implemented, Not Verified as full Phase 1**

| Item | Result |
|---|---|
| Scope delivered | `InternalNotifier` + Laravel database notifications; Arabic RTL notification center (list, unread count, mark one / mark all); nav badge + topbar bell |
| Recipients | `notifyUser`, `notifyRole` (active users only), `notifyMany`; reusable for future modules |
| Channels | **Database only** — no email, WhatsApp, push, websocket, or broadcast |
| Payload safety | Requires `title_ar`; HTML stripped; secrets redacted via `AttributeRedactor`; `action_url` relative paths only |
| Authorization | Authenticated users see/update **own** notifications only; foreign UUID → **404**; guests → login |
| Automated tests | `php artisan test` — **86 passed** (506 assertions), including `tests/Feature/Account/NotificationFoundationTest.php` (10 tests) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4) |
| Manual UI — desktop | `/account/notifications` unread card + badge; mark-as-read clears unread; empty state «لا توجد إشعارات» with no fake samples |
| Manual UI — mobile | Emulated 390×844 — empty/center layout RTL; bell in topbar |
| Manual UI — denied | Guest `GET /account/notifications` → **302** |
| Packages | No new Composer packages |
| Phase 1 overall | **Not Accepted** — Prompt 7 only; email config foundation still outstanding |

### Phase 1 — Prompt 8 (email configuration foundation) — **Implemented, Not Verified as full Phase 1**

| Item | Result |
|---|---|
| Scope delivered | Admin mail status page (`/admin/settings/mail`); masked config display; test-send to own account only; settings hub card; docs in `AUTH_MAIL.md` |
| Credentials | Environment-only; never in DB/HTML/audit; SMTP password shown as `••••••••` when set |
| Local vs delivery | `log`/`array` labelled local-only (not inbox delivery); test result flash distinguishes local acceptance |
| Test send | CSRF + `throttle:3,1` + `settings.manage`; recipient = authenticated admin email only; `Mail::fake()` in tests |
| Real external email | **None sent** during automated or manual verification (log/array / Mail::fake) |
| Automated tests | `php artisan test` — **94 passed** (558 assertions), including `MailSettingsFoundationTest` (8 tests) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4) |
| Manual UI | Desktop RTL status + local-only banner; settings hub link; mobile width checked |
| Password reset | Still covered by existing + foundation test with `Notification::fake()` |
| Packages | No new Composer packages |
| Phase 1 overall | **Ready for Review** as foundation scope (Prompts 1–8 delivered); stakeholder acceptance still required for full Phase 1 **Accepted** |

---

## 9b. Phase 2 progress (verified evidence only)

### Phase 2 — Prompt 1 (customer records and contacts) — **Implemented, Not Verified as full Phase 2**

| Item | Result |
|---|---|
| Scope delivered | Arabic RTL list / search / show / create / edit for customers + nested contacts; no customer number; no locations/maps/credit/visits/products |
| Data model | `customers`: `name_ar`, `assigned_sales_rep_id` (nullable FK → users). `customer_contacts`: `role` (`account` \| `accounting`), `name_ar`, `phone`, `email` |
| Authorization | `CustomerPolicy` + `RecordScopeResolver`: Sales Rep **Assigned** only; Sales Manager **All** + manage; Executive/Admin view without manage; Procurement deny; Warehouse/Accounts **Work** (empty until operational links exist) |
| Audit | `customer.created` / `customer.updated` via `AuditLogger` (name, assignment, contact counts/roles — no secrets) |
| Automated tests | `php artisan test` — **103 passed** (620 assertions), including `tests/Feature/Customers/CustomerRecordsTest.php` (9 tests: CRUD, validation, search, audit, Assigned deny, role matrix) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4) |
| Manual UI — desktop | `/customers` empty + populated list; create form (account/accounting contacts); show detail; edit form; RTL `lang=ar` `dir=rtl`; Sales Manager «عميل جديد»; Admin list has no create button |
| Manual UI — mobile | Emulated 390×844 — customers list/search reachable; RTL retained |
| Manual UI — unauthorized | Procurement `GET /customers` → **403**; Admin `GET /customers/create` → **403**; Sales Rep other-rep customer covered by automated deny tests |
| Packages | No new Composer/npm packages |
| Phase 2 overall | **Not Accepted** — Prompt 1 only; visits, locations, photos, credit-limit workflows, and remaining Phase 2 scope still outstanding |

**Deferred by design (open questions / later prompts):** customer numbering (`NUM*`); Work-scope operational linking for Warehouse/Accounts. (`ORG6` resolved: org-only.)

### Phase 2 — Prompt 2 (credit profile + limit approval workflow) — **Implemented, Not Verified as full Phase 2**

| Item | Result |
|---|---|
| Scope delivered | Customer approved credit limit + payment term; request workflow Rep → Accounts review → SM final; Arabic RTL profile + request queue/detail |
| Requested vs approved | Requested values on `credit_limit_requests` only; customer `approved_*` updated **only** on SM approve; rejects leave approved values unchanged |
| Statuses | `pending_review` → `pending_final` → `approved`; review/final `rejected` terminal. Return-for-revision **not** invented (`CR-CREDIT-RETURN`) |
| Authorization | Rep request on assigned customers only; Accounts `approve.credit_limit_review`; SM `approve.credit_limit_final`; Warehouse denied credit UI |
| Audit | `credit_limit.requested`, `review_forwarded`, `review_rejected`, `approved`, `final_rejected` |
| Notifications | Internal DB only — Accounts on request; SM on forward; requester on outcome |
| Exposure / order gate | **Deferred** — documented in `docs/CREDIT.md`; UI shows «غير محسوب بعد»; no fake balances |
| Automated tests | `php artisan test` — **110 passed** (673 assertions), including `tests/Feature/Credit/CreditLimitWorkflowTest.php` (7 tests) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4) |
| Manual UI | Credit profile RTL (approved empty + deferred exposure note); finance nav «الملف الائتماني للعملاء»; SM view without request form; empty request history |
| Packages | No new packages |
| Phase 2 overall | **Not Accepted** — Prompt 2 only; visits, locations/photos, and remaining Phase 2 scope still outstanding |

### Phase 2 — Prompt 3 (customer location, map pin, photos) — **Implemented, Not Verified as full Phase 2**

| Item | Result |
|---|---|
| Scope delivered | `address_ar` + `latitude`/`longitude` on customers; RTL location editor with Leaflet pin picker; private customer photos via existing attachment foundation |
| Map | npm `leaflet` + configurable OSM tiles (`config/maps.php` / `docs/MAPS.md`); no geocoding; manual lat/lng fallback; `MAPS_ENABLED=false` / tile errors degrade safely |
| Photos | Morph alias `customer`; image-only allowlist; private disk; download/delete scoped to customer view/manage + record scope |
| Authorization | Sales Rep Assigned only (direct URL/UUID denied for other reps); SM All; Executive view-only location |
| Audit | `customer.location_updated`; reuse `attachment.uploaded` / `attachment.removed` (no file contents) |
| Automated tests | `php artisan test` — **117 passed** (713 assertions), including `CustomerLocationAndPhotosTest` (7 tests) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4; Leaflet assets bundled) |
| Manual UI | `/customers/{id}/location` — Leaflet map + OSM attribution + address/coord form; photos link; RTL |
| Package added | **`leaflet`** (essential interactive map; free OSM tiles; no paid provider/API key) |
| Phase 2 overall | **Not Accepted** — Prompt 3 only; visits and remaining Phase 2 scope still outstanding |

### Phase 2 — Prompt 4 (visit scheduling and visit reports) — **Implemented & Verified; fixed assignee + ORG6 follow-up**

| Item | Result |
|---|---|
| Scope delivered | Arabic RTL visit list/search/status filter; schedule; show; reschedule; complete (outcome/notes/customer needs); cancel scheduled; customer show «سجل الزيارات» + schedule link |
| Data model | `customer_visits`: `customer_id`, `assigned_user_id`, `created_by_user_id`, `scheduled_at`, `status`, `notes`, `outcome`, `customer_needs`, `completed_at`, `cancelled_at` |
| States (approved) | `scheduled` → `completed` \| `cancelled` (terminal) |
| Assignment (approved) | **Always** customer’s `assigned_sales_rep_id` (server-set; client `assigned_user_id` stripped/ignored). **No** SM override. Unassigned customer → Arabic validation block |
| Cancellation (approved) | Rep: **Own** assignee + scheduled only; SM: any scheduled in **All** scope; terminal states denied |
| Authorization (matrix) | Rep **Cust** for view/update/complete; SM **All**; Warehouse/Accounts **Work** (empty); Procurement deny |
| Audit | `visit.scheduled`, `visit.rescheduled`, `visit.completed`, `visit.cancelled` |
| Notifications (approved) | In-app to assignee when **another** user creates / changes scheduled time / cancels; no self-action or unchanged-time notify; no reminders; no visit GPS |
| ORG6 | Resolved: org/establishment-only; UI label **اسم المنشأة** for `name_ar`; no schema / type discriminator |
| Automated tests | `php artisan test --filter='VisitWorkflowTest\|CustomerRecordsTest'` — **22 passed** (165 assertions); full `php artisan test` — **130 passed** (816 assertions) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4) |
| Manual UI | SM schedule form: read-only «مندوب العميل: … ولا يمكن تغييره من هنا»; no assignee `<select>`; customer create shows **اسم المنشأة** + org-only copy; RTL desktop + 390×844 |
| Packages | No new Composer/npm packages |
| Customer schema | **Unchanged** |
| Phase 2 overall | **Accepted** (stakeholder) — Prompts 1–4 delivered and verified; Phase 3 started 2026-10-16 |

---

## 9c. Phase 3 progress (verified evidence only)

### Phase 3 — Prompt 1 (product catalog, units, technical specs) — **Implemented, Not Verified as full Phase 3**

| Item | Result |
|---|---|
| Scope delivered | Arabic RTL product list/search/show/create/edit; admin unit catalog; flexible technical specs with documented **الكثافة**; purchase-cost cost-only path for Procurement |
| Data model | `units_of_measure` (`name_ar`, `symbol_ar`, `is_active`); `products` (`name_ar`, `unit_id`, `purchase_cost` DECIMAL(15,2)); `product_specifications` (`label_ar`, `value`, `unit_ar`, `sort_order`) |
| Money | Documented in `docs/MONEY.md` — same DECIMAL(15,2) convention as credit limits; no markup/VAT/rounding |
| Authorization | `ProductPolicy` / `UnitOfMeasurePolicy`: `products.view` all catalog viewers; `products.manage` Administrator master; `products.edit_purchase_cost` Procurement cost-only (server-side; UI hidden insufficient) |
| Cost visibility | Purchase cost shown only to `products.manage` or `products.edit_purchase_cost` (not to Sales/Warehouse/etc.) |
| Audit | `product.created`, `product.updated`, `product.purchase_cost_updated`, `unit_of_measure.created`, `unit_of_measure.updated` |
| Out of scope (deferred) | Stock balances/movements, SKU/numbering, unit conversions, categories, product photos, selling prices, fake/demo catalog seed |
| Automated tests | `php artisan test` — **138 passed** (898 assertions), including `tests/Feature/Products/ProductCatalogTest.php` (8 tests) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4) |
| Manual UI — desktop | Admin: empty products → units create «طن» → product create with density/cost → list/show/edit; RTL; sidebar «المنتجات» enabled under المخزون |
| Manual UI — mobile | Emulated 390×844 — product show (name, unit, cost 250.50, specs section) readable RTL |
| Manual UI — roles | Sales Manager list: empty/view without create/cost; Admin create + units; Procurement cost-only covered by automated deny/update tests |
| Packages | No new Composer/npm packages |
| Phase 3 overall | **Not Accepted** — Prompt 1 only; inventory balances/movements/adjustments remain |

**Open product-policy notes surfaced:** see `PROD-COST-VIEW`, `PROD-SPEC-CATALOG` in `docs/OPEN_QUESTIONS.md` (do not invent answers).

**UI/UX polish (designer):** Applied 2026-10-16 — catalog/units surfaces aligned with inventory stock/adjustment polish (ds-page-bar forms, metric cards, specs readout, inactive unit tone). Cost visibility / Procurement cost-only / الكثافة contracts preserved. Phase 3 stakeholder Accepted separately; this note is visual alignment only.

### Phase 3 — Prompt 2 (single-warehouse balances + inventory adjustments) — **Implemented, Not Verified as full Phase 3**

| Item | Result |
|---|---|
| Scope delivered | Stock balances list (search); product show stock section; adjustment create/queue/show + SM approve/reject; Arabic RTL; INV3 reject on insufficient decrease |
| Data model | `product_stock_balances` (unique warehouse+product; `on_hand`/`reserved` DECIMAL(15,3); available computed); `inventory_adjustments` (signed `quantity_delta`, pending→approved\|rejected); append-only `inventory_ledger_entries` |
| INV3 | Decreasing approve fails when resulting `on_hand < reserved`; no silent clamp; Arabic session error |
| Service | `InventoryService`: ensure+`lockForUpdate` balance; atomic balance+ledger; idempotent approve/reject; after-commit notify; assert hooks for future reserve/deduct (not wired to SO/delivery) |
| Authorization | `stock.view` for balances; `inventory_adjustment.record` Warehouse; approve via `canApproveWorkflow('inventory_adjustment')` (SM); no Warehouse self-approve; Admin no bypass; no prices on Warehouse stock UI |
| Audit / notify | `inventory_adjustment.recorded` / `.approved` / `.rejected`; SM notified on record; Warehouse requester notified on decide |
| Docs | `docs/INVENTORY.md`; INV3 already in Resolved (`OPEN_QUESTIONS` / `WORKFLOWS`) |
| Out of scope (deferred) | Lot/batch (`INV4`); reserve/deduct/release wired to SO/delivery (plat-guide #22); soft allocation; unit conversions; fake stock seed |
| Automated tests | `php artisan test` — **151 passed** (982 assertions), including `tests/Feature/Inventory/InventoryAdjustmentWorkflowTest.php` (13 tests) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4) |
| Packages | No new Composer/npm packages |
| Phase 3 overall | **Not Accepted** — Prompt 2 covers balances+adjustments only; movements / reserve-deduct-release (item 22) and full phase criteria remain |

**UI/UX polish (designer):** Applied 2026-10-16 — stock list hierarchy (available emphasis), adjustment queue deltas/status badges, create sectioning, show decide panel + balance metrics, product show stock metrics. Routes/authz/INV3 message meaning preserved. Phase 3 remains **Not Accepted**.

### Phase 3 — Prompt 3 (reserve / deduct / release service + ledger) — **Implemented, Not Verified as full Phase 3 Accepted**

| Item | Result |
|---|---|
| Scope delivered | `InventoryService::reserve` / `deduct` / `release` + `remainingReservedFor`; atomic balance+ledger; INV3 reject; INV5 exactly-once release via idempotency |
| Idempotency | `inventory_ledger_entries.idempotency_key` nullable UNIQUE; same key returns existing row; unique-violation race handled |
| Per-reference remaining | Deduct optional `$reservationOwner`; release default qty = remaining for morph reference; ledger properties store owner on deduct |
| UI | **None** — no movements screens / nav enabling; morph stubs in tests only (`FakeInventoryDocument`) |
| Docs | `docs/INVENTORY.md` updated (API + idempotency); plat-guide #22 struck |
| Automated tests | `php artisan test` — **159 passed** (1028 assertions), including `tests/Feature/Inventory/InventoryReserveDeductReleaseTest.php` (8) + adjustment regression (13) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4); no UI changes in this prompt |
| Out of scope | SO / Delivery models/routes/UI; lot tracking; claiming Phase 3 stakeholder Accepted |
| Phase 3 overall | **Accepted** (stakeholder, 2026-10-16) — catalog + balances/adjustments + reserve/deduct/release service spine. SO/delivery UI wiring deferred to Phase 5. Open notes (`PROD-COST-VIEW`, `PROD-SPEC-CATALOG`, `INV4` if lots) remain documented, not invented. |

**Recommended next (superseded):** Pricing P0 resolved; Phase 4 Prompt 1 delivered — see below.

### Phase 4 — Prompt 1 (sales requests + procurement supplier cost) — **Implemented, Not Verified as full Phase 4**

| Item | Result |
|---|---|
| Scope delivered | Arabic RTL sales-request CRUD/submit/cancel; auto line availability from stock (`SR-AUTO-STOCK`); procurement tasks (1 per needs_procurement line); supplier cost SAR; ready_for_pricing without quotations |
| Statuses | `draft` → `awaiting_procurement` \| `ready_for_pricing` → `cancelled` (no `quotation_linked` yet) |
| Authz | Cust/All/Referred/Work/Admin scopes; Warehouse denied; Accounts Work empty; SM cancel task; no Warehouse prices |
| Audit / notify | `sales_request.*`, `procurement_task.*`; Procurement notified on submit needing cost; Rep + SM notified when cost recorded |
| Docs | `docs/SALES_REQUESTS.md`; plat-guide #24 struck |
| Out of scope | Quotations, margin UI, VAT quote math, PDF, email (#25) |
| Automated tests | `php artisan test` — **169 passed** (1069 assertions), including `tests/Feature/SalesRequests/SalesRequestProcurementTest.php` (10) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4) |
| Packages | No new Composer/npm packages |
| Phase 4 overall | **Not Accepted** — Prompt 1 only; #25 pricing/quotations remain |

**UI/UX polish (designer):** Applied 2026-10-16 — sales-request list/create/edit/show + procurement tasks index/show aligned with inventory/catalog polish (`ds-page-bar`, metrics, badges, decide panels, elevated tables, ~390px stack). Statuses, scopes, explicit availability, SAR 2dp cost, Warehouse cost denial preserved. Phase 4 remains **Not Accepted**.

### Phase 4 — Prompt 2 (SM margin + VAT-inclusive quotations + send) — **Implemented, Ready for Review / Not Accepted**

| Item | Result |
|---|---|
| Scope delivered | Quotation from `ready_for_pricing`; draft margins; submit/approve/reject; print Blade; email send; supersede/cancel/expiry guards |
| Math | PM1 cost+margin; PM4b 2 dp half-up; TX8 15%; TX2 VAT-inclusive; no discount surface |
| Cost snapshot | Procurement line cost or product `purchase_cost`; missing cost blocks create |
| Sales request | Status `quotation_linked` on create; cancel blocked while linked |
| Authz | Warehouse denied; Cust/All scopes; `price_margin` approve workflow; no self-approve |
| Email | `Mail::fake` covered; send log; failure ≠ sent; recipient = account then accounting; **CM1b** live SMTP not claimed |
| Docs | `docs/QUOTATIONS.md`; `SALES_REQUESTS.md` updated; plat-guide #25 struck |
| Automated tests | `php artisan test` — **179 passed** (1116 assertions), including `QuotationPricingWorkflowTest` (10) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4) |
| Packages | No new Composer packages |
| Phase 4 overall | **Accepted** (stakeholder, 2026-10-16) — #24+#25 + Designer polish; `CM1b` live SMTP still open |

**UI/UX polish (designer):** Applied 2026-10-16 — quotation list/show/edit/print + sales-request quotation link presentation aligned with inventory/sales-request polish. PM1 cost/margin/selling clarity, no discount UI, VAT-inclusive 15% copy, send log visibility, print from Company settings only. Authz/send guards preserved.

**Recommended next:** ~~Phase 5 Prompt 1~~ delivered — see below.

### Phase 5 — Prompt 1 (Customer PO + Deal 1:1 + SO draft/submit/approve reserve) — **Implemented, Ready for Review / Not Accepted**

| Item | Result |
|---|---|
| Scope delivered | Customer PO from approved/sent quotation; Deal 1:1 on create; SO requires PO; draft/submit; SM approve/reject; SM cancel-approved + release once |
| PF7 / ORG3 | No SO without `customer_purchase_order_id`; unique Deal per CPO; SO inherits `deal_id` |
| Inventory | Approve → `InventoryService.reserve` (idempotent keys); cancel approved → `release` once; insufficient stock blocks approve |
| Credit | `CreditExposure` live (Phase 7); gate wired on approve (limit `0` blocks) |
| Authz | Rep Cust PO + Own SO; SM All + approve/cancel; Warehouse All SO, no `prices.view`; audit + InternalNotifier |
| Docs | `docs/SALES_ORDERS.md`; plat-guide #27 struck; ROUTE_INVENTORY updated; CREDIT.md gate note |
| Automated tests | `php artisan test` — **205 passed** (1293 assertions), including `CustomerPoDealSalesOrderTest` (14) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4) |
| Out of scope | Delivery/POD/deduct (Phase 6); invoice/collections/deal close UI (Phase 7) |
| Phase 5 overall | **Accepted** (stakeholder, 2026-10-16) — #27+#28 covered + Designer polish; live credit exposure Phase 7 |

**UI/UX polish (designer):** Applied 2026-10-16 — Customer PO + Sales Order screens aligned with quotation polish (`ds-page-bar`, Deal read-only cards, allocation/unallocated guidance, decide panels with reserve/release confirms, honest credit stub copy `0.00` until invoices, Warehouse no-price layout). PF7/ORG3 preserved; no delivery/POD or deal-close controls.

**#28 review:** Struck as covered — SM cancel-approved + release and credit gate stub are in Prompt 1; no separate developer prompt required. Real unpaid-invoice exposure awaits Phase 7.

**Recommended next:** ~~Phase 6 Prompt 1~~ delivered — see below.

### Phase 6 — Prompt 1 (preparation + multi-delivery + POD + confirm deduct) — **Implemented, Ready for Review / Not Accepted**

| Item | Result |
|---|---|
| Scope delivered | Preparation queue for approved SOs; multi-delivery drafts; POD private attachments; confirm → deduct; cancel before confirm |
| Caps | Delivery qty ≤ undelivered (incl. open drafts); confirm ≤ `remainingReservedFor(SO)` |
| Inventory | `InventoryService.deduct` per line, `reservationOwner` = SO; idempotency `deduct:delivery:{id}:line:{lineId}`; SO cancel releases remainder only |
| Fulfillment | `fulfillment_status` pending / partially_delivered / fully_delivered |
| Authz | Warehouse + SM manage/cancel; Exec/Admin view; Rep & Accounts denied delivery UI; Warehouse no prices |
| Docs | `docs/DELIVERIES.md`; plat-guide #30 struck; ROUTE_INVENTORY; ATTACHMENTS morph `delivery` |
| Automated tests | `php artisan test` — **206 passed** (1260 assertions), including `DeliveryWorkflowTest` (11) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4) |
| Out of scope | Invoices/collections/deal close (Phase 7); driver GPS; lot tracking |
| Phase 6 overall | **Accepted** (stakeholder, 2026-10-16) — #30 + Designer polish |

**UI/UX polish (designer):** Applied 2026-10-16 — preparation queue + deliveries list/create/edit/show aligned with SO/inventory polish (`ds-page-bar`, prep/fulfillment badges, POD panel, blocked-without-POD messaging, confirm→deduct / cancel-no-deduct confirms, post-confirm read-only banner, Warehouse بلا أسعار). Multi-delivery caps copy preserved; no GPS/invoice CTAs.

**Recommended next:** Phase 7 invoicing/collections — Prompt 1 delivered (see below).

---

### Phase 7 Prompt 1 verification (2026-10-16)

| Item | Result |
|---|---|
| Scope delivered | Invoice per confirmed delivery (PF3); collections draft→confirm; credit/debit notes Accounts create / SM approve; live `CreditExposure`; Accounts deal close (TX5b); overdue `Mail::fake` + Accounts resend + artisan command |
| CR7 unpaid | issued − confirmed allocations − approved credits + approved debits; draft collections ignored |
| Credit gate | `CreditExposure.forCustomer()` sums unpaid issued invoices; SO approve blocked when exposure ≥ limit |
| Authz | Warehouse denied invoices; Rep Cust-scoped invoice view; Accounts issue/collect/notes/close/resend; SM note/reversal approve |
| Docs | `docs/INVOICES.md`; CREDIT/SALES_ORDERS/MONEY/ROUTE_INVENTORY updated; plat-guide #32–#33 struck |
| Automated tests | `php artisan test` — **212 passed** (1300 assertions), including `InvoiceFinanceWorkflowTest` (6) — 2026-10-16 |
| Frontend production build | `npm run build` — **passed** (Vite 8.3.4) |
| Out of scope | ZATCA API; write-offs (TX5a); INV8 stock return on credit note; Phase 8 live SMTP productization |
| Phase 7 overall | **Accepted** (stakeholder, 2026-10-16) — #32–#33 + Designer polish; plat-guide #34 struck; `CM1b` live SMTP still open for Phase 8 |

**UI/UX polish (designer):** Applied 2026-10-16 — invoices/collections/invoice-notes/deals aligned with deliveries/quotations polish. CR7 unpaid breakdown with draft-collections honesty (struck-through / banner), draft vs confirmed collection badges, note credit/debit + SM decide panels, deal close/blocked TX5b messaging, overdue CM1b copy, no discount/Warehouse finance CTAs. No contract bugs reported (Designer → Developer).

**CM1b:** Overdue reminders tested with `Mail::fake` only; do not claim production SMTP — Phase 8 productizes email.

**Recommended next:** Phase 10 Prompt 1 (#38) delivered — see Phase 10 verification. Phase 8 remains deferred.

---

## 10. Document control

| Version | Change |
|---|---|
| Prompt 3 | Initial PHASE_ACCEPTANCE.md; ties Phase 0 docs criteria to Phases 1–10 gates and Phase 1 safe-start list |
| Auth follow-up | Phase 5 criteria: Sales Rep SO own-record view; only Sales Manager cancels approved SO |
| Auth final | Product Admin + Procurement cost-only; one role/user; no driver accounts; visits/PO scopes; SO Referred/Exec read/Admin; quotes/invoices SM+Accounts All, WH no prices/invoices; Exec read-only; collection reversal + notes SM approve; reports Own vs All; 0.12 Met |
| Phase 1 Prompt 1 | RTL shell + architecture convention; tests/build evidence recorded; Phase 1 not marked complete |
| Phase 1 Prompt 2 | Auth/session/password recovery; tests/build/UI evidence; Phase 1 not marked complete |
| Phase 1 Prompt 3 | Roles/permissions/approval authority/user admin; tests/build/UI evidence; Phase 1 not marked complete |
| Phase 1 Prompt 4 | Company + single warehouse settings; tests/build/UI evidence; Phase 1 not marked complete |
| Phase 1 Prompt 5 | Append-only audit trail + Arabic RTL audit UI; tests/build/UI evidence; Phase 1 not marked complete |
| Phase 1 Prompt 6 | Private attachment foundation for company/warehouse; tests/build/UI evidence; Phase 1 not marked complete |
| Phase 1 Prompt 7 | Internal database notifications + Arabic RTL center; tests/build/UI evidence; Phase 1 not marked complete |
| Phase 1 Prompt 8 | Email config status + own-account test send; tests/build/UI; no real external email; Phase 1 foundation scope complete pending stakeholder Accepted |
| Phase 2 Prompt 1 | Customer records + account/accounting contacts; Assigned-scope for Sales Rep; audit create/update; tests/build/UI evidence; Phase 2 overall still incomplete (visits/locations/credit later) |
| Phase 2 Prompt 2 | Credit profile + Rep→Accounts→SM workflow; approved-only activation; audit/notifications; exposure/gate deferred; tests/build/UI; Phase 2 still incomplete (visits/locations) |
| Phase 2 Prompt 3 | Customer location + Leaflet pin + private photos; Assigned-scope; maps config docs; tests/build/UI; Phase 2 still incomplete (visits) |
| Phase 2 Prompt 4 | Visit schedule/complete/cancel; Cust/All scopes; audit + assignee notify; tests/build/UI evidence |
| Visit policies approved | VISIT-STATUS / ASSIGNEE / CANCEL / NOTIFY + GPS2 excluded from v1 recorded in OPEN_QUESTIONS / WORKFLOWS / ROLES |
| Visit policy alignment | Gaps 1–6 closed; fixed-assignee (no override) + ORG6 org-only verified; Phase 2 still **Not Accepted** pending stakeholder Accepted |
| Phase 3 Prompt 1 | Product catalog + UoM + specs; cost authz; tests/build/UI; Phase 3 Not Accepted |
| Phase 3 Prompt 2 | Single-warehouse balances + adjustment workflow + ledger + INV3; tests/build; Phase 3 Not Accepted (item 22 remains) |
| Phase 3 Prompt 3 | Reserve/deduct/release service + idempotent ledger; tests/build; plat-guide #22 done; Phase 3 Not Accepted pending stakeholder |
| Phase 3 Accepted | Stakeholder Accepted 2026-10-16 — plat-guide #23 struck; Phase 4 gated on PM1/PM4a/PM4b/TX8 |
| Phase 4 Prompt 1 | Sales requests + procurement supplier-cost path; tests/build; #24 struck; Phase 4 Not Accepted (#25 remains) |
| Phase 4 Prompt 2 | Quotation margin + VAT-inclusive + Mail::fake send; #25 struck; Phase 4 Not Accepted pending Designer + stakeholder; CM1b noted |
| Phase 4 Prompt 2 UI | Designer polish on quotations list/show/edit/print + SR quote CTAs; PM1/VAT/no-discount preserved; Phase 4 Not Accepted pending stakeholder |
| Phase 4 Prompt 1 UI | Designer polish on sales-requests + procurement tasks; routes/authz/contracts preserved; #25 still open |
| Phase 4 Accepted | Stakeholder Accepted 2026-10-16 — plat-guide #26 struck; CM1b limitation noted; Phase 5 next when opens allow |
| Phase 5 Prompt 1 | Customer PO + Deal 1:1 + SO draft/submit/SM approve(reserve)/reject/cancel-approved(release); CreditExposure stub; #27 struck; Phase 5 Not Accepted |
| Phase 5 Prompt 1 UI | Designer polish on customer-pos + sales-orders; PF7/ORG3/credit stub/WH no-price preserved; Phase 5 Not Accepted pending #28 review + stakeholder |
| Phase 5 #28 closed | Covered by #27 (cancel-approved + CreditExposure stub); plat-guide #28 struck; awaiting stakeholder Accepted (#29) |
| Phase 5 Accepted | Stakeholder Accepted 2026-10-16 — plat-guide #29 struck; Phase 6 next |
| Phase 6 Prompt 1 | Preparation queue + multi-delivery + POD attachments + confirm deduct; #30 struck; Phase 6 Not Accepted |
| Phase 6 Prompt 1 UI | Designer polish on preparation + deliveries; POD/confirm/WH no-price preserved; Phase 6 Not Accepted pending stakeholder |
| Phase 6 Accepted | Stakeholder Accepted 2026-10-16 — plat-guide #31 struck; PF3/CR7/TX5b resolved for Phase 7 |
| Phase 7 Prompt 1 | Invoice/collections/notes/deal close + live CreditExposure + overdue Mail::fake; #32–#33 struck; Phase 7 Not Accepted |
| Phase 7 Prompt 1 UI | Designer polish on invoices/collections/notes/deals; CR7 draft honesty + TX5b gate preserved; Phase 7 Not Accepted pending stakeholder |
| Phase 7 Accepted | Stakeholder Accepted 2026-10-16 — plat-guide #34 struck |
| Phase 8 Deferred | Stakeholder 2026-10-16 — live email + WhatsApp postponed; continue to Phase 9 after RP gates |
| Phase 9 gates | RP1/RP2/RP3–RP4/PF1 resolved 2026-10-16 — Prompt 1 unblocked |
| Phase 9 Prompt 1 | Home KPIs + AR/statements + PF1 + Asia/Riyadh; #36 struck; Phase 9 Not Accepted pending Designer + stakeholder |
| Phase 9 Prompt 1 UI | Designer polish on dashboard/AR/statements/PF1; RP1/CR7/PF1/no-export preserved; Phase 9 Not Accepted pending stakeholder (#37) |
| Phase 9 Accepted | Stakeholder Accepted 2026-10-17 — plat-guide #37 struck; Phase 10 next |
| Phase 10 Prompt 1 | E2E + authz + concurrency + PILOT/KNOWN_LIMITATIONS; #38 struck; #39 ZATCA deferred for now; Phase 10 Not Accepted pending optional stakeholder pilot stamp |
