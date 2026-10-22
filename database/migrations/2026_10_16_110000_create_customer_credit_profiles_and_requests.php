<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer credit profile (approved values only) + credit-limit change requests.
 * Exposure / order gate deferred until invoices and Sales Orders exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('approved_credit_limit', 15, 2)->nullable()->after('assigned_sales_rep_id');
            $table->string('approved_payment_term', 255)->nullable()->after('approved_credit_limit');
        });

        Schema::create('credit_limit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('requested_credit_limit', 15, 2);
            $table->string('requested_payment_term', 255)->nullable();
            $table->string('status', 32);
            $table->text('request_notes')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_limit_requests');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['approved_credit_limit', 'approved_payment_term']);
        });
    }
};
