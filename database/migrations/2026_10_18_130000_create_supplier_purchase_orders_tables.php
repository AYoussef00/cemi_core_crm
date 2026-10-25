<?php

use App\Support\SchemaConstraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROC-BUY-ON-SO / PROC-SPO-AR / PROC-RECV — supplier purchase orders for stock shortfall.
 *
 * Foreign key and index names are explicit: MySQL identifiers are limited to 64 characters.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_purchase_orders')) {
            Schema::create('supplier_purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_order_id')
                    ->nullable()
                    ->constrained('sales_orders', 'id', 'spo_sales_order_fk')
                    ->nullOnDelete();
                $table->string('status', 32);
                $table->string('supplier_name_ar', 255)->nullable();
                $table->timestamp('expected_at')->nullable();
                $table->string('notes_ar', 2000)->nullable();
                $table->foreignId('created_by_user_id')
                    ->constrained('users', 'id', 'spo_created_by_fk')
                    ->restrictOnDelete();
                $table->timestamp('ordered_at')->nullable();
                $table->foreignId('received_by_user_id')
                    ->nullable()
                    ->constrained('users', 'id', 'spo_received_by_fk')
                    ->nullOnDelete();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at'], 'spo_status_created_idx');
                $table->index('sales_order_id', 'spo_sales_order_idx');
            });
        } else {
            $this->ensureSupplierPurchaseOrdersConstraints();
        }

        if (! Schema::hasTable('supplier_purchase_order_lines')) {
            Schema::create('supplier_purchase_order_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_purchase_order_id')
                    ->constrained('supplier_purchase_orders', 'id', 'spo_lines_po_fk')
                    ->cascadeOnDelete();
                $table->foreignId('product_id')
                    ->constrained('products', 'id', 'spo_lines_product_fk')
                    ->restrictOnDelete();
                $table->foreignId('sales_order_line_id')
                    ->nullable()
                    ->constrained('sales_order_lines', 'id', 'spo_lines_so_line_fk')
                    ->nullOnDelete();
                $table->decimal('quantity', 15, 3);
                $table->decimal('received_quantity', 15, 3)->default(0);
                $table->decimal('unit_cost', 15, 2)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['supplier_purchase_order_id', 'sort_order'], 'spo_lines_po_sort_idx');
            });
        } else {
            $this->ensureSupplierPurchaseOrderLinesConstraints();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_purchase_order_lines');
        Schema::dropIfExists('supplier_purchase_orders');
    }

    /**
     * Complete constraints if CREATE TABLE succeeded but a later ALTER failed (MySQL DDL auto-commits).
     */
    private function ensureSupplierPurchaseOrdersConstraints(): void
    {
        $this->ensureForeignKey(
            'supplier_purchase_orders',
            'sales_order_id',
            'spo_sales_order_fk',
            'sales_orders',
            'set null',
        );
        $this->ensureForeignKey(
            'supplier_purchase_orders',
            'created_by_user_id',
            'spo_created_by_fk',
            'users',
            'restrict',
        );
        $this->ensureForeignKey(
            'supplier_purchase_orders',
            'received_by_user_id',
            'spo_received_by_fk',
            'users',
            'set null',
        );
        $this->ensureIndex(
            'supplier_purchase_orders',
            ['status', 'created_at'],
            'spo_status_created_idx',
        );
        $this->ensureIndex(
            'supplier_purchase_orders',
            ['sales_order_id'],
            'spo_sales_order_idx',
        );
    }

    /**
     * Complete constraints if CREATE TABLE succeeded but a later ALTER failed (MySQL DDL auto-commits).
     */
    private function ensureSupplierPurchaseOrderLinesConstraints(): void
    {
        $this->ensureForeignKey(
            'supplier_purchase_order_lines',
            'supplier_purchase_order_id',
            'spo_lines_po_fk',
            'supplier_purchase_orders',
            'cascade',
        );
        $this->ensureForeignKey(
            'supplier_purchase_order_lines',
            'product_id',
            'spo_lines_product_fk',
            'products',
            'restrict',
        );
        $this->ensureForeignKey(
            'supplier_purchase_order_lines',
            'sales_order_line_id',
            'spo_lines_so_line_fk',
            'sales_order_lines',
            'set null',
        );
        $this->ensureIndex(
            'supplier_purchase_order_lines',
            ['supplier_purchase_order_id', 'sort_order'],
            'spo_lines_po_sort_idx',
        );
    }

    private function ensureForeignKey(
        string $table,
        string $column,
        string $name,
        string $referencedTable,
        string $onDelete,
    ): void {
        if (SchemaConstraints::foreignKeyExists($table, $column, $referencedTable)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $name, $referencedTable, $onDelete): void {
            $foreign = $blueprint->foreign($column, $name)
                ->references('id')
                ->on($referencedTable);

            if ($onDelete === 'cascade') {
                $foreign->cascadeOnDelete();
            } elseif ($onDelete === 'set null') {
                $foreign->nullOnDelete();
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
        if (SchemaConstraints::indexExistsOnColumns($table, $columns)
            || SchemaConstraints::indexExistsByName($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
            $blueprint->index($columns, $name);
        });
    }
};
