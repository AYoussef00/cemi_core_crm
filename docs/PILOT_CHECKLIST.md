# Pilot checklist — Phase 10

**Document type:** Ops / stakeholder sign-off  
**Related:** `docs/PHASE_ACCEPTANCE.md` § Phase 10, plat-guide #38–#39  
**Status:** Developer verification complete for #38. **#39 ZATCA/accountant deferred** by stakeholder for now (not required for current pilot). Phase 10 **Accepted** still needs stakeholder pilot sign-off §4 if desired.

---

## 1. Automated evidence (#38)

| Check | Result | Date |
|---|---|---|
| `php artisan test` (full suite) | **233 passed** (1616 assertions) | 2026-10-17 |
| Phase 10 lifecycle scenarios (1–8, 12–13) | `Phase10LifecycleScenariosTest` | 2026-10-17 |
| Phase 10 authz + concurrency (9–11) | `Phase10AuthzConcurrencyTest` | 2026-10-17 |
| Existing module suites still green | Full suite | |

---

## 2. Scenario coverage map

| # | Scenario | Automated |
|---|---|---|
| 1 | Customer + visit → quote → PO → SO → delivery → invoice → collect → deal close | Yes |
| 2 | Unavailable / procurement cost path | Yes |
| 3 | Quotation expires without send | Yes |
| 4 | One PO, multi SO + multi delivery | Yes |
| 5 | Partial delivery; invoice delivered qty only | Yes |
| 6 | Credit limit blocks; payment re-enables | Yes |
| 7 | Partial then full collection; deal close gated | Yes |
| 8 | Delivery without inventing GPS | Yes (schema/route absence) |
| 9 | Email — Mail::fake only (Phase 8 deferred) | Yes |
| 10 | Unauthorized access across roles | Yes |
| 11 | Double-submit reserve/deduct/release + duplicate invoice/collection | Yes |
| 12 | AR/statements reconcile to CR7 | Yes |
| 13 | Credit/debit notes; issued total immutable | Yes |
| 14 | Accountant / ZATCA pre-launch | **Deferred for now** (stakeholder 2026-10-17) — see §5 |

---

## 3. Manual pilot smoke (stakeholder)

| Step | Owner | Pass? | Initials / date |
|---|---|---|---|
| Login at `/` works; dashboard at `/home` | | ☐ | |
| Create org customer + visit (Rep) | | ☐ | |
| Available SR → quote → PO → SO approve → delivery + POD → invoice → collect → deal close | | ☐ | |
| Warehouse screens show **بلا أسعار**; no invoice nav | | ☐ | |
| Rep cannot see other Rep’s customer invoices | | ☐ | |
| Credit gate blocks when exposure ≥ limit | | ☐ | |
| AR report / statement matches unpaid on invoice show | | ☐ | |
| Known limitations reviewed (`docs/KNOWN_LIMITATIONS.md`) | | ☐ | |

---

## 4. Stakeholder acceptance (#38)

| Role | Name | Decision | Date |
|---|---|---|---|
| Product / business owner | | ☐ Accepted · ☐ Waived with notes | |
| Ops / pilot lead | | ☐ Accepted · ☐ Waived with notes | |

**Notes / waivers:**

```
(leave blank until signed)
```

---

## 5. Accountant / ZATCA pre-launch (#39) — deferred for now

**Stakeholder decision (2026-10-17):** ZATCA / accountant pre-launch review is **not required for the current pilot**. No ZATCA e-invoicing API will be built now. This is **not** a legal-compliance certificate.

| Item | Status |
|---|---|
| E-invoicing API (ZATCA) in product | **Not implemented** · deferred |
| Accountant / ZATCA checklist (#39) | **Deferred** — revisit before regulated production go-live if needed |
| Compliance claim | **None** |

Optional future sign-off (when ZATCA is required again):

| Reviewer | Organization | Sign-off | Date |
|---|---|---|---|
| Accountant | | ☐ Deferred until later | |
| Compliance (if any) | | ☐ Deferred until later | |
