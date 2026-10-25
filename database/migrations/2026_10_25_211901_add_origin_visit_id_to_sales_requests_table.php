<?php

use App\Support\SchemaConstraints;
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
                    ->after('customer_id');
            });
        }

        if (! SchemaConstraints::foreignKeyExists('sales_requests', 'origin_visit_id', 'customer_visits')) {
            Schema::table('sales_requests', function (Blueprint $table) {
                $table->foreign('origin_visit_id', 'sr_origin_visit_fk')
                    ->references('id')
                    ->on('customer_visits')
                    ->restrictOnDelete();
            });
        }

        if (! SchemaConstraints::indexExistsOnColumns('sales_requests', ['origin_visit_id'])
            && ! SchemaConstraints::indexExistsByName('sales_requests', 'sr_origin_visit_idx')) {
            Schema::table('sales_requests', function (Blueprint $table) {
                $table->index('origin_visit_id', 'sr_origin_visit_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sales_requests', 'origin_visit_id')) {
            return;
        }

        Schema::table('sales_requests', function (Blueprint $table) {
            foreach (Schema::getForeignKeys('sales_requests') as $foreignKey) {
                if (($foreignKey['columns'] ?? []) === ['origin_visit_id'] && is_string($foreignKey['name']) && $foreignKey['name'] !== '') {
                    $table->dropForeign($foreignKey['name']);
                }
            }

            if (SchemaConstraints::indexExistsByName('sales_requests', 'sr_origin_visit_idx')) {
                $table->dropIndex('sr_origin_visit_idx');
            }

            $table->dropColumn('origin_visit_id');
        });
    }
};
