<?php

namespace App\Models;

use App\Support\Authorization\RecordScope;
use App\Support\SalesRequests\LineAvailability;
use App\Support\SalesRequests\SalesRequestStatus;
use Database\Factories\SalesRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_id',
    'origin_visit_id',
    'created_by_user_id',
    'status',
    'notes_ar',
    'submitted_at',
    'cancelled_at',
    'cancelled_by_user_id',
])]
class SalesRequest extends Model
{
    /** @use HasFactory<SalesRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SalesRequestStatus::class,
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function originVisit(): BelongsTo
    {
        return $this->belongsTo(CustomerVisit::class, 'origin_visit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesRequestLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function procurementTasks(): HasMany
    {
        return $this->hasMany(ProcurementTask::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function isDraft(): bool
    {
        return $this->status === SalesRequestStatus::Draft;
    }

    public function needsProcurement(): bool
    {
        return $this->lines->contains(
            fn (SalesRequestLine $line) => $line->availability === LineAvailability::NeedsProcurement
        );
    }

    /**
     * @return array{
     *     assigned_sales_rep_id: int|null,
     *     is_referred_to_procurement: bool,
     *     is_work_related: bool
     * }
     */
    public function authorizationContext(): array
    {
        $this->loadMissing(['customer', 'lines']);

        // Referred only after submit creates procurement tasks (not mere draft flags).
        $hasTasks = $this->relationLoaded('procurementTasks')
            ? $this->procurementTasks->isNotEmpty()
            : $this->procurementTasks()->exists();

        return [
            'assigned_sales_rep_id' => $this->customer?->assigned_sales_rep_id,
            'is_referred_to_procurement' => $hasTasks,
            // Accounts Work: empty until later commercial links exist.
            'is_work_related' => false,
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $scope = $user->permissionScope('sales_requests.view');

        if ($scope === null && ! $user->can('sales_requests.view')) {
            return $query->whereRaw('0 = 1');
        }

        return match ($scope) {
            RecordScope::Cust, RecordScope::Assigned => $query->whereHas(
                'customer',
                fn (Builder $q) => $q->where('assigned_sales_rep_id', $user->id)
            ),
            RecordScope::All, RecordScope::Admin => $query,
            RecordScope::Referred => $query->whereHas('procurementTasks'),
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
            $inner->where('notes_ar', 'like', $like)
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name_ar', 'like', $like))
                ->orWhereHas('lines.product', fn (Builder $p) => $p->where('name_ar', 'like', $like));
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
