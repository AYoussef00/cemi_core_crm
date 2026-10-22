# Attachments foundation

**Phase:** 1 — Prompt 6  
**Status:** Initial conservative defaults (stakeholder may widen later)

## Storage

- Disk: `attachments` (`storage/app/private/attachments_disk`)
- `serve` disabled; files are **never** exposed via `/storage` symlink or public URLs
- Download only through authenticated routes that authorize `settings.manage` and verify attachable ownership
- Storage filenames are randomized UUIDs; original name kept as sanitized metadata only

## Initial allowlist (assumption)

Docs did not define types/size. Initial v1 defaults:

| Rule | Value |
|---|---|
| Extensions | `pdf`, `jpg`, `jpeg`, `png`, `webp`, `gif` |
| MIME | `application/pdf`, `image/jpeg`, `image/png`, `image/webp`, `image/gif` |
| Max size | **5120 KB (5 MiB)** per file (`ATTACHMENTS_MAX_KB`) |

Override via `.env`: `ATTACHMENTS_DISK`, `ATTACHMENTS_MAX_KB`.

## Current attachables

- `company` → company settings record — auth: **`settings.manage`**
- `warehouse` → warehouse settings record — auth: **`settings.manage`**
- `customer` → customer photos / site images — auth: **`customers.view`** (download) / **`customers.manage`** + record scope (upload/remove); Sales Rep Assigned only
- `delivery` → proof of delivery (POD) — auth: **`delivery.view`** (download) / **`delivery.manage`** while delivery open (upload/remove); PDF + images

Customer photo allowlist (images only): `jpg`, `jpeg`, `png`, `webp`, `gif` via `attachments.customer_photo_extensions` (PDF not accepted on customers).

Future modules (quotations, POs, invoices) should reuse `AttachmentService` + `HasAttachments` and map new morph aliases in `config/attachments.php`.

## Audit

- `attachment.uploaded` / `attachment.removed`
- Properties: attachable alias/id, original name, mime, extension, size — **not** file contents or storage paths
