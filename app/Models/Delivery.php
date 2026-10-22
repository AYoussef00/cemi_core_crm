<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasDocumentNumber;
use App\Support\Authorization\RecordScope;
use App\Support\Documents\DocumentNumber;
use App\Support\Warehouse\DeliveryStatus;
use Database\Factories\DeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'sales_order_id',
    'customer_id',
    'created_by_user_id',
    'status',
    'notes_ar',
    'pending_pod_at',
    'confirmed_at',
    'confirmed_by_user_id',
    'cancelled_at',
    'cancelled_by_user_id',
    'cancel_notes_ar',
])]
class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use HasAttachments;

    use HasDocumentNumber;
    use HasFactory;

    public static function documentNumberPrefix(): string
    {
        return DocumentNumber::PREFIX_DELIVERY_NOTE;
    }

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'pending_pod_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function hasPodAttachment(): bool
    {
        return $this->attachments()->exists();
    }

    /**
     * @return array{owner_user_id: int|null}
     */
    public function authorizationContext(): array
    {
        return [
            'owner_user_id' => $this->created_by_user_id,
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $scope = $user->permissionScope('delivery.view');

        if ($scope === null && ! $user->can('delivery.view')) {
            return $query->whereRaw('0 = 1');
        }

        return match ($scope) {
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
