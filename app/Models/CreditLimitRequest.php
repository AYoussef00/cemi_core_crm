<?php

namespace App\Models;

use App\Support\Credit\CreditLimitRequestStatus;
use Database\Factories\CreditLimitRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'requested_by_user_id',
    'requested_credit_limit',
    'requested_payment_term',
    'status',
    'request_notes',
    'reviewed_by_user_id',
    'reviewed_at',
    'review_notes',
    'decided_by_user_id',
    'decided_at',
    'decision_notes',
])]
class CreditLimitRequest extends Model
{
    /** @use HasFactory<CreditLimitRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'requested_credit_limit' => 'decimal:2',
            'status' => CreditLimitRequestStatus::class,
            'reviewed_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function statusLabelAr(): string
    {
        $status = $this->status;

        return $status instanceof CreditLimitRequestStatus
            ? $status->labelAr()
            : (string) $status;
    }

    public function isOpen(): bool
    {
        return $this->status instanceof CreditLimitRequestStatus && $this->status->isOpen();
    }
}
