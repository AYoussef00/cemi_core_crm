<?php

namespace Tests\Feature\Procurement;

use App\Support\SchemaConstraints;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupplierPurchaseOrderSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_purchase_order_constraint_names_fit_mysql_identifier_limit(): void
    {
        $this->assertTrue(Schema::hasTable('supplier_purchase_orders'));
        $this->assertTrue(Schema::hasTable('supplier_purchase_order_lines'));
        $this->assertTrue(Schema::hasColumn('supplier_purchase_orders', 'quotation_id'));

        $this->assertTrue(SchemaConstraints::indexExistsByName('supplier_purchase_order_lines', 'spo_lines_po_sort_idx'));
        $this->assertTrue(SchemaConstraints::indexExistsOnColumns('supplier_purchase_orders', ['quotation_id']));
        $this->assertTrue(SchemaConstraints::foreignKeyExists(
            'supplier_purchase_order_lines',
            'supplier_purchase_order_id',
            'supplier_purchase_orders',
        ));
        $this->assertSame(
            'cascade',
            $this->foreignKeyOn('supplier_purchase_order_lines', 'supplier_purchase_order_id')['on_delete'],
        );
        $this->assertSame(
            'set null',
            $this->foreignKeyOn('supplier_purchase_orders', 'quotation_id')['on_delete'],
        );

        foreach (['supplier_purchase_orders', 'supplier_purchase_order_lines'] as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                $this->assertLessThanOrEqual(64, strlen((string) $index['name']), $table.' index '.$index['name']);
            }

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (is_string($foreignKey['name']) && $foreignKey['name'] !== '') {
                    $this->assertLessThanOrEqual(64, strlen($foreignKey['name']), $table.' fk '.$foreignKey['name']);
                }
            }
        }
    }

    public function test_supplier_purchase_order_migration_adds_missing_line_sort_index(): void
    {
        Schema::table('supplier_purchase_order_lines', function (Blueprint $table): void {
            $table->dropIndex('spo_lines_po_sort_idx');
        });
        $this->assertFalse(SchemaConstraints::indexExistsByName('supplier_purchase_order_lines', 'spo_lines_po_sort_idx'));

        $this->loadMigration('2026_10_18_130000_create_supplier_purchase_orders_tables')->up();

        $this->assertTrue(SchemaConstraints::indexExistsByName('supplier_purchase_order_lines', 'spo_lines_po_sort_idx'));
    }

    public function test_quotation_id_migration_can_rerun_when_column_already_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('supplier_purchase_orders', 'quotation_id'));
        $this->assertTrue(SchemaConstraints::foreignKeyExists(
            'supplier_purchase_orders',
            'quotation_id',
            'quotations',
        ));

        $this->loadMigration('2026_10_18_150000_add_quotation_id_to_supplier_purchase_orders')->up();

        $this->assertTrue(Schema::hasColumn('supplier_purchase_orders', 'quotation_id'));
        $this->assertTrue(SchemaConstraints::indexExistsOnColumns('supplier_purchase_orders', ['quotation_id']));
        $this->assertSame(
            'set null',
            $this->foreignKeyOn('supplier_purchase_orders', 'quotation_id')['on_delete'],
        );
        $this->assertCount(
            1,
            array_filter(
                Schema::getForeignKeys('supplier_purchase_orders'),
                fn (array $foreignKey): bool => $foreignKey['columns'] === ['quotation_id'],
            ),
        );
    }

    private function loadMigration(string $filename): object
    {
        return require database_path('migrations/'.$filename.'.php');
    }

    /**
     * @return array{name: string|null, columns: list<string>, foreign_table: string, on_delete: string|null}|array{}
     */
    private function foreignKeyOn(string $table, string $column): array
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if ($foreignKey['columns'] === [$column]) {
                return $foreignKey;
            }
        }

        return [];
    }
}
