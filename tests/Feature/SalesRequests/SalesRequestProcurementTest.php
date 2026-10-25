<?php

namespace Tests\Feature\SalesRequests;

use App\Models\Customer;
use App\Models\ProcurementTask;
use App\Models\Product;
use App\Models\ProductStockBalance;
use App\Models\Quotation;
use App\Models\SalesRequest;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Authorization\RoleSlug;
use App\Support\Procurement\ProcurementTaskStatus;
use App\Support\Quotations\QuotationStatus;
use App\Support\SalesRequests\LineAvailability;
use App\Support\SalesRequests\SalesRequestStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class SalesRequestProcurementTest extends TestCase
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

        $this->rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $this->manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $this->procurement = User::factory()->withRole(RoleSlug::Procurement)->create();
        $this->customer = Customer::factory()->assignedTo($this->rep)->create(['name_ar' => 'منشأة طلب بيع']);
        $unit = UnitOfMeasure::factory()->create();
        $this->product = Product::factory()->create([
            'name_ar' => 'منتج طلب',
            'unit_id' => $unit->id,
            'purchase_cost' => '50.00',
        ]);
    }

    public function test_guest_redirected_from_sales_requests_and_procurement(): void
    {
        $this->get(route('sales-requests.index'))->assertRedirect(route('login'));
        $this->get(route('procurement.tasks.index'))->assertRedirect(route('login'));
    }

    public function test_index_search_matches_any_letter_of_customer_name(): void
    {
        $match = Customer::factory()->assignedTo($this->rep)->create(['name_ar' => 'شركة النور']);
        $other = Customer::factory()->assignedTo($this->rep)->create(['name_ar' => 'مؤسسة الفجر']);

        SalesRequest::factory()->create([
            'customer_id' => $match->id,
            'created_by_user_id' => $this->rep->id,
        ]);
        SalesRequest::factory()->create([
            'customer_id' => $other->id,
            'created_by_user_id' => $this->rep->id,
        ]);

        $this->actingAs($this->rep)
            ->get(route('sales-requests.index', ['q' => 'ن']))
            ->assertOk()
            ->assertSee('شركة النور', false)
            ->assertDontSee('مؤسسة الفجر', false);

        $this->actingAs($this->rep)
            ->get(route('sales-requests.index', ['q' => 'ش']))
            ->assertOk()
            ->assertSee('شركة النور', false)
            ->assertDontSee('مؤسسة الفجر', false);
    }

    public function test_rep_creates_and_submits_available_only_auto_opens_quotation(): void
    {
        $this->seedWarehouseStock('100.000');

        $this->actingAs($this->rep)->post(route('sales-requests.store'), [
            'customer_id' => $this->customer->id,
            'notes_ar' => 'طلب متوفر',
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => '2.5',
            ]],
        ])->assertRedirect();

        $request = SalesRequest::query()->firstOrFail();
        $this->assertSame(SalesRequestStatus::Draft, $request->status);

        $this->actingAs($this->rep)
            ->post(route('sales-requests.submit', $request))
            ->assertRedirect(route('sales-requests.show', $request));

        $request->refresh();
        $this->assertSame(SalesRequestStatus::QuotationLinked, $request->status);
        $this->assertDatabaseCount('procurement_tasks', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sales_request.submitted']);
        $quotation = Quotation::query()->where('sales_request_id', $request->id)->firstOrFail();
        $this->assertSame(QuotationStatus::Draft, $quotation->status);
        $this->assertGreaterThanOrEqual(1, $this->manager->fresh()->notifications()->count());
    }

    public function test_submit_needs_procurement_creates_task_and_notifies_procurement(): void
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

        $this->actingAs($this->rep)->post(route('sales-requests.submit', $request))->assertRedirect();

        $request->refresh();
        $this->assertSame(SalesRequestStatus::AwaitingProcurement, $request->status);
        $task = ProcurementTask::query()->firstOrFail();
        $this->assertSame(ProcurementTaskStatus::Open, $task->status);
        $this->assertSame(1, $this->procurement->fresh()->notifications()->count());
    }

    public function test_procurement_records_cost_auto_opens_quotation_for_sales_manager(): void
    {
        $this->actingAs($this->rep)->post(route('sales-requests.store'), [
            'customer_id' => $this->customer->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => '3',
                'availability' => LineAvailability::NeedsProcurement->value,
            ]],
        ]);
        $request = SalesRequest::query()->firstOrFail();
        $this->actingAs($this->rep)->post(route('sales-requests.submit', $request));
        $task = ProcurementTask::query()->firstOrFail();

        $this->actingAs($this->procurement)->post(route('procurement.tasks.cost', $task), [
            'supplier_cost' => '120.50',
            'supplier_name_ar' => 'مورد الاختبار',
            'supplier_quote_pdf' => UploadedFile::fake()->create('supplier-quote.pdf', 40, 'application/pdf'),
        ])->assertRedirect(route('procurement.tasks.show', $task));

        $this->assertSame(1, $task->fresh()->attachments()->count());

        $task->refresh();
        $request->refresh();
        $this->assertSame(ProcurementTaskStatus::CostRecorded, $task->status);
        $this->assertSame('120.50', (string) $task->supplier_cost);
        $this->assertSame(SalesRequestStatus::QuotationLinked, $request->status);
        $this->assertSame('120.50', (string) $request->lines()->first()->supplier_cost);
        $quotation = Quotation::query()->where('sales_request_id', $request->id)->firstOrFail();
        $this->assertSame(QuotationStatus::Draft, $quotation->status);
        $this->assertSame('120.50', (string) $quotation->lines()->first()->supplier_cost);
        $this->assertDatabaseHas('audit_logs', ['action' => 'procurement_task.cost_recorded']);
        $this->assertGreaterThanOrEqual(1, $this->rep->fresh()->notifications()->count());
        $this->assertGreaterThanOrEqual(1, $this->manager->fresh()->notifications()->count());
    }

    public function test_rep_cannot_view_other_reps_request_cust_scope(): void
    {
        $otherRep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $otherCustomer = Customer::factory()->assignedTo($otherRep)->create();

        $this->actingAs($otherRep)->post(route('sales-requests.store'), [
            'customer_id' => $otherCustomer->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => '1',
                'availability' => LineAvailability::Available->value,
            ]],
        ]);
        $request = SalesRequest::query()->firstOrFail();

        $this->actingAs($this->rep)
            ->get(route('sales-requests.show', $request))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->get(route('sales-requests.show', $request))
            ->assertOk();
    }

    public function test_warehouse_denied_sales_requests_and_procurement(): void
    {
        $warehouse = User::factory()->withRole(RoleSlug::Warehouse)->create();

        $this->actingAs($warehouse)->get(route('sales-requests.index'))->assertForbidden();
        $this->actingAs($warehouse)->get(route('procurement.tasks.index'))->assertForbidden();
    }

    public function test_accounts_work_scope_sees_empty_sales_requests(): void
    {
        $accounts = User::factory()->withRole(RoleSlug::Accounts)->create();

        $this->actingAs($this->rep)->post(route('sales-requests.store'), [
            'customer_id' => $this->customer->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => '1',
                'availability' => LineAvailability::Available->value,
            ]],
        ]);

        $this->actingAs($accounts)
            ->get(route('sales-requests.index'))
            ->assertOk()
            ->assertSee('لا توجد طلبات بيع', false);
    }

    public function test_cancel_sales_request_and_open_procurement_tasks(): void
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

        $this->actingAs($this->manager)
            ->post(route('sales-requests.cancel', $request))
            ->assertRedirect();

        $this->assertSame(SalesRequestStatus::Cancelled, $request->fresh()->status);
        $this->assertSame(ProcurementTaskStatus::Cancelled, ProcurementTask::query()->firstOrFail()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sales_request.cancelled']);
    }

    public function test_sm_can_cancel_procurement_task_warehouse_cannot_record_cost(): void
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
        $task = ProcurementTask::query()->firstOrFail();

        $warehouse = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $this->actingAs($warehouse)
            ->post(route('procurement.tasks.cost', $task), [
                'supplier_cost' => '10',
                'supplier_quote_pdf' => UploadedFile::fake()->create('supplier-quote.pdf', 20, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->post(route('procurement.tasks.cancel', $task))
            ->assertRedirect();

        $this->assertSame(ProcurementTaskStatus::Cancelled, $task->fresh()->status);
    }

    public function test_procurement_sees_referred_sales_request_after_submit(): void
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

        $this->actingAs($this->procurement)
            ->get(route('sales-requests.show', $request))
            ->assertForbidden();

        $this->actingAs($this->rep)->post(route('sales-requests.submit', $request));

        $this->actingAs($this->procurement)
            ->get(route('sales-requests.show', $request))
            ->assertOk();
    }

    private function seedWarehouseStock(string $onHand): void
    {
        Warehouse::saveSingleton(['name_ar' => 'المستودع الرئيسي']);
        ProductStockBalance::factory()->create([
            'warehouse_id' => Warehouse::current()->id,
            'product_id' => $this->product->id,
            'on_hand' => $onHand,
            'reserved' => '0.000',
        ]);
    }
}
