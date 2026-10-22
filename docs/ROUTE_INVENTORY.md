# Route and Page Inventory

**Source:** `php artisan route:list` + `routes/web.php` (no `routes/api.php`)  
**Stack:** Laravel Blade + shared `layouts/app` · Arabic RTL  
**Last UI pass:** 2026-10-16 — reference-driven design system from `design/`  
**Design system:** `docs/DESIGN_SYSTEM.md` (dark auth + light RTL sidebar app)  
**Status:** Existing pages restyled to dual-surface identity; admin users/roles/approvals included when routed

---

## 1. Totals (current)

| Metric | Count |
|---|---|
| Registered routes (artisan) | **84** (2026-10-16 recount) |
| Orphan views (no route) | **1** (`welcome.blade.php` — untouched) |
| Pages **functionally verified** (automated) | Foundation through Phase 3 Prompt 2 — `php artisan test` **151 passed** |
| Incomplete / blocked product UI | Inventory **movements** UI / SO reserve-deduct-release wiring — **not routed** (plat-guide #22); stock balances + adjustments **routed** |

---

## 2. Full route inventory

| Method | URI | Name | Handler | Middleware | View / response | Module | Impl. status | Accessible now | UI completeness | Design work | Verification |
|---|---|---|---|---|---|---|---|---|---|---|---|
| GET | `/` | `login` | Login (guest) / redirect → `home` (auth) | `web` | `auth/login` | Auth | Working | Guests | **Polished** · root entry | Done | Tests + functional |
| GET | `/login` | — | Redirect → `/` | `web`, `guest` | Redirect | Auth | Working | Guests | N/A | Legacy alias | Tests |
| POST | `/login` | `login.store` | `…@store` | `web`, `guest`, `throttle:10,1` | Redirect | Auth | Working | Guests | N/A | None | Functional tests |
| GET | `/home` | `home` | `Foundation\HomeController` | `web`, `auth` | `foundation/dashboard` | Foundation | Working | Auth | Role KPIs (Phase 9) polished | — | Tests + functional |
| POST | `/logout` | `logout` | `…@destroy` | `web`, `auth` | Redirect → login (`/`) | Auth | Working | Auth | N/A | None | Functional tests |
| GET | `/forgot-password` | `password.request` | `PasswordResetLinkController@create` | `web`, `guest` | `auth/forgot-password` | Auth | Working | Guests | **Polished** + mail warning | Done | Visually + tests |
| POST | `/forgot-password` | `password.email` | `…@store` | `web`, `guest`, `throttle:6,1` | Redirect | Auth | Working | Guests | N/A | None | Functional tests |
| GET | `/reset-password/{token}` | `password.reset` | `NewPasswordController@create` | `web`, `guest` | `auth/reset-password` | Auth | Working | Guests + token | **Polished** | Done | Visually (local token) + tests |
| POST | `/reset-password` | `password.store` | `…@store` | `web`, `guest`, `throttle:6,1` | Redirect | Auth | Working | Guests | N/A | None | Functional tests |
| GET | `/account` | `account.profile` | `Account\ProfileController@show` | `web`, `auth` | `account/profile` | Account | Working | Auth | **Polished** | Done | Visually + tests |
| PUT | `/account/password` | `account.password` | `…@updatePassword` | `web`, `auth`, `throttle:6,1` | Redirect | Account | Working | Auth | N/A | None | Functional tests |
| GET | `/up` | — | Framework health | — | Plain health | Ops | Framework | Yes | Non-UI | Exclude | N/A |
| GET | `storage/{path}` | `storage.local` | Framework | — | File serve | Storage | Framework | Env-dependent | Non-UI | Exclude | N/A |
| PUT | `storage/{path}` | `storage.local.upload` | Framework | — | Upload | Storage | Framework | Env-dependent | Non-UI | Exclude | N/A |

### Not registered

| Expected later (Phase 1+) | Status |
|---|---|
| User admin CRUD, roles/permissions UI | Not routed — out of UIX scope |
| Domain modules | Not routed |
| `/register` | Intentionally absent |

### Orphan

| Asset | Note |
|---|---|
| `resources/views/welcome.blade.php` | Untouched per approval |

---

## 3. User-facing page matrix

| Page | Status |
|---|---|
| Login `/` (root) | **Completed** (Implemented · Functionally verified · Visually verified) |
| Home `/home` | **Completed** (Implemented · Functionally verified · Visually verified) |
| Login | **Completed** (incl. validation error state visually verified) |
| Forgot password | **Completed** |
| Reset password | **Completed** (visual via local reset token) |
| Account profile | **Completed** |
| Customers list `GET /customers` | **Completed** (UI polish · empty + search · RTL desktop/mobile verified) |
| Customer create `GET /customers/create` | **Completed** (اسم المنشأة · org-only copy · role-aware assign) |
| Customer show `GET /customers/{id}` | **Completed** (UI polish · contact role distinction) |
| Customer edit `GET /customers/{id}/edit` | **Completed** (UI polish · same form patterns as create) |
| Credit profile `GET /customers/{id}/credit` | **Completed** (approved vs deferred · RTL desktop + ~390px verified) |
| Credit requests queue `GET /credit-requests` | **Completed** (open table · empty state · filter · RTL verified) |
| Credit request show `GET /credit-requests/{id}` | **Completed** (requested vs approved · SM decide CTAs · RTL verified) |
| Customer location `GET /customers/{id}/location` | **Completed** (map + form · Leaflet/OSM · RTL desktop/~390px verified) |
| Customer photos `GET /customers/{id}/photos` | **Completed** (private upload · empty state · RTL verified) |
| Visits list `GET /visits` | **Completed** (filter · badges · RTL desktop/~390px verified) |
| Visit create `GET /visits/create` | **Completed** (fixed locked assignee · blocked no-rep · منشأة select) |
| Visit show `GET /visits/{id}` | **Completed** (status · fixed-assignee note · distinct cancel/complete) |
| Visit edit `GET /visits/{id}/edit` | **Completed** (reschedule only · locked assignee display) |
| Products list `GET /products` | **Completed** (Prompt 1 UI polish · elevated table · cost column role-gated · aligned with stock list) |
| Product create `GET /products/create` | **Completed** (ds-page-bar · بيانات المنتج · الكثافة · flexible specs · mobile stack) |
| Product show `GET /products/{id}` | **Completed** (catalog metrics · specs readout · stock metrics · Procurement cost-only · no nested detail cards) |
| Product edit `GET /products/{id}/edit` | **Completed** (Administrator master · form card aligned with inventory polish) |
| Units list `GET /products/units` | **Completed** (admin catalog · status badges · inactive row tone · no conversions) |
| Unit create/edit | **Completed** |
| Stock balances `GET /inventory/stock` | **Completed** (Phase 3 Prompt 2 · elevated table · available hierarchy · no prices · RTL) |
| Adjustments list `GET /inventory/adjustments` | **Completed** (queue · status badges · signed deltas · Warehouse create CTA) |
| Adjustment create `GET /inventory/adjustments/create` | **Completed** (signed qty · Arabic reason · sectioned form) |
| Adjustment show `GET /inventory/adjustments/{id}` | **Completed** (SM decide panel · INV3 alert · balance metrics · confirms) |
| Sales requests list `GET /sales-requests` | **Completed** (UI polish 2026-10-16 · status badges · filters · empty state · row menu) |
| Sales request create/edit | **Completed** (UI polish · sectioned form · line rows · stock hint informational) |
| Sales request show | **Completed** (UI polish · metrics · lines + cost when authorized · submit/cancel decide panel · task links) |
| Procurement tasks list | **Completed** (UI polish · open urgency · cost column gated · search/status filters) |
| Procurement task show | **Completed** (UI polish · context card · SAR cost form · cancel · flashes) |
| Quotations list `GET /quotations` | **Completed** (UI polish 2026-10-16 · status badges · totals · version · empty state) |
| Quotation edit | **Completed** (UI polish · margin inputs · live cost→selling preview · no discount fields) |
| Quotation show | **Completed** (UI polish · metrics · lines · decide/send/cancel/supersede · send log table) |
| Quotation print | **Completed** (UI polish · RTL document · company header · VAT-inclusive note) |
| Sales-request quotation CTAs | **Completed** (link presentation polish · badges · create CTA preserved) |

### Phase 3 Prompt 2 — inventory routes (additive)

| Method | URI | Name | Handler | Middleware | Module | Impl. status |
|---|---|---|---|---|---|---|
| GET | `/inventory/stock` | `inventory.stock.index` | `Inventory\StockBalanceController@index` | `auth`, `permission:stock.view` | Inventory | Working |
| GET | `/inventory/adjustments` | `inventory.adjustments.index` | `Inventory\InventoryAdjustmentController@index` | `auth` + policy | Inventory | Working |
| GET | `/inventory/adjustments/create` | `inventory.adjustments.create` | `…@create` | `permission:inventory_adjustment.record` | Inventory | Working |
| POST | `/inventory/adjustments` | `inventory.adjustments.store` | `…@store` | `permission:inventory_adjustment.record`, throttle | Inventory | Working |
| GET | `/inventory/adjustments/{adjustment}` | `inventory.adjustments.show` | `…@show` | `auth` + policy | Inventory | Working |
| POST | `/inventory/adjustments/{adjustment}/decide` | `inventory.adjustments.decide` | `…@decide` | `auth` + policy approve/reject, throttle | Inventory | Working |

### Phase 4 Prompt 1 — sales requests + procurement (additive)

| Method | URI | Name | Module | Impl. status | UI notes |
|---|---|---|---|---|---|
| GET/POST | `/sales-requests` | `sales-requests.index` / `.store` | Sales | Working | Elevated table · status badges · q/status filters · empty `لا توجد طلبات بيع` |
| GET | `/sales-requests/create` | `sales-requests.create` | Sales | Working | Sectioned form · line add/remove · auto availability from stock (`SR-AUTO-STOCK`) |
| GET/PUT | `/sales-requests/{sales_request}` | `.show` / `.update` | Sales | Working | Metrics · lines · cost if authorized · decide CTAs · procurement links |
| GET | `/sales-requests/{sales_request}/edit` | `.edit` | Sales | Working | Draft-only form polish (same `_form`) |
| POST | `/sales-requests/{sales_request}/submit` | `.submit` | Sales | Working | Real action from show (confirm) |
| POST | `/sales-requests/{sales_request}/cancel` | `.cancel` | Sales | Working | Real action from show (confirm) |
| GET | `/procurement/tasks` | `procurement.tasks.index` | Procurement | Working | Open urgency · cost gated · search/status |
| GET | `/procurement/tasks/{task}` | `procurement.tasks.show` | Procurement | Working | Context · SAR cost form · cancel affordance |
| POST | `/procurement/tasks/{task}/cost` | `procurement.tasks.cost` | Procurement | Working | Form fields: cost + optional supplier/notes |
| POST | `/procurement/tasks/{task}/cancel` | `procurement.tasks.cancel` | Procurement | Working | Confirm cancel on show |
| GET | `/procurement/tasks/{task}/quote-pdf/{attachment}` | `procurement.tasks.quote-pdf` | Procurement | Working | Stream mandatory supplier-quote PDF |
| GET | `/supplier-pos` | `supplier-pos.index` | Procurement | Working | Shortfall SPOs · status filters |
| GET | `/supplier-pos/{supplier_po}` | `supplier-pos.show` | Procurement | Working | Mark ordered · warehouse receive · lines |
| GET | `/supplier-pos/{supplier_po}/pdf` | `supplier-pos.pdf` | Procurement | Working | Arabic RTL PDF (`PROC-SPO-AR`) |
| POST | `/supplier-pos/{supplier_po}/mark-ordered` | `supplier-pos.mark-ordered` | Procurement | Working | expected_at → notify Warehouse |
| POST | `/supplier-pos/{supplier_po}/receive` | `supplier-pos.receive` | Warehouse | Working | Creates goods receipt + ↑ on_hand (`PROC-RECV-SHIP`) |
| GET | `/supplier-pos/{supplier_po}/attachments/{uuid}` | `supplier-pos.attachment` | Procurement | Working | Stream attached PO PDF |
| GET | `/supplier-goods-receipts/{goods_receipt}` | `supplier-goods-receipts.show` | Warehouse | Working | Shipment receipt document |
| GET | `/supplier-goods-receipts/{goods_receipt}/pdf` | `supplier-goods-receipts.pdf` | Warehouse | Working | Arabic receipt PDF |

### Phase 4 Prompt 2 — quotations (additive)

| Method | URI | Name | Module | Impl. status | UI notes |
|---|---|---|---|---|---|
| GET | `/quotations` | `quotations.index` | Quotations | Working | Elevated table · status badges · VAT-incl totals · filters · empty state |
| POST | `/sales-requests/{id}/quotations` | `sales-requests.quotations.store` | Quotations | Working | CTA on sales-request show (badge list polish) |
| GET | `/quotations/{quotation}` | `quotations.show` | Quotations | Working | Metrics · PM1 line table · decide panel · send log |
| GET/PUT | `/quotations/{quotation}/edit` · update | `.edit` / `.update` | Quotations | Working | `.edit` redirects to show `#quote-pricing` · inline SM margin form on show · live preview |
| POST | `/quotations/{quotation}/submit-price` | `.submit-price` | Quotations | Working | Confirm from show |
| POST | `/quotations/{quotation}/decide` | `.decide` | Quotations | Working | Approve/reject panel + notes |
| POST | `/quotations/{quotation}/send` | `.send` | Quotations | Working (`Mail::fake`) | Confirm; failure flash via errors |
| POST | `/quotations/{quotation}/cancel` · supersede | `.cancel` / `.supersede` | Quotations | Working | Confirm actions |
| GET | `/quotations/{quotation}/print` | `.print` | Quotations | Working | RTL print/PDF · official Cemi logo |
| GET | `/deliveries/{delivery}/print` | `deliveries.print` | Deliveries | Working | إذن تسليم print · logo · no prices for Warehouse |
| GET | `/invoices/{invoice}/print` | `invoices.print` | Invoices | Working | فاتورة print · logo · CR7 unpaid (not ZATCA) |

### Phase 5 Prompt 1 — customer POs + sales orders (additive)

| Method | URI | Name | Module | Impl. status | UI notes |
|---|---|---|---|---|---|
| GET | `/customer-pos` | `customer-pos.index` | Customer PO | Working | UI polish · badges · Deal column · quote link · row menu · empty state |
| GET/POST | `/customer-pos/create` · store | `.create` / `.store` | Customer PO | Working | UI polish · quote picker · qty ≤ quote · Deal-will-create notice |
| GET | `/customer-pos/{customer_po}` | `.show` | Customer PO | Working | UI polish · Deal read-only card · allocation · SO badges · SM cancel confirm |
| GET/PUT | `/customer-pos/{id}/edit` · update | `.edit` / `.update` | Customer PO | Working | UI polish · reference/notes only · lines immutable copy |
| POST | `/customer-pos/{id}/cancel` | `.cancel` | Customer PO | Working | Confirm from show |
| GET | `/sales-orders` | `sales-orders.index` | Sales Orders | Working | UI polish · status badges · filters · WH no-price note · empty state |
| GET/POST | `/sales-orders/create` · store | `.create` / `.store` | Sales Orders | Working | UI polish · PO required · unallocated guidance · Deal context |
| GET | `/sales-orders/{sales_order}` | `.show` | Sales Orders | Working | UI polish · Deal/PO card · decide panel · credit stub honesty · no prices |
| GET/PUT | `/sales-orders/{id}/edit` · update | `.edit` / `.update` | Sales Orders | Working | UI polish · draft qty ≤ remaining |
| POST | `/sales-orders/{id}/submit` | `.submit` | Sales Orders | Working | Confirm from show |
| POST | `/sales-orders/{id}/decide` | `.decide` | Sales Orders | Working | Approve-reserve / reject confirms |
| POST | `/sales-orders/{id}/cancel-approved` | `.cancel-approved` | Sales Orders | Working | Confirm release from show |

### Phase 6 Prompt 1 — preparation + deliveries (additive)

| Method | URI | Name | Module | Impl. status | UI notes |
|---|---|---|---|---|---|
| GET | `/warehouse/preparation` | `warehouse.preparation.index` | Preparation | Working | UI polish · prep/fulfillment badges · ready confirm · empty state |
| POST | `/warehouse/preparation/{sales_order}/ready` | `.ready` | Preparation | Working | Confirm from queue |
| GET | `/deliveries` | `deliveries.index` | Deliveries | Working | UI polish · status badges · SO link · filters · empty · بلا أسعار |
| GET/POST | `/deliveries/create` · store | `.create` / `.store` | Deliveries | Working | UI polish · SO picker · undelivered caps copy · no prices |
| GET | `/deliveries/{delivery}` | `.show` | Deliveries | Working | UI polish · POD panel · blocked-without-POD · confirm/cancel · read-only post-confirm |
| GET/PUT | `/deliveries/{id}/edit` · update | `.edit` / `.update` | Deliveries | Working | UI polish · draft qty ≤ remaining |
| POST | `/deliveries/{id}/pending-pod` | `.pending-pod` | Deliveries | Working | From show decide panel |
| POST | `/deliveries/{id}/confirm` | `.confirm` | Deliveries | Working | Strong confirm · POD required · deduct once |
| POST | `/deliveries/{id}/cancel` | `.cancel` | Deliveries | Working | Confirm · no deduct |
| POST/GET/DELETE | `/deliveries/{id}/pod` · download · destroy | `.pod.*` | Deliveries | Working | Elevated POD upload/list/download/remove |

### Phase 7 Prompt 1 — invoices / collections / notes / deals (additive)

| Method | URI | Name | Module | Impl. status | UI notes |
|---|---|---|---|---|---|
| GET | `/invoices` | `invoices.index` | Invoices | Working | UI polish · due/unpaid emphasis · delivery/deal links · empty |
| GET/POST | `/invoices/create` · store | `.create` / `.store` | Invoices | Working | UI polish · delivery picker · qty preview · PF3 copy |
| GET | `/invoices/{invoice}` | `.show` | Invoices | Working | UI polish · CR7 breakdown · draft vs confirmed · overdue CM1b |
| POST | `/invoices/{invoice}/resend-overdue` | `.resend-overdue` | Invoices | Working | Honest Mail::fake / CM1b copy |
| GET | `/collections` | `collections.index` | Collections | Working | UI polish · draft vs confirmed badges · draft hint |
| GET/POST | `/collections/create` · store | `.create` / `.store` | Collections | Working | UI polish · draft-only messaging · unpaid caps |
| GET | `/collections/{collection}` | `.show` | Collections | Working | UI polish · draft banner · confirm/reversal panels |
| POST | `/collections/{id}/confirm` | `.confirm` | Collections | Working | Strong confirm |
| POST | `/collections/{id}/request-reversal` | `.request-reversal` | Collections | Working | From show |
| POST | `/collections/{id}/decide-reversal` | `.decide-reversal` | Collections | Working | SM decide panel |
| GET | `/invoice-notes` | `invoice-notes.index` | Notes | Working | UI polish · credit/debit badges · status |
| GET/POST | `/invoice-notes/create` · store | `.create` / `.store` | Notes | Working | UI polish · type clarity · unpaid hint |
| GET | `/invoice-notes/{note}` | `.show` | Notes | Working | UI polish · submit/decide like quotation |
| POST | `/invoice-notes/{id}/submit` | `.submit` | Notes | Working | Confirm from show |
| POST | `/invoice-notes/{id}/decide` | `.decide` | Notes | Working | SM approve/reject |
| GET | `/deals` | `deals.index` | Deals | Working | UI polish · open/closed badges · PO link |
| GET | `/deals/{deal}` | `.show` | Deals | Working | UI polish · unpaid per invoice · close/blocked gate |
| POST | `/deals/{deal}/close` | `.close` | Deals | Working | TX5b confirm |

Artisan: `invoices:send-overdue-reminders` (non-HTTP).

### Phase 9 Prompt 1 — reports / dashboard / PF1 (additive)

| Method | URI | Name | Module | Impl. status | UI notes |
|---|---|---|---|---|---|
| GET | `/home` (auth) | `home` | Dashboard | Working | UI polish · ds-page-bar · real KPIs only (RP1) · no export/schedule |
| GET | `/reports/ar` | `reports.ar.index` | Reports | Working | UI polish · unpaid CR7 · overdue filter · Asia/Riyadh · no export |
| GET | `/reports/statements` | `reports.statements.index` | Reports | Working | UI polish · customer picker · scoped · empty surface |
| GET | `/reports/statements/{customer}` | `reports.statements.show` | Reports | Working | UI polish · statement · CR7 unpaid emphasis |
| GET | `/customer-pos/{po}` | `customer-pos.show` | Sales | Working | + PF1 card · three separate labels |
| GET | `/deals/{deal}` | `deals.show` | Deals | Working | + PF1 card · three separate labels |

---

## 4. UIX pass notes

**System table redesign (Designer):** Shared `.admin-table` / `.ds-table-surface` / `.quotes-list-panel` use the quotations enterprise sheet (white panel, hairline rows, muted header). Content width widened (`app-main` ~1560px) to cut side gaps. Form line editors stay denser. No fake columns. `npm run build` after CSS.

### Prior — 2026-10-16

**Pass 1 (subtle):** token tweaks only — user reported no visible change.

**Pass 2 (visible redesign):**
- Dark brand header + mark; dark footer; Almarai as the system typeface.
- Home: split stage (green hero brand plane + status side) — not a single gray card.
- Auth/account: split layout (brand pane + form pane); pill primary actions.
- Forms: stronger invalid/focus; reduced-motion respected.
- Factory: `is_active => true` for auth tests after roles scaffolding landed.
- Build: `npm run build` passed; tests: `php artisan test` — 22 passed.
- Visually re-verified: home + login (split UI).

---

## 5. Excluded (non user-facing)

- `POST login`, `POST logout`, `POST/PUT password` actions  
- `GET/PUT storage/{path}`  
- `GET /up`  

---

## 6. Unresolved (not in this UI scope)

- Phase 1: roles, user admin, audit, attachments, company/warehouse settings  
- Domain P0 questions in `docs/OPEN_QUESTIONS.md`  
- Production mail (`docs/AUTH_MAIL.md`)  

---

## 7. Document control

| Version | Change |
|---|---|
| 2026-10-16 | Initial inventory |
| 2026-10-16 | UIX polish complete for 5 pages; matrix updated with visual/functional verification |
| 2026-10-16 | Phase 2 Prompt 1: customer list/detail/create/edit UI polish |
| 2026-10-16 | Phase 2 Prompt 2: credit profile + request queue/detail UI polish |
| 2026-10-16 | Phase 2 Prompt 3: customer location map + photos UI polish |
| 2026-10-16 | Phase 2 Prompt 4: visit schedule/list/show/edit UI polish |
| 2026-10-16 | Phase 3 Prompt 2: stock balances + inventory adjustments routes; totals recount 84 |
| 2026-10-16 | Phase 4 Prompt 1: sales-requests + procurement.tasks routes |
| 2026-10-16 | Phase 4 Prompt 2: quotations routes + print/send |
| 2026-10-16 | Phase 4 Prompt 1 UI polish: sales-requests + procurement tasks (visual only) |
| 2026-10-16 | Phase 4 Prompt 2 UI polish: quotations index/show/edit/print + SR quote links |
| 2026-10-16 | Phase 5 Prompt 1 UI polish: customer-pos + sales-orders (visual only) |
| 2026-10-16 | Phase 6 Prompt 1 UI polish: preparation + deliveries/POD (visual only) |
| 2026-10-16 | Phase 7 Prompt 1: invoices/collections/invoice-notes/deals routes (baseline RTL; Designer polish pending) |
| 2026-10-17 | Root `/` = login; operational dashboard moved to `/home`; `/login` redirects to `/` |
| 2026-10-16 | Phase 7 Prompt 1 UI polish: invoices/collections/notes/deals (visual only) |
