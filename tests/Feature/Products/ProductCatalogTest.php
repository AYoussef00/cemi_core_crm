<?php

namespace Tests\Feature\Products;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();
    }

    public function test_administrator_can_create_unit_and_product_with_specs_and_audit(): void
    {
        $admin = User::factory()->withRole(RoleSlug::Administrator)->create();

        $this->actingAs($admin)->post(route('products.units.store'), [
            'name_ar' => 'كيلوغرام',
            'symbol_ar' => 'كغ',
            'is_active' => '1',
        ])->assertRedirect(route('products.units.index'));

        $unit = UnitOfMeasure::query()->where('name_ar', 'كيلوغرام')->first();
        $this->assertNotNull($unit);
        $this->assertDatabaseHas('audit_logs', ['action' => 'unit_of_measure.created']);

        $response = $this->actingAs($admin)->post(route('products.store'), [
            'name_ar' => 'إسمنت بورتلاند',
            'unit_id' => $unit->id,
            'purchase_cost' => '12.50',
            'density_value' => '1.44',
            'density_unit_ar' => 'غ/سم³',
            'specifications' => [
                ['label_ar' => 'اللون', 'value' => 'رمادي', 'unit_ar' => null],
            ],
        ]);

        $product = Product::query()->where('name_ar', 'إسمنت بورتلاند')->first();
        $this->assertNotNull($product);
        $response->assertRedirect(route('products.show', $product));
        $this->assertSame('12.50', (string) $product->purchase_cost);
        $this->assertDatabaseCount('product_specifications', 2);
        $this->assertDatabaseHas('product_specifications', [
            'product_id' => $product->id,
            'label_ar' => Product::DENSITY_LABEL_AR,
            'value' => '1.44',
            'unit_ar' => 'غ/سم³',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.created']);

        $this->actingAs($admin)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('إسمنت بورتلاند', false)
            ->assertSee('الكثافة', false)
            ->assertSee('1.44', false)
            ->assertSee('12.50', false);
    }

    public function test_administrator_can_update_product_master_fields(): void
    {
        $admin = User::factory()->withRole(RoleSlug::Administrator)->create();
        $unitA = UnitOfMeasure::factory()->create(['name_ar' => 'طن']);
        $unitB = UnitOfMeasure::factory()->create(['name_ar' => 'كيس']);
        $product = Product::factory()->create([
            'name_ar' => 'منتج قديم',
            'unit_id' => $unitA->id,
            'purchase_cost' => '5.00',
        ]);

        $this->actingAs($admin)->put(route('products.update', $product), [
            'name_ar' => 'منتج محدّث',
            'unit_id' => $unitB->id,
            'purchase_cost' => '7.25',
            'density_value' => '2.1',
            'specifications' => [],
        ])->assertRedirect(route('products.show', $product));

        $product->refresh();
        $this->assertSame('منتج محدّث', $product->name_ar);
        $this->assertSame($unitB->id, $product->unit_id);
        $this->assertSame('7.25', (string) $product->purchase_cost);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.purchase_cost_updated']);
    }

    public function test_procurement_can_update_purchase_cost_only(): void
    {
        $procurement = User::factory()->withRole(RoleSlug::Procurement)->create();
        $unit = UnitOfMeasure::factory()->create();
        $product = Product::factory()->create([
            'name_ar' => 'منتج مشتريات',
            'unit_id' => $unit->id,
            'purchase_cost' => '10.00',
        ]);

        $this->actingAs($procurement)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('تحديث تكلفة الشراء', false)
            ->assertDontSee('href="'.route('products.edit', $product).'"', false);

        $this->actingAs($procurement)
            ->put(route('products.purchase-cost.update', $product), [
                'purchase_cost' => '15.75',
            ])
            ->assertRedirect(route('products.show', $product));

        $product->refresh();
        $this->assertSame('15.75', (string) $product->purchase_cost);
        $this->assertSame('منتج مشتريات', $product->name_ar);

        $log = AuditLog::query()->where('action', 'product.purchase_cost_updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($procurement->id, $log->actor_user_id);
        $this->assertSame('cost_only', $log->properties['via'] ?? null);
        $this->assertSame('10.00', $log->properties['before']['purchase_cost'] ?? null);
        $this->assertSame('15.75', $log->properties['after']['purchase_cost'] ?? null);
    }

    public function test_procurement_cannot_modify_master_fields_via_update(): void
    {
        $procurement = User::factory()->withRole(RoleSlug::Procurement)->create();
        $unit = UnitOfMeasure::factory()->create();
        $product = Product::factory()->create([
            'name_ar' => 'محمي',
            'unit_id' => $unit->id,
            'purchase_cost' => '8.00',
        ]);

        $this->actingAs($procurement)
            ->get(route('products.edit', $product))
            ->assertForbidden();

        $this->actingAs($procurement)
            ->put(route('products.update', $product), [
                'name_ar' => 'محاولة تغيير الاسم',
                'unit_id' => $unit->id,
                'purchase_cost' => '99.00',
            ])
            ->assertForbidden();

        $this->actingAs($procurement)
            ->get(route('products.create'))
            ->assertForbidden();

        $this->actingAs($procurement)
            ->post(route('products.store'), [
                'name_ar' => 'منتج غير مسموح',
                'unit_id' => $unit->id,
            ])
            ->assertForbidden();

        $this->actingAs($procurement)
            ->get(route('products.units.index'))
            ->assertForbidden();

        $product->refresh();
        $this->assertSame('محمي', $product->name_ar);
        $this->assertSame('8.00', (string) $product->purchase_cost);
    }

    public function test_sales_roles_can_view_catalog_without_purchase_cost(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $unit = UnitOfMeasure::factory()->create(['name_ar' => 'لتر']);
        $product = Product::factory()->create([
            'name_ar' => 'منتج مرئي',
            'unit_id' => $unit->id,
            'purchase_cost' => '42.00',
        ]);

        foreach ([$rep, $manager] as $user) {
            $this->actingAs($user)
                ->get(route('products.index'))
                ->assertOk()
                ->assertSee('منتج مرئي', false)
                ->assertDontSee('42.00', false);

            $this->actingAs($user)
                ->get(route('products.show', $product))
                ->assertOk()
                ->assertSee('منتج مرئي', false)
                ->assertSee('لتر', false)
                ->assertDontSee('تكلفة الشراء', false)
                ->assertDontSee('42.00', false);

            $this->actingAs($user)
                ->put(route('products.purchase-cost.update', $product), [
                    'purchase_cost' => '1.00',
                ])
                ->assertForbidden();
        }
    }

    public function test_guest_denied_and_validation_rejects_invalid_records(): void
    {
        $this->get(route('products.index'))->assertRedirect(route('login'));

        $admin = User::factory()->withRole(RoleSlug::Administrator)->create();
        $inactive = UnitOfMeasure::factory()->inactive()->create(['name_ar' => 'قديمة']);

        $this->actingAs($admin)->post(route('products.store'), [
            'name_ar' => '',
            'unit_id' => $inactive->id,
            'purchase_cost' => '-1',
            'density_unit_ar' => 'كغ/م³',
            'specifications' => [
                ['label_ar' => 'بدون قيمة', 'value' => '', 'unit_ar' => null],
            ],
        ])->assertSessionHasErrors(['name_ar', 'unit_id', 'purchase_cost', 'density_value', 'specifications.0.label_ar']);

        $unit = UnitOfMeasure::factory()->create();
        $this->actingAs($admin)->post(route('products.store'), [
            'name_ar' => 'منتج كثافة مكررة',
            'unit_id' => $unit->id,
            'specifications' => [
                ['label_ar' => Product::DENSITY_LABEL_AR, 'value' => '1', 'unit_ar' => null],
            ],
        ])->assertSessionHasErrors(['specifications.0.label_ar']);
    }

    public function test_product_search_and_empty_list(): void
    {
        $admin = User::factory()->withRole(RoleSlug::Administrator)->create();

        $this->actingAs($admin)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('لا توجد منتجات', false);

        $unit = UnitOfMeasure::factory()->create(['name_ar' => 'متر']);
        Product::factory()->create(['name_ar' => 'أنبوب PVC', 'unit_id' => $unit->id]);
        Product::factory()->create(['name_ar' => 'صمام نحاس', 'unit_id' => $unit->id]);

        $this->actingAs($admin)
            ->get(route('products.index', ['q' => 'أنبوب']))
            ->assertOk()
            ->assertSee('أنبوب PVC', false)
            ->assertDontSee('صمام نحاس', false);
    }

    public function test_unit_update_and_inactive_unit_blocked_on_create(): void
    {
        $admin = User::factory()->withRole(RoleSlug::Administrator)->create();
        $unit = UnitOfMeasure::factory()->create(['name_ar' => 'برميل', 'is_active' => true]);

        $this->actingAs($admin)->put(route('products.units.update', $unit), [
            'name_ar' => 'برميل محدّث',
            'symbol_ar' => 'بر',
            'is_active' => '0',
        ])->assertRedirect(route('products.units.index'));

        $unit->refresh();
        $this->assertSame('برميل محدّث', $unit->name_ar);
        $this->assertFalse($unit->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'unit_of_measure.updated']);

        $this->actingAs($admin)->post(route('products.store'), [
            'name_ar' => 'يجب أن يفشل',
            'unit_id' => $unit->id,
        ])->assertSessionHasErrors(['unit_id']);
    }
}
