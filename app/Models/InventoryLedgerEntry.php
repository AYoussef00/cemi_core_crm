<?php

namespace App\Models;

use App\Support\Inventory\InventoryLedgerEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'warehouse_id',
    'product_id',
    'event_type',
    'quantity_delta',
    'on_hand_before',
    'on_hand_after',
    'reserved_before',
    'reserved_after',
    'reference_type',
    'reference_id',
    'idempotency_key',
    'actor_user_id',
    'properties',
    'created_at',
])]
class InventoryLedgerEntry extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'event_type' => InventoryLedgerEventType::class,
            'quantity_delta' => 'decimal:3',
            'on_hand_before' => 'decimal:3',
            'on_hand_after' => 'decimal:3',
            'reserved_before' => 'decimal:3',
            'reserved_after' => 'decimal:3',
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }
}
