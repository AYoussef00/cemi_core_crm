<?php

namespace Tests\Feature\Finance;

use App\Mail\InvoiceOverdueMail;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerPurchaseOrder;
use App\Models\Deal;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\InvoiceNote;
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
use App\Support\Finance\CollectionStatus;
use App\Support\Finance\InvoiceNoteStatus;
use App\Support\Finance\InvoiceNoteType;
use App\Support\Finance\OverdueInvoiceReminder;
use App\Support\Sales\DealStatus;
use App\Support\Sales\SalesOrderStatus;
use App\Support\SalesRequests\LineAvailability;
use App\Support\Warehouse\DeliveryStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class InvoiceFinanceWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    private User $rep;

    private User $manager;

    private User $accounts;

    private User $warehouseUser;

    private Customer $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
        Storage::fake((string) config('attachments.disk', 'attachments'));
        Warehouse::saveSingleton(['name_ar' => 'المستودع']);

        $this->rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $this->manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $this->accounts = User::factory()->withRole(RoleSlug::Accounts)->create();
        $this->warehouseUser = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $this->customer = Customer::factory()->assignedTo($this->rep)->create([
            'name_ar' => 'منشأة مالية',
            'approved_credit_limit' => '10000.00',
        ]);
        CustomerContact::query()->create([
            'customer_id' => $this->customer->id,
            'role' => CustomerContactRole::Account,
            'name_ar' => 'جهة',
            'email' => 'finance@example.test',
            'phone' => null,
        ]);
        $unit = UnitOfMeasure::factory()->create();
        $this->product = Product::factory()->create([
            'name_ar' => 'منتج فاتورة',
            'unit_id' => $unit->id,
            'purchase_cost' => '100.00',
        ]);
        ProductStockBalance::factory()->create([
            'warehouse_id' => Warehouse::current()->id,
            'product_id' => $this->product->id,
            'on_hand' => '100.000',
            'reserved' => '0.000',
        ]);
    }

    public function test_warehouse_denied_invoices(): void
    {
        $this->actingAs($this->warehouseUser)->get(route('invoices.index'))->assertForbidden();
    }

    public function test_pf3_one_invoice_per_delivery_and_cr7_collections_notes_deal_close(): void
    {
        $delivery = $this->createConfirmedDelivery('2');
        // Quotation margin 10 → selling 110 VAT-incl; qty 2 → total 220.00
        // Invoice auto-issued on delivery confirm.

        $invoice = Invoice::query()->where('delivery_id', $delivery->id)->firstOrFail();
        $this->assertSame($delivery->id, $invoice->delivery_id);
        $this->assertSame('220.00', (string) $invoice->total_vat_inclusive);
        $this->assertSame('220.00', $invoice->unpaidBalance());

        // Manual duplicate invoice blocked
        $this->actingAs($this->accounts)
            ->from(route('invoices.create'))
            ->post(route('invoices.store'), ['delivery_id' => $delivery->id])
            ->assertRedirect()
            ->assertSessionHasErrors('invoice');

        // Draft collection does NOT reduce unpaid
        $this->actingAs($this->accounts)->post(route('collections.store'), [
            'customer_id' => $this->customer->id,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => '100.00']],
        ])->assertRedirect();
        $collection = Collection::query()->firstOrFail();
        $this->assertSame(CollectionStatus::Draft, $collection->status);
        $this->assertSame('220.00', $invoice->fresh()->unpaidBalance());

        // Confirm reduces unpaid
        $this->actingAs($this->accounts)->post(route('collections.confirm', $collection))->assertRedirect();
        $this->assertSame('120.00', $invoice->fresh()->unpaidBalance());

        // Credit note approved reduces further
        $this->actingAs($this->accounts)->post(route('invoice-notes.store'), [
            'invoice_id' => $invoice->id,
            'type' => InvoiceNoteType::Credit->value,
            'amount' => '20.00',
        ]);
        $note = InvoiceNote::query()->firstOrFail();
        $this->actingAs($this->accounts)->post(route('invoice-notes.submit', $note));
        $this->actingAs($this->manager)->post(route('invoice-notes.decide', $note), [
            'decision' => 'approve',
        ]);
        $this->assertSame(InvoiceNoteStatus::Approved, $note->fresh()->status);
        $this->assertSame('100.00', $invoice->fresh()->unpaidBalance());

        // Pay remainder
        $this->actingAs($this->accounts)->post(route('collections.store'), [
            'customer_id' => $this->customer->id,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => '100.00']],
        ]);
        $c2 = Collection::query()->latest('id')->firstOrFail();
        $this->actingAs($this->accounts)->post(route('collections.confirm', $c2));
        $this->assertTrue($invoice->fresh()->isFullyPaid());

        $deal = Deal::query()->findOrFail($invoice->deal_id);
        $this->actingAs($this->accounts)->post(route('deals.close', $deal))->assertRedirect();
        $this->assertSame(DealStatus::Closed, $deal->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deal.closed']);
    }

    public function test_deal_close_rejected_when_unpaid(): void
    {
        $delivery = $this->createConfirmedDelivery('1');
        $invoice = Invoice::query()->where('delivery_id', $delivery->id)->firstOrFail();
        $deal = Deal::query()->findOrFail($invoice->deal_id);

        $this->actingAs($this->accounts)
            ->from(route('deals.show', $deal))
            ->post(route('deals.close', $deal))
            ->assertRedirect()
            ->assertSessionHasErrors('deal');

        $this->assertSame(DealStatus::Open, $deal->fresh()->status);
    }

    public function test_credit_exposure_live_blocks_so_approve(): void
    {
        $this->createConfirmedDelivery('2'); // 220 unpaid (invoice auto-issued)
        $this->customer->update(['approved_credit_limit' => '100.00']);

        $exposure = app(CreditExposure::class);
        $this->assertSame('220.00', $exposure->forCustomer($this->customer->fresh()));
        $this->assertFalse($exposure->allowsNewOrder($this->customer->fresh()));

        // New SO pending approval should be blocked
        $order = $this->createPendingSalesOrder('1');
        $this->actingAs($this->manager)
            ->from(route('sales-orders.show', $order))
            ->post(route('sales-orders.decide', $order), ['decision' => 'approve'])
            ->assertRedirect()
            ->assertSessionHasErrors('sales_order');
        $this->assertSame(SalesOrderStatus::PendingApproval, $order->fresh()->status);
    }

    public function test_rep_cust_scope_and_overdue_mail_fake(): void
    {
        Mail::fake();
        $delivery = $this->createConfirmedDelivery('1');
        $invoice = Invoice::query()->where('delivery_id', $delivery->id)->firstOrFail();
        $invoice->update(['due_at' => now()->subDay()]);

        $this->actingAs($this->rep)->get(route('invoices.show', $invoice))->assertOk();

        $otherRep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $this->actingAs($otherRep)->get(route('invoices.show', $invoice))->assertForbidden();

        app(OverdueInvoiceReminder::class)->sendOne($invoice->fresh());
        Mail::assertSent(InvoiceOverdueMail::class);
        $this->assertNotNull($invoice->fresh()->last_overdue_reminded_at);
    }

    public function test_debit_note_increases_unpaid(): void
    {
        $delivery = $this->createConfirmedDelivery('1'); // 110
        $invoice = Invoice::query()->where('delivery_id', $delivery->id)->firstOrFail();

        $this->actingAs($this->accounts)->post(route('invoice-notes.store'), [
            'invoice_id' => $invoice->id,
            'type' => InvoiceNoteType::Debit->value,
            'amount' => '15.00',
        ]);
        $note = InvoiceNote::query()->firstOrFail();
        $this->actingAs($this->accounts)->post(route('invoice-notes.submit', $note));
        $this->actingAs($this->manager)->post(route('invoice-notes.decide', $note), ['decision' => 'approve']);

        $this->assertSame('125.00', $invoice->fresh()->unpaidBalance());
    }

    private function createConfirmedDelivery(string $qty): Delivery
    {
        $order = $this->createApprovedSalesOrder($qty);
        $line = $order->lines()->firstOrFail();
        $this->actingAs($this->warehouseUser)->post(route('deliveries.store'), [
            'sales_order_id' => $order->id,
            'lines' => [['sales_order_line_id' => $line->id, 'quantity' => $qty]],
        ]);
        $delivery = Delivery::query()->latest('id')->firstOrFail();
        $file = UploadedFile::fake()->create('pod.pdf', 40, 'application/pdf');
        $this->actingAs($this->warehouseUser)->post(route('deliveries.pod.store', $delivery), ['file' => $file]);
        $this->actingAs($this->warehouseUser)->post(route('deliveries.confirm', $delivery));
        $this->assertSame(DeliveryStatus::Confirmed, $delivery->fresh()->status);

        return $delivery->fresh();
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

        return $order->fresh(['lines']);
    }

    private function createPendingSalesOrder(string $qty): SalesOrder
    {
        // Reuse same product/customer — need new quote/PO path with available stock after prior reserve
        ProductStockBalance::query()->where('product_id', $this->product->id)->update([
            'on_hand' => '200.000',
            'reserved' => ProductStockBalance::query()->where('product_id', $this->product->id)->value('reserved'),
        ]);

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
            'lines' => [['id' => $qLine->id, 'margin_amount' => '5']],
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

        return $order->fresh();
    }
}
