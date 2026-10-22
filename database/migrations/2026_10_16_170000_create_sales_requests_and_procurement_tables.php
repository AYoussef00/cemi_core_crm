<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 Prompt 1 — sales requests + procurement tasks for unavailable lines.
 * Availability is an explicit line choice (auto-from-stock still OPEN).
 * Statuses: draft → awaiting_procurement | ready_for_pricing → cancelled.
 * No quotation_linked until Prompt 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('notes_ar', 2000)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('customer_id');
        });

        Schema::create('sales_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_request_id')->constrained('sales_requests')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->string('availability', 32);
            $table->decimal('supplier_cost', 15, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['sales_request_id', 'sort_order']);
        });

        Schema::create('procurement_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_request_id')->constrained('sales_requests')->restrictOnDelete();
            $table->foreignId('sales_request_line_id')->constrained('sales_request_lines')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('status', 32);
            $table->decimal('supplier_cost', 15, 2)->nullable();
            $table->string('supplier_name_ar', 255)->nullable();
            $table->string('notes_ar', 1000)->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cost_recorded_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique('sales_request_line_id');
            $table->index(['status', 'created_at']);
            $table->index('sales_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_tasks');
        Schema::dropIfExists('sales_request_lines');
        Schema::dropIfExists('sales_requests');
    }
};
