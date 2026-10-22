<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Support\Authorization\RecordScope;
use App\Support\Procurement\ProcurementTaskStatus;
use Database\Factories\ProcurementTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sales_request_id',
    'sales_request_line_id',
    'product_id',
    'status',
    'supplier_cost',
    'supplier_name_ar',
    'notes_ar',
    'recorded_by_user_id',
    'cost_recorded_at',
    'cancelled_by_user_id',
    'cancelled_at',
])]
class ProcurementTask extends Model
{
    /** @use HasFactory<ProcurementTaskFactory> */
    use HasAttachments;
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ProcurementTaskStatus::class,
            'supplier_cost' => 'decimal:2',
            'cost_recorded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function salesRequest(): BelongsTo
    {
        return $this->belongsTo(SalesRequest::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(SalesRequestLine::class, 'sales_request_line_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function isOpen(): bool
    {
        return $this->status === ProcurementTaskStatus::Open;
    }

    /**
     * @return array{assigned_sales_rep_id: int|null, is_referred_to_procurement: bool}
     */
    public function authorizationContext(): array
    {
        $this->loadMissing('salesRequest.customer');

        return [
            'assigned_sales_rep_id' => $this->salesRequest?->customer?->assigned_sales_rep_id,
            'is_referred_to_procurement' => true,
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->can('procurement.view')) {
            return $query->whereRaw('0 = 1');
        }

        $scope = $user->permissionScope('procurement.view');

        // Boolean grant (Procurement role): all tasks.
        if ($scope === null) {
            return $query;
        }

        return match ($scope) {
            RecordScope::All, RecordScope::Admin => $query,
            default => $query->whereRaw('0 = 1'),
        };
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('supplier_name_ar', 'like', $like)
                ->orWhere('notes_ar', 'like', $like)
                ->orWhereHas('product', fn (Builder $p) => $p->where('name_ar', 'like', $like))
                ->orWhereHas('salesRequest.customer', fn (Builder $c) => $c->where('name_ar', 'like', $like));
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
