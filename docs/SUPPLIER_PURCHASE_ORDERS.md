# Supplier purchase orders & goods receipts

Decisions: `PROC-BUY-ON-CPO`, `PROC-BUY-ON-SO`, `PROC-SPO-AR`, `PROC-SPO-ATTACH`, `PROC-RECV`, `PROC-RECV-SHIP` in `docs/OPEN_QUESTIONS.md`. Related: `docs/SALES_ORDERS.md`, `docs/QUOTATIONS.md`, `docs/INVENTORY.md` (`INV3`).

## Full cycle (organized)

```
SM approves quotation (margin) → notify Rep only (quote ready)
        ↓
Rep sends quote / customer accepts
        ↓
Rep converts quote → attaches customer PO PDF → SO draft
        ↓
Supplier PO (أمر شراء مورد) — open  ← PROC-BUY-ON-CPO (procurement lines only)
        ↓  Procurement issues to supplier
Ordered + Arabic PO PDF attached in system (+ optional signed copy)
        ↓  Warehouse notified with expected_at
Shipment 1 arrives → Goods receipt #A (أمر استلام) → ↑ on_hand
Shipment 2 arrives → Goods receipt #B → ↑ on_hand
        … until PO lines fully received
        ↓
SM approve SO (stock available → reserve)
```

**Safety net (`PROC-BUY-ON-SO`):** If SO approve still lacks stock and no open quote-sourced SPO exists, create a shortfall SPO. If an open SPO already exists for the quotation, block approve and point to that SPO (no duplicate buy).

Partial shipments are **first-class**: each warehouse arrival creates its own **goods receipt document** linked to the same PO. Line `received_quantity` accumulates across receipts.

## When PO is created

| Trigger | Lines | Decision |
|---|---|---|
| Quote **converted** to SO with customer PO PDF | Procurement-sourced lines only (`needs_procurement` / linked task) | `PROC-BUY-ON-CPO` |
| SO **approve** shortfall | Shortfall qty only; skip create if open SPO already exists for the quote | `PROC-BUY-ON-SO` + `INV3` |

## PO lifecycle

| Status | Meaning |
|---|---|
| `open` | Created from SO shortfall; awaiting Procurement issue |
| `ordered` | Issued to supplier; PO PDF attached; Warehouse notified |
| `partially_received` | ≥1 goods receipt; remaining qty on some lines |
| `received` | All lines fully received across shipments |
| `cancelled` | Reserved (UI cancel not exposed yet) |

## Goods receipt (أمر استلام شحنة)

| Field | Role |
|---|---|
| Links to | One `supplier_purchase_order` |
| Lines | Qty received **this shipment** (≤ remaining on PO line) |
| Effect | `InventoryService::receive` → ↑ `on_hand` immediately (`PROC-RECV`); ledger reference = **goods receipt** |
| Document | Show page + Arabic PDF |
| Signed note (`PROC-RECV-NOTE`) | **Mandatory** PDF of the warehouse-signed supplier delivery note on every receipt |

## Roles

| Action | Who | Permission |
|---|---|---|
| List / view PO + receipts / PDFs | Procurement, Warehouse (`procurement.view` or `stock.view`) | Policy |
| Issue PO (mark ordered) + attach | Procurement | `procurement.manage` |
| Record shipment / create goods receipt | Warehouse (`طلبيات واردة`) | `delivery.manage` — only after PO is **ordered** |

## Attachments (`PROC-SPO-ATTACH`)

On mark-ordered the system generates the Arabic PO PDF and **stores it** as a private attachment on the SPO. An optional extra PDF (signed copy) may be uploaded. Stream via `supplier-pos.attachment`.

## Routes

| Method | URI | Name |
|---|---|---|
| GET | `/supplier-pos` | `supplier-pos.index` |
| GET | `/supplier-pos/{supplier_po}` | `supplier-pos.show` |
| GET | `/supplier-pos/{supplier_po}/pdf` | `supplier-pos.pdf` |
| GET | `/supplier-pos/{supplier_po}/attachments/{uuid}` | `supplier-pos.attachment` |
| POST | `/supplier-pos/{supplier_po}/mark-ordered` | `supplier-pos.mark-ordered` |
| POST | `/supplier-pos/{supplier_po}/receive` | `supplier-pos.receive` → creates goods receipt |
| GET | `/supplier-goods-receipts/{goods_receipt}` | `supplier-goods-receipts.show` |
| GET | `/supplier-goods-receipts/{goods_receipt}/pdf` | `supplier-goods-receipts.pdf` |

## Code map

| Piece | Location |
|---|---|
| Models | `SupplierPurchaseOrder`, `SupplierPurchaseOrderLine`, `SupplierGoodsReceipt`, `SupplierGoodsReceiptLine` |
| Workflow | `SupplierPurchaseOrderWorkflow` (`markOrdered`, `receiveShipment`) |
| SO hook | `SalesOrderWorkflow::approve` (shortfall outside approve tx) |
| PDF | `SupplierPurchaseOrderPdfRenderer`, `SupplierGoodsReceiptPdfRenderer` |
| UI | `resources/views/supplier-pos/*`, `resources/views/supplier-goods-receipts/*` |
