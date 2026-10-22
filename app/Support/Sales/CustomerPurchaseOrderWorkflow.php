<?php

namespace App\Support\Sales;

use App\Models\Customer;
use App\Models\CustomerPurchaseOrder;
use App\Models\CustomerPurchaseOrderLine;
use App\Models\Deal;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Quotations\QuotationStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

class CustomerPurchaseOrderWorkflow
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Create PO from an approved or sent quotation for the same customer.
     * Line qtys must be > 0 and ≤ quotation line qty (PF5 still open — we enforce ≤ quote as honest v1 rule).
     *
     * @param  array{
     *     customer_id: int,
     *     quotation_id: int,
     *     reference_ar?: string|null,
     *     notes_ar?: string|null,
     *     lines: list<array{quotation_line_id: int, quantity: string}>
     * }  $payload
     */
    public function create(User $actor, array $payload): CustomerPurchaseOrder
    {
        $customer = Customer::query()->findOrFail($payload['customer_id']);
        $quotation = Quotation::query()->with('lines')->findOrFail($payload['quotation_id']);

        if ((int) $quotation->customer_id !== (int) $customer->id) {
            throw new DomainException('عرض السعر لا يخص هذه المنشأة.');
        }

        if (! in_array($quotation->status, [QuotationStatus::Approved, QuotationStatus::Sent], true)) {
            throw new DomainException('يُربط أمر شراء العميل بعرض سعر معتمد أو مُرسل فقط.');
        }

        if ($payload['lines'] === []) {
            throw new DomainException('يجب إضافة بند واحد على الأقل.');
        }

        return DB::transaction(function () use ($actor, $payload, $customer, $quotation): CustomerPurchaseOrder {
            $po = CustomerPurchaseOrder::query()->create([
                'customer_id' => $customer->id,
                'quotation_id' => $quotation->id,
                'created_by_user_id' => $actor->id,
                'status' => CustomerPurchaseOrderStatus::Open,
                'reference_ar' => $payload['reference_ar'] ?? null,
                'notes_ar' => $payload['notes_ar'] ?? null,
            ]);

            foreach (array_values($payload['lines']) as $index => $lineInput) {
                /** @var QuotationLine $qLine */
                $qLine = $quotation->lines->firstWhere('id', (int) $lineInput['quotation_line_id']);
                if ($qLine === null) {
                    throw new DomainException('بند عرض سعر غير صالح.');
                }

                $qty = (float) $lineInput['quantity'];
                if ($qty <= 0) {
                    throw new DomainException('كمية بند أمر الشراء يجب أن تكون موجبة.');
                }
                if ($qty > (float) $qLine->quantity + 1e-9) {
                    throw new DomainException(
                        'كمية أمر الشراء تتجاوز كمية عرض السعر للبند: '.$qLine->product_name_ar
                    );
                }

                CustomerPurchaseOrderLine::query()->create([
                    'customer_purchase_order_id' => $po->id,
                    'quotation_line_id' => $qLine->id,
                    'product_id' => $qLine->product_id,
                    'quantity' => number_format($qty, 3, '.', ''),
                    'sort_order' => $index,
                ]);
            }

            $deal = Deal::query()->create([
                'customer_id' => $customer->id,
                'customer_purchase_order_id' => $po->id,
                'status' => DealStatus::Open,
            ]);

            $this->audit->record('customer_po.created', $po, [
                'customer_id' => $customer->id,
                'quotation_id' => $quotation->id,
                'deal_id' => $deal->id,
                'status' => $po->status->value,
                'line_count' => count($payload['lines']),
            ], $actor);

            $this->audit->record('deal.created', $deal, [
                'customer_id' => $customer->id,
                'customer_purchase_order_id' => $po->id,
                'status' => $deal->status->value,
            ], $actor);

            return $po->fresh(['customer', 'quotation', 'lines.product', 'deal']);
        });
    }

    /**
     * @param  array{reference_ar?: string|null, notes_ar?: string|null}  $payload
     */
    public function updateOpen(CustomerPurchaseOrder $po, User $actor, array $payload): CustomerPurchaseOrder
    {
        if (! $po->isOpen()) {
            throw new DomainException('لا يمكن تعديل أمر شراء ملغى.');
        }

        return DB::transaction(function () use ($po, $actor, $payload): CustomerPurchaseOrder {
            $po->fill([
                'reference_ar' => $payload['reference_ar'] ?? $po->reference_ar,
                'notes_ar' => array_key_exists('notes_ar', $payload) ? $payload['notes_ar'] : $po->notes_ar,
            ])->save();

            $this->audit->record('customer_po.updated', $po, [
                'status' => $po->status->value,
            ], $actor);

            return $po->fresh(['customer', 'quotation', 'lines.product', 'deal']);
        });
    }

    public function cancel(CustomerPurchaseOrder $po, User $actor): CustomerPurchaseOrder
    {
        if (! $po->isOpen()) {
            throw new DomainException('أمر الشراء ملغى مسبقًا.');
        }

        $hasActiveSo = $po->salesOrders()
            ->whereIn('status', [
                SalesOrderStatus::Draft->value,
                SalesOrderStatus::PendingApproval->value,
                SalesOrderStatus::Approved->value,
            ])
            ->exists();

        if ($hasActiveSo) {
            throw new DomainException('لا يمكن إلغاء أمر شراء مرتبط بأوامر بيع نشطة (مسودة/بانتظار/معتمد).');
        }

        return DB::transaction(function () use ($po, $actor): CustomerPurchaseOrder {
            $po->fill([
                'status' => CustomerPurchaseOrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->id,
            ])->save();

            $this->audit->record('customer_po.cancelled', $po, [
                'status' => $po->status->value,
            ], $actor);

            return $po->fresh(['customer', 'deal', 'lines']);
        });
    }
}
