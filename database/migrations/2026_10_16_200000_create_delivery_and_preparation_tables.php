<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 Prompt 1 — Preparation flags on SO + multi-delivery + POD (attachments) + deduct on confirm.
 * No driver GPS / dispatch states.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('preparation_status', 32)->default('pending')->after('status');
            $table->string('preparation_notes_ar', 2000)->nullable()->after('preparation_status');
            $table->timestamp('preparation_ready_at')->nullable()->after('preparation_notes_ar');
            $table->foreignId('preparation_ready_by_user_id')->nullable()->after('preparation_ready_at')
                ->constrained('users')->nullOnDelete();
            $table->string('fulfillment_status', 32)->default('pending')->after('preparation_ready_by_user_id');
            $table->index(['status', 'preparation_status']);
            $table->index(['status', 'fulfillment_status']);
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('notes_ar', 2000)->nullable();
            $table->timestamp('pending_pod_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_notes_ar', 1000)->nullable();
            $table->timestamps();

            $table->index(['sales_order_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('delivery_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
            $table->foreignId('sales_order_line_id')->constrained('sales_order_lines')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['delivery_id', 'sales_order_line_id'], 'delivery_lines_delivery_so_line_unique');
            $table->index(['sales_order_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_lines');
        Schema::dropIfExists('deliveries');

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preparation_ready_by_user_id');
            $table->dropColumn([
                'preparation_status',
                'preparation_notes_ar',
                'preparation_ready_at',
                'fulfillment_status',
            ]);
        });
    }
};
