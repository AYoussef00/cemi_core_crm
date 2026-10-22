<?php

namespace App\Models;

use App\Support\Authorization\RecordScope;
use App\Support\Visits\VisitStatus;
use Database\Factories\CustomerVisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'assigned_user_id',
    'created_by_user_id',
    'scheduled_at',
    'status',
    'notes',
    'outcome',
    'customer_needs',
    'completed_at',
    'cancelled_at',
])]
class CustomerVisit extends Model
{
    /** @use HasFactory<CustomerVisitFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => VisitStatus::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function statusLabelAr(): string
    {
        return $this->status instanceof VisitStatus
            ? $this->status->labelAr()
            : (string) $this->status;
    }

    public function isScheduled(): bool
    {
        return $this->status === VisitStatus::Scheduled;
    }

    /**
     * @return array{assigned_sales_rep_id: int|null, is_work_related: bool}
     */
    public function authorizationContext(): array
    {
        $customer = $this->relationLoaded('customer')
            ? $this->customer
            : $this->customer()->first();

        return [
            'assigned_sales_rep_id' => $customer?->assigned_sales_rep_id,
            'is_work_related' => false,
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $scope = $user->permissionScope('visits.view');

        if ($scope === null && ! $user->can('visits.view')) {
            return $query->whereRaw('0 = 1');
        }

        return match ($scope) {
            RecordScope::Cust, RecordScope::Assigned => $query->whereHas(
                'customer',
                fn (Builder $q) => $q->where('assigned_sales_rep_id', $user->id)
            ),
            RecordScope::All, RecordScope::Admin => $query,
            RecordScope::Work => $query->whereRaw('0 = 1'),
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
            $inner->where('notes', 'like', $like)
                ->orWhere('outcome', 'like', $like)
                ->orWhere('customer_needs', 'like', $like)
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name_ar', 'like', $like))
                ->orWhereHas('assignedUser', fn (Builder $u) => $u->where('name', 'like', $like));
        });
    }
}
