<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 Prompt 1 — Invoice per confirmed delivery (PF3), collections (CR7),
 * credit/debit notes, deal close support (TX5b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->unique()->constrained('deliveries')->restrictOnDelete();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->restrictOnDelete();
            $table->foreignId('deal_id')->constrained('deals')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 32);
            $table->decimal('total_vat_inclusive', 15, 2);
            $table->timestamp('issued_at');
            $table->timestamp('due_at')->nullable();
            $table->string('notes_ar', 2000)->nullable();
            $table->timestamp('last_overdue_reminded_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['deal_id', 'status']);
            $table->index(['due_at', 'status']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('delivery_line_id')->constrained('delivery_lines')->restrictOnDelete();
            $table->foreignId('sales_order_line_id')->constrained('sales_order_lines')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name_ar', 255);
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price_vat_inclusive', 15, 2);
            $table->decimal('line_total_vat_inclusive', 15, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['invoice_id', 'delivery_line_id'], 'invoice_lines_invoice_delivery_line_unique');
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 32);
            $table->decimal('amount_total', 15, 2);
            $table->string('notes_ar', 2000)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        Schema::create('collection_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['collection_id', 'invoice_id'], 'collection_alloc_collection_invoice_unique');
        });

        Schema::create('collection_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('collections')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('reason_ar', 2000)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision_notes', 1000)->nullable();
            $table->timestamps();

            $table->index(['collection_id', 'status']);
        });

        Schema::create('invoice_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 16);
            $table->string('status', 32);
            $table->decimal('amount', 15, 2);
            $table->string('reason_ar', 2000)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision_notes', 1000)->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_notes');
        Schema::dropIfExists('collection_reversals');
        Schema::dropIfExists('collection_allocations');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
