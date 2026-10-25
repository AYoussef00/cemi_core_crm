<?php

namespace Tests\Feature\Support;

use App\Support\SchemaConstraints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_key_exists_matches_column_and_referenced_table_not_constraint_name(): void
    {
        $foreignKey = $this->foreignKeyOn(
            'supplier_goods_receipt_lines',
            'supplier_purchase_order_line_id',
        );

        $this->assertNotSame([], $foreignKey);
        $this->assertSame(['supplier_purchase_order_line_id'], $foreignKey['columns']);
        $this->assertSame('supplier_purchase_order_lines', $foreignKey['foreign_table']);

        $this->assertTrue(SchemaConstraints::foreignKeyExists(
            'supplier_goods_receipt_lines',
            'supplier_purchase_order_line_id',
            'supplier_purchase_order_lines',
        ));
        $this->assertFalse(SchemaConstraints::foreignKeyExists(
            'supplier_goods_receipt_lines',
            'supplier_purchase_order_line_id',
            'users',
        ));
    }

    public function test_index_exists_on_columns_uses_column_order(): void
    {
        $this->assertTrue(SchemaConstraints::indexExistsOnColumns(
            'supplier_goods_receipts',
            ['supplier_purchase_order_id', 'received_at'],
        ));
        $this->assertFalse(SchemaConstraints::indexExistsOnColumns(
            'supplier_goods_receipts',
            ['received_at', 'supplier_purchase_order_id'],
        ));
        $this->assertTrue(SchemaConstraints::indexExistsByName(
            'supplier_goods_receipts',
            'sgr_po_received_at_idx',
        ));
    }

    public function test_pending_migrations_do_not_call_unsupported_schema_helpers(): void
    {
        $files = [
            '2026_10_18_130000_create_supplier_purchase_orders_tables.php',
            '2026_10_18_140000_create_supplier_goods_receipts_tables.php',
            '2026_10_18_150000_add_quotation_id_to_supplier_purchase_orders.php',
            '2026_10_25_211901_add_origin_visit_id_to_sales_requests_table.php',
        ];

        foreach ($files as $file) {
            $source = (string) file_get_contents(database_path('migrations/'.$file));
            $this->assertStringNotContainsString('Schema::hasForeignKey', $source, $file);
            $this->assertStringNotContainsString('Schema::hasIndex(', $source, $file);
        }
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
