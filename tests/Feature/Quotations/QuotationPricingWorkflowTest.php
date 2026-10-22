<?php

namespace Tests\Feature\Quotations;

use App\Mail\QuotationApprovedMail;
use App\Models\Attachment;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerPurchaseOrder;
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
use App\Support\Money\Money;
use App\Support\Quotations\QuotationStatus;
use App\Support\Sales\SalesOrderStatus;
use App\Support\SalesRequests\LineAvailability;
use App\Support\SalesRequests\SalesRequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class QuotationPricingWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    private User $rep;

    private User $manager;

    private Customer $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();

        $this->rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $this->manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $this->customer = Customer::factory()->assignedTo($this->rep)->create(['name_ar' => 'منشأة عرض']);

        CustomerContact::query()->create([
            'customer_id' => $this->customer->id,
            'role' => CustomerContactRole::Account,
            'name_ar' => 'جهة تجارية',
            'email' => 'buyer@example.test',
            'phone' => null,
        ]);

        $unit = UnitOfMeasure::factory()->create();
        $this->product = Product::factory()->create([
            'name_ar' => 'منتج تسعير',
            'unit_id' => $unit->id,
            'purchase_cost' => '200.00',
        ]);
    }

    public function test_guest_redirected_from_quotations(): void
    {
        $this->get(route('quotations.index'))->assertRedirect(route('login'));
    }

    public function test_warehouse_denied_quotations(): void
    {
        $warehouse = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $this->actingAs($warehouse)->get(route('quotations.index'))->assertForbidden();
    }

    public function test_pm1_sm_sets_margin_then_rep_sends(): void
    {
        Mail::fake();

        $request = $this->createReadySalesRequest(LineAvailability::Available);

        $quotation = Quotation::query()->where('sales_request_id', $request->id)->firstOrFail();
        $this->assertSame(SalesRequestStatus::QuotationLinked, $request->fresh()->status);
        $this->assertSame(QuotationStatus::Draft, $quotation->status);
        $this->assertSame('200.00', (string) $quotation->lines()->first()->supplier_cost);
        $this->assertGreaterThanOrEqual(1, $this->manager->fresh()->notifications()->count());

        // Rep cannot set margins
        $line = $quotation->lines()->firstOrFail();
        $this->actingAs($this->rep)
            ->put(route('quotations.update', $quotation), [
                'expires_at' => now()->addDays(10)->format('Y-m-d\TH:i'),
                'lines' => [['id' => $line->id, 'margin_amount' => '100']],
            ])
            ->assertForbidden();

        // Rep cannot submit-price (legacy path closed)
        $this->actingAs($this->rep)
            ->post(route('quotations.submit-price', $quotation))
            ->assertForbidden();

        // SM sets margin and finalizes from draft
        $this->actingAs($this->manager)->put(route('quotations.update', $quotation), [
            'expires_at' => now()->addDays(10)->format('Y-m-d\TH:i'),
            'lines' => [['id' => $line->id, 'margin_amount' => '100']],
        ])->assertRedirect(route('quotations.show', $quotation));

        $line->refresh();
        $this->assertSame('100.00', (string) $line->margin_amount);
        $this->assertSame('300.00', (string) $line->selling_price_vat_inclusive);
        $this->assertSame('39.13', Money::vatFromInclusive('300.00'));

        $notifyBefore = $this->rep->fresh()->notifications()->count();
        $this->actingAs($this->manager)->post(route('quotations.decide', $quotation), [
            'decision' => 'approve',
            'decision_notes' => 'موافق',
        ])->assertRedirect();
        $this->assertSame(QuotationStatus::Approved, $quotation->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation.approved']);
        $this->assertGreaterThan($notifyBefore, $this->rep->fresh()->notifications()->count());

        $this->actingAs($this->rep)
            ->post(route('quotations.send', $quotation))
            ->assertRedirect();

        Mail::assertSent(QuotationApprovedMail::class, function (QuotationApprovedMail $mail) {
            return $mail->hasTo('buyer@example.test');
        });
        $this->assertSame(QuotationStatus::Sent, $quotation->fresh()->status);
    }

    public function test_cannot_send_unapproved_quotation(): void
    {
        Mail::fake();
        $request = $this->createReadySalesRequest(LineAvailability::Available);
        $quotation = Quotation::query()->where('sales_request_id', $request->id)->firstOrFail();

        $this->actingAs($this->rep)
            ->post(route('quotations.send', $quotation))
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_missing_purchase_cost_blocks_auto_quotation_on_submit(): void
    {
        $this->product->update(['purchase_cost' => null]);
        Warehouse::saveSingleton(['name_ar' => 'المستودع الرئيسي']);
        ProductStockBalance::factory()->create([
            'warehouse_id' => Warehouse::current()->id,
            'product_id' => $this->product->id,
            'on_hand' => '100.000',
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
        $this->actingAs($this->rep)->post(route('sales-requests.submit', $request))->assertRedirect();

        // Request may be ready_for_pricing but auto-quote fails without cost.
        $this->assertDatabaseCount('quotations', 0);
        $this->assertGreaterThanOrEqual(1, $this->manager->fresh()->notifications()->count());
    }

    public function test_procurement_cost_auto_opens_quotation_for_sales_manager(): void
    {
        $this->actingAs($this->rep)->post(route('sales-requests.store'), [
            'customer_id' => $this->customer->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => '1',
                'availability' => LineAvailability::NeedsProcurement->value,
            ]],
        ]);
        $request = SalesRequest::query()->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-requests.submit', $request));
        $task = $request->fresh()->procurementTasks()->firstOrFail();
        $procurement = User::factory()->withRole(RoleSlug::Procurement)->create();
        $this->actingAs($procurement)->post(route('procurement.tasks.cost', $task), [
            'supplier_cost' => '175.25',
            'supplier_quote_pdf' => UploadedFile::fake()->create('supplier-quote.pdf', 40, 'application/pdf'),
        ]);

        $this->assertSame(SalesRequestStatus::QuotationLinked, $request->fresh()->status);
        $quotation = Quotation::query()->where('sales_request_id', $request->id)->firstOrFail();
        $this->assertSame(QuotationStatus::Draft, $quotation->status);
        $this->assertSame('175.25', (string) $quotation->lines()->first()->supplier_cost);
        $this->assertGreaterThanOrEqual(1, $this->manager->fresh()->notifications()->count());
    }

    public function test_expired_approved_cannot_send(): void
    {
        Mail::fake();
        $request = $this->createReadySalesRequest(LineAvailability::Available);
        $quotation = Quotation::query()->where('sales_request_id', $request->id)->firstOrFail();
        $line = $quotation->lines()->firstOrFail();

        $this->actingAs($this->manager)->put(route('quotations.update', $quotation), [
            'expires_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'lines' => [['id' => $line->id, 'margin_amount' => '10']],
        ]);
        $this->actingAs($this->manager)->post(route('quotations.decide', $quotation), ['decision' => 'approve']);

        $quotation->update(['expires_at' => now()->subDay()]);

        $this->actingAs($this->rep)
            ->from(route('quotations.show', $quotation))
            ->post(route('quotations.send', $quotation))
            ->assertRedirect()
            ->assertSessionHasErrors('send');

        Mail::assertNothingSent();
        $this->assertSame(QuotationStatus::Expired, $quotation->fresh()->status);
    }

    public function test_supersede_preserves_history(): void
    {
        $request = $this->createReadySalesRequest(LineAvailability::Available);
        $quotation = Quotation::query()->where('sales_request_id', $request->id)->firstOrFail();
        $line = $quotation->lines()->firstOrFail();
        $this->actingAs($this->manager)->put(route('quotations.update', $quotation), [
            'lines' => [['id' => $line->id, 'margin_amount' => '5']],
        ]);
        $this->actingAs($this->manager)->post(route('quotations.decide', $quotation), ['decision' => 'approve']);

        $this->actingAs($this->manager)
            ->post(route('quotations.supersede', $quotation))
            ->assertRedirect();

        $this->assertSame(QuotationStatus::Superseded, $quotation->fresh()->status);
        $new = Quotation::query()->where('supersedes_quotation_id', $quotation->id)->firstOrFail();
        $this->assertSame(QuotationStatus::Draft, $new->status);
        $this->assertSame(2, $new->version);
        $this->assertSame(2, Quotation::query()->count());
    }

    public function test_rounding_half_up_to_two_decimals(): void
    {
        $this->assertSame('10.13', Money::round2(10.125));
        $this->assertSame('10.12', Money::round2(10.124));
        $this->assertSame('250.56', Money::sellingPriceVatInclusive('200.555', '50'));
    }

    public function test_money_display_uses_separators_without_trailing_zeros(): void
    {
        $this->assertSame('2,600,000', Money::display('2600000.00'));
        $this->assertSame('1,300', Money::display(1300));
        $this->assertSame('2,600', Money::display(2600));
        $this->assertSame('175.25', Money::display('175.25'));
        $this->assertSame('339,130.43', Money::display('339130.43'));
        $this->assertSame('0', Money::display(0));
        $this->assertSame('—', Money::display(null));
        $this->assertSame('2,000', Money::displayQuantity('2000.000'));
        $this->assertSame('2,000.5', Money::displayQuantity('2000.500'));
    }

    public function test_convert_approved_quotation_to_sales_order_requires_customer_po_pdf(): void
    {
        Storage::fake((string) config('attachments.disk', 'attachments'));

        $request = $this->createReadySalesRequest(LineAvailability::Available);
        $quotation = Quotation::query()->where('sales_request_id', $request->id)->firstOrFail();
        $line = $quotation->lines()->firstOrFail();

        $this->actingAs($this->rep)
            ->get(route('quotations.convert', $quotation))
            ->assertForbidden();

        $this->actingAs($this->manager)->put(route('quotations.update', $quotation), [
            'lines' => [['id' => $line->id, 'margin_amount' => '25']],
        ]);
        $this->actingAs($this->manager)->post(route('quotations.decide', $quotation), [
            'decision' => 'approve',
        ]);

        $this->actingAs($this->rep)
            ->get(route('quotations.index'))
            ->assertOk()
            ->assertSee('تحويل إلى أمر بيع', false)
            ->assertSee('quote-convert-modal', false);

        $this->actingAs($this->rep)
            ->get(route('quotations.convert', $quotation))
            ->assertRedirect(route('quotations.index', ['convert' => $quotation->id]));

        $this->actingAs($this->rep)
            ->get(route('quotations.index', ['convert' => $quotation->id]))
            ->assertOk()
            ->assertSee('ارفع', false);

        $this->actingAs($this->rep)
            ->post(route('quotations.convert.store', $quotation), [
                'reference_ar' => 'PO-100',
            ])
            ->assertRedirect(route('quotations.index', ['convert' => $quotation->id]))
            ->assertSessionHasErrors('customer_po_pdf');

        $pdf = UploadedFile::fake()->create('customer-po.pdf', 64, 'application/pdf');

        $response = $this->actingAs($this->rep)
            ->post(route('quotations.convert.store', $quotation), [
                'customer_po_pdf' => $pdf,
                'reference_ar' => 'PO-100',
            ]);

        $order = SalesOrder::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('sales-orders.show', $order));
        $this->assertSame(SalesOrderStatus::Draft, $order->status);

        $po = CustomerPurchaseOrder::query()->where('quotation_id', $quotation->id)->firstOrFail();
        $this->assertSame('PO-100', $po->reference_ar);
        $this->assertSame(1, Attachment::query()
            ->where('attachable_type', 'customer_purchase_order')
            ->where('attachable_id', $po->id)
            ->count());

        $this->actingAs($this->rep)
            ->get(route('customer-pos.index'))
            ->assertOk()
            ->assertSee(route('customer-pos.pdf', $po), false);

        $pdfResponse = $this->actingAs($this->rep)
            ->get(route('customer-pos.pdf', $po));

        $pdfResponse->assertOk();
        $this->assertStringContainsString(
            'application/pdf',
            (string) $pdfResponse->headers->get('Content-Type'),
        );
        $this->assertStringContainsString(
            'inline',
            strtolower((string) $pdfResponse->headers->get('Content-Disposition')),
        );
    }

    public function test_offer_pdf_available_only_after_sm_approval(): void
    {
        $request = $this->createReadySalesRequest(LineAvailability::Available);
        $quotation = Quotation::query()->where('sales_request_id', $request->id)->firstOrFail();

        $this->actingAs($this->rep)
            ->get(route('quotations.print', $quotation))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->get(route('quotations.index'))
            ->assertOk()
            ->assertDontSee('عرض عرض السعر PDF', false);

        $line = $quotation->lines()->firstOrFail();
        $this->actingAs($this->manager)->put(route('quotations.update', $quotation), [
            'lines' => [['id' => $line->id, 'margin_amount' => '10']],
        ]);
        $this->actingAs($this->manager)->post(route('quotations.decide', $quotation), [
            'decision' => 'approve',
        ]);

        $pdf = $this->actingAs($this->rep)
            ->get(route('quotations.print', $quotation))
            ->assertOk();

        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('inline; filename="quotation-'.$quotation->id.'.pdf"', (string) $pdf->headers->get('content-disposition'));

        $this->actingAs($this->rep)
            ->get(route('quotations.index'))
            ->assertOk()
            ->assertSee('عرض عرض السعر PDF', false);
    }

    private function createReadySalesRequest(LineAvailability $availability): SalesRequest
    {
        if ($availability === LineAvailability::Available) {
            Warehouse::saveSingleton(['name_ar' => 'المستودع الرئيسي']);
            ProductStockBalance::factory()->create([
                'warehouse_id' => Warehouse::current()->id,
                'product_id' => $this->product->id,
                'on_hand' => '100.000',
                'reserved' => '0.000',
            ]);
        }

        $this->actingAs($this->rep)->post(route('sales-requests.store'), [
            'customer_id' => $this->customer->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => '2',
            ]],
        ]);
        $request = SalesRequest::query()->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-requests.submit', $request));

        return $request->fresh();
    }
}
