<?php

namespace App\Models;

use App\Support\Authorization\RecordScope;
use App\Support\Sales\SalesOrderNextAction;
use App\Support\Sales\SalesOrderStatus;
use App\Support\Warehouse\DeliveryStatus;
use App\Support\Warehouse\FulfillmentStatus;
use App\Support\Warehouse\PreparationStatus;
use Database\Factories\SalesOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_id',
    'customer_purchase_order_id',
    'deal_id',
    'created_by_user_id',
    'status',
    'preparation_status',
    'preparation_notes_ar',
    'preparation_ready_at',
    'preparation_ready_by_user_id',
    'fulfillment_status',
    'notes_ar',
    'submitted_at',
    'submitted_by_user_id',
    'decided_at',
    'decided_by_user_id',
    'decision_notes',
    'cancelled_at',
    'cancelled_by_user_id',
])]
class SalesOrder extends Model
{
    /** @use HasFactory<SalesOrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SalesOrderStatus::class,
            'preparation_status' => PreparationStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'preparation_ready_at' => 'datetime',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerPurchaseOrder::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function preparationReadyBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preparation_ready_by_user_id');
    }

    public function isDraft(): bool
    {
        return $this->status === SalesOrderStatus::Draft;
    }

    public function isApproved(): bool
    {
        return $this->status === SalesOrderStatus::Approved;
    }

    public function isPreparationReady(): bool
    {
        return $this->preparation_status === PreparationStatus::Ready;
    }

    public function isFullyDelivered(): bool
    {
        return $this->fulfillment_status === FulfillmentStatus::FullyDelivered;
    }

    public function nextAction(): SalesOrderNextAction
    {
        return SalesOrderNextAction::for($this);
    }

    /**
     * Confirmed delivered qty for a sales order line.
     */
    public function confirmedDeliveredQuantityForLine(SalesOrderLine $line): float
    {
        return (float) DeliveryLine::query()
            ->where('sales_order_line_id', $line->id)
            ->whereHas('delivery', fn (Builder $q) => $q->where('status', DeliveryStatus::Confirmed->value))
            ->sum('quantity');
    }

    /**
     * Qty on open (draft/pending_pod) deliveries for a line — blocks over-commit.
     */
    public function inFlightDeliveryQuantityForLine(SalesOrderLine $line, ?Delivery $exclude = null): float
    {
        return (float) DeliveryLine::query()
            ->where('sales_order_line_id', $line->id)
            ->when($exclude, fn ($q) => $q->where('delivery_id', '!=', $exclude->id))
            ->whereHas('delivery', fn (Builder $q) => $q->whereIn('status', [
                DeliveryStatus::Draft->value,
                DeliveryStatus::PendingPod->value,
            ]))
            ->sum('quantity');
    }

    public function undeliveredQuantityForLine(SalesOrderLine $line, ?Delivery $excludeOpen = null): float
    {
        $confirmed = $this->confirmedDeliveredQuantityForLine($line);
        $inFlight = $this->inFlightDeliveryQuantityForLine($line, $excludeOpen);

        return max(0.0, (float) $line->quantity - $confirmed - $inFlight);
    }

    public function refreshFulfillmentStatus(): void
    {
        $this->loadMissing('lines');
        $anyDelivered = false;
        $allFully = true;

        foreach ($this->lines as $line) {
            $delivered = $this->confirmedDeliveredQuantityForLine($line);
            if ($delivered > 1e-9) {
                $anyDelivered = true;
            }
            if ($delivered + 1e-9 < (float) $line->quantity) {
                $allFully = false;
            }
        }

        $status = match (true) {
            $allFully && $anyDelivered => FulfillmentStatus::FullyDelivered,
            $anyDelivered => FulfillmentStatus::PartiallyDelivered,
            default => FulfillmentStatus::Pending,
        };

        if ($this->fulfillment_status !== $status) {
            $this->fill(['fulfillment_status' => $status])->save();
        }
    }

    /**
     * @return array{owner_user_id: int|null, assigned_sales_rep_id: int|null, is_referred_to_procurement: bool}
     */
    public function authorizationContext(): array
    {
        $this->loadMissing('customer');

        return [
            'owner_user_id' => $this->created_by_user_id,
            'assigned_sales_rep_id' => $this->customer?->assigned_sales_rep_id,
            'is_referred_to_procurement' => false,
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $scope = $user->permissionScope('sales_orders.view');

        if ($scope === null && ! $user->can('sales_orders.view')) {
            return $query->whereRaw('0 = 1');
        }

        return match ($scope) {
            RecordScope::Own => $query->where('created_by_user_id', $user->id),
            RecordScope::Cust, RecordScope::Assigned => $query->whereHas(
                'customer',
                fn (Builder $q) => $q->where('assigned_sales_rep_id', $user->id)
            ),
            RecordScope::All, RecordScope::Admin => $query,
            RecordScope::Referred => $query->whereRaw('0 = 1'),
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
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name_ar', 'like', $like));
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
