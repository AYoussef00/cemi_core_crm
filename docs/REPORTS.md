# Reports & home dashboard (Phase 9 Prompt 1)

**Timezone (`RP2`):** `Asia/Riyadh` (`config/app.php` / `APP_TIMEZONE`).

**No scheduled reports / file exports in v1 (`RP3`/`RP4`).**

## Home dashboard (`/`)

`HomeDashboardBuilder` emits permission-scoped KPIs from real tables only (zero is a real count):

| KPI | Permission gate |
|---|---|
| طلبات المبيعات | `sales_requests.view` |
| عروض الأسعار | `quotations.view` |
| أوامر شراء العملاء | `customer_pos.view` |
| أوامر البيع (+ بانتظار التسليم) | `sales_orders.view` |
| طابور التجهيز | `delivery.view` |
| الذمم (AR) / متأخرة | `invoices.view` — **not** Warehouse |
| صفقات مفتوحة | `invoices.view` or `deals.close` |
| الزيارات | `visits.view` |

Unpaid AR uses **CR7** (`Invoice::unpaidBalance`).

## In-app reports

| Route | Purpose | Auth |
|---|---|---|
| `GET /reports/ar` | Unpaid issued invoices; overdue filter | `reports.ar` / `reports.financial` / `invoices.view` |
| `GET /reports/statements` | Customer list for statements | same |
| `GET /reports/statements/{customer}` | Customer invoice statement + CR7 unpaid | same + customer in scope |

Sales Rep Own/Cust: assigned customers only. Warehouse: **403** on AR/statements.

## PF1 labels (Customer PO + Deal show)

Separate figures — never collapsed:

- **غير مخصّص** = ordered − allocated on active SOs  
- **غير مسلّم** = ordered − confirmed delivered  
- **غير مفوتر** = delivered − invoiced  

## UI polish (Designer)

Dashboard / AR / statements use `ds-page-bar`, inventory metrics, unpaid emphasis, and empty surfaces aligned with invoices/deliveries. PF1 on Customer PO + Deal show sits in a `pf1-card` with three **separate** labels. Sidebar report links enabled where permitted; Phase 8 email nav stays deferred. **No** export or schedule CTAs.

## Out of scope (this prompt)

Phase 8 live email/WhatsApp; Excel/PDF export; scheduled jobs; sales/procurement/inventory report packs (nav stubs remain disabled).
