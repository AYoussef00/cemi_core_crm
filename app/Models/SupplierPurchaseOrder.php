<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Support\Authorization\RecordScope;
use App\Support\Procurement\SupplierPurchaseOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sales_order_id',
    'quotation_id',
    'status',
    'supplier_name_ar',
    'expected_at',
    'notes_ar',
    'created_by_user_id',
    'ordered_at',
    'received_by_user_id',
    'received_at',
])]
class SupplierPurchaseOrder extends Model
{
    use HasAttachments;

    protected function casts(): array
    {
        return [
            'status' => SupplierPurchaseOrderStatus::class,
            'expected_at' => 'datetime',
            'ordered_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierPurchaseOrderLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(SupplierGoodsReceipt::class)->latest('received_at')->latest('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function isOpen(): bool
    {
        return $this->status === SupplierPurchaseOrderStatus::Open;
    }

    public function canMarkOrdered(): bool
    {
        return in_array($this->status, [
            SupplierPurchaseOrderStatus::Open,
            SupplierPurchaseOrderStatus::Ordered,
        ], true);
    }

    public function canReceive(): bool
    {
        // Warehouse receives only after Procurement issues the PO to the supplier.
        return in_array($this->status, [
            SupplierPurchaseOrderStatus::Ordered,
            SupplierPurchaseOrderStatus::PartiallyReceived,
        ], true);
    }

    public function totalOrderedQuantity(): float
    {
        return (float) $this->lines->sum(fn ($line) => (float) $line->quantity);
    }

    public function totalReceivedQuantity(): float
    {
        return (float) $this->lines->sum(fn ($line) => (float) $line->received_quantity);
    }

    public function totalRemainingQuantity(): float
    {
        return max(0.0, $this->totalOrderedQuantity() - $this->totalReceivedQuantity());
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('procurement.view') || $user->can('stock.view')) {
            $scope = $user->permissionScope('procurement.view')
                ?? ($user->can('stock.view') ? RecordScope::All : null);

            if ($scope === null || in_array($scope, [RecordScope::All, RecordScope::Admin], true)) {
                return $query;
            }
        }

        return $query->whereRaw('0 = 1');
    }
}
