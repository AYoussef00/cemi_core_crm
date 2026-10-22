<?php

namespace App\Support\Warehouse;

enum PreparationStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';

    public function labelAr(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار التجهيز',
            self::Ready => 'جاهز للتسليم',
        };
    }
}
