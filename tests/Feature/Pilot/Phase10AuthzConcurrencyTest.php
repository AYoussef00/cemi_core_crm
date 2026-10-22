<?php

namespace Tests\Feature\Pilot;

use App\Mail\InvoiceOverdueMail;
use App\Models\Collection;
use App\Models\InventoryLedgerEntry;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductStockBalance;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Authorization\RoleSlug;
use App\Support\Finance\OverdueInvoiceReminder;
use App\Support\Inventory\InventoryLedgerEventType;
use App\Support\Inventory\InventoryService;
use App\Support\Warehouse\DeliveryWorkflow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsCommercialLifecycle;
use Tests\Concerns\SeedsAuthorization;
use Tests\Support\Inventory\FakeInventoryDocument;
use Tests\TestCase;

/**
 * Phase 10 Prompt 1 — scenarios 9–11: Mail::fake honesty, authz deny matrix, concurrency.
 */
class Phase10AuthzConcurrencyTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;
    use BuildsCommercialLifecycle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
        $this->bootCommercialLifecycle();
    }

    /** Scenario 9 — email remains Mail::fake / no live SMTP claim. */
    public function test_scenario_9_overdue_mail_uses_mail_fake_only(): void
    {
        Mail::fake();
        $quotation = $this->createApprovedQuotation('1', '10');
        $order = $this->createPoAndApprovedSalesOrder($quotation, '1');
        $invoice = $this->issueInvoice($this->confirmDelivery($order, '1'));
        $invoice->update(['due_at' => now()->subDay()]);

        app(OverdueInvoiceReminder::class)->sendOne($invoice->fresh());
        Mail::assertSent(InvoiceOverdueMail::class);

        $this->actingAs($this->accounts)
            ->post(route('invoices.resend-overdue', $invoice))
            ->assertRedirect();
        Mail::assertSent(InvoiceOverdueMail::class, 2);
    }

    /** Scenario 10 — unauthorized access across roles. */
    public function test_scenario_10_authz_deny_matrix_across_roles(): void
    {
        $quotation = $this->createApprovedQuotation('1', '10');
        $order = $this->createPoAndApprovedSalesOrder($quotation, '1');
        $delivery = $this->confirmDelivery($order, '1');
        $invoice = $this->issueInvoice($delivery);

        $otherRep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $executive = User::factory()->withRole(RoleSlug::Executive)->create();

        // Warehouse: no invoices, no prices
        $this->assertFalse($this->warehouseUser->can('invoices.view'));
        $this->assertFalse($this->warehouseUser->can('prices.view'));
        $this->actingAs($this->warehouseUser)->get(route('invoices.index'))->assertForbidden();
        $this->actingAs($this->warehouseUser)->get(route('invoices.show', $invoice))->assertForbidden();
        $this->actingAs($this->warehouseUser)->get(route('reports.ar.index'))->assertForbidden();
        $this->actingAs($this->warehouseUser)
            ->get(route('deliveries.show', $delivery))
            ->assertOk()
            ->assertSee('بلا أسعار', false);

        // Rep: own customer OK; other Rep forbidden
        $this->actingAs($this->rep)->get(route('invoices.show', $invoice))->assertOk();
        $this->actingAs($otherRep)->get(route('invoices.show', $invoice))->assertForbidden();
        $this->actingAs($this->rep)->post(route('invoices.store'), [
            'delivery_id' => $delivery->id,
        ])->assertForbidden();

        // Executive: read invoices, no issue / no deal close
        $this->actingAs($executive)->get(route('invoices.show', $invoice))->assertOk();
        $this->assertFalse($executive->can('invoices.issue'));
        $this->assertFalse($executive->can('deals.close'));

        // Accounts cannot approve credit notes / sales orders
        $this->assertFalse($this->accounts->can('credit_notes.approve'));
        $this->assertFalse($this->accounts->can('sales_orders.approve'));

        // Rep denied warehouse delivery manage
        $this->actingAs($this->rep)->get(route('deliveries.index'))->assertForbidden();
    }

    /** Scenario 11 — idempotent reserve/deduct/release + duplicate invoice/collection. */
    public function test_scenario_11_concurrency_idempotent_and_duplicate_guards(): void
    {
        if (! Schema::hasTable('fake_inventory_documents')) {
            Schema::create('fake_inventory_documents', function (Blueprint $table): void {
                $table->id();
                $table->string('label')->nullable();
                $table->timestamps();
            });
        }

        $unit = UnitOfMeasure::factory()->create();
        $product = Product::factory()->create([
            'name_ar' => 'منتج تزامن',
            'unit_id' => $unit->id,
            'purchase_cost' => '1.00',
        ]);
        ProductStockBalance::factory()->create([
            'warehouse_id' => Warehouse::current()->id,
            'product_id' => $product->id,
            'on_hand' => '50.000',
            'reserved' => '0.000',
        ]);

        $inventory = app(InventoryService::class);
        $so = FakeInventoryDocument::query()->create(['label' => 'SO-p10']);
        $del = FakeInventoryDocument::query()->create(['label' => 'DEL-p10']);

        $r1 = $inventory->reserve(Warehouse::current(), $product, 20, $this->manager, $so, 'reserve:p10:'.$so->id);
        $r2 = $inventory->reserve(Warehouse::current(), $product, 20, $this->manager, $so, 'reserve:p10:'.$so->id);
        $this->assertTrue($r1->is($r2));
        $this->assertSame(1, InventoryLedgerEntry::query()->where('event_type', InventoryLedgerEventType::Reserve)->where('idempotency_key', 'reserve:p10:'.$so->id)->count());

        $d1 = $inventory->deduct(Warehouse::current(), $product, 5, $this->manager, $del, 'deduct:p10:'.$del->id, $so);
        $d2 = $inventory->deduct(Warehouse::current(), $product, 5, $this->manager, $del, 'deduct:p10:'.$del->id, $so);
        $this->assertTrue($d1->is($d2));

        $rel1 = $inventory->release(Warehouse::current(), $product, $this->manager, $so, 'release:p10:'.$so->id);
        $rel2 = $inventory->release(Warehouse::current(), $product, $this->manager, $so, 'release:p10:'.$so->id);
        $this->assertTrue($rel1->is($rel2));

        // HTTP: duplicate invoice of same delivery blocked
        $quotation = $this->createApprovedQuotation('1', '10');
        $order = $this->createPoAndApprovedSalesOrder($quotation, '1');
        $delivery = $this->confirmDelivery($order, '1');
        $this->issueInvoice($delivery);

        $this->actingAs($this->accounts)
            ->from(route('invoices.create'))
            ->post(route('invoices.store'), ['delivery_id' => $delivery->id])
            ->assertRedirect()
            ->assertSessionHasErrors('invoice');
        $this->assertSame(1, Invoice::query()->where('delivery_id', $delivery->id)->count());

        // Double confirm delivery does not double-deduct
        $workflow = app(DeliveryWorkflow::class);
        $before = InventoryLedgerEntry::query()->where('event_type', InventoryLedgerEventType::Deduct)->count();
        $workflow->confirm($delivery->fresh(), $this->warehouseUser);
        $this->assertSame($before, InventoryLedgerEntry::query()->where('event_type', InventoryLedgerEventType::Deduct)->count());

        // Second confirmed collection cannot over-allocate beyond unpaid
        $invoice = Invoice::query()->where('delivery_id', $delivery->id)->firstOrFail();
        $this->actingAs($this->accounts)->post(route('collections.store'), [
            'customer_id' => $this->customer->id,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => '110.00']],
        ]);
        $c1 = Collection::query()->latest('id')->firstOrFail();
        $this->actingAs($this->accounts)->post(route('collections.confirm', $c1))->assertRedirect();
        $this->assertTrue($invoice->fresh()->isFullyPaid());

        $this->actingAs($this->accounts)
            ->from(route('collections.create'))
            ->post(route('collections.store'), [
                'customer_id' => $this->customer->id,
                'allocations' => [['invoice_id' => $invoice->id, 'amount' => '10.00']],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('collection');
    }
}
