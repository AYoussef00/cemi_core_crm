<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 Prompt 3 — idempotent reserve/deduct/release without SO/delivery tables.
 * Callers supply a stable idempotency_key (e.g. "reserve:so:12", "deduct:delivery:4").
 * NULL keys remain allowed for adjustments (multiple NULLs permitted by UNIQUE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_ledger_entries', function (Blueprint $table) {
            $table->string('idempotency_key', 191)->nullable()->after('reference_id');
            $table->unique('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_ledger_entries', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
