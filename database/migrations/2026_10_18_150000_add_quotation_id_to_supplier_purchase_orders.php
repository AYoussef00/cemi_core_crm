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
                    ->after('sales_order_id')
                    ->constrained('quotations')
                    ->nullOnDelete();
                $table->index('quotation_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('supplier_purchase_orders', 'quotation_id')) {
            Schema::table('supplier_purchase_orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('quotation_id');
            });
        }
    }
};
