<?php

namespace Tests\Feature\Procurement;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupplierGoodsReceiptSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_goods_receipt_constraint_names_fit_mysql_identifier_limit(): void
    {
        $this->assertTrue(Schema::hasTable('supplier_goods_receipts'));
        $this->assertTrue(Schema::hasTable('supplier_goods_receipt_lines'));

        $this->assertTrue(Schema::hasColumns('supplier_goods_receipts', [
            'supplier_purchase_order_id',
            'received_by_user_id',
            'received_at',
            'shipment_reference_ar',
            'notes_ar',
        ]));
        $this->assertTrue(Schema::hasColumns('supplier_goods_receipt_lines', [
            'supplier_goods_receipt_id',
            'supplier_purchase_order_line_id',
            'product_id',
            'quantity',
            'sort_order',
        ]));

        $this->assertTrue(Schema::hasIndex('supplier_goods_receipts', 'sgr_po_received_at_idx'));
        $this->assertTrue(Schema::hasIndex('supplier_goods_receipt_lines', 'sgr_lines_receipt_sort_idx'));

        foreach (['supplier_goods_receipts', 'supplier_goods_receipt_lines'] as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                $this->assertLessThanOrEqual(64, strlen((string) $index['name']), $table.' index '.$index['name']);
            }

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (is_string($foreignKey['name']) && $foreignKey['name'] !== '') {
                    $this->assertLessThanOrEqual(64, strlen($foreignKey['name']), $table.' fk '.$foreignKey['name']);
                }
            }
        }

        $this->assertNotSame(
            [],
            $this->foreignKeyOn('supplier_goods_receipt_lines', 'supplier_purchase_order_line_id'),
        );
        $this->assertSame(
            'restrict',
            $this->foreignKeyOn('supplier_goods_receipt_lines', 'supplier_purchase_order_line_id')['on_delete'],
        );
        $this->assertSame(
            'cascade',
            $this->foreignKeyOn('supplier_goods_receipt_lines', 'supplier_goods_receipt_id')['on_delete'],
        );
        $this->assertSame(
            'restrict',
            $this->foreignKeyOn('supplier_goods_receipts', 'supplier_purchase_order_id')['on_delete'],
        );
        $this->assertSame(
            'restrict',
            $this->foreignKeyOn('supplier_goods_receipt_lines', 'product_id')['on_delete'],
        );
    }

    public function test_all_migrated_index_and_foreign_key_names_fit_mysql_identifier_limit(): void
    {
        foreach (Schema::getTableListing() as $table) {
            if (str_starts_with($table, 'sqlite_')) {
                continue;
            }

            foreach (Schema::getIndexes($table) as $index) {
                $this->assertLessThanOrEqual(
                    64,
                    strlen((string) $index['name']),
                    $table.' index '.$index['name'],
                );
            }

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (! is_string($foreignKey['name']) || $foreignKey['name'] === '') {
                    continue;
                }

                $this->assertLessThanOrEqual(
                    64,
                    strlen($foreignKey['name']),
                    $table.' fk '.$foreignKey['name'],
                );
            }
        }
    }

    public function test_goods_receipt_migration_recreates_missing_lines_table(): void
    {
        Schema::dropIfExists('supplier_goods_receipt_lines');
        $this->assertFalse(Schema::hasTable('supplier_goods_receipt_lines'));

        $this->loadGoodsReceiptMigration()->up();

        $this->assertTrue(Schema::hasTable('supplier_goods_receipt_lines'));
        $this->assertTrue(Schema::hasIndex('supplier_goods_receipt_lines', 'sgr_lines_receipt_sort_idx'));
        $this->assertNotSame(
            [],
            $this->foreignKeyOn('supplier_goods_receipt_lines', 'supplier_purchase_order_line_id'),
        );
    }

    public function test_goods_receipt_migration_adds_missing_short_header_index(): void
    {
        Schema::table('supplier_goods_receipts', function (Blueprint $table): void {
            $table->dropIndex('sgr_po_received_at_idx');
        });
        $this->assertFalse(Schema::hasIndex('supplier_goods_receipts', 'sgr_po_received_at_idx'));

        $this->loadGoodsReceiptMigration()->up();

        $this->assertTrue(Schema::hasIndex('supplier_goods_receipts', 'sgr_po_received_at_idx'));
    }

    private function loadGoodsReceiptMigration(): object
    {
        return require database_path('migrations/2026_10_18_140000_create_supplier_goods_receipts_tables.php');
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
