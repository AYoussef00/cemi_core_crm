<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Prompt 1 — product catalog, units of measure, technical specifications.
 * No SKU/numbering, stock, conversions, categories, or selling prices.
 * Money: decimal(15, 2) matching customer credit-limit convention (see docs/MONEY.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 255);
            $table->string('symbol_ar', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name_ar');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 255);
            $table->foreignId('unit_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('purchase_cost', 15, 2)->nullable();
            $table->timestamps();

            $table->index('name_ar');
        });

        Schema::create('product_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('label_ar', 255);
            $table->string('value', 255);
            $table->string('unit_ar', 64)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_specifications');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units_of_measure');
    }
};
