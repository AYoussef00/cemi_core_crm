<?php

namespace Tests\Support\Inventory;

use Illuminate\Database\Eloquent\Model;

/**
 * Test-only morph target for ledger reference linkage until SalesOrder / Delivery exist.
 * Table is created in feature tests that need it.
 */
class FakeInventoryDocument extends Model
{
    protected $table = 'fake_inventory_documents';

    protected $guarded = [];
}
