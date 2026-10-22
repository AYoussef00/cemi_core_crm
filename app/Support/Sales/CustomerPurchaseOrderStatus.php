<?php

namespace App\Support\Sales;

enum CustomerPurchaseOrderStatus: string
{
    case Open = 'open';
    case Cancelled = 'cancelled';

    public function labelAr(): string
    {
        return match ($this) {
            self::Open => 'مفتوح',
            self::Cancelled => 'ملغى',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
