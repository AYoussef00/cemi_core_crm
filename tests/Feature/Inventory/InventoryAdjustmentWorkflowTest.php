<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryAdjustment;
use App\Models\InventoryLedgerEntry;
use App\Models\Product;
use App\Models\ProductStockBalance;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Authorization\RoleSlug;
use App\Support\Inventory\InventoryAdjustmentStatus;
use App\Support\Inventory\InventoryLedgerEventType;
use App\Support\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsAuthorization;
use Tests\TestCase;

class InventoryAdjustmentWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();

        Warehouse::saveSingleton(['name_ar' => 'المستودع الرئيسي']);
        $this->warehouse = Warehouse::current();
        $this->assertNotNull($this->warehouse);

        $unit = UnitOfMeasure::factory()->create(['name_ar' => 'طن']);
        $this->product = Product::factory()->create([
            'name_ar' => 'إسمنت اختبار',
            'unit_id' => $unit->id,
            'purchase_cost' => '10.00',
        ]);
    }

    public function test_guest_is_redirected_from_stock_and_adjustments(): void
    {
        $this->get(route('inventory.stock.index'))->assertRedirect(route('login'));
        $this->get(route('inventory.adjustments.index'))->assertRedirect(route('login'));
        $this->get(route('inventory.adjustments.create'))->assertRedirect(route('login'));
    }

    public function test_warehouse_records_pending_adjustment_and_notifies_sales_manager(): void
    {
        $warehouseUser = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();

        $this->actingAs($warehouseUser)->post(route('inventory.adjustments.store'), [
            'product_id' => $this->product->id,
            'quantity_delta' => '25.5',
            'reason_ar' => 'جرد افتتاحي',
        ])->assertRedirect();

        $adjustment = InventoryAdjustment::query()->first();
        $this->assertNotNull($adjustment);
        $this->assertSame(InventoryAdjustmentStatus::Pending, $adjustment->status);
        $this->assertSame('25.500', (string) $adjustment->quantity_delta);

        $balance = ProductStockBalance::query()->where('product_id', $this->product->id)->first();
        $this->assertNotNull($balance);
        $this->assertSame('0.000', (string) $balance->on_hand);

        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory_adjustment.recorded']);
        $this->assertSame(1, $manager->fresh()->notifications()->count());
    }

    public function test_sales_manager_approves_increase_and_updates_balance_with_ledger(): void
    {
        $warehouseUser = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();

        $this->actingAs($warehouseUser)->post(route('inventory.adjustments.store'), [
            'product_id' => $this->product->id,
            'quantity_delta' => '10',
            'reason_ar' => 'إضافة مخزون',
        ]);
        $adjustment = InventoryAdjustment::query()->firstOrFail();

        $this->actingAs($manager)->post(route('inventory.adjustments.decide', $adjustment), [
            'decision' => 'approve',
            'decision_notes' => 'معتمد',
        ])->assertRedirect(route('inventory.adjustments.show', $adjustment));

        $adjustment->refresh();
        $this->assertSame(InventoryAdjustmentStatus::Approved, $adjustment->status);

        $balance = ProductStockBalance::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->firstOrFail();
        $this->assertSame('10.000', (string) $balance->on_hand);
        $this->assertSame('0.000', (string) $balance->reserved);
        $this->assertSame('10.000', $balance->available());

        $this->assertDatabaseHas('inventory_ledger_entries', [
            'product_id' => $this->product->id,
            'event_type' => InventoryLedgerEventType::Adjustment->value,
            'on_hand_after' => '10.000',
            'reference_id' => $adjustment->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory_adjustment.approved']);
        $this->assertGreaterThanOrEqual(1, $warehouseUser->fresh()->notifications()->count());
    }

    public function test_approve_decrease_updates_on_hand_when_sufficient(): void
    {
        $warehouseUser = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();

        ProductStockBalance::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'on_hand' => '20.000',
            'reserved' => '0.000',
        ]);

        $this->actingAs($warehouseUser)->post(route('inventory.adjustments.store'), [
            'product_id' => $this->product->id,
            'quantity_delta' => '-7.5',
            'reason_ar' => 'تالف',
        ]);
        $adjustment = InventoryAdjustment::query()->firstOrFail();

        $this->actingAs($manager)->post(route('inventory.adjustments.decide', $adjustment), [
            'decision' => 'approve',
        ])->assertRedirect();

        $balance = ProductStockBalance::query()->where('product_id', $this->product->id)->firstOrFail();
        $this->assertSame('12.500', (string) $balance->on_hand);
    }

    public function test_inv3_rejects_decrease_when_insufficient_available(): void
    {
        $warehouseUser = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();

        ProductStockBalance::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'on_hand' => '5.000',
            'reserved' => '2.000',
        ]);

        $this->actingAs($warehouseUser)->post(route('inventory.adjustments.store'), [
            'product_id' => $this->product->id,
            'quantity_delta' => '-4',
            'reason_ar' => 'نقص يتجاوز المتاح',
        ]);
        $adjustment = InventoryAdjustment::query()->firstOrFail();

        $this->actingAs($manager)
            ->from(route('inventory.adjustments.show', $adjustment))
            ->post(route('inventory.adjustments.decide', $adjustment), [
                'decision' => 'approve',
            ])
            ->assertRedirect(route('inventory.adjustments.show', $adjustment))
            ->assertSessionHasErrors('decision');

        $adjustment->refresh();
        $this->assertSame(InventoryAdjustmentStatus::Pending, $adjustment->status);

        $balance = ProductStockBalance::query()->where('product_id', $this->product->id)->firstOrFail();
        $this->assertSame('5.000', (string) $balance->on_hand);
        $this->assertSame('2.000', (string) $balance->reserved);
        $this->assertDatabaseCount('inventory_ledger_entries', 0);
    }

    public function test_reject_leaves_balances_unchanged(): void
    {
        $warehouseUser = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();

        ProductStockBalance::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'on_hand' => '8.000',
            'reserved' => '0.000',
        ]);

        $this->actingAs($warehouseUser)->post(route('inventory.adjustments.store'), [
            'product_id' => $this->product->id,
            'quantity_delta' => '100',
            'reason_ar' => 'طلب مرفوض',
        ]);
        $adjustment = InventoryAdjustment::query()->firstOrFail();

        $this->actingAs($manager)->post(route('inventory.adjustments.decide', $adjustment), [
            'decision' => 'reject',
            'decision_notes' => 'غير مبرر',
        ])->assertRedirect();

        $this->assertSame(InventoryAdjustmentStatus::Rejected, $adjustment->fresh()->status);
        $this->assertSame('8.000', (string) ProductStockBalance::query()->where('product_id', $this->product->id)->value('on_hand'));
        $this->assertDatabaseCount('inventory_ledger_entries', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory_adjustment.rejected']);
    }

    public function test_double_approve_is_idempotent_and_does_not_double_apply(): void
    {
        $warehouseUser = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();

        $this->actingAs($warehouseUser)->post(route('inventory.adjustments.store'), [
            'product_id' => $this->product->id,
            'quantity_delta' => '3',
            'reason_ar' => 'مرة واحدة',
        ]);
        $adjustment = InventoryAdjustment::query()->firstOrFail();

        $this->actingAs($manager)->post(route('inventory.adjustments.decide', $adjustment), [
            'decision' => 'approve',
        ])->assertRedirect();

        // Second HTTP attempt: policy denies non-pending → forbidden
        $this->actingAs($manager)
            ->post(route('inventory.adjustments.decide', $adjustment), [
                'decision' => 'approve',
            ])
            ->assertForbidden();

        $this->assertSame('3.000', (string) ProductStockBalance::query()->where('product_id', $this->product->id)->value('on_hand'));
        $this->assertSame(1, InventoryLedgerEntry::query()->count());

        // Service-level idempotency (already approved)
        $service = app(InventoryService::class);
        $again = $service->approveAdjustment($adjustment->fresh(), $manager);
        $this->assertSame(InventoryAdjustmentStatus::Approved, $again->status);
        $this->assertSame('3.000', (string) ProductStockBalance::query()->where('product_id', $this->product->id)->value('on_hand'));
        $this->assertSame(1, InventoryLedgerEntry::query()->count());
    }

    public function test_warehouse_cannot_approve_own_or_any_adjustment(): void
    {
        $warehouseUser = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $otherWarehouse = User::factory()->withRole(RoleSlug::Warehouse)->create();

        $this->actingAs($warehouseUser)->post(route('inventory.adjustments.store'), [
            'product_id' => $this->product->id,
            'quantity_delta' => '1',
            'reason_ar' => 'ذاتي',
        ]);
        $adjustment = InventoryAdjustment::query()->firstOrFail();

        $this->actingAs($warehouseUser)
            ->post(route('inventory.adjustments.decide', $adjustment), ['decision' => 'approve'])
            ->assertForbidden();

        $this->actingAs($otherWarehouse)
            ->post(route('inventory.adjustments.decide', $adjustment), ['decision' => 'approve'])
            ->assertForbidden();
    }

    public function test_administrator_cannot_bypass_approve(): void
    {
        $warehouseUser = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $admin = User::factory()->withRole(RoleSlug::Administrator)->create();

        $this->actingAs($warehouseUser)->post(route('inventory.adjustments.store'), [
            'product_id' => $this->product->id,
            'quantity_delta' => '1',
            'reason_ar' => 'أدمن',
        ]);
        $adjustment = InventoryAdjustment::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('inventory.adjustments.decide', $adjustment), ['decision' => 'approve'])
            ->assertForbidden();
    }

    public function test_sales_rep_denied_stock_and_adjustments_when_without_permissions(): void
    {
        $rep = User::factory()->withRole(RoleSlug::SalesRepresentative)->create();

        // Rep has stock.view per matrix — stock index allowed
        $this->actingAs($rep)->get(route('inventory.stock.index'))->assertOk();

        // Rep cannot record or view adjustments queue
        $this->actingAs($rep)->get(route('inventory.adjustments.index'))->assertForbidden();
        $this->actingAs($rep)->get(route('inventory.adjustments.create'))->assertForbidden();
        $this->actingAs($rep)->post(route('inventory.adjustments.store'), [
            'product_id' => $this->product->id,
            'quantity_delta' => '1',
            'reason_ar' => 'ممنوع',
        ])->assertForbidden();
    }

    public function test_stock_index_and_product_show_stock_section_without_prices_for_warehouse(): void
    {
        $warehouseUser = User::factory()->withRole(RoleSlug::Warehouse)->create();

        ProductStockBalance::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'on_hand' => '15.000',
            'reserved' => '4.000',
        ]);

        $this->actingAs($warehouseUser)
            ->get(route('inventory.stock.index'))
            ->assertOk()
            ->assertSee('إسمنت اختبار', false)
            ->assertSee('15.000', false)
            ->assertSee('4.000', false)
            ->assertSee('11.000', false)
            ->assertDontSee((string) $this->product->purchase_cost, false);

        $this->actingAs($warehouseUser)
            ->get(route('products.show', $this->product))
            ->assertOk()
            ->assertSee('رصيد المخزون', false)
            ->assertSee('15.000', false)
            ->assertDontSee('تكلفة الشراء', false)
            ->assertDontSee('10.00', false);
    }

    public function test_missing_balance_row_is_created_then_locked_on_approve(): void
    {
        $warehouseUser = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();

        $this->assertDatabaseMissing('product_stock_balances', ['product_id' => $this->product->id]);

        $this->actingAs($warehouseUser)->post(route('inventory.adjustments.store'), [
            'product_id' => $this->product->id,
            'quantity_delta' => '2',
            'reason_ar' => 'صف جديد',
        ]);

        // recordAdjustment already ensures row exists at 0
        $this->assertDatabaseHas('product_stock_balances', [
            'product_id' => $this->product->id,
            'on_hand' => '0.000',
        ]);

        $adjustment = InventoryAdjustment::query()->firstOrFail();
        $this->actingAs($manager)->post(route('inventory.adjustments.decide', $adjustment), [
            'decision' => 'approve',
        ])->assertRedirect();

        $this->assertSame('2.000', (string) ProductStockBalance::query()->where('product_id', $this->product->id)->value('on_hand'));
    }

    public function test_concurrent_approve_applies_once_via_row_lock_pattern(): void
    {
        $warehouseUser = User::factory()->withRole(RoleSlug::Warehouse)->create();
        $manager = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $service = app(InventoryService::class);

        $this->actingAs($warehouseUser)->post(route('inventory.adjustments.store'), [
            'product_id' => $this->product->id,
            'quantity_delta' => '5',
            'reason_ar' => 'تزامن',
        ]);
        $adjustment = InventoryAdjustment::query()->firstOrFail();

        // Simulate race-safe sequential locks: both call approve; second is idempotent.
        DB::transaction(function () use ($service, $adjustment, $manager): void {
            $service->approveAdjustment($adjustment, $manager);
        });
        DB::transaction(function () use ($service, $adjustment, $manager): void {
            $service->approveAdjustment($adjustment->fresh(), $manager);
        });

        $this->assertSame('5.000', (string) ProductStockBalance::query()->where('product_id', $this->product->id)->value('on_hand'));
        $this->assertSame(1, InventoryLedgerEntry::query()->count());
        $this->assertSame(InventoryAdjustmentStatus::Approved, $adjustment->fresh()->status);
    }
}
