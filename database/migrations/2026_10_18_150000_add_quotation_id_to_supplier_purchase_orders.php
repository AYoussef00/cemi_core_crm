<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROC-BUY-ON-QUOTE — link supplier POs created at quotation approve.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('supplier_purchase_orders', 'quotation_id')) {
            Schema::table('supplier_purchase_orders', function (Blueprint $table) {
                $table->foreignId('quotation_id')
                    ->nullable()
                    ->after('sales_order_id');
            });
        }

        if (! Schema::hasForeignKey('supplier_purchase_orders', ['quotation_id'])) {
            Schema::table('supplier_purchase_orders', function (Blueprint $table) {
                $table->foreign('quotation_id', 'spo_quotation_fk')
                    ->references('id')
                    ->on('quotations')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasIndex('supplier_purchase_orders', ['quotation_id'])
            && ! Schema::hasIndex('supplier_purchase_orders', 'spo_quotation_idx')) {
            Schema::table('supplier_purchase_orders', function (Blueprint $table) {
                $table->index('quotation_id', 'spo_quotation_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('supplier_purchase_orders', 'quotation_id')) {
            return;
        }

        Schema::table('supplier_purchase_orders', function (Blueprint $table) {
            foreach (Schema::getForeignKeys('supplier_purchase_orders') as $foreignKey) {
                if ($foreignKey['columns'] === ['quotation_id'] && is_string($foreignKey['name']) && $foreignKey['name'] !== '') {
                    $table->dropForeign($foreignKey['name']);
                }
            }

            if (Schema::hasIndex('supplier_purchase_orders', 'spo_quotation_idx')) {
                $table->dropIndex('spo_quotation_idx');
            }

            $table->dropColumn('quotation_id');
        });
    }
};
