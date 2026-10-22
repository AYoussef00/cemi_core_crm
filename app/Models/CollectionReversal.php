<?php

namespace App\Models;

use App\Support\Finance\CollectionReversalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'collection_id',
    'requested_by_user_id',
    'status',
    'reason_ar',
    'decided_at',
    'decided_by_user_id',
    'decision_notes',
])]
class CollectionReversal extends Model
{
    protected function casts(): array
    {
        return [
            'status' => CollectionReversalStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
