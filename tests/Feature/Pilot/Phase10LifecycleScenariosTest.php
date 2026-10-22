<?php

namespace Tests\Feature\Pilot;

use App\Models\Collection;
use App\Models\CustomerPurchaseOrder;
use App\Models\Deal;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\InvoiceNote;
use App\Models\ProcurementTask;
use App\Models\ProductStockBalance;
use App\Models\SalesOrder;
use App\Models\SalesRequest;
use App\Support\Credit\CreditExposure;
use App\Support\Finance\CollectionStatus;
use App\Support\Finance\InvoiceNoteStatus;
use App\Support\Finance\InvoiceNoteType;
use App\Support\Procurement\ProcurementTaskStatus;
use App\Support\Quotations\QuotationStatus;
use App\Support\Reports\ArReportQuery;
use App\Support\Sales\DealStatus;
use App\Support\Sales\SalesOrderStatus;
use App\Support\SalesRequests\SalesRequestStatus;
use App\Support\Warehouse\FulfillmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsCommercialLifecycle;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

/**
 * Phase 10 Prompt 1 — lifecycle scenarios 1–8, 12–13 (PHASE_ACCEPTANCE).
 */
class Phase10LifecycleScenariosTest extends TestCase
{
    use BuildsCommercialLifecycle;
    use RefreshDatabase;
    use SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
        $this->bootCommercialLifecycle();
    }

    /** Scenario 1 — full happy path including visit. */
    public function test_scenario_1_full_happy_path_customer_visit_to_deal_close(): void
    {
        $this->scheduleAndCompleteVisit();

        $quotation = $this->createApprovedQuotation('2', '10');
        $order = $this->createPoAndApprovedSalesOrder($quotation, '2');
        $delivery = $this->confirmDelivery($order, '2');
        $invoice = $this->issueInvoice($delivery);

        // 2 × 110 = 220
        $this->assertSame('220.00', (string) $invoice->total_vat_inclusive);
        $this->assertSame('220.00', $invoice->unpaidBalance());

        $this->actingAs($this->accounts)->post(route('collections.store'), [
            'customer_id' => $this->customer->id,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => '220.00']],
        ])->assertRedirect();
        $collection = Collection::query()->latest('id')->firstOrFail();
        $this->actingAs($this->accounts)->post(route('collections.confirm', $collection))->assertRedirect();

        $this->assertTrue($invoice->fresh()->isFullyPaid());
        $deal = Deal::query()->findOrFail($invoice->deal_id);
        $this->actingAs($this->accounts)->post(route('deals.close', $deal))->assertRedirect();
        $this->assertSame(DealStatus::Closed, $deal->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deal.closed']);
    }

    /** Scenario 2 — unavailable product / procurement path. */
    public function test_scenario_2_needs_procurement_then_ready_for_pricing(): void
    {
        ProductStockBalance::query()
            ->where('product_id', $this->product->id)
            ->update(['on_hand' => '0.000', 'reserved' => '0.000']);

        $this->actingAs($this->rep)->post(route('sales-requests.store'), [
            'customer_id' => $this->customer->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => '1',
            ]],
        ])->assertRedirect();

        $request = SalesRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-requests.submit', $request))->assertRedirect();
        $this->assertSame(SalesRequestStatus::AwaitingProcurement, $request->fresh()->status);

        $task = ProcurementTask::query()->firstOrFail();
        $this->actingAs($this->procurement)->post(route('procurement.tasks.cost', $task), [
            'supplier_cost' => '95.00',
            'supplier_name_ar' => 'مورد تجريبي',
            'supplier_quote_pdf' => UploadedFile::fake()->create('supplier-quote.pdf', 40, 'application/pdf'),
        ])->assertRedirect();

        $this->assertSame(ProcurementTaskStatus::CostRecorded, $task->fresh()->status);
        $this->assertSame(SalesRequestStatus::QuotationLinked, $request->fresh()->status);
        $this->assertSame('95.00', (string) $request->lines()->first()->supplier_cost);
        $this->assertDatabaseHas('quotations', ['sales_request_id' => $request->id]);
    }

    /** Scenario 3 — quotation expires without acceptance/send. */
    public function test_scenario_3_expired_quotation_cannot_send(): void
    {
        Mail::fake();
        $quotation = $this->createApprovedQuotation('1', '10');
        $quotation->update(['expires_at' => now()->subDay()]);

        $this->actingAs($this->rep)
            ->from(route('quotations.show', $quotation))
            ->post(route('quotations.send', $quotation))
            ->assertRedirect()
            ->assertSessionHasErrors('send');

        Mail::assertNothingSent();
        $this->assertSame(QuotationStatus::Expired, $quotation->fresh()->status);
    }

    /** Scenario 4 — one PO, multiple SOs and multi-delivery. */
    public function test_scenario_4_one_po_multi_so_and_multi_delivery(): void
    {
        $quotation = $this->createApprovedQuotation('10', '10');
        $qLine = $quotation->lines()->firstOrFail();

        $this->actingAs($this->rep)->post(route('customer-pos.store'), [
            'customer_id' => $this->customer->id,
            'quotation_id' => $quotation->id,
            'lines' => [['quotation_line_id' => $qLine->id, 'quantity' => '10']],
        ])->assertRedirect();
        $po = CustomerPurchaseOrder::query()->latest('id')->firstOrFail();

        $so1 = $this->createSecondSalesOrderFromPo($po, '4');
        $so2 = $this->createSecondSalesOrderFromPo($po, '6');
        $this->assertSame($so1->deal_id, $so2->deal_id);
        $this->assertSame(2, SalesOrder::query()->where('customer_purchase_order_id', $po->id)->count());

        $d1 = $this->confirmDelivery($so1, '2');
        $d2 = $this->confirmDelivery($so1, '2');
        $this->assertSame(2, Delivery::query()->where('sales_order_id', $so1->id)->count());
        $this->assertSame(FulfillmentStatus::FullyDelivered, $so1->fresh()->fulfillment_status);
        $this->assertNotSame($d1->id, $d2->id);
        $this->confirmDelivery($so2, '6');
        $this->assertSame(FulfillmentStatus::FullyDelivered, $so2->fresh()->fulfillment_status);
    }

    /** Scenario 5 — partial delivery; invoice delivered qty only. */
    public function test_scenario_5_partial_delivery_invoice_qty_only(): void
    {
        $quotation = $this->createApprovedQuotation('10', '10');
        $order = $this->createPoAndApprovedSalesOrder($quotation, '10');
        $delivery = $this->confirmDelivery($order, '3');
        $invoice = $this->issueInvoice($delivery);

        // 3 × 110 = 330 — not full SO qty
        $this->assertSame('330.00', (string) $invoice->total_vat_inclusive);
        $this->assertSame(1, $invoice->lines()->count());
        $this->assertSame('3.000', (string) $invoice->lines()->first()->quantity);
        $this->assertSame(FulfillmentStatus::PartiallyDelivered, $order->fresh()->fulfillment_status);
    }

    /** Scenario 6 — credit limit blocks; payment re-enables. */
    public function test_scenario_6_credit_block_then_payment_re_enables(): void
    {
        $this->customer->update(['approved_credit_limit' => '200.00']);

        $quotation = $this->createApprovedQuotation('2', '10'); // 220 unpaid after invoice
        $order = $this->createPoAndApprovedSalesOrder($quotation, '2');
        $invoice = $this->issueInvoice($this->confirmDelivery($order, '2'));
        $this->assertSame('220.00', app(CreditExposure::class)->forCustomer($this->customer->fresh()));
        $this->assertFalse(app(CreditExposure::class)->allowsNewOrder($this->customer->fresh()));

        $blockedQuote = $this->createApprovedQuotation('1', '5');
        $qLine = $blockedQuote->lines()->firstOrFail();
        $this->actingAs($this->rep)->post(route('customer-pos.store'), [
            'customer_id' => $this->customer->id,
            'quotation_id' => $blockedQuote->id,
            'lines' => [['quotation_line_id' => $qLine->id, 'quantity' => '1']],
        ]);
        $po = CustomerPurchaseOrder::query()->latest('id')->firstOrFail();
        $poLine = $po->lines()->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-orders.store'), [
            'customer_purchase_order_id' => $po->id,
            'lines' => [['customer_purchase_order_line_id' => $poLine->id, 'quantity' => '1']],
        ]);
        $pending = SalesOrder::query()->latest('id')->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-orders.submit', $pending));
        $this->actingAs($this->manager)
            ->from(route('sales-orders.show', $pending))
            ->post(route('sales-orders.decide', $pending), ['decision' => 'approve'])
            ->assertRedirect()
            ->assertSessionHasErrors('sales_order');
        $this->assertSame(SalesOrderStatus::PendingApproval, $pending->fresh()->status);

        // Pay down → exposure drops → approve allowed
        $this->actingAs($this->accounts)->post(route('collections.store'), [
            'customer_id' => $this->customer->id,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => '220.00']],
        ]);
        $c = Collection::query()->latest('id')->firstOrFail();
        $this->actingAs($this->accounts)->post(route('collections.confirm', $c));
        $this->assertSame('0.00', app(CreditExposure::class)->forCustomer($this->customer->fresh()));
        $this->assertTrue(app(CreditExposure::class)->allowsNewOrder($this->customer->fresh()));

        $this->actingAs($this->manager)->post(route('sales-orders.decide', $pending), [
            'decision' => 'approve',
        ])->assertRedirect();
        $this->assertSame(SalesOrderStatus::Approved, $pending->fresh()->status);
    }

    /** Scenario 7 — partial collection then full; deal close gated. */
    public function test_scenario_7_partial_then_full_collection_and_deal_close_gate(): void
    {
        $quotation = $this->createApprovedQuotation('1', '10'); // 110
        $order = $this->createPoAndApprovedSalesOrder($quotation, '1');
        $invoice = $this->issueInvoice($this->confirmDelivery($order, '1'));
        $deal = Deal::query()->findOrFail($invoice->deal_id);

        $this->actingAs($this->accounts)->post(route('collections.store'), [
            'customer_id' => $this->customer->id,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => '40.00']],
        ]);
        $partial = Collection::query()->latest('id')->firstOrFail();
        $this->actingAs($this->accounts)->post(route('collections.confirm', $partial));
        $this->assertSame('70.00', $invoice->fresh()->unpaidBalance());

        $this->actingAs($this->accounts)
            ->from(route('deals.show', $deal))
            ->post(route('deals.close', $deal))
            ->assertRedirect()
            ->assertSessionHasErrors('deal');
        $this->assertSame(DealStatus::Open, $deal->fresh()->status);

        $this->actingAs($this->accounts)->post(route('collections.store'), [
            'customer_id' => $this->customer->id,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => '70.00']],
        ]);
        $rest = Collection::query()->latest('id')->firstOrFail();
        $this->actingAs($this->accounts)->post(route('collections.confirm', $rest));
        $this->actingAs($this->accounts)->post(route('deals.close', $deal))->assertRedirect();
        $this->assertSame(DealStatus::Closed, $deal->fresh()->status);
    }

    /** Scenario 8 — delivery without GPS fields (v1). */
    public function test_scenario_8_delivery_has_no_gps_columns_or_routes(): void
    {
        $this->assertFalse(Schema::hasColumn('deliveries', 'gps_lat'));
        $this->assertFalse(Schema::hasColumn('deliveries', 'latitude'));
        $this->assertFalse(Schema::hasColumn('deliveries', 'driver_gps'));
        $this->assertFalse(Route::has('deliveries.gps'));

        $quotation = $this->createApprovedQuotation('1', '10');
        $order = $this->createPoAndApprovedSalesOrder($quotation, '1');
        $delivery = $this->confirmDelivery($order, '1');
        $this->assertNotNull($delivery->confirmed_at);
    }

    /** Scenario 12 — AR report unpaid reconciles to CR7. */
    public function test_scenario_12_ar_report_reconciles_cr7_unpaid(): void
    {
        $quotation = $this->createApprovedQuotation('2', '10'); // 220
        $order = $this->createPoAndApprovedSalesOrder($quotation, '2');
        $invoice = $this->issueInvoice($this->confirmDelivery($order, '2'));

        $this->actingAs($this->accounts)->post(route('collections.store'), [
            'customer_id' => $this->customer->id,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => '50.00']],
        ]);
        $draft = Collection::query()->latest('id')->firstOrFail();
        $this->assertSame(CollectionStatus::Draft, $draft->status);
        // Draft must not change CR7
        $this->assertSame('220.00', $invoice->fresh()->unpaidBalance());

        $this->actingAs($this->accounts)->post(route('collections.confirm', $draft));
        $this->assertSame('170.00', $invoice->fresh()->unpaidBalance());

        $summary = app(ArReportQuery::class)->summaryFor($this->accounts);
        $this->assertSame('170.00', $summary['unpaid_total']);
        $this->assertSame(1, $summary['unpaid_count']);

        $this->actingAs($this->accounts)->get(route('reports.ar.index'))
            ->assertOk()
            ->assertSee('170.00', false)
            ->assertSee($this->customer->name_ar, false);

        $statement = app(ArReportQuery::class)->statementFor($this->accounts, $this->customer);
        $this->assertSame('170.00', $statement['unpaid_total']);

        $this->actingAs($this->accounts)
            ->get(route('reports.statements.show', $this->customer))
            ->assertOk()
            ->assertSee('170.00', false);
    }

    /** Scenario 13 — credit/debit notes; issued total immutable. */
    public function test_scenario_13_notes_preserve_issued_invoice_total(): void
    {
        $quotation = $this->createApprovedQuotation('1', '10'); // 110
        $order = $this->createPoAndApprovedSalesOrder($quotation, '1');
        $invoice = $this->issueInvoice($this->confirmDelivery($order, '1'));
        $issued = (string) $invoice->total_vat_inclusive;

        $this->actingAs($this->accounts)->post(route('invoice-notes.store'), [
            'invoice_id' => $invoice->id,
            'type' => InvoiceNoteType::Credit->value,
            'amount' => '10.00',
        ]);
        $credit = InvoiceNote::query()->latest('id')->firstOrFail();
        $this->actingAs($this->accounts)->post(route('invoice-notes.submit', $credit));
        $this->actingAs($this->manager)->post(route('invoice-notes.decide', $credit), [
            'decision' => 'approve',
        ]);

        $this->actingAs($this->accounts)->post(route('invoice-notes.store'), [
            'invoice_id' => $invoice->id,
            'type' => InvoiceNoteType::Debit->value,
            'amount' => '5.00',
        ]);
        $debit = InvoiceNote::query()->latest('id')->firstOrFail();
        $this->actingAs($this->accounts)->post(route('invoice-notes.submit', $debit));
        $this->actingAs($this->manager)->post(route('invoice-notes.decide', $debit), [
            'decision' => 'approve',
        ]);

        $invoice->refresh();
        $this->assertSame($issued, (string) $invoice->total_vat_inclusive);
        $this->assertSame(InvoiceNoteStatus::Approved, $credit->fresh()->status);
        $this->assertSame(InvoiceNoteStatus::Approved, $debit->fresh()->status);
        // 110 − 10 + 5 = 105
        $this->assertSame('105.00', $invoice->unpaidBalance());
    }
}
