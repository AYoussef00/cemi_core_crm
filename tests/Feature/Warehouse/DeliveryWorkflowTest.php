<?php

namespace Tests\Feature\Warehouse;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerPurchaseOrder;
use App\Models\Delivery;
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
use App\Support\Customers\CustomerContactRole;
use App\Support\Inventory\InventoryLedgerEventType;
use App\Support\Inventory\InventoryService;
use App\Support\Sales\SalesOrderStatus;
use App\Support\SalesRequests\LineAvailability;
use App\Support\Warehouse\DeliveryStatus;
use App\Support\Warehouse\DeliveryWorkflow;
use App\Support\Warehouse\FulfillmentStatus;
use App\Support\Warehouse\PreparationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class DeliveryWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    private User $rep;

    private User $manager;

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
        $this->warehouseUser = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $this->customer = Customer::factory()->assignedTo($this->rep)->create(['name_ar' => 'منشأة تسليم']);

        CustomerContact::query()->create([
            'customer_id' => $this->customer->id,
            'role' => CustomerContactRole::Account,
            'name_ar' => 'جهة',
            'email' => 'delivery@example.test',
            'phone' => null,
        ]);

        $unit = UnitOfMeasure::factory()->create();
        $this->product = Product::factory()->create([
            'name_ar' => 'منتج تسليم',
            'unit_id' => $unit->id,
            'purchase_cost' => '50.00',
        ]);

        ProductStockBalance::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'on_hand' => '100.000',
            'reserved' => '0.000',
        ]);
    }

    public function test_guest_redirected_from_deliveries_and_preparation(): void
    {
        $this->get(route('deliveries.index'))->assertRedirect(route('login'));
        $this->get(route('warehouse.preparation.index'))->assertRedirect(route('login'));
    }

    public function test_rep_denied_delivery_and_preparation(): void
    {
        $this->actingAs($this->rep)->get(route('deliveries.index'))->assertForbidden();
        $this->actingAs($this->rep)->get(route('warehouse.preparation.index'))->assertForbidden();
    }

    public function test_preparation_ready_and_multi_delivery_with_pod_deduct(): void
    {
        $order = $this->createApprovedSalesOrder('10');
        $this->assertSame('10.000', (string) ProductStockBalance::query()
            ->where('product_id', $this->product->id)->value('reserved'));

        $this->actingAs($this->warehouseUser)
            ->post(route('warehouse.preparation.ready', $order))
            ->assertRedirect();
        $this->assertSame(PreparationStatus::Ready, $order->fresh()->preparation_status);

        $line = $order->lines()->firstOrFail();

        // First partial delivery: 4
        $this->actingAs($this->warehouseUser)->post(route('deliveries.store'), [
            'sales_order_id' => $order->id,
            'lines' => [['sales_order_line_id' => $line->id, 'quantity' => '4']],
        ])->assertRedirect();
        $d1 = Delivery::query()->firstOrFail();
        $this->uploadPod($d1);
        $this->actingAs($this->warehouseUser)->post(route('deliveries.confirm', $d1))->assertRedirect();

        $this->assertSame(DeliveryStatus::Confirmed, $d1->fresh()->status);
        $this->assertDatabaseHas('invoices', ['delivery_id' => $d1->id]);
        $this->assertSame(FulfillmentStatus::PartiallyDelivered, $order->fresh()->fulfillment_status);
        $balance = ProductStockBalance::query()->where('product_id', $this->product->id)->firstOrFail();
        $this->assertSame('96.000', (string) $balance->on_hand);
        $this->assertSame('6.000', (string) $balance->reserved);

        // Second delivery: remaining 6
        $this->actingAs($this->warehouseUser)->post(route('deliveries.store'), [
            'sales_order_id' => $order->id,
            'lines' => [['sales_order_line_id' => $line->id, 'quantity' => '6']],
        ])->assertRedirect();
        $d2 = Delivery::query()->latest('id')->firstOrFail();
        $this->uploadPod($d2);
        $this->actingAs($this->warehouseUser)->post(route('deliveries.confirm', $d2))->assertRedirect();

        $this->assertSame(FulfillmentStatus::FullyDelivered, $order->fresh()->fulfillment_status);
        $balance->refresh();
        $this->assertSame('90.000', (string) $balance->on_hand);
        $this->assertSame('0.000', (string) $balance->reserved);
        $this->assertSame(2, InventoryLedgerEntry::query()
            ->where('event_type', InventoryLedgerEventType::Deduct)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'delivery.confirmed']);
    }

    public function test_pod_required_before_confirm(): void
    {
        $order = $this->createApprovedSalesOrder('5');
        $line = $order->lines()->firstOrFail();
        $this->actingAs($this->warehouseUser)->post(route('deliveries.store'), [
            'sales_order_id' => $order->id,
            'lines' => [['sales_order_line_id' => $line->id, 'quantity' => '2']],
        ]);
        $delivery = Delivery::query()->firstOrFail();

        $this->actingAs($this->warehouseUser)
            ->from(route('deliveries.show', $delivery))
            ->post(route('deliveries.confirm', $delivery))
            ->assertRedirect()
            ->assertSessionHasErrors('delivery');

        $this->assertSame(DeliveryStatus::Draft, $delivery->fresh()->status);
        $this->assertSame(0, InventoryLedgerEntry::query()
            ->where('event_type', InventoryLedgerEventType::Deduct)->count());
    }

    public function test_cancel_before_confirm_does_not_deduct(): void
    {
        $order = $this->createApprovedSalesOrder('5');
        $line = $order->lines()->firstOrFail();
        $this->actingAs($this->warehouseUser)->post(route('deliveries.store'), [
            'sales_order_id' => $order->id,
            'lines' => [['sales_order_line_id' => $line->id, 'quantity' => '3']],
        ]);
        $delivery = Delivery::query()->firstOrFail();
        $this->uploadPod($delivery);

        $this->actingAs($this->warehouseUser)
            ->post(route('deliveries.cancel', $delivery), ['cancel_notes_ar' => 'تأجيل'])
            ->assertRedirect();

        $this->assertSame(DeliveryStatus::Cancelled, $delivery->fresh()->status);
        $this->assertSame(0, InventoryLedgerEntry::query()
            ->where('event_type', InventoryLedgerEventType::Deduct)->count());
        $this->assertSame('5.000', (string) ProductStockBalance::query()
            ->where('product_id', $this->product->id)->value('reserved'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'delivery.cancelled']);
    }

    public function test_delivery_qty_cannot_exceed_undelivered(): void
    {
        $order = $this->createApprovedSalesOrder('5');
        $line = $order->lines()->firstOrFail();

        $this->actingAs($this->warehouseUser)->post(route('deliveries.store'), [
            'sales_order_id' => $order->id,
            'lines' => [['sales_order_line_id' => $line->id, 'quantity' => '4']],
        ]);

        $this->actingAs($this->warehouseUser)
            ->from(route('deliveries.create', ['sales_order_id' => $order->id]))
            ->post(route('deliveries.store'), [
                'sales_order_id' => $order->id,
                'lines' => [['sales_order_line_id' => $line->id, 'quantity' => '2']],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('delivery');

        $this->assertSame(1, Delivery::query()->count());
    }

    public function test_confirm_idempotent_deduct_once(): void
    {
        $order = $this->createApprovedSalesOrder('3');
        $line = $order->lines()->firstOrFail();
        $this->actingAs($this->warehouseUser)->post(route('deliveries.store'), [
            'sales_order_id' => $order->id,
            'lines' => [['sales_order_line_id' => $line->id, 'quantity' => '3']],
        ]);
        $delivery = Delivery::query()->firstOrFail();
        $this->uploadPod($delivery);

        $workflow = app(DeliveryWorkflow::class);
        $workflow->confirm($delivery, $this->warehouseUser);
        $workflow->confirm($delivery->fresh(), $this->warehouseUser);

        $this->assertSame(1, InventoryLedgerEntry::query()
            ->where('event_type', InventoryLedgerEventType::Deduct)
            ->where('reference_type', $delivery->getMorphClass())
            ->where('reference_id', $delivery->id)
            ->count());
    }

    public function test_warehouse_show_has_no_prices_copy(): void
    {
        $order = $this->createApprovedSalesOrder('1');
        $line = $order->lines()->firstOrFail();
        $this->actingAs($this->warehouseUser)->post(route('deliveries.store'), [
            'sales_order_id' => $order->id,
            'lines' => [['sales_order_line_id' => $line->id, 'quantity' => '1']],
        ]);
        $delivery = Delivery::query()->firstOrFail();

        $this->assertFalse($this->warehouseUser->can('prices.view'));
        $this->actingAs($this->warehouseUser)
            ->get(route('deliveries.show', $delivery))
            ->assertOk()
            ->assertSee('بلا أسعار', false);
    }

    public function test_sm_can_cancel_open_delivery(): void
    {
        $order = $this->createApprovedSalesOrder('2');
        $line = $order->lines()->firstOrFail();
        $this->actingAs($this->warehouseUser)->post(route('deliveries.store'), [
            'sales_order_id' => $order->id,
            'lines' => [['sales_order_line_id' => $line->id, 'quantity' => '1']],
        ]);
        $delivery = Delivery::query()->firstOrFail();

        $this->actingAs($this->manager)
            ->post(route('deliveries.cancel', $delivery))
            ->assertRedirect();
        $this->assertSame(DeliveryStatus::Cancelled, $delivery->fresh()->status);
    }

    public function test_remaining_reservation_blocks_oversized_confirm(): void
    {
        $order = $this->createApprovedSalesOrder('5');
        $line = $order->lines()->firstOrFail();

        // Artificially release some reservation to simulate inconsistency edge
        $inventory = app(InventoryService::class);
        $inventory->release(
            $this->warehouse,
            $this->product,
            $this->manager,
            $order,
            'release:test:shrink:'.$order->id,
            '3',
        );

        $this->actingAs($this->warehouseUser)->post(route('deliveries.store'), [
            'sales_order_id' => $order->id,
            'lines' => [['sales_order_line_id' => $line->id, 'quantity' => '4']],
        ]);
        // Create path may allow based on undelivered SO qty; confirm must fail on reservation.
        $delivery = Delivery::query()->firstOrFail();
        $this->uploadPod($delivery);

        $this->actingAs($this->warehouseUser)
            ->from(route('deliveries.show', $delivery))
            ->post(route('deliveries.confirm', $delivery))
            ->assertRedirect()
            ->assertSessionHasErrors('delivery');

        $this->assertSame(DeliveryStatus::Draft, $delivery->fresh()->status);
    }

    public function test_accounts_denied_delivery_view(): void
    {
        $accounts = User::factory()->withRole(RoleSlug::Accounts)->create();
        $this->actingAs($accounts)->get(route('deliveries.index'))->assertForbidden();
    }

    private function uploadPod(Delivery $delivery): void
    {
        $file = UploadedFile::fake()->create('pod.pdf', 100, 'application/pdf');
        $this->actingAs($this->warehouseUser)
            ->post(route('deliveries.pod.store', $delivery), ['file' => $file])
            ->assertRedirect();
        $this->assertTrue($delivery->fresh()->hasPodAttachment());
    }

    private function createApprovedSalesOrder(string $qty): SalesOrder
    {
        $this->actingAs($this->rep)->post(route('sales-requests.store'), [
            'customer_id' => $this->customer->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => $qty,
                'availability' => LineAvailability::Available->value,
            ]],
        ]);
        $request = SalesRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-requests.submit', $request));
        $quotation = Quotation::query()->where('sales_request_id', $request->id)->latest('id')->firstOrFail();
        $qLine = $quotation->lines()->firstOrFail();
        $this->actingAs($this->manager)->put(route('quotations.update', $quotation), [
            'lines' => [['id' => $qLine->id, 'margin_amount' => '10']],
        ]);
        $this->actingAs($this->manager)->post(route('quotations.decide', $quotation), ['decision' => 'approve']);

        $this->actingAs($this->rep)->post(route('customer-pos.store'), [
            'customer_id' => $this->customer->id,
            'quotation_id' => $quotation->id,
            'lines' => [['quotation_line_id' => $qLine->id, 'quantity' => $qty]],
        ]);
        $po = CustomerPurchaseOrder::query()->latest('id')->firstOrFail();
        $poLine = $po->lines()->firstOrFail();

        $this->actingAs($this->rep)->post(route('sales-orders.store'), [
            'customer_purchase_order_id' => $po->id,
            'lines' => [['customer_purchase_order_line_id' => $poLine->id, 'quantity' => $qty]],
        ]);
        $order = SalesOrder::query()->latest('id')->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-orders.submit', $order));
        $this->actingAs($this->manager)->post(route('sales-orders.decide', $order), ['decision' => 'approve']);

        $this->assertSame(SalesOrderStatus::Approved, $order->fresh()->status);

        return $order->fresh(['lines']);
    }
}
