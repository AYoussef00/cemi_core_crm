<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasDocumentNumber;
use App\Support\Authorization\RecordScope;
use App\Support\Documents\DocumentNumber;
use App\Support\Finance\InvoiceStatus;
use App\Support\Sales\CustomerPurchaseOrderStatus;
use App\Support\Sales\SalesOrderStatus;
use App\Support\Warehouse\DeliveryStatus;
use Database\Factories\CustomerPurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'customer_id',
    'quotation_id',
    'created_by_user_id',
    'status',
    'reference_ar',
    'notes_ar',
    'cancelled_at',
    'cancelled_by_user_id',
])]
class CustomerPurchaseOrder extends Model
{
    /** @use HasFactory<CustomerPurchaseOrderFactory> */
    use HasAttachments;

    use HasDocumentNumber;
    use HasFactory;

    public static function documentNumberPrefix(): string
    {
        return DocumentNumber::PREFIX_PURCHASE_ORDER;
    }

    protected function casts(): array
    {
        return [
            'status' => CustomerPurchaseOrderStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
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
        return $this->hasMany(CustomerPurchaseOrderLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function deal(): HasOne
    {
        return $this->hasOne(Deal::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function isOpen(): bool
    {
        return $this->status === CustomerPurchaseOrderStatus::Open;
    }

    /**
     * @return array{assigned_sales_rep_id: int|null, is_work_related: bool}
     */
    public function authorizationContext(): array
    {
        $this->loadMissing('customer');

        return [
            'assigned_sales_rep_id' => $this->customer?->assigned_sales_rep_id,
            'is_work_related' => false,
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $scope = $user->permissionScope('customer_pos.view');

        if ($scope === null && ! $user->can('customer_pos.view')) {
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
            $inner->where('reference_ar', 'like', $like)
                ->orWhere('notes_ar', 'like', $like)
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name_ar', 'like', $like));
        });
    }

    /**
     * Sum of SO line qtys for this PO line on draft/pending/approved SOs.
     */
    public function allocatedQuantityForLine(CustomerPurchaseOrderLine $poLine): float
    {
        return (float) SalesOrderLine::query()
            ->where('customer_purchase_order_line_id', $poLine->id)
            ->whereHas('salesOrder', function (Builder $q): void {
                $q->whereIn('status', [
                    SalesOrderStatus::Draft->value,
                    SalesOrderStatus::PendingApproval->value,
                    SalesOrderStatus::Approved->value,
                ]);
            })
            ->sum('quantity');
    }

    public function unallocatedQuantityForLine(CustomerPurchaseOrderLine $poLine): float
    {
        return max(0.0, (float) $poLine->quantity - $this->allocatedQuantityForLine($poLine));
    }

    /** Confirmed deliveries linked to this PO line (PF1 غير مسلّم). */
    public function deliveredQuantityForLine(CustomerPurchaseOrderLine $poLine): float
    {
        return (float) DeliveryLine::query()
            ->whereHas(
                'salesOrderLine',
                fn (Builder $q) => $q->where('customer_purchase_order_line_id', $poLine->id)
            )
            ->whereHas(
                'delivery',
                fn (Builder $q) => $q->where('status', DeliveryStatus::Confirmed->value)
            )
            ->sum('quantity');
    }

    public function undeliveredQuantityForLine(CustomerPurchaseOrderLine $poLine): float
    {
        return max(0.0, (float) $poLine->quantity - $this->deliveredQuantityForLine($poLine));
    }

    /** Issued invoice qty linked to this PO line (PF1 غير مفوتر). */
    public function invoicedQuantityForLine(CustomerPurchaseOrderLine $poLine): float
    {
        return (float) InvoiceLine::query()
            ->whereHas(
                'salesOrderLine',
                fn (Builder $q) => $q->where('customer_purchase_order_line_id', $poLine->id)
            )
            ->whereHas(
                'invoice',
                fn (Builder $q) => $q->where('status', InvoiceStatus::Issued->value)
            )
            ->sum('quantity');
    }

    public function uninvoicedQuantityForLine(CustomerPurchaseOrderLine $poLine): float
    {
        return max(0.0, $this->deliveredQuantityForLine($poLine) - $this->invoicedQuantityForLine($poLine));
    }

    /**
     * PF1 aggregates across all lines (separate figures — never collapsed).
     *
     * @return array{ordered: float, unallocated: float, undelivered: float, uninvoiced: float}
     */
    public function partialFulfillmentTotals(): array
    {
        $this->loadMissing('lines');

        $ordered = 0.0;
        $unallocated = 0.0;
        $undelivered = 0.0;
        $uninvoiced = 0.0;

        foreach ($this->lines as $line) {
            $ordered += (float) $line->quantity;
            $unallocated += $this->unallocatedQuantityForLine($line);
            $undelivered += $this->undeliveredQuantityForLine($line);
            $uninvoiced += $this->uninvoicedQuantityForLine($line);
        }

        return [
            'ordered' => $ordered,
            'unallocated' => $unallocated,
            'undelivered' => $undelivered,
            'uninvoiced' => $uninvoiced,
        ];
    }
}
