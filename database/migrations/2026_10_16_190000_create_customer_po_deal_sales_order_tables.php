<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 Prompt 1 — Customer PO + Deal (1:1 ORG3) + Sales Orders (PF7).
 * SO always requires customer_po_id. Deal close UI deferred to Phase 7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('quotation_id')->constrained('quotations')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('reference_ar', 255)->nullable();
            $table->string('notes_ar', 2000)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index('quotation_id');
        });

        Schema::create('customer_purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_purchase_order_id')->constrained('customer_purchase_orders')->cascadeOnDelete();
            $table->foreignId('quotation_line_id')->constrained('quotation_lines')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['customer_purchase_order_id', 'quotation_line_id'], 'cpo_lines_po_quote_line_unique');
        });

        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('customer_purchase_order_id')->unique()->constrained('customer_purchase_orders')->restrictOnDelete();
            $table->string('status', 32);
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('customer_purchase_order_id')->constrained('customer_purchase_orders')->restrictOnDelete();
            $table->foreignId('deal_id')->constrained('deals')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('notes_ar', 2000)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision_notes', 1000)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('customer_purchase_order_id');
            $table->index('created_by_user_id');
        });

        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignId('customer_purchase_order_line_id')->constrained('customer_purchase_order_lines')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['sales_order_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_lines');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('deals');
        Schema::dropIfExists('customer_purchase_order_lines');
        Schema::dropIfExists('customer_purchase_orders');
    }
};
