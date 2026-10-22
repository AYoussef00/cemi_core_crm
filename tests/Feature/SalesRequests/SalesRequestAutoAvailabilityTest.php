<?php

namespace Tests\Feature\SalesRequests;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductStockBalance;
use App\Models\SalesRequest;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Authorization\RoleSlug;
use App\Support\SalesRequests\LineAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class SalesRequestAutoAvailabilityTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    private User $rep;

    private Customer $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();

        $this->rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $this->customer = Customer::factory()->assignedTo($this->rep)->create(['name_ar' => 'منشأة توفر تلقائي']);
        $unit = UnitOfMeasure::factory()->create();
        $this->product = Product::factory()->create([
            'name_ar' => 'منتج توفر',
            'unit_id' => $unit->id,
            'purchase_cost' => '40.00',
        ]);

        Warehouse::saveSingleton(['name_ar' => 'المستودع الرئيسي']);
        ProductStockBalance::factory()->create([
            'warehouse_id' => Warehouse::current()->id,
            'product_id' => $this->product->id,
            'on_hand' => '5.000',
            'reserved' => '0.000',
        ]);
    }

    public function test_qty_within_stock_resolves_available_even_if_client_sends_needs_procurement(): void
    {
        $this->actingAs($this->rep)->post(route('sales-requests.store'), [
            'customer_id' => $this->customer->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => '3',
                'availability' => LineAvailability::NeedsProcurement->value,
            ]],
        ])->assertRedirect();

        $line = SalesRequest::query()->firstOrFail()->lines()->firstOrFail();
        $this->assertSame(LineAvailability::Available, $line->availability);
    }

    public function test_qty_above_stock_resolves_needs_procurement_even_if_client_sends_available(): void
    {
        $this->actingAs($this->rep)->post(route('sales-requests.store'), [
            'customer_id' => $this->customer->id,
            'lines' => [[
                'product_id' => $this->product->id,
                'quantity' => '6',
                'availability' => LineAvailability::Available->value,
            ]],
        ])->assertRedirect();

        $line = SalesRequest::query()->firstOrFail()->lines()->firstOrFail();
        $this->assertSame(LineAvailability::NeedsProcurement, $line->availability);
    }

    public function test_create_form_shows_auto_availability_copy_not_manual_choice(): void
    {
        $this->actingAs($this->rep)
            ->get(route('sales-requests.create'))
            ->assertOk()
            ->assertSee('التوفر يُحدَّد تلقائيًا من رصيد المستودع', false)
            ->assertDontSee('اختر التوفر صراحةً', false);
    }
}
