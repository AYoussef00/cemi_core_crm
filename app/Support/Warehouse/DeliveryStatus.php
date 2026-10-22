<?php

namespace App\Support\Warehouse;

enum DeliveryStatus: string
{
    case Draft = 'draft';
    case PendingPod = 'pending_pod';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function labelAr(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::PendingPod => 'بانتظار إثبات التسليم',
            self::Confirmed => 'مؤكد',
            self::Cancelled => 'ملغى',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::PendingPod], true);
    }

    /** Counts against SO undelivered / over-commit caps. */
    public function allocatesQuantity(): bool
    {
        return in_array($this, [self::Draft, self::PendingPod, self::Confirmed], true);
    }

    public function isConfirmed(): bool
    {
        return $this === self::Confirmed;
    }
}
