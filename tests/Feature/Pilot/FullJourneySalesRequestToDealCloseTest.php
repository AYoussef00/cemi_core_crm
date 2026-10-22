<?php

namespace Tests\Feature\Pilot;

use App\Models\Collection;
use App\Models\Deal;
use App\Models\ProductStockBalance;
use App\Models\SalesOrder;
use App\Models\SupplierGoodsReceipt;
use App\Models\SupplierPurchaseOrder;
use App\Support\Procurement\SupplierPurchaseOrderStatus;
use App\Support\Sales\DealStatus;
use App\Support\Sales\SalesOrderStatus;
use App\Support\SalesRequests\LineAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCommercialLifecycle;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

/**
 * رحلة كاملة من طلب البيع حتى إغلاق الصفقة —
 * تشمل نقص المخزون + أمر مورد + استلام على دفعتين ثم التسليم والفاتورة والتحصيل.
 */
class FullJourneySalesRequestToDealCloseTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;
    use BuildsCommercialLifecycle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
        // Low stock so SO approve creates supplier PO for shortfall.
        $this->bootCommercialLifecycle(onHand: '2.000');
    }

    public function test_full_journey_from_sales_request_through_supplier_shipments_to_deal_close(): void
    {
        // 1–3 — طلب بيع متوفر → عرض سعر معتمد → أمر شراء عميل → أمر بيع
        $quotation = $this->createApprovedQuotation('10', '10');
        $qLine = $quotation->lines()->firstOrFail();

        $this->actingAs($this->rep)->post(route('customer-pos.store'), [
            'customer_id' => $this->customer->id,
            'quotation_id' => $quotation->id,
            'lines' => [['quotation_line_id' => $qLine->id, 'quantity' => '10']],
        ])->assertRedirect();

        $cpo = \App\Models\CustomerPurchaseOrder::query()->latest('id')->firstOrFail();
        $cpoLine = $cpo->lines()->firstOrFail();

        $this->actingAs($this->rep)->post(route('sales-orders.store'), [
            'customer_purchase_order_id' => $cpo->id,
            'lines' => [['customer_purchase_order_line_id' => $cpoLine->id, 'quantity' => '10']],
        ])->assertRedirect();

        $order = SalesOrder::query()->latest('id')->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-orders.submit', $order))->assertRedirect();

        // 4 — اعتماد يفشل لنقص المخزون → أمر شراء مورد (shortfall 8)
        $this->actingAs($this->manager)
            ->from(route('sales-orders.show', $order))
            ->post(route('sales-orders.decide', $order), ['decision' => 'approve'])
            ->assertRedirect()
            ->assertSessionHasErrors('sales_order');

        $this->assertSame(SalesOrderStatus::PendingApproval, $order->fresh()->status);
        $spo = SupplierPurchaseOrder::query()->where('sales_order_id', $order->id)->firstOrFail();
        $this->assertSame('8.000', (string) $spo->lines()->firstOrFail()->quantity);

        // 5 — المشتريات تصدر PO وتُرفق PDF
        $this->actingAs($this->procurement)->post(route('supplier-pos.mark-ordered', $spo), [
            'expected_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'supplier_name_ar' => 'مورد الرحلة',
        ])->assertRedirect();
        $this->assertSame(SupplierPurchaseOrderStatus::Ordered, $spo->fresh()->status);
        $this->assertGreaterThanOrEqual(1, $spo->attachments()->count());

        $spoLineId = $spo->lines()->firstOrFail()->id;

        // 6 — المستودع: شحنتان (أوامر استلام منفصلة)
        $this->actingAs($this->warehouseUser)->post(route('supplier-pos.receive', $spo), [
            'shipment_reference_ar' => 'شحنة-أ',
            'receipts' => [['line_id' => $spoLineId, 'quantity' => '5']],
        ])->assertRedirect();
        $this->assertSame(SupplierPurchaseOrderStatus::PartiallyReceived, $spo->fresh()->status);

        $this->actingAs($this->warehouseUser)->post(route('supplier-pos.receive', $spo), [
            'shipment_reference_ar' => 'شحنة-ب',
            'receipts' => [['line_id' => $spoLineId, 'quantity' => '3']],
        ])->assertRedirect();
        $this->assertSame(SupplierPurchaseOrderStatus::Received, $spo->fresh()->status);
        $this->assertSame(2, SupplierGoodsReceipt::query()->where('supplier_purchase_order_id', $spo->id)->count());
        $this->assertSame('10.000', (string) ProductStockBalance::query()
            ->where('product_id', $this->product->id)->value('on_hand'));

        // 7 — إعادة اعتماد أمر البيع (حجز المخزون)
        $this->actingAs($this->manager)->post(route('sales-orders.decide', $order), [
            'decision' => 'approve',
        ])->assertRedirect();
        $this->assertSame(SalesOrderStatus::Approved, $order->fresh()->status);
        $this->assertSame('10.000', (string) ProductStockBalance::query()
            ->where('product_id', $this->product->id)->value('reserved'));

        // 8–10 — تسليم → فاتورة → تحصيل → إغلاق صفقة
        $delivery = $this->confirmDelivery($order->fresh(), '10');
        $invoice = $this->issueInvoice($delivery);
        // 10 × (100 cost + 10 margin) = 1100
        $this->assertSame('1100.00', (string) $invoice->total_vat_inclusive);

        $this->actingAs($this->accounts)->post(route('collections.store'), [
            'customer_id' => $this->customer->id,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => '1100.00']],
        ])->assertRedirect();
        $collection = Collection::query()->latest('id')->firstOrFail();
        $this->actingAs($this->accounts)->post(route('collections.confirm', $collection))->assertRedirect();
        $this->assertTrue($invoice->fresh()->isFullyPaid());

        $deal = Deal::query()->findOrFail($invoice->deal_id);
        $this->actingAs($this->accounts)->post(route('deals.close', $deal))->assertRedirect();
        $this->assertSame(DealStatus::Closed, $deal->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deal.closed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplier_po.ordered']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplier_goods_receipt.created']);
    }

    public function test_available_stock_happy_path_still_works_without_supplier_po(): void
    {
        ProductStockBalance::query()
            ->where('product_id', $this->product->id)
            ->update(['on_hand' => '50.000', 'reserved' => '0.000']);

        $quotation = $this->createApprovedQuotation('3', '10');
        $order = $this->createPoAndApprovedSalesOrder($quotation, '3');
        $this->assertDatabaseCount('supplier_purchase_orders', 0);

        $delivery = $this->confirmDelivery($order, '3');
        $invoice = $this->issueInvoice($delivery);
        $this->assertSame('330.00', (string) $invoice->total_vat_inclusive);

        $this->actingAs($this->accounts)->post(route('collections.store'), [
            'customer_id' => $this->customer->id,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => '330.00']],
        ])->assertRedirect();
        $collection = Collection::query()->latest('id')->firstOrFail();
        $this->actingAs($this->accounts)->post(route('collections.confirm', $collection))->assertRedirect();

        $deal = Deal::query()->findOrFail($invoice->deal_id);
        $this->actingAs($this->accounts)->post(route('deals.close', $deal))->assertRedirect();
        $this->assertSame(DealStatus::Closed, $deal->fresh()->status);
    }
}
