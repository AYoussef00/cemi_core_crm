# Money / currency representation

**Status:** Active — Phase 2+ storage; Phase 4 pricing rules resolved 2026-10-16

| Item | Convention |
|---|---|
| Storage | `DECIMAL(15, 2)` in MySQL / Laravel `decimal:2` cast |
| Currency | Single-currency **SAR** for v1 amounts; no multi-currency columns (`PM6` still open if ever needed) |
| Display | `number_format((float) $amount, 2)` with LTR numeral span where needed |
| Rounding (`PM4b`) | **2 decimal places (nearest halala)**, half-up |
| Discounts (`PM4a`) | **None** in v1 |
| VAT (`TX8`) | Fixed **15%**; quotation/customer prices are **VAT-inclusive** (`TX2`) |
| Pricing (`PM1`) | `selling_price = supplier_cost + margin_amount` (absolute SAR margin) |

**Applied so far (storage):** `customers.approved_credit_limit`, `credit_limit_requests.requested_credit_limit`, `products.purchase_cost`, `procurement_tasks.supplier_cost` / `sales_request_lines.supplier_cost`, quotation line `supplier_cost` + `margin_amount` + `selling_price_vat_inclusive`, invoice / collection / note amounts (`DECIMAL(15,2)`).

**Phase 4+ / 7:** Selling-price / quotation / invoice amounts follow `PM1` + `PM4a` + `PM4b` + `TX8`. Invoice line unit prices snapshot quotation VAT-inclusive selling prices. Do not invent %-markup-only formulas or discount fields.
