<?php

namespace Tests\Feature\Procurement;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerPurchaseOrder;
use App\Models\InventoryLedgerEntry;
use App\Models\Product;
use App\Models\ProductStockBalance;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesRequest;
use App\Models\SupplierGoodsReceipt;
use App\Models\SupplierPurchaseOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Authorization\RoleSlug;
use App\Support\Customers\CustomerContactRole;
use App\Support\Inventory\InventoryLedgerEventType;
use App\Support\Procurement\SupplierPurchaseOrderStatus;
use App\Support\Quotations\QuotationStatus;
use App\Support\Sales\SalesOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class SupplierPurchaseOrderFlowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    private User $rep;

    private User $manager;

    private User $procurement;

    private User $warehouseUser;

    private Customer $customer;

    private Product $product;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
        Storage::fake((string) config('attachments.disk', 'attachments'));

        Warehouse::saveSingleton(['name_ar' => 'المستودع الرئيسي']);
        $this->warehouse = Warehouse::current();

        $this->rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $this->manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $this->procurement = User::factory()->withRole(RoleSlug::Procurement)->create();
        $this->warehouseUser = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $this->customer = Customer::factory()->assignedTo($this->rep)->create(['name_ar' => 'منشأة توريد']);

        CustomerContact::query()->create([
            'customer_id' => $this->customer->id,
            'role' => CustomerContactRole::Account,
            'name_ar' => 'جهة',
            'email' => 'buyer-spo@example.test',
            'phone' => null,
        ]);

        $unit = UnitOfMeasure::factory()->create();
        $this->product = Product::factory()->create([
            'name_ar' => 'منتج توريد',
            'unit_id' => $unit->id,
            'purchase_cost' => '80.00',
        ]);

        ProductStockBalance::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'on_hand' => '1.000',
            'reserved' => '0.000',
        ]);
    }

    public function test_guest_redirected_from_supplier_pos(): void
    {
        $this->get(route('supplier-pos.index'))->assertRedirect(route('login'));
    }

    public function test_multi_shipment_receipts_then_approve(): void
    {
        $order = $this->createPendingOrderNeedingStock(needQty: '5');

        $procBefore = $this->procurement->fresh()->notifications()->count();
        $this->actingAs($this->manager)
            ->from(route('sales-orders.show', $order))
            ->post(route('sales-orders.decide', $order), ['decision' => 'approve'])
            ->assertRedirect()
            ->assertSessionHasErrors('sales_order');

        $this->assertSame(SalesOrderStatus::PendingApproval, $order->fresh()->status);
        $this->assertSame($procBefore + 1, $this->procurement->fresh()->notifications()->count());

        $spo = SupplierPurchaseOrder::query()->where('sales_order_id', $order->id)->firstOrFail();
        $this->assertSame(SupplierPurchaseOrderStatus::Open, $spo->status);
        $this->assertSame('4.000', (string) $spo->lines()->firstOrFail()->quantity);

        $poLineIdEarly = $spo->lines()->firstOrFail()->id;
        $this->actingAs($this->warehouseUser)->post(route('supplier-pos.receive', $spo), [
            'receipts' => [['line_id' => $poLineIdEarly, 'quantity' => '1']],
        ])->assertForbidden();

        $whBefore = $this->warehouseUser->fresh()->notifications()->count();
        $this->actingAs($this->procurement)->post(route('supplier-pos.mark-ordered', $spo), [
            'expected_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'supplier_name_ar' => 'مورد تجريبي',
        ])->assertRedirect(route('supplier-pos.show', $spo));

        $spo->refresh();
        $this->assertSame(SupplierPurchaseOrderStatus::Ordered, $spo->status);
        $this->assertSame('مورد تجريبي', $spo->supplier_name_ar);
        $this->assertSame($whBefore + 1, $this->warehouseUser->fresh()->notifications()->count());
        $this->assertGreaterThanOrEqual(1, $spo->attachments()->count());

        $poLineId = $spo->lines()->firstOrFail()->id;

        // Shipment 1 — partial (signed delivery note required)
        $this->actingAs($this->warehouseUser)->post(route('supplier-pos.receive', $spo), [
            'shipment_reference_ar' => 'شحنة-1',
            'delivery_note_pdf' => UploadedFile::fake()->create('delivery-1.pdf', 40, 'application/pdf'),
            'receipts' => [['line_id' => $poLineId, 'quantity' => '1.5']],
        ])->assertRedirect();

        $receipt1 = SupplierGoodsReceipt::query()->latest('id')->firstOrFail();
        $this->assertSame('شحنة-1', $receipt1->shipment_reference_ar);
        $this->assertSame(1, $receipt1->attachments()->count());
        $this->assertSame(SupplierPurchaseOrderStatus::PartiallyReceived, $spo->fresh()->status);
        $this->assertSame('2.500', (string) ProductStockBalance::query()
            ->where('product_id', $this->product->id)->value('on_hand'));

        // PROC-PARTIAL-SHIP: first receipt auto-activates SO (no SM decide).
        $this->assertSame(SalesOrderStatus::Approved, $order->fresh()->status);
        $this->assertSame('2.500', (string) ProductStockBalance::query()
            ->where('product_id', $this->product->id)->value('reserved'));

        $this->actingAs($this->warehouseUser)
            ->get(route('supplier-goods-receipts.show', $receipt1))
            ->assertOk()
            ->assertSee('أمر استلام شحنة', false)
            ->assertSee('مستند التسليم الموقع', false);

        $this->actingAs($this->warehouseUser)
            ->get(route('supplier-goods-receipts.pdf', $receipt1))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // Shipment 2 — remainder tops up reservation on the approved SO
        $this->actingAs($this->warehouseUser)->post(route('supplier-pos.receive', $spo), [
            'shipment_reference_ar' => 'شحنة-2',
            'delivery_note_pdf' => UploadedFile::fake()->create('delivery-2.pdf', 40, 'application/pdf'),
            'receipts' => [['line_id' => $poLineId, 'quantity' => '2.5']],
        ])->assertRedirect();

        $this->assertSame(2, SupplierGoodsReceipt::query()->where('supplier_purchase_order_id', $spo->id)->count());
        $this->assertSame(SupplierPurchaseOrderStatus::Received, $spo->fresh()->status);
        $this->assertSame('5.000', (string) ProductStockBalance::query()
            ->where('product_id', $this->product->id)->value('on_hand'));

        $receipt2 = SupplierGoodsReceipt::query()->latest('id')->firstOrFail();
        $this->assertSame(2, InventoryLedgerEntry::query()
            ->where('event_type', InventoryLedgerEventType::Receipt)
            ->count());
        $this->assertSame(1, InventoryLedgerEntry::query()
            ->where('event_type', InventoryLedgerEventType::Receipt)
            ->where('reference_type', $receipt2->getMorphClass())
            ->where('reference_id', $receipt2->id)
            ->count());

        $this->assertSame('5.000', (string) ProductStockBalance::query()
            ->where('product_id', $this->product->id)->value('reserved'));
    }

    public function test_sufficient_stock_does_not_create_spo(): void
    {
        $order = $this->createPendingOrderNeedingStock(needQty: '3');

        ProductStockBalance::query()
            ->where('product_id', $this->product->id)
            ->update(['on_hand' => '10.000', 'reserved' => '0.000']);

        $this->actingAs($this->manager)->post(route('sales-orders.decide', $order), [
            'decision' => 'approve',
        ])->assertRedirect();

        $this->assertSame(SalesOrderStatus::Approved, $order->fresh()->status);
        $this->assertDatabaseCount('supplier_purchase_orders', 0);
    }

    public function test_rep_cannot_receive_or_mark_ordered(): void
    {
        $order = $this->createPendingOrderNeedingStock(needQty: '3');
        $this->actingAs($this->manager)->post(route('sales-orders.decide', $order), ['decision' => 'approve']);
        $spo = SupplierPurchaseOrder::query()->firstOrFail();

        $this->actingAs($this->rep)
            ->post(route('supplier-pos.mark-ordered', $spo), [
                'expected_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ])
            ->assertForbidden();

        $this->actingAs($this->rep)
            ->post(route('supplier-pos.receive', $spo), [
                'receipts' => [['line_id' => $spo->lines()->firstOrFail()->id, 'quantity' => '1']],
            ])
            ->assertForbidden();
    }

    private function createPendingOrderNeedingStock(string $needQty): SalesOrder
    {
        // Temporarily enough stock so SR-AUTO-STOCK keeps the line available through quote/SO create.
        ProductStockBalance::query()->where('product_id', $this->product->id)->update([
            'on_hand' => '1000.000',
            'reserved' => '0.000',
        ]);

        $this->actingAs($this->rep)->post(route('sales-requests.store'), [
            'customer_id' => $this->customer->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => $needQty,
            ]],
        ]);
        $request = SalesRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-requests.submit', $request));
        $quotation = Quotation::query()->where('sales_request_id', $request->id)->latest('id')->firstOrFail();
        $line = $quotation->lines()->firstOrFail();
        $this->actingAs($this->manager)->put(route('quotations.update', $quotation), [
            'lines' => [['id' => $line->id, 'margin_amount' => '10']],
        ]);
        $this->actingAs($this->manager)->post(route('quotations.decide', $quotation), [
            'decision' => 'approve',
        ]);
        $this->assertSame(QuotationStatus::Approved, $quotation->fresh()->status);

        $qLine = $quotation->lines()->firstOrFail();
        $this->actingAs($this->rep)->post(route('customer-pos.store'), [
            'customer_id' => $this->customer->id,
            'quotation_id' => $quotation->id,
            'lines' => [['quotation_line_id' => $qLine->id, 'quantity' => $needQty]],
        ]);
        $po = CustomerPurchaseOrder::query()->latest('id')->firstOrFail();
        $poLine = $po->lines()->firstOrFail();

        $this->actingAs($this->rep)->post(route('sales-orders.store'), [
            'customer_purchase_order_id' => $po->id,
            'lines' => [['customer_purchase_order_line_id' => $poLine->id, 'quantity' => $needQty]],
        ]);
        $order = SalesOrder::query()->latest('id')->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-orders.submit', $order));

        // Restore shortfall for SO approve → buy-on-SO path.
        ProductStockBalance::query()->where('product_id', $this->product->id)->update([
            'on_hand' => '1.000',
            'reserved' => '0.000',
        ]);

        return $order->fresh();
    }
}
