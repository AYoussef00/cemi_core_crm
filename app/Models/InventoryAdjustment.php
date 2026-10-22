<?php

namespace App\Models;

use App\Support\Inventory\InventoryAdjustmentStatus;
use Database\Factories\InventoryAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'warehouse_id',
    'product_id',
    'quantity_delta',
    'reason_ar',
    'status',
    'recorded_by_user_id',
    'decided_by_user_id',
    'decided_at',
    'decision_notes',
])]
class InventoryAdjustment extends Model
{
    /** @use HasFactory<InventoryAdjustmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'status' => InventoryAdjustmentStatus::class,
            'decided_at' => 'datetime',
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isDecrease(): bool
    {
        return (float) $this->quantity_delta < 0;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('reason_ar', 'like', $like)
                ->orWhereHas('product', fn (Builder $p) => $p->where('name_ar', 'like', $like));
        });
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if ($status === null || $status === '') {
            return $query;
        }

        return $query->where('status', $status);
    }
}
