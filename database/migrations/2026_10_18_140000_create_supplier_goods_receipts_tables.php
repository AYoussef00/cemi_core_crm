<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROC-RECV-SHIP — one goods-receipt document per warehouse shipment against a supplier PO.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_goods_receipts')) {
            Schema::create('supplier_goods_receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_purchase_order_id')->constrained('supplier_purchase_orders')->restrictOnDelete();
                $table->foreignId('received_by_user_id')->constrained('users')->restrictOnDelete();
                $table->timestamp('received_at');
                $table->string('shipment_reference_ar', 255)->nullable();
                $table->string('notes_ar', 2000)->nullable();
                $table->timestamps();

                $table->index(['supplier_purchase_order_id', 'received_at']);
            });
        }

        if (! Schema::hasTable('supplier_goods_receipt_lines')) {
            Schema::create('supplier_goods_receipt_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_goods_receipt_id')->constrained('supplier_goods_receipts')->cascadeOnDelete();
                $table->foreignId('supplier_purchase_order_line_id')->constrained('supplier_purchase_order_lines')->restrictOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->decimal('quantity', 15, 3);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['supplier_goods_receipt_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_goods_receipt_lines');
        Schema::dropIfExists('supplier_goods_receipts');
    }
};
