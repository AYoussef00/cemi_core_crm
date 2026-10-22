<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerPurchaseOrder;
use App\Models\Deal;
use App\Models\InventoryLedgerEntry;
use App\Models\Product;
use App\Models\ProductStockBalance;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesRequest;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Authorization\RoleSlug;
use App\Support\Credit\CreditExposure;
use App\Support\Customers\CustomerContactRole;
use App\Support\Inventory\InventoryLedgerEventType;
use App\Support\Quotations\QuotationStatus;
use App\Support\Sales\CustomerPurchaseOrderStatus;
use App\Support\Sales\DealStatus;
use App\Support\Sales\SalesOrderStatus;
use App\Support\Sales\SalesOrderWorkflow;
use App\Support\SalesRequests\LineAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class CustomerPoDealSalesOrderTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    private User $rep;

    private User $manager;

    private Customer $customer;

    private Product $product;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();

        Warehouse::saveSingleton(['name_ar' => 'المستودع الرئيسي']);
        $this->warehouse = Warehouse::current();

        $this->rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $this->manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $this->customer = Customer::factory()->assignedTo($this->rep)->create(['name_ar' => 'منشأة أوامر']);

        CustomerContact::query()->create([
            'customer_id' => $this->customer->id,
            'role' => CustomerContactRole::Account,
            'name_ar' => 'جهة',
            'email' => 'buyer-po@example.test',
            'phone' => null,
        ]);

        $unit = UnitOfMeasure::factory()->create();
        $this->product = Product::factory()->create([
            'name_ar' => 'منتج أمر بيع',
            'unit_id' => $unit->id,
            'purchase_cost' => '100.00',
        ]);

        ProductStockBalance::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'on_hand' => '50.000',
            'reserved' => '0.000',
        ]);
    }

    public function test_guest_redirected_from_customer_pos_and_sales_orders(): void
    {
        $this->get(route('customer-pos.index'))->assertRedirect(route('login'));
        $this->get(route('sales-orders.index'))->assertRedirect(route('login'));
    }

    public function test_pf7_cannot_create_sales_order_without_po(): void
    {
        $this->actingAs($this->rep)
            ->post(route('sales-orders.store'), [
                'notes_ar' => 'بلا أمر شراء',
                'lines' => [],
            ])
            ->assertSessionHasErrors('customer_purchase_order_id');

        $this->assertDatabaseCount('sales_orders', 0);
    }

    public function test_po_creates_deal_one_to_one_and_so_approve_reserves(): void
    {
        $quotation = $this->createApprovedQuotation(quantity: '10');
        $qLine = $quotation->lines()->firstOrFail();

        $this->actingAs($this->rep)->post(route('customer-pos.store'), [
            'customer_id' => $this->customer->id,
            'quotation_id' => $quotation->id,
            'reference_ar' => 'PO-عميل-1',
            'lines' => [[
                'quotation_line_id' => $qLine->id,
                'quantity' => '10',
            ]],
        ])->assertRedirect();

        $po = CustomerPurchaseOrder::query()->firstOrFail();
        $this->assertSame(CustomerPurchaseOrderStatus::Open, $po->status);
        $deal = Deal::query()->where('customer_purchase_order_id', $po->id)->firstOrFail();
        $this->assertSame(DealStatus::Open, $deal->status);
        $this->assertSame(1, Deal::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer_po.created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deal.created']);

        $poLine = $po->lines()->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-orders.store'), [
            'customer_purchase_order_id' => $po->id,
            'lines' => [[
                'customer_purchase_order_line_id' => $poLine->id,
                'quantity' => '4',
            ]],
        ])->assertRedirect();

        $order = SalesOrder::query()->firstOrFail();
        $this->assertSame($po->id, $order->customer_purchase_order_id);
        $this->assertSame($deal->id, $order->deal_id);
        $this->assertSame(SalesOrderStatus::Draft, $order->status);

        $warehouseUser = User::factory()->withRole(RoleSlug::Warehouse)->create();

        $managerNotifsBefore = $this->manager->fresh()->notifications()->count();
        $this->actingAs($this->rep)->post(route('sales-orders.submit', $order))->assertRedirect();
        $this->assertSame(SalesOrderStatus::PendingApproval, $order->fresh()->status);
        $this->assertSame($managerNotifsBefore + 1, $this->manager->fresh()->notifications()->count());

        $warehouseBefore = $warehouseUser->fresh()->notifications()->count();
        $repBefore = $this->rep->fresh()->notifications()->count();
        $this->actingAs($this->manager)->post(route('sales-orders.decide', $order), [
            'decision' => 'approve',
        ])->assertRedirect();

        $this->assertSame(SalesOrderStatus::Approved, $order->fresh()->status);
        $balance = ProductStockBalance::query()
            ->where('product_id', $this->product->id)
            ->firstOrFail();
        $this->assertSame('50.000', (string) $balance->on_hand);
        $this->assertSame('4.000', (string) $balance->reserved);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sales_order.approved']);
        $this->assertSame($warehouseBefore + 1, $warehouseUser->fresh()->notifications()->count());
        $this->assertSame($repBefore + 1, $this->rep->fresh()->notifications()->count());
    }

    public function test_org3_unique_deal_per_customer_po(): void
    {
        $quotation = $this->createApprovedQuotation();
        $qLine = $quotation->lines()->firstOrFail();
        $this->actingAs($this->rep)->post(route('customer-pos.store'), [
            'customer_id' => $this->customer->id,
            'quotation_id' => $quotation->id,
            'lines' => [['quotation_line_id' => $qLine->id, 'quantity' => '2']],
        ]);
        $po = CustomerPurchaseOrder::query()->firstOrFail();

        $this->expectException(\Illuminate\Database\QueryException::class);
        Deal::query()->create([
            'customer_id' => $this->customer->id,
            'customer_purchase_order_id' => $po->id,
            'status' => DealStatus::Open,
        ]);
    }

    public function test_rep_own_scope_cannot_view_other_rep_sales_order(): void
    {
        $otherRep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $otherCustomer = Customer::factory()->assignedTo($otherRep)->create();
        $quotation = $this->createApprovedQuotationFor($otherRep, $otherCustomer);
        $po = $this->createPoFromQuotation($otherRep, $quotation);
        $order = $this->createSubmittedOrder($otherRep, $po, '1');

        $this->actingAs($this->rep)
            ->get(route('sales-orders.show', $order))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->get(route('sales-orders.show', $order))
            ->assertOk();
    }

    public function test_inv3_insufficient_stock_rejects_approve_and_creates_supplier_po(): void
    {
        ProductStockBalance::query()
            ->where('product_id', $this->product->id)
            ->update(['on_hand' => '2.000', 'reserved' => '0.000']);

        $quotation = $this->createApprovedQuotation(quantity: '5');
        $po = $this->createPoFromQuotation($this->rep, $quotation, '5');
        $order = $this->createSubmittedOrder($this->rep, $po, '5');

        $this->actingAs($this->manager)
            ->from(route('sales-orders.show', $order))
            ->post(route('sales-orders.decide', $order), ['decision' => 'approve'])
            ->assertRedirect()
            ->assertSessionHasErrors('sales_order');

        $this->assertSame(SalesOrderStatus::PendingApproval, $order->fresh()->status);
        $this->assertSame('0.000', (string) ProductStockBalance::query()
            ->where('product_id', $this->product->id)->value('reserved'));

        $spo = \App\Models\SupplierPurchaseOrder::query()->where('sales_order_id', $order->id)->firstOrFail();
        $this->assertSame('3.000', (string) $spo->lines()->firstOrFail()->quantity);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplier_po.created']);
    }

    public function test_double_approve_is_idempotent_for_reservation(): void
    {
        $quotation = $this->createApprovedQuotation(quantity: '3');
        $po = $this->createPoFromQuotation($this->rep, $quotation, '3');
        $order = $this->createSubmittedOrder($this->rep, $po, '3');

        $workflow = app(SalesOrderWorkflow::class);
        $workflow->approve($order, $this->manager);
        $workflow->approve($order->fresh(), $this->manager);

        $this->assertSame(1, InventoryLedgerEntry::query()
            ->where('event_type', InventoryLedgerEventType::Reserve)
            ->where('reference_type', $order->getMorphClass())
            ->where('reference_id', $order->id)
            ->count());
        $this->assertSame('3.000', (string) ProductStockBalance::query()
            ->where('product_id', $this->product->id)->value('reserved'));
    }

    public function test_reject_does_not_reserve(): void
    {
        $quotation = $this->createApprovedQuotation(quantity: '2');
        $po = $this->createPoFromQuotation($this->rep, $quotation, '2');
        $order = $this->createSubmittedOrder($this->rep, $po, '2');

        $repBefore = $this->rep->fresh()->notifications()->count();
        $this->actingAs($this->manager)->post(route('sales-orders.decide', $order), [
            'decision' => 'reject',
            'decision_notes' => 'غير مناسب',
        ])->assertRedirect();

        $this->assertSame(SalesOrderStatus::Rejected, $order->fresh()->status);
        $this->assertSame(0, InventoryLedgerEntry::query()
            ->where('event_type', InventoryLedgerEventType::Reserve)->count());
        $this->assertSame($repBefore + 1, $this->rep->fresh()->notifications()->count());
    }

    public function test_cancel_approved_releases_once(): void
    {
        $quotation = $this->createApprovedQuotation(quantity: '6');
        $po = $this->createPoFromQuotation($this->rep, $quotation, '6');
        $order = $this->createSubmittedOrder($this->rep, $po, '6');

        $this->actingAs($this->manager)->post(route('sales-orders.decide', $order), [
            'decision' => 'approve',
        ]);
        $this->assertSame('6.000', (string) ProductStockBalance::query()
            ->where('product_id', $this->product->id)->value('reserved'));

        $this->actingAs($this->manager)->post(route('sales-orders.cancel-approved', $order))->assertRedirect();
        $this->assertSame(SalesOrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame('0.000', (string) ProductStockBalance::query()
            ->where('product_id', $this->product->id)->value('reserved'));
        $this->assertSame(1, InventoryLedgerEntry::query()
            ->where('event_type', InventoryLedgerEventType::Release)->count());

        // Second cancel is idempotent at workflow level
        app(SalesOrderWorkflow::class)->cancelApproved($order->fresh(), $this->manager);
        $this->assertSame(1, InventoryLedgerEntry::query()
            ->where('event_type', InventoryLedgerEventType::Release)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'sales_order.cancelled']);
    }

    public function test_rep_cannot_cancel_approved_or_approve(): void
    {
        $quotation = $this->createApprovedQuotation(quantity: '2');
        $po = $this->createPoFromQuotation($this->rep, $quotation, '2');
        $order = $this->createSubmittedOrder($this->rep, $po, '2');

        $this->actingAs($this->rep)
            ->post(route('sales-orders.decide', $order), ['decision' => 'approve'])
            ->assertForbidden();

        $this->actingAs($this->manager)->post(route('sales-orders.decide', $order), [
            'decision' => 'approve',
        ]);

        $this->actingAs($this->rep)
            ->post(route('sales-orders.cancel-approved', $order))
            ->assertForbidden();
    }

    public function test_warehouse_can_view_all_sales_orders_without_prices_permission(): void
    {
        $quotation = $this->createApprovedQuotation(quantity: '1');
        $po = $this->createPoFromQuotation($this->rep, $quotation, '1');
        $order = $this->createSubmittedOrder($this->rep, $po, '1');

        $warehouse = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $this->assertFalse($warehouse->can('prices.view'));

        $this->actingAs($warehouse)
            ->get(route('sales-orders.show', $order))
            ->assertOk()
            ->assertSee('بلا أسعار', false);
    }

    public function test_credit_exposure_zero_without_invoices_and_blocks_when_limit_zero(): void
    {
        $exposure = app(CreditExposure::class);
        $this->assertSame('0.00', $exposure->forCustomer($this->customer));
        $this->assertTrue($exposure->allowsNewOrder($this->customer));

        $this->customer->update(['approved_credit_limit' => '0.00']);
        $this->assertFalse($exposure->allowsNewOrder($this->customer->fresh()));

        $quotation = $this->createApprovedQuotation(quantity: '1');
        $po = $this->createPoFromQuotation($this->rep, $quotation, '1');
        $order = $this->createSubmittedOrder($this->rep, $po, '1');

        $this->actingAs($this->manager)
            ->from(route('sales-orders.show', $order))
            ->post(route('sales-orders.decide', $order), ['decision' => 'approve'])
            ->assertRedirect()
            ->assertSessionHasErrors('sales_order');

        $this->assertSame(SalesOrderStatus::PendingApproval, $order->fresh()->status);
    }

    public function test_po_qty_cannot_exceed_quotation_line(): void
    {
        $quotation = $this->createApprovedQuotation(quantity: '3');
        $qLine = $quotation->lines()->firstOrFail();

        $this->actingAs($this->rep)
            ->from(route('customer-pos.create'))
            ->post(route('customer-pos.store'), [
                'customer_id' => $this->customer->id,
                'quotation_id' => $quotation->id,
                'lines' => [['quotation_line_id' => $qLine->id, 'quantity' => '5']],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('customer_po');

        $this->assertDatabaseCount('customer_purchase_orders', 0);
    }

    public function test_so_qty_cannot_exceed_po_unallocated(): void
    {
        $quotation = $this->createApprovedQuotation(quantity: '5');
        $po = $this->createPoFromQuotation($this->rep, $quotation, '5');
        $poLine = $po->lines()->firstOrFail();

        $this->actingAs($this->rep)->post(route('sales-orders.store'), [
            'customer_purchase_order_id' => $po->id,
            'lines' => [['customer_purchase_order_line_id' => $poLine->id, 'quantity' => '4']],
        ]);

        $this->actingAs($this->rep)
            ->from(route('sales-orders.create', ['customer_purchase_order_id' => $po->id]))
            ->post(route('sales-orders.store'), [
                'customer_purchase_order_id' => $po->id,
                'lines' => [['customer_purchase_order_line_id' => $poLine->id, 'quantity' => '2']],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('sales_order');

        $this->assertSame(1, SalesOrder::query()->count());
    }

    private function createApprovedQuotation(string $quantity = '2'): Quotation
    {
        return $this->createApprovedQuotationFor($this->rep, $this->customer, $quantity);
    }

    private function createApprovedQuotationFor(User $rep, Customer $customer, string $quantity = '2'): Quotation
    {
        $this->actingAs($rep)->post(route('sales-requests.store'), [
            'customer_id' => $customer->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => $quantity,
                'availability' => LineAvailability::Available->value,
            ]],
        ]);
        $request = SalesRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($rep)->post(route('sales-requests.submit', $request));
        $quotation = Quotation::query()->where('sales_request_id', $request->id)->latest('id')->firstOrFail();
        $line = $quotation->lines()->firstOrFail();
        $this->actingAs($this->manager)->put(route('quotations.update', $quotation), [
            'lines' => [['id' => $line->id, 'margin_amount' => '10']],
        ]);
        $this->actingAs($this->manager)->post(route('quotations.decide', $quotation), [
            'decision' => 'approve',
        ]);

        $this->assertSame(QuotationStatus::Approved, $quotation->fresh()->status);

        return $quotation->fresh(['lines']);
    }

    private function createPoFromQuotation(User $actor, Quotation $quotation, ?string $qty = null): CustomerPurchaseOrder
    {
        $qLine = $quotation->lines()->firstOrFail();
        $this->actingAs($actor)->post(route('customer-pos.store'), [
            'customer_id' => $quotation->customer_id,
            'quotation_id' => $quotation->id,
            'lines' => [[
                'quotation_line_id' => $qLine->id,
                'quantity' => $qty ?? (string) $qLine->quantity,
            ]],
        ]);

        return CustomerPurchaseOrder::query()->latest('id')->firstOrFail();
    }

    private function createSubmittedOrder(User $actor, CustomerPurchaseOrder $po, string $qty): SalesOrder
    {
        $poLine = $po->lines()->firstOrFail();
        $this->actingAs($actor)->post(route('sales-orders.store'), [
            'customer_purchase_order_id' => $po->id,
            'lines' => [[
                'customer_purchase_order_line_id' => $poLine->id,
                'quantity' => $qty,
            ]],
        ]);
        $order = SalesOrder::query()->latest('id')->firstOrFail();
        $this->actingAs($actor)->post(route('sales-orders.submit', $order));

        return $order->fresh();
    }
}
