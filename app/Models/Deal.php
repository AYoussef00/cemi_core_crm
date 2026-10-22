<?php

namespace App\Models;

use App\Support\Sales\DealStatus;
use Database\Factories\DealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_id',
    'customer_purchase_order_id',
    'status',
    'closed_by_user_id',
    'closed_at',
])]
class Deal extends Model
{
    /** @use HasFactory<DealFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => DealStatus::class,
            'closed_at' => 'datetime',
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

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isOpen(): bool
    {
        return $this->status === DealStatus::Open;
    }

    public function isClosed(): bool
    {
        return $this->status === DealStatus::Closed;
    }

    /** TX5b: every linked issued invoice unpaid (CR7) is 0. */
    public function allInvoicesFullyPaid(): bool
    {
        $invoices = $this->invoices()->get();
        if ($invoices->isEmpty()) {
            return false;
        }

        foreach ($invoices as $invoice) {
            if (! $invoice->isFullyPaid()) {
                return false;
            }
        }

        return true;
    }
}
