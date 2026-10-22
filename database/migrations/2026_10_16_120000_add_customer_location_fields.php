<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer site location (Phase 2 Prompt 3).
 * Confirmed fields only: address + latitude/longitude. No branches/geocoding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('address_ar')->nullable()->after('assigned_sales_rep_id');
            $table->decimal('latitude', 10, 7)->nullable()->after('address_ar');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['address_ar', 'latitude', 'longitude']);
        });
    }
};
