<?php

namespace App\Models;

use App\Support\Finance\CollectionStatus;
use Database\Factories\CollectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'customer_id',
    'created_by_user_id',
    'status',
    'amount_total',
    'notes_ar',
    'confirmed_at',
    'confirmed_by_user_id',
    'reversed_at',
    'reversed_by_user_id',
])]
class Collection extends Model
{
    /** @use HasFactory<CollectionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => CollectionStatus::class,
            'amount_total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CollectionAllocation::class);
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(CollectionReversal::class);
    }

    public function isDraft(): bool
    {
        return $this->status === CollectionStatus::Draft;
    }

    public function isConfirmed(): bool
    {
        return $this->status === CollectionStatus::Confirmed;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->can('collections.record') && ! $user->can('invoices.view')) {
            return $query->whereRaw('0 = 1');
        }

        // Accounts + SM (via invoices All) + Exec: show all; Rep Cust via customer assignment.
        $scope = $user->permissionScope('invoices.view');

        return match ($scope) {
            \App\Support\Authorization\RecordScope::Cust,
            \App\Support\Authorization\RecordScope::Assigned => $query->whereHas(
                'customer',
                fn (Builder $q) => $q->where('assigned_sales_rep_id', $user->id)
            ),
            \App\Support\Authorization\RecordScope::All,
            \App\Support\Authorization\RecordScope::Admin => $query,
            default => $user->can('collections.record') ? $query : $query->whereRaw('0 = 1'),
        };
    }
}
