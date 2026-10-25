<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROC-RECV-SHIP — one goods-receipt document per warehouse shipment against a supplier PO.
 *
 * Foreign key and index names are explicit: MySQL identifiers are limited to 64 characters,
 * and Laravel's default names for these tables exceed that limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_goods_receipts')) {
            Schema::create('supplier_goods_receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_purchase_order_id')
                    ->constrained('supplier_purchase_orders', 'id', 'sgr_po_fk')
                    ->restrictOnDelete();
                $table->foreignId('received_by_user_id')
                    ->constrained('users', 'id', 'sgr_received_by_fk')
                    ->restrictOnDelete();
                $table->timestamp('received_at');
                $table->string('shipment_reference_ar', 255)->nullable();
                $table->string('notes_ar', 2000)->nullable();
                $table->timestamps();

                $table->index(['supplier_purchase_order_id', 'received_at'], 'sgr_po_received_at_idx');
            });
        } else {
            $this->ensureSupplierGoodsReceiptsConstraints();
        }

        if (! Schema::hasTable('supplier_goods_receipt_lines')) {
            Schema::create('supplier_goods_receipt_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_goods_receipt_id')
                    ->constrained('supplier_goods_receipts', 'id', 'sgr_lines_receipt_fk')
                    ->cascadeOnDelete();
                $table->foreignId('supplier_purchase_order_line_id')
                    ->constrained('supplier_purchase_order_lines', 'id', 'sgr_lines_po_line_fk')
                    ->restrictOnDelete();
                $table->foreignId('product_id')
                    ->constrained('products', 'id', 'sgr_lines_product_fk')
                    ->restrictOnDelete();
                $table->decimal('quantity', 15, 3);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['supplier_goods_receipt_id', 'sort_order'], 'sgr_lines_receipt_sort_idx');
            });
        } else {
            $this->ensureSupplierGoodsReceiptLinesConstraints();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_goods_receipt_lines');
        Schema::dropIfExists('supplier_goods_receipts');
    }

    /**
     * Complete constraints if CREATE TABLE succeeded but a later ALTER failed (MySQL DDL auto-commits).
     */
    private function ensureSupplierGoodsReceiptsConstraints(): void
    {
        $this->ensureForeignKey(
            'supplier_goods_receipts',
            'supplier_purchase_order_id',
            'sgr_po_fk',
            'supplier_purchase_orders',
            cascadeOnDelete: false,
        );
        $this->ensureForeignKey(
            'supplier_goods_receipts',
            'received_by_user_id',
            'sgr_received_by_fk',
            'users',
            cascadeOnDelete: false,
        );
        $this->ensureIndex(
            'supplier_goods_receipts',
            ['supplier_purchase_order_id', 'received_at'],
            'sgr_po_received_at_idx',
        );
    }

    /**
     * Complete constraints if CREATE TABLE succeeded but a later ALTER failed (MySQL DDL auto-commits).
     */
    private function ensureSupplierGoodsReceiptLinesConstraints(): void
    {
        $this->ensureForeignKey(
            'supplier_goods_receipt_lines',
            'supplier_goods_receipt_id',
            'sgr_lines_receipt_fk',
            'supplier_goods_receipts',
            cascadeOnDelete: true,
        );
        $this->ensureForeignKey(
            'supplier_goods_receipt_lines',
            'supplier_purchase_order_line_id',
            'sgr_lines_po_line_fk',
            'supplier_purchase_order_lines',
            cascadeOnDelete: false,
        );
        $this->ensureForeignKey(
            'supplier_goods_receipt_lines',
            'product_id',
            'sgr_lines_product_fk',
            'products',
            cascadeOnDelete: false,
        );
        $this->ensureIndex(
            'supplier_goods_receipt_lines',
            ['supplier_goods_receipt_id', 'sort_order'],
            'sgr_lines_receipt_sort_idx',
        );
    }

    private function ensureForeignKey(
        string $table,
        string $column,
        string $name,
        string $referencedTable,
        bool $cascadeOnDelete,
    ): void {
        if (Schema::hasForeignKey($table, [$column])) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $name, $referencedTable, $cascadeOnDelete): void {
            $foreign = $blueprint->foreign($column, $name)
                ->references('id')
                ->on($referencedTable);

            if ($cascadeOnDelete) {
                $foreign->cascadeOnDelete();
            } else {
                $foreign->restrictOnDelete();
            }
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function ensureIndex(string $table, array $columns, string $name): void
    {
        if (Schema::hasIndex($table, $columns) || Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
            $blueprint->index($columns, $name);
        });
    }
};
