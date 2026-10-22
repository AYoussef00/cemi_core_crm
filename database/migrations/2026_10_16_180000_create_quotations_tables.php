<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 Prompt 2 — VAT-inclusive quotations (PM1 / PM4b / TX8 / TX2).
 * No discount columns. Cost + margin snapshotted on lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_request_id')->constrained('sales_requests')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('supersedes_quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->string('status', 32);
            $table->timestamp('expires_at')->nullable();
            $table->string('notes_ar', 2000)->nullable();
            $table->decimal('vat_rate', 5, 4)->default('0.1500');
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_notes', 1000)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to_email', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('customer_id');
            $table->index('sales_request_id');
        });

        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('sales_request_line_id')->nullable()->constrained('sales_request_lines')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name_ar', 255);
            $table->decimal('quantity', 15, 3);
            $table->decimal('supplier_cost', 15, 2);
            $table->decimal('margin_amount', 15, 2)->default(0);
            $table->decimal('selling_price_vat_inclusive', 15, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['quotation_id', 'sort_order']);
        });

        Schema::create('quotation_send_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email', 255);
            $table->string('status', 32);
            $table->string('error_message', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['quotation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_send_logs');
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('quotations');
    }
};
