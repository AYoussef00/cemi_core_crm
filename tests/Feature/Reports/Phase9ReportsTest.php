<?php

namespace Tests\Feature\Reports;

use App\Models\Customer;
use App\Models\CustomerPurchaseOrder;
use App\Models\CustomerPurchaseOrderLine;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Support\Finance\InvoiceStatus;
use App\Support\Sales\CustomerPurchaseOrderStatus;
use App\Support\Sales\DealStatus;
use App\Support\Sales\SalesOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class Phase9ReportsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
    }

    public function test_app_timezone_is_asia_riyadh(): void
    {
        $this->assertSame('Asia/Riyadh', config('app.timezone'));
    }

    public function test_warehouse_cannot_view_ar_report(): void
    {
        $warehouse = User::factory()->withRole(RoleSlug::Warehouse)->create();

        $this->actingAs($warehouse)
            ->get(route('reports.ar.index'))
            ->assertForbidden();
    }

    public function test_accounts_sees_ar_report_with_real_unpaid(): void
    {
        $accounts = User::factory()->withRole(RoleSlug::Accounts)->create();
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($rep)->create(['name_ar' => 'عميل تقرير']);

        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'issued_by_user_id' => $accounts->id,
            'status' => InvoiceStatus::Issued,
            'total_vat_inclusive' => '150.00',
            'issued_at' => now()->subDays(10),
            'due_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($accounts)->get(route('reports.ar.index'));

        $response->assertOk();
        $response->assertSee('الذمم المدينة', false);
        $response->assertSee('عميل تقرير', false);
        $response->assertSee('150.00', false);
        $response->assertSee('متأخرة', false);
        $response->assertSee('Asia/Riyadh', false);
        $response->assertDontSee('بيانات معاينة', false);
    }

    public function test_sales_rep_statement_scoped_to_assigned_customers(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $other = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $mine = Customer::factory()->assignedTo($rep)->create(['name_ar' => 'منشأتي']);
        $theirs = Customer::factory()->assignedTo($other)->create(['name_ar' => 'منشأة غيري']);

        $this->actingAs($rep)
            ->get(route('reports.statements.index'))
            ->assertOk()
            ->assertSee('منشأتي', false)
            ->assertDontSee('منشأة غيري', false);

        $this->actingAs($rep)
            ->get(route('reports.statements.show', $theirs))
            ->assertForbidden();

        $this->actingAs($rep)
            ->get(route('reports.statements.show', $mine))
            ->assertOk()
            ->assertSee('منشأتي', false);
    }

    public function test_home_dashboard_shows_deals_for_accounts_preparation_for_warehouse(): void
    {
        $accounts = User::factory()->withRole(RoleSlug::Accounts)->create(['name' => 'محاسب']);
        $warehouse = User::factory()->withRole(RoleSlug::Warehouse)->create(['name' => 'مستودع']);

        $this->actingAs($accounts)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('الذمم (AR)', false)
            ->assertSee('صفقات مفتوحة', false);

        $this->actingAs($warehouse)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('الذمم (AR)', false)
            ->assertSee('طابور التجهيز', false);
    }

    public function test_customer_po_show_includes_separate_pf1_labels(): void
    {
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $customer = Customer::factory()->assignedTo($rep)->create();
        $unit = UnitOfMeasure::factory()->create();
        $product = Product::factory()->create([
            'unit_id' => $unit->id,
            'purchase_cost' => '100.00',
        ]);

        $quotation = Quotation::factory()->create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $manager->id,
        ]);

        $qLine = $quotation->lines()->create([
            'product_id' => $product->id,
            'product_name_ar' => $product->name_ar,
            'quantity' => '10.000',
            'supplier_cost' => '100.00',
            'margin_amount' => '10.00',
            'selling_price_vat_inclusive' => '110.00',
            'sort_order' => 1,
        ]);

        $po = CustomerPurchaseOrder::factory()->create([
            'customer_id' => $customer->id,
            'quotation_id' => $quotation->id,
            'created_by_user_id' => $manager->id,
            'status' => CustomerPurchaseOrderStatus::Open,
        ]);

        $poLine = CustomerPurchaseOrderLine::query()->create([
            'customer_purchase_order_id' => $po->id,
            'quotation_line_id' => $qLine->id,
            'product_id' => $product->id,
            'quantity' => '10.000',
            'sort_order' => 1,
        ]);

        $deal = Deal::factory()->create([
            'customer_id' => $customer->id,
            'customer_purchase_order_id' => $po->id,
            'status' => DealStatus::Open,
        ]);

        $so = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'customer_purchase_order_id' => $po->id,
            'deal_id' => $deal->id,
            'created_by_user_id' => $rep->id,
            'status' => SalesOrderStatus::Approved,
        ]);

        SalesOrderLine::query()->create([
            'sales_order_id' => $so->id,
            'customer_purchase_order_line_id' => $poLine->id,
            'product_id' => $product->id,
            'quantity' => '4.000',
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($manager)->get(route('customer-pos.show', $po));

        $response->assertOk();
        $response->assertSee('غير مخصّص', false);
        $response->assertSee('غير مسلّم', false);
        $response->assertSee('غير مفوتر', false);
        // Ordered 10, allocated 4 → unallocated 6; nothing delivered → undelivered 10
        $response->assertSee('6.000', false);
        $response->assertSee('10.000', false);
    }
}
