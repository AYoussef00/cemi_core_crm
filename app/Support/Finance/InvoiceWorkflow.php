<?php

namespace App\Support\Finance;

use App\Models\Delivery;
use App\Models\DeliveryLine;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\QuotationLine;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Money\Money;
use App\Support\Notifications\InternalNotifier;
use App\Support\Warehouse\DeliveryStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

class InvoiceWorkflow
{
    public function __construct(
        private AuditLogger $audit,
        private InternalNotifier $notifier,
    ) {}

    /**
     * Issue one invoice for a confirmed delivery (PF3). Prices snapshot from quotation line
     * via CPO line (VAT-inclusive selling price). due_at = issued_at + 30 days (v1 default; CR5 open).
     *
     * @param  bool  $idempotent  When true (auto-issue on delivery confirm), return existing invoice instead of error.
     */
    public function issueFromDelivery(
        Delivery $delivery,
        User $actor,
        ?string $notes = null,
        bool $idempotent = false,
    ): Invoice {
        if ($delivery->status !== DeliveryStatus::Confirmed) {
            throw new DomainException('الفاتورة تُصدر لتسليم مؤكد فقط (بعد إثبات التسليم).');
        }

        $existing = Invoice::query()->where('delivery_id', $delivery->id)->first();
        if ($existing !== null) {
            if ($idempotent) {
                return $existing->loadMissing(['customer', 'lines', 'delivery', 'deal']);
            }

            throw new DomainException('هذا التسليم مُفوتر مسبقًا — فاتورة واحدة لكل تسليم (PF3).');
        }

        $delivery->load([
            'lines.product',
            'lines.salesOrderLine.customerPurchaseOrderLine.quotationLine',
            'salesOrder.deal',
            'customer',
        ]);

        if ($delivery->lines->isEmpty()) {
            throw new DomainException('التسليم بلا بنود.');
        }

        $order = $delivery->salesOrder;
        if ($order === null || $order->deal === null) {
            throw new DomainException('أمر البيع أو الصفقة غير موجودة.');
        }

        return DB::transaction(function () use ($delivery, $actor, $notes, $order): Invoice {
            $lineRows = [];
            $total = 0.0;

            foreach ($delivery->lines as $index => $dLine) {
                $unitPrice = $this->resolveUnitPriceVatInclusive($dLine);
                $lineTotal = (float) Money::round2((float) $unitPrice * (float) $dLine->quantity);
                $total += $lineTotal;

                $lineRows[] = [
                    'delivery_line_id' => $dLine->id,
                    'sales_order_line_id' => $dLine->sales_order_line_id,
                    'product_id' => $dLine->product_id,
                    'product_name_ar' => $dLine->product?->name_ar
                        ?? $this->quotationLineFor($dLine)?->product_name_ar
                        ?? 'منتج',
                    'quantity' => number_format((float) $dLine->quantity, 3, '.', ''),
                    'unit_price_vat_inclusive' => $unitPrice,
                    'line_total_vat_inclusive' => Money::round2($lineTotal),
                    'sort_order' => $index,
                ];
            }

            $issuedAt = now();
            $invoice = Invoice::query()->create([
                'delivery_id' => $delivery->id,
                'sales_order_id' => $order->id,
                'deal_id' => $order->deal_id,
                'customer_id' => $delivery->customer_id,
                'issued_by_user_id' => $actor->id,
                'status' => InvoiceStatus::Issued,
                'total_vat_inclusive' => Money::round2($total),
                'issued_at' => $issuedAt,
                'due_at' => $issuedAt->copy()->addDays(30),
                'notes_ar' => $notes,
            ]);

            foreach ($lineRows as $row) {
                InvoiceLine::query()->create(array_merge($row, ['invoice_id' => $invoice->id]));
            }

            $this->audit->record('invoice.issued', $invoice, [
                'delivery_id' => $delivery->id,
                'deal_id' => $order->deal_id,
                'total_vat_inclusive' => (string) $invoice->total_vat_inclusive,
                'line_count' => count($lineRows),
            ], $actor);

            return $invoice->fresh(['customer', 'lines', 'delivery', 'deal']);
        });
    }

    private function resolveUnitPriceVatInclusive(DeliveryLine $dLine): string
    {
        $qLine = $this->quotationLineFor($dLine);
        if ($qLine === null || $qLine->selling_price_vat_inclusive === null) {
            throw new DomainException(
                'تعذر تحديد سعر البيع شامل الضريبة لبند التسليم (من عرض السعر). المنتج: '
                .($dLine->product?->name_ar ?? '#')
            );
        }

        return Money::round2($qLine->selling_price_vat_inclusive);
    }

    private function quotationLineFor(DeliveryLine $dLine): ?QuotationLine
    {
        /** @var SalesOrderLine|null $soLine */
        $soLine = $dLine->salesOrderLine;
        $soLine?->loadMissing('customerPurchaseOrderLine.quotationLine');

        return $soLine?->customerPurchaseOrderLine?->quotationLine;
    }
}
