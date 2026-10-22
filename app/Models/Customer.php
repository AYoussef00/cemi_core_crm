<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Support\Authorization\RecordScope;
use App\Support\Authorization\RecordScopeResolver;
use App\Support\Authorization\RoleSlug;
use App\Support\Credit\CreditLimitRequestStatus;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name_ar',
    'assigned_sales_rep_id',
    'address_ar',
    'latitude',
    'longitude',
    'approved_credit_limit',
    'approved_payment_term',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasAttachments;
    use HasFactory;

    protected function casts(): array
    {
        return [
            'approved_credit_limit' => 'decimal:2',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function hasMapCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function assignedSalesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_sales_rep_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class)->orderBy('role')->orderBy('id');
    }

    public function creditLimitRequests(): HasMany
    {
        return $this->hasMany(CreditLimitRequest::class)->latest('id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(CustomerVisit::class)->orderByDesc('scheduled_at');
    }

    public function salesRequests(): HasMany
    {
        return $this->hasMany(SalesRequest::class)->latest('id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->latest('id');
    }

    public function customerPurchaseOrders(): HasMany
    {
        return $this->hasMany(CustomerPurchaseOrder::class)->latest('id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class)->latest('id');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class)->latest('id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->latest('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('id');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class)->latest('id');
    }

    public function openCreditLimitRequest(): ?CreditLimitRequest
    {
        return $this->creditLimitRequests()
            ->whereIn('status', [
                CreditLimitRequestStatus::PendingReview->value,
                CreditLimitRequestStatus::PendingFinal->value,
            ])
            ->first();
    }

    /**
     * @return array{assigned_sales_rep_id: int|null, is_work_related: bool}
     */
    public function authorizationContext(): array
    {
        return [
            'assigned_sales_rep_id' => $this->assigned_sales_rep_id,
            // Work-linked customers appear when operational modules attach later.
            'is_work_related' => false,
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $scope = $user->permissionScope('customers.view');

        if ($scope === null && ! $user->can('customers.view')) {
            return $query->whereRaw('0 = 1');
        }

        return match ($scope) {
            RecordScope::Assigned, RecordScope::Cust => $query->where('assigned_sales_rep_id', $user->id),
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
            $inner->where('name_ar', 'like', $like)
                ->orWhereHas('contacts', function (Builder $contacts) use ($like): void {
                    $contacts->where('name_ar', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
        });
    }

    public function isAccessibleBy(User $user, string $permission = 'customers.view'): bool
    {
        if (! $user->can($permission)) {
            return false;
        }

        return app(RecordScopeResolver::class)->allowsRecord(
            $user,
            $permission,
            $this->authorizationContext(),
        );
    }

    public static function salesRepresentativeOptions(): Builder
    {
        return User::query()
            ->whereHas('role', fn (Builder $q) => $q->where('slug', RoleSlug::SalesRepresentative->value))
            ->where('is_active', true)
            ->orderBy('name');
    }
}
