<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryLedgerEntry;
use App\Models\Product;
use App\Models\ProductStockBalance;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Authorization\RoleSlug;
use App\Support\Inventory\InventoryLedgerEventType;
use App\Support\Inventory\InventoryService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\Concerns\SeedsAuthorization;
use Tests\Support\Inventory\FakeInventoryDocument;
use Tests\TestCase;

class InventoryReserveDeductReleaseTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAuthorization;

    private Warehouse $warehouse;

    private Product $product;

    private User $actor;

    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorization();

        if (! Schema::hasTable('fake_inventory_documents')) {
            Schema::create('fake_inventory_documents', function (Blueprint $table): void {
                $table->id();
                $table->string('label')->nullable();
                $table->timestamps();
            });
        }

        Warehouse::saveSingleton(['name_ar' => 'المستودع الرئيسي']);
        $this->warehouse = Warehouse::current();
        $this->assertNotNull($this->warehouse);

        $unit = UnitOfMeasure::factory()->create(['name_ar' => 'طن']);
        $this->product = Product::factory()->create([
            'name_ar' => 'منتج حركات',
            'unit_id' => $unit->id,
            'purchase_cost' => '9.00',
        ]);

        ProductStockBalance::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'on_hand' => '100.000',
            'reserved' => '0.000',
        ]);

        $this->actor = User::factory()->withRole(RoleSlug::SalesManager)->create();
        $this->inventory = app(InventoryService::class);
    }

    public function test_happy_path_reserve_partial_deduct_then_release_remainder(): void
    {
        $so = FakeInventoryDocument::query()->create(['label' => 'SO-1']);
        $delivery = FakeInventoryDocument::query()->create(['label' => 'DEL-1']);

        $this->inventory->reserve(
            $this->warehouse,
            $this->product,
            40,
            $this->actor,
            $so,
            'reserve:fake:'.$so->id,
        );

        $balance = $this->balance();
        $this->assertSame('100.000', (string) $balance->on_hand);
        $this->assertSame('40.000', (string) $balance->reserved);
        $this->assertSame('60.000', $balance->available());

        $this->inventory->deduct(
            $this->warehouse,
            $this->product,
            15,
            $this->actor,
            $delivery,
            'deduct:fake:'.$delivery->id,
            $so,
        );

        $balance->refresh();
        $this->assertSame('85.000', (string) $balance->on_hand);
        $this->assertSame('25.000', (string) $balance->reserved);
        $this->assertSame(25.0, round($this->inventory->remainingReservedFor($so), 3));

        $this->inventory->release(
            $this->warehouse,
            $this->product,
            $this->actor,
            $so,
            'release:fake:'.$so->id,
        );

        $balance->refresh();
        $this->assertSame('85.000', (string) $balance->on_hand);
        $this->assertSame('0.000', (string) $balance->reserved);
        $this->assertSame(0.0, round($this->inventory->remainingReservedFor($so), 3));

        $this->assertSame(1, InventoryLedgerEntry::query()->where('event_type', InventoryLedgerEventType::Reserve)->count());
        $this->assertSame(1, InventoryLedgerEntry::query()->where('event_type', InventoryLedgerEventType::Deduct)->count());
        $this->assertSame(1, InventoryLedgerEntry::query()->where('event_type', InventoryLedgerEventType::Release)->count());

        $release = InventoryLedgerEntry::query()->where('event_type', InventoryLedgerEventType::Release)->firstOrFail();
        $this->assertSame('-25.000', (string) $release->quantity_delta);
    }

    public function test_inv3_reserve_beyond_available_fails(): void
    {
        $so = FakeInventoryDocument::query()->create(['label' => 'SO-over']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('تتجاوز المتاح');

        $this->inventory->reserve(
            $this->warehouse,
            $this->product,
            100.001,
            $this->actor,
            $so,
            'reserve:fake:'.$so->id,
        );
    }

    public function test_inv3_deduct_beyond_global_reserved_fails(): void
    {
        $so = FakeInventoryDocument::query()->create(['label' => 'SO-r']);
        $delivery = FakeInventoryDocument::query()->create(['label' => 'DEL-r']);

        $this->inventory->reserve(
            $this->warehouse,
            $this->product,
            10,
            $this->actor,
            $so,
            'reserve:fake:'.$so->id,
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('المحجوزة المتبقية');

        $this->inventory->deduct(
            $this->warehouse,
            $this->product,
            11,
            $this->actor,
            $delivery,
            'deduct:fake:'.$delivery->id,
            $so,
        );
    }

    public function test_inv3_deduct_beyond_reference_remaining_fails(): void
    {
        $soA = FakeInventoryDocument::query()->create(['label' => 'SO-A']);
        $soB = FakeInventoryDocument::query()->create(['label' => 'SO-B']);
        $delivery = FakeInventoryDocument::query()->create(['label' => 'DEL-A']);

        $this->inventory->reserve($this->warehouse, $this->product, 10, $this->actor, $soA, 'reserve:fake:'.$soA->id);
        $this->inventory->reserve($this->warehouse, $this->product, 20, $this->actor, $soB, 'reserve:fake:'.$soB->id);

        // Global reserved = 30, but SO-A remaining = 10
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('لهذا المرجع');

        $this->inventory->deduct(
            $this->warehouse,
            $this->product,
            15,
            $this->actor,
            $delivery,
            'deduct:fake:'.$delivery->id,
            $soA,
        );
    }

    public function test_double_reserve_deduct_release_are_idempotent(): void
    {
        $so = FakeInventoryDocument::query()->create(['label' => 'SO-idem']);
        $delivery = FakeInventoryDocument::query()->create(['label' => 'DEL-idem']);

        $r1 = $this->inventory->reserve($this->warehouse, $this->product, 30, $this->actor, $so, 'reserve:fake:'.$so->id);
        $r2 = $this->inventory->reserve($this->warehouse, $this->product, 30, $this->actor, $so, 'reserve:fake:'.$so->id);
        $this->assertTrue($r1->is($r2));
        $this->assertSame('30.000', (string) $this->balance()->reserved);
        $this->assertSame(1, InventoryLedgerEntry::query()->where('event_type', InventoryLedgerEventType::Reserve)->count());

        $d1 = $this->inventory->deduct($this->warehouse, $this->product, 10, $this->actor, $delivery, 'deduct:fake:'.$delivery->id, $so);
        $d2 = $this->inventory->deduct($this->warehouse, $this->product, 10, $this->actor, $delivery, 'deduct:fake:'.$delivery->id, $so);
        $this->assertTrue($d1->is($d2));
        $this->assertSame('90.000', (string) $this->balance()->on_hand);
        $this->assertSame('20.000', (string) $this->balance()->reserved);
        $this->assertSame(1, InventoryLedgerEntry::query()->where('event_type', InventoryLedgerEventType::Deduct)->count());

        $rel1 = $this->inventory->release($this->warehouse, $this->product, $this->actor, $so, 'release:fake:'.$so->id);
        $rel2 = $this->inventory->release($this->warehouse, $this->product, $this->actor, $so, 'release:fake:'.$so->id);
        $this->assertTrue($rel1->is($rel2));
        $this->assertSame('0.000', (string) $this->balance()->reserved);
        $this->assertSame(1, InventoryLedgerEntry::query()->where('event_type', InventoryLedgerEventType::Release)->count());
        $this->assertSame('-20.000', (string) $rel1->quantity_delta);
    }

    public function test_release_after_full_deduct_is_zero_and_idempotent(): void
    {
        $so = FakeInventoryDocument::query()->create(['label' => 'SO-full']);
        $delivery = FakeInventoryDocument::query()->create(['label' => 'DEL-full']);

        $this->inventory->reserve($this->warehouse, $this->product, 12, $this->actor, $so, 'reserve:fake:'.$so->id);
        $this->inventory->deduct($this->warehouse, $this->product, 12, $this->actor, $delivery, 'deduct:fake:'.$delivery->id, $so);

        $release = $this->inventory->release($this->warehouse, $this->product, $this->actor, $so, 'release:fake:'.$so->id);
        $this->assertSame('0.000', (string) $release->quantity_delta);
        $this->assertSame('0.000', (string) $this->balance()->reserved);

        $again = $this->inventory->release($this->warehouse, $this->product, $this->actor, $so, 'release:fake:'.$so->id);
        $this->assertTrue($release->is($again));
        $this->assertSame(1, InventoryLedgerEntry::query()->where('event_type', InventoryLedgerEventType::Release)->count());
    }

    public function test_concurrent_style_double_apply_via_lock_does_not_double_reserve(): void
    {
        $so = FakeInventoryDocument::query()->create(['label' => 'SO-lock']);
        $key = 'reserve:fake:'.$so->id;

        DB::transaction(function () use ($so, $key): void {
            $this->inventory->reserve($this->warehouse, $this->product, 5, $this->actor, $so, $key);
        });
        DB::transaction(function () use ($so, $key): void {
            $this->inventory->reserve($this->warehouse, $this->product, 5, $this->actor, $so, $key);
        });

        $this->assertSame('5.000', (string) $this->balance()->reserved);
        $this->assertSame(1, InventoryLedgerEntry::query()->where('idempotency_key', $key)->count());
    }

    public function test_release_explicit_qty_cannot_exceed_remaining_for_reference(): void
    {
        $so = FakeInventoryDocument::query()->create(['label' => 'SO-ex']);

        $this->inventory->reserve($this->warehouse, $this->product, 8, $this->actor, $so, 'reserve:fake:'.$so->id);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('لهذا المرجع');

        $this->inventory->release(
            $this->warehouse,
            $this->product,
            $this->actor,
            $so,
            'release:fake:'.$so->id,
            9,
        );
    }

    private function balance(): ProductStockBalance
    {
        return ProductStockBalance::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->firstOrFail();
    }
}
