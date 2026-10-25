<?php

namespace Tests\Feature\SalesRequests;

use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\Product;
use App\Models\SalesRequest;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Support\SalesRequests\SalesRequestStatus;
use App\Support\Visits\VisitStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class VisitToSalesRequestHandoffTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    private User $rep;

    private User $otherRep;

    private Customer $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();

        $this->rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $this->otherRep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $this->customer = Customer::factory()->assignedTo($this->rep)->create(['name_ar' => 'منشأة جسر الزيارة']);
        $unit = UnitOfMeasure::factory()->create();
        $this->product = Product::factory()->create([
            'name_ar' => 'منتج الجسر',
            'unit_id' => $unit->id,
        ]);
    }

    public function test_completed_visit_offers_sales_request_handoff_without_creating_one(): void
    {
        $visit = CustomerVisit::factory()->completed()->create([
            'customer_id' => $this->customer->id,
            'assigned_user_id' => $this->rep->id,
            'created_by_user_id' => $this->rep->id,
            'customer_needs' => 'عرض أسعار مواد بناء',
        ]);

        $this->assertSame(0, SalesRequest::query()->count());

        $this->actingAs($this->rep)
            ->get(route('visits.show', $visit))
            ->assertOk()
            ->assertSee('طلب بيع لهذه المنشأة', false)
            ->assertSee('الزيارة لا تُنشئ طلب بيع تلقائيًا', false)
            ->assertSee('إنشاء طلب بيع', false)
            ->assertSee('/sales-requests/create?customer_id='.$this->customer->id, false)
            ->assertSee('visit_id='.$visit->id, false);

        $this->assertSame(0, SalesRequest::query()->count());
    }

    public function test_customer_profile_has_sales_request_cta_without_a_visit(): void
    {
        $this->actingAs($this->rep)
            ->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertSee('طلب بيع', false)
            ->assertSee(route('sales-requests.create', [
                'customer_id' => $this->customer->id,
            ], false), false)
            ->assertSee('الزيارة ليست شرطًا', false);
    }

    public function test_create_from_completed_visit_prefills_customer_and_needs_and_stores_origin(): void
    {
        $visit = CustomerVisit::factory()->completed()->create([
            'customer_id' => $this->customer->id,
            'assigned_user_id' => $this->rep->id,
            'created_by_user_id' => $this->rep->id,
            'customer_needs' => 'عرض أسعار مواد بناء',
        ]);

        $this->actingAs($this->rep)
            ->get(route('sales-requests.create', [
                'customer_id' => $this->customer->id,
                'visit_id' => $visit->id,
            ]))
            ->assertOk()
            ->assertSee('من زيارة مكتملة', false)
            ->assertSee('منشأة جسر الزيارة', false)
            ->assertSee('عرض أسعار مواد بناء', false)
            ->assertSee('منشأة ثابتة من الزيارة', false)
            ->assertDontSee('اختر المنشأة', false);

        $this->actingAs($this->rep)->post(route('sales-requests.store'), [
            'customer_id' => $this->customer->id,
            'origin_visit_id' => $visit->id,
            'notes_ar' => 'عرض أسعار مواد بناء',
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => '2',
            ]],
        ])->assertRedirect();

        $request = SalesRequest::query()->firstOrFail();
        $this->assertSame($this->customer->id, $request->customer_id);
        $this->assertSame($visit->id, $request->origin_visit_id);
        $this->assertSame(SalesRequestStatus::Draft, $request->status);
        $this->assertSame('عرض أسعار مواد بناء', $request->notes_ar);

        $this->actingAs($this->rep)
            ->get(route('sales-requests.show', $request))
            ->assertOk()
            ->assertSee('من زيارة', false)
            ->assertSee(route('visits.show', $visit, false), false);

        $this->actingAs($this->rep)
            ->get(route('visits.show', $visit))
            ->assertOk()
            ->assertSee('طلب بيع آخر', false)
            ->assertSee('#'.$request->id, false);
    }

    public function test_sales_request_can_be_created_without_a_visit(): void
    {
        $this->actingAs($this->rep)->post(route('sales-requests.store'), [
            'customer_id' => $this->customer->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => '1',
            ]],
        ])->assertRedirect();

        $request = SalesRequest::query()->firstOrFail();
        $this->assertNull($request->origin_visit_id);
    }

    public function test_origin_visit_must_be_completed_and_same_customer(): void
    {
        $scheduled = CustomerVisit::factory()->scheduled()->create([
            'customer_id' => $this->customer->id,
            'assigned_user_id' => $this->rep->id,
            'created_by_user_id' => $this->rep->id,
        ]);

        $this->actingAs($this->rep)
            ->from(route('sales-requests.create'))
            ->post(route('sales-requests.store'), [
                'customer_id' => $this->customer->id,
                'origin_visit_id' => $scheduled->id,
                'lines' => [[
                    'product_id' => $this->product->id,
                    'quantity' => '1',
                ]],
            ])
            ->assertRedirect(route('sales-requests.create'))
            ->assertSessionHasErrors('origin_visit_id');

        $otherCustomer = Customer::factory()->assignedTo($this->rep)->create(['name_ar' => 'منشأة أخرى']);
        $otherVisit = CustomerVisit::factory()->completed()->create([
            'customer_id' => $otherCustomer->id,
            'assigned_user_id' => $this->rep->id,
            'created_by_user_id' => $this->rep->id,
        ]);

        $this->actingAs($this->rep)
            ->from(route('sales-requests.create'))
            ->post(route('sales-requests.store'), [
                'customer_id' => $this->customer->id,
                'origin_visit_id' => $otherVisit->id,
                'lines' => [[
                    'product_id' => $this->product->id,
                    'quantity' => '1',
                ]],
            ])
            ->assertRedirect(route('sales-requests.create'))
            ->assertSessionHasErrors('origin_visit_id');

        $this->assertSame(0, SalesRequest::query()->count());
    }

    public function test_rep_cannot_use_another_reps_visit_as_origin(): void
    {
        $foreignCustomer = Customer::factory()->assignedTo($this->otherRep)->create(['name_ar' => 'منشأة مندوب آخر']);
        $foreignVisit = CustomerVisit::factory()->completed()->create([
            'customer_id' => $foreignCustomer->id,
            'assigned_user_id' => $this->otherRep->id,
            'created_by_user_id' => $this->otherRep->id,
        ]);

        $this->actingAs($this->rep)
            ->get(route('sales-requests.create', ['visit_id' => $foreignVisit->id]))
            ->assertForbidden();

        $this->actingAs($this->rep)
            ->from(route('sales-requests.create'))
            ->post(route('sales-requests.store'), [
                'customer_id' => $this->customer->id,
                'origin_visit_id' => $foreignVisit->id,
                'lines' => [[
                    'product_id' => $this->product->id,
                    'quantity' => '1',
                ]],
            ])
            ->assertRedirect(route('sales-requests.create'))
            ->assertSessionHasErrors('origin_visit_id');
    }

    public function test_warehouse_cannot_open_sales_request_create(): void
    {
        $warehouse = User::factory()->withRole(RoleSlug::Warehouse)->create();

        $this->actingAs($warehouse)
            ->get(route('sales-requests.create'))
            ->assertForbidden();
    }

    public function test_scheduled_visit_has_no_sales_request_handoff_panel(): void
    {
        $visit = CustomerVisit::factory()->scheduled()->create([
            'customer_id' => $this->customer->id,
            'assigned_user_id' => $this->rep->id,
            'created_by_user_id' => $this->rep->id,
        ]);

        $this->actingAs($this->rep)
            ->get(route('visits.show', $visit))
            ->assertOk()
            ->assertDontSee('طلب بيع لهذه المنشأة', false)
            ->assertSee(VisitStatus::Scheduled->labelAr(), false);
    }
}
