<?php

namespace App\Support\Sales;

enum DealStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function labelAr(): string
    {
        return match ($this) {
            self::Open => 'مفتوحة',
            self::Closed => 'مغلقة',
        };
    }
}
