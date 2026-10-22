<?php

namespace App\Models;

use App\Support\Finance\InvoiceNoteStatus;
use App\Support\Finance\InvoiceNoteType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id',
    'customer_id',
    'created_by_user_id',
    'type',
    'status',
    'amount',
    'reason_ar',
    'submitted_at',
    'decided_at',
    'decided_by_user_id',
    'decision_notes',
])]
class InvoiceNote extends Model
{
    protected function casts(): array
    {
        return [
            'type' => InvoiceNoteType::class,
            'status' => InvoiceNoteStatus::class,
            'amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceNoteStatus::Draft;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->can('credit_notes.create') && ! $user->can('credit_notes.approve') && ! $user->can('invoices.view')) {
            return $query->whereRaw('0 = 1');
        }

        $scope = $user->permissionScope('invoices.view');

        return match ($scope) {
            \App\Support\Authorization\RecordScope::Cust,
            \App\Support\Authorization\RecordScope::Assigned => $query->whereHas(
                'customer',
                fn (Builder $q) => $q->where('assigned_sales_rep_id', $user->id)
            ),
            \App\Support\Authorization\RecordScope::All,
            \App\Support\Authorization\RecordScope::Admin => $query,
            default => ($user->can('credit_notes.create') || $user->can('credit_notes.approve'))
                ? $query
                : $query->whereRaw('0 = 1'),
        };
    }
}
