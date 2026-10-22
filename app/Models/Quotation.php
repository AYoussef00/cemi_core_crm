<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentNumber;
use App\Support\Authorization\RecordScope;
use App\Support\Documents\DocumentNumber;
use App\Support\Money\Money;
use App\Support\Quotations\QuotationStatus;
use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'sales_request_id',
    'customer_id',
    'created_by_user_id',
    'version',
    'supersedes_quotation_id',
    'status',
    'expires_at',
    'notes_ar',
    'vat_rate',
    'submitted_by_user_id',
    'submitted_at',
    'decided_by_user_id',
    'decided_at',
    'decision_notes',
    'sent_at',
    'sent_to_email',
])]
class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasDocumentNumber;

    use HasFactory;

    public static function documentNumberPrefix(): string
    {
        return DocumentNumber::PREFIX_QUOTATION;
    }

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'version' => 'integer',
            'expires_at' => 'datetime',
            'vat_rate' => 'decimal:4',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function salesRequest(): BelongsTo
    {
        return $this->belongsTo(SalesRequest::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_quotation_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function sendLogs(): HasMany
    {
        return $this->hasMany(QuotationSendLog::class)->latest('id');
    }

    public function isDraft(): bool
    {
        return $this->status === QuotationStatus::Draft;
    }

    public function isExpiredByDate(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function totalVatInclusive(): string
    {
        $sum = $this->lines->sum(fn (QuotationLine $line) => (float) $line->lineTotalVatInclusive());

        return Money::round2($sum);
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
        $scope = $user->permissionScope('quotations.view');

        if ($scope === null && ! $user->can('quotations.view')) {
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

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if ($status === null || $status === '') {
            return $query;
        }

        return $query->where('status', $status);
    }
}
