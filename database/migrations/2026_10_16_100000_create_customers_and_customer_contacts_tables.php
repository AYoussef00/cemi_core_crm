<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer records + contacts (Phase 2 Prompt 1).
 * Conservative fields only — no customer number (NUM*), no org type (ORG6),
 * no credit limit workflow, no locations/maps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->foreignId('assigned_sales_rep_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('name_ar');
            $table->index('assigned_sales_rep_id');
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            /** @see App\Support\Customers\CustomerContactRole */
            $table->string('role', 32);
            $table->string('name_ar');
            $table->string('phone', 64)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'role']);
            $table->index('name_ar');
            $table->index('phone');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customers');
    }
};
