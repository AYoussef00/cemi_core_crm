<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Prompt 2 — single-warehouse stock balances, adjustments, append-only ledger.
 * Available is computed (on_hand - reserved). INV3: reject insufficient decreases.
 * Quantities: DECIMAL(15, 3) — see docs/INVENTORY.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('on_hand', 15, 3)->default(0);
            $table->decimal('reserved', 15, 3)->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'product_id']);
        });

        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity_delta', 15, 3);
            $table->string('reason_ar', 1000);
            $table->string('status', 32);
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_notes', 1000)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('product_id');
        });

        Schema::create('inventory_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('event_type', 32);
            $table->decimal('quantity_delta', 15, 3);
            $table->decimal('on_hand_before', 15, 3);
            $table->decimal('on_hand_after', 15, 3);
            $table->decimal('reserved_before', 15, 3);
            $table->decimal('reserved_after', 15, 3);
            $table->string('reference_type', 64)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_ledger_entries');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('product_stock_balances');
    }
};
