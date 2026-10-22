<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v1: exactly one company and one warehouse (ORG1 / INV2).
 * Fields are limited to identity/contact; tax/VAT policy is intentionally omitted (TX8 open).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('singleton_key')->default('default')->unique();
            $table->string('name_ar');
            $table->string('address_ar')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('singleton_key')->default('default')->unique();
            $table->string('name_ar');
            $table->string('address_ar')->nullable();
            $table->string('contact_phone')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('companies');
    }
};
