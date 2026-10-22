# Known limitations — pilot / Phase 10

**Status:** Active for pilot readiness  
**Rule:** Do not treat absence of a feature below as a defect unless PHASE_ACCEPTANCE requires it.

---

## Deferred / out of initial release

| ID | Limitation | Impact |
|---|---|---|
| Phase 8 / `CM1b` | **Live SMTP / email productization deferred.** Quotation send, overdue reminders, and mail settings use `Mail::fake` in tests and log/config placeholders in app — not production provider recovery. | No outbound customer email reliability claim |
| WhatsApp | Out of initial release (`CM1`) | No WhatsApp channel |
| ZATCA e-invoicing API | Not implemented; **#39 deferred for now** (stakeholder 2026-10-17) — not required for current pilot; no compliance claim | Revisit before regulated production if needed |
| Export / scheduled reports | Not in v1 (`RP3`/`RP4` resolved as excluded) | AR/statements are on-screen only |
| Driver GPS / visit GPS | Excluded (`GPS1`/`GPS2`/`GPS4`) | Delivery confirm uses POD only |
| Write-offs (`TX5a`) | Not implemented | Settle via collections + notes only |
| Stock return on credit note (`INV8`) | Not implemented | Notes affect unpaid only |
| Discounts (`PM4a`) | None in v1 | No discount fields |
| Lot tracking (`INV4`) | Open / not built | Single warehouse qty only |
| Document numbering UX (`NUM*`) | Open | Internal IDs used |
| Phase 1 formal Accepted | Stakeholder #14 still open | Foundation shipped; formal stamp pending |

---

## Honest behaviors (not bugs)

- Draft collections **do not** reduce unpaid (`CR7`).
- One invoice per confirmed delivery (`PF3`); duplicate issue returns a form error.
- Local file uploads (customer PO PDF, POD, photos): if PHP shows `unable to create a temporary file`, start the app with `bin/serve-local.sh` (sets `upload_tmp_dir` under `storage/app/php-upload-tmp`). Do not rely on a sandboxed `artisan serve` for multipart uploads.
- Deal close only when all linked invoices unpaid = 0 (`TX5b`); Accounts only.
- `CreditExposure` = sum of unpaid issued invoices; blocks SO approve at/above limit.
- Warehouse has **no** `invoices.view` / `prices.view`.
- Root `/` is login; operational home is `/home`.

---

## Pilot risk acceptance

Stakeholders reviewing Phase 10 must acknowledge Phase 8 deferral. ZATCA/#39 is **deferred for now** (not a pilot blocker). Revisit ZATCA before regulated production go-live if required.
