<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer visits (Phase 2 Prompt 4).
 * Minimal states: scheduled → completed | cancelled.
 * No GPS, recurrence, or route optimization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('status', 32);
            $table->text('notes')->nullable();
            $table->text('outcome')->nullable();
            $table->text('customer_needs')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('customer_id');
            $table->index('assigned_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_visits');
    }
};
