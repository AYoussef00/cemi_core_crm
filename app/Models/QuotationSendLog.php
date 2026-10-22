<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'quotation_id',
    'actor_user_id',
    'recipient_email',
    'status',
    'error_message',
    'created_at',
])]
class QuotationSendLog extends Model
{
    public $timestamps = false;

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
