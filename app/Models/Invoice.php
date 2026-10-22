<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentNumber;
use App\Support\Authorization\RecordScope;
use App\Support\Documents\DocumentNumber;
use App\Support\Finance\CollectionStatus;
use App\Support\Finance\InvoiceNoteStatus;
use App\Support\Finance\InvoiceNoteType;
use App\Support\Finance\InvoiceStatus;
use App\Support\Money\Money;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'delivery_id',
    'sales_order_id',
    'deal_id',
    'customer_id',
    'issued_by_user_id',
    'status',
    'total_vat_inclusive',
    'issued_at',
    'due_at',
    'notes_ar',
    'last_overdue_reminded_at',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasDocumentNumber;

    use HasFactory;

    public static function documentNumberPrefix(): string
    {
        return DocumentNumber::PREFIX_INVOICE;
    }

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'total_vat_inclusive' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'last_overdue_reminded_at' => 'datetime',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(InvoiceNote::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CollectionAllocation::class);
    }

    /**
     * CR7 unpaid balance for this issued invoice.
     */
    public function unpaidBalance(): string
    {
        $issued = (float) $this->total_vat_inclusive;

        $confirmedCollections = (float) CollectionAllocation::query()
            ->where('invoice_id', $this->id)
            ->whereHas('collection', fn (Builder $q) => $q->where('status', CollectionStatus::Confirmed->value))
            ->sum('amount');

        $approvedCredits = (float) InvoiceNote::query()
            ->where('invoice_id', $this->id)
            ->where('type', InvoiceNoteType::Credit->value)
            ->where('status', InvoiceNoteStatus::Approved->value)
            ->sum('amount');

        $approvedDebits = (float) InvoiceNote::query()
            ->where('invoice_id', $this->id)
            ->where('type', InvoiceNoteType::Debit->value)
            ->where('status', InvoiceNoteStatus::Approved->value)
            ->sum('amount');

        return Money::round2($issued - $confirmedCollections - $approvedCredits + $approvedDebits);
    }

    public function isFullyPaid(): bool
    {
        return abs((float) $this->unpaidBalance()) < 0.005;
    }

    public function isOverdue(): bool
    {
        if ($this->due_at === null || $this->isFullyPaid()) {
            return false;
        }

        return $this->due_at->isPast();
    }

    /**
     * @return array{assigned_sales_rep_id: int|null}
     */
    public function authorizationContext(): array
    {
        $this->loadMissing('customer');

        return [
            'assigned_sales_rep_id' => $this->customer?->assigned_sales_rep_id,
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $scope = $user->permissionScope('invoices.view');

        if ($scope === null && ! $user->can('invoices.view')) {
            return $query->whereRaw('0 = 1');
        }

        return match ($scope) {
            RecordScope::Cust, RecordScope::Assigned => $query->whereHas(
                'customer',
                fn (Builder $q) => $q->where('assigned_sales_rep_id', $user->id)
            ),
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
            $inner->where('notes_ar', 'like', $like)
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name_ar', 'like', $like));
        });
    }
}
