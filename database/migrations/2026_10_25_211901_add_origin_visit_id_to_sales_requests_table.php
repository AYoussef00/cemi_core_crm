<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional origin visit on a sales request (handoff, not obligation).
 * Visit remains optional; completing a visit does not create a request.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales_requests', 'origin_visit_id')) {
            Schema::table('sales_requests', function (Blueprint $table) {
                $table->foreignId('origin_visit_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained('customer_visits')
                    ->restrictOnDelete();
                $table->index('origin_visit_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_requests', 'origin_visit_id')) {
            Schema::table('sales_requests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('origin_visit_id');
            });
        }
    }
};
