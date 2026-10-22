<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROC-BUY-ON-SO / PROC-SPO-AR / PROC-RECV — supplier purchase orders for stock shortfall.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_purchase_orders')) {
            Schema::create('supplier_purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
                $table->string('status', 32);
                $table->string('supplier_name_ar', 255)->nullable();
                $table->timestamp('expected_at')->nullable();
                $table->string('notes_ar', 2000)->nullable();
                $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
                $table->timestamp('ordered_at')->nullable();
                $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index('sales_order_id');
            });
        }

        if (! Schema::hasTable('supplier_purchase_order_lines')) {
            Schema::create('supplier_purchase_order_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_purchase_order_id')->constrained('supplier_purchase_orders')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines')->nullOnDelete();
                $table->decimal('quantity', 15, 3);
                $table->decimal('received_quantity', 15, 3)->default(0);
                $table->decimal('unit_cost', 15, 2)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['supplier_purchase_order_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_purchase_order_lines');
        Schema::dropIfExists('supplier_purchase_orders');
    }
};
