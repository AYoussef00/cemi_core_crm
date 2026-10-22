<?php

namespace Tests\Feature\Procurement;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Product;
use App\Models\ProductStockBalance;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesRequest;
use App\Models\SupplierPurchaseOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Authorization\RoleSlug;
use App\Support\Customers\CustomerContactRole;
use App\Support\Procurement\SupplierPurchaseOrderStatus;
use App\Support\Quotations\QuotationStatus;
use App\Support\Sales\SalesOrderStatus;
use App\Support\SalesRequests\LineAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class BuyOnQuotationApprovalTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    private User $rep;

    private User $manager;

    private User $procurement;

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
        $this->procurement = User::factory()->withRole(RoleSlug::Procurement)->create();
        $this->customer = Customer::factory()->assignedTo($this->rep)->create([
            'name_ar' => 'منشأة شراء بعد التحويل',
        ]);
        CustomerContact::query()->create([
            'customer_id' => $this->customer->id,
            'role' => CustomerContactRole::Account,
            'name_ar' => 'جهة',
            'email' => 'buy-quote@example.test',
            'phone' => null,
        ]);
        $unit = UnitOfMeasure::factory()->create();
        $this->product = Product::factory()->create([
            'name_ar' => 'منتج توريد عرض',
            'unit_id' => $unit->id,
            'purchase_cost' => '50.00',
        ]);
    }

    public function test_quotation_approve_notifies_rep_and_does_not_create_supplier_po(): void
    {
        $this->actingAs($this->rep)->post(route('sales-requests.store'), [
            'customer_id' => $this->customer->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => '7.5',
                'availability' => LineAvailability::NeedsProcurement->value,
            ]],
        ]);
        $request = SalesRequest::query()->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-requests.submit', $request));

        $task = $request->fresh()->procurementTasks()->firstOrFail();
        $this->actingAs($this->procurement)->post(route('procurement.tasks.cost', $task), [
            'supplier_cost' => '90.00',
            'supplier_name_ar' => 'مورد العرض',
            'supplier_quote_pdf' => UploadedFile::fake()->create('q.pdf', 40, 'application/pdf'),
        ]);

        $quotation = Quotation::query()->where('sales_request_id', $request->id)->firstOrFail();
        $line = $quotation->lines()->firstOrFail();
        $this->actingAs($this->manager)->put(route('quotations.update', $quotation), [
            'lines' => [['id' => $line->id, 'margin_amount' => '15']],
        ]);

        $procNotifs = $this->procurement->fresh()->notifications()->count();
        $repNotifs = $this->rep->fresh()->notifications()->count();
        $this->actingAs($this->manager)->post(route('quotations.decide', $quotation), [
            'decision' => 'approve',
        ])->assertRedirect();

        $this->assertSame(QuotationStatus::Approved, $quotation->fresh()->status);
        $this->assertDatabaseCount('supplier_purchase_orders', 0);
        $this->assertSame($procNotifs, $this->procurement->fresh()->notifications()->count());
        $this->assertSame($repNotifs + 1, $this->rep->fresh()->notifications()->count());
    }

    public function test_convert_to_sales_order_creates_supplier_po_for_procurement_lines(): void
    {
        $this->actingAs($this->rep)->post(route('sales-requests.store'), [
            'customer_id' => $this->customer->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => '7.5',
                'availability' => LineAvailability::NeedsProcurement->value,
            ]],
        ]);
        $request = SalesRequest::query()->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-requests.submit', $request));

        $task = $request->fresh()->procurementTasks()->firstOrFail();
        $this->actingAs($this->procurement)->post(route('procurement.tasks.cost', $task), [
            'supplier_cost' => '90.00',
            'supplier_name_ar' => 'مورد العرض',
            'supplier_quote_pdf' => UploadedFile::fake()->create('q.pdf', 40, 'application/pdf'),
        ]);

        $quotation = Quotation::query()->where('sales_request_id', $request->id)->firstOrFail();
        $line = $quotation->lines()->firstOrFail();
        $this->actingAs($this->manager)->put(route('quotations.update', $quotation), [
            'lines' => [['id' => $line->id, 'margin_amount' => '15']],
        ]);
        $this->actingAs($this->manager)->post(route('quotations.decide', $quotation), [
            'decision' => 'approve',
        ]);

        $procNotifs = $this->procurement->fresh()->notifications()->count();
        $this->actingAs($this->rep)->post(route('quotations.convert.store', $quotation), [
            'customer_po_pdf' => UploadedFile::fake()->create('customer-po.pdf', 64, 'application/pdf'),
            'reference_ar' => 'PO-CONV-1',
        ])->assertRedirect();

        $order = SalesOrder::query()->latest('id')->firstOrFail();
        $spo = SupplierPurchaseOrder::query()->where('quotation_id', $quotation->id)->firstOrFail();
        $this->assertSame(SupplierPurchaseOrderStatus::Open, $spo->status);
        $this->assertSame($order->id, $spo->sales_order_id);
        $this->assertSame('مورد العرض', $spo->supplier_name_ar);
        $this->assertSame('7.500', (string) $spo->lines()->firstOrFail()->quantity);
        $this->assertSame($procNotifs + 1, $this->procurement->fresh()->notifications()->count());

        // Blocked until first warehouse receipt (SPO still open / not yet received).
        $this->actingAs($this->rep)
            ->post(route('sales-orders.submit', $order))
            ->assertRedirect()
            ->assertSessionHasErrors();
        $this->assertSame(SalesOrderStatus::Draft, $order->fresh()->status);

        $this->actingAs($this->rep)
            ->get(route('sales-orders.show', $order))
            ->assertOk()
            ->assertSee('بانتظار توريد المشتريات', false)
            ->assertDontSee('>إرسال للاعتماد<', false);

        // Idempotent second convert path is blocked by existing CPO/SO rules; re-call is covered by open SPO.
        $this->assertSame(1, SupplierPurchaseOrder::query()->where('quotation_id', $quotation->id)->count());
    }

    public function test_available_only_convert_does_not_create_supplier_po(): void
    {
        ProductStockBalance::factory()->create([
            'warehouse_id' => Warehouse::current()->id,
            'product_id' => $this->product->id,
            'on_hand' => '50.000',
            'reserved' => '0.000',
        ]);

        $this->actingAs($this->rep)->post(route('sales-requests.store'), [
            'customer_id' => $this->customer->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => '2',
            ]],
        ]);
        $request = SalesRequest::query()->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-requests.submit', $request));
        $quotation = Quotation::query()->where('sales_request_id', $request->id)->firstOrFail();
        $line = $quotation->lines()->firstOrFail();
        $this->actingAs($this->manager)->put(route('quotations.update', $quotation), [
            'lines' => [['id' => $line->id, 'margin_amount' => '10']],
        ]);
        $this->actingAs($this->manager)->post(route('quotations.decide', $quotation), [
            'decision' => 'approve',
        ])->assertRedirect();

        $this->assertDatabaseCount('supplier_purchase_orders', 0);

        $this->actingAs($this->rep)->post(route('quotations.convert.store', $quotation), [
            'customer_po_pdf' => UploadedFile::fake()->create('customer-po.pdf', 64, 'application/pdf'),
            'reference_ar' => 'PO-AVAIL',
        ])->assertRedirect();

        $this->assertDatabaseCount('supplier_purchase_orders', 0);
    }
}
